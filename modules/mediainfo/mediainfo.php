<?php

class MediaInfo extends Module
{
	public $Name = 'mediainfo';
	public $Block = 'foot';

	function __construct()
	{
		global $_d;

		$_d['medinfo.ds'] = $_d['db']->medinfo;

		$this->cols = array(
			'path' => array('short' => 'Path',
				'title' => 'Physical File Location'),
			'mtime' => array('short' => 'MTime',
				'title' => 'File Modification Time'),
			'fsize' => array('short' => 'FS',
				'title' => 'File Size'),

			'General.Duration' => array('short' => 'D',
				'title' => 'Duration'),
			'General.Format' => array('short' => 'Format',
				'title' => 'Internal File Format'),
			'General.Format_Info' => array('short' => 'FI',
				'title' => 'Format Info'),
			'General.Overall_bit_rate' => array('short' => 'OBR',
				'title' => 'Overall Bit Rate'),

			'Video.Bits__Pixel_Frame_' => array('short' => 'BPPF',
				'title' => 'Bits per Pixel per Frame'),
			'Video.Bit_rate' => array('short' => 'VBR',
				'title' => 'Video Bit Rate'),
			'Video.Codec_ID' => array('short' => 'CID',
				'title' => 'Video Codec ID'),
			'Video.Display_aspect_ratio' => array('short' => 'AR',
				'title' => 'Aspect Ratio'),
			'Video.Duration' => array('short' => 'VD',
				'title' => 'Video Duration'),
			'Video.Format' => array('short' => 'VF',
				'title' => 'Video Format'),
			'Video.Frame_rate' => array('short' => 'FR',
				'title' => 'Frame Rate'),
			'Video.Height' => array('short' => 'Y',
				'title' => 'Vertical Resolution'),
			'Video.Stream_size' => array('short' => 'VSS',
				'title' => 'Video Stream Size'),
			'Video.Width' => array('short' => 'X',
				'title' => 'Horizontal Resolution'),

			'Audio.Bit_rate' => array('short' => 'ABR',
				'title' => 'Audio Bit Rate'),
			'Audio.Bit_rate_mode' => array('short' => 'ABRM',
				'title' => 'Audio Bit Rate Mode'),
			'Audio.Channel_s_' => array('short' => 'AC',
				'title' => 'Audio Channels'),
			'Audio.Duration' => array('short' => 'AD',
				'title' => 'Audio Duration'),
			'Audio.Format' => array('short' => 'AF',
				'title' => 'Audio Format'),
			'Audio.Format_profile' => array('short' => 'AFP',
				'title' => 'Audio Format Profile'),
			'Audio.Sampling_rate' => array('short' => 'ASR',
				'title' => 'Audio Sampling Rate'),
			'Audio.Stream_size' => array('short' => 'ASS',
				'title' => 'Audio Stream Size'),
		);

		$this->colorize = array(
			'fsize',
			'mtime',
			'Video.Bit_rate',
			'Video.Width',
			'Video.Height',
			'Video.Frame_rate',
			'Audio.Bit_rate',
			'Audio.Channel_s_',
			'General.Overall_bit_rate',
			'Video.Display_aspect_ratio',
			'Video.Bits__Pixel_Frame_'
		);

		$ix = 0;
		foreach ($this->cols as &$i) $i['index'] = $ix++;

		$this->CheckActive($this->Name);
	}

	function Link()
	{
		global $_d;

		if (empty($_d['q'][0]))
			$_d['nav.links']['Video and Audio Statistics'] = 'mediainfo';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'process')
		{
			var_dump($_d['q'][2]);
			$q['_id'] = new MongoId($_d['q'][2]);
			$res = $_d['entry.ds']->findone($q);
			$this->Process($res['path']);
			die();
		}
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]) || $this->Active)
		{
			$r['head'] = <<<EOF
<link type="text/css" rel="stylesheet" href="modules/mediainfo/css.css" />
<script type="text/javascript" src="xedlib/js/jquery.tablesorter.js"></script>
<script type="text/javascript" src="modules/mediainfo/js.js"></script>
EOF;
		}

		if (!$this->Active) return;

		$t = new Template();
		$t->ReWrite('column', array(&$this, 'TagColumn'));
		$t->ReWrite('item', array(&$this, 'TagItem'));
		$r['default'] = $t->ParseFile('modules/mediainfo/t.xml');
		return $r;
	}

	function TagItem($t, $g)
	{
		global $_d;

		# Collect lump sum of all cached data.
		$q['codec']['$exists'] = true;
		$cols['path'] = 1;
		$cols['codec'] = 1;
		$dr = $_d['entry.ds']->find($q, $cols);

		# Catalog data by path into local var.
		if (!empty($dr))
		foreach ($dr as $r)
		{
			$stat = $r['codec'];
			$stat['path'] = $r['path'];
			$stat['_id'] = $r['_id'];

			$stats[$r['path']] = $stat;
		}

		# Catalog data we will colorize into it's own arrays.
		foreach ($stats as $p => $v)
			foreach ($this->colorize as $col)
				$nums[$col][$p] = @$v[$col];

		# Decide on what color each item will be.
		foreach ($nums as $col => $n)
			$flat_stats[$col] = Math::RespectiveSize($n, 1, 5);

		foreach ($flat_stats as $k => $ps)
			foreach ($ps as $p => $s)
				$stats[$p]['col_'.$k] = $s;

		ksort($stats);

		$t2 = new Template();
		$t2->ReWrite('icol', array(&$this, 'TagICol'));
		$ret = '';
		foreach ($stats as $p => $stat)
		{
			$t2->Set($stat);
			$this->curitem = $stat;
			$ret .= $t2->GetString($g);
		}
		return $ret;
	}

	function TagColumn($t, $g)
	{
		return VarParser::Concat($g, $this->cols);
	}

	function TagICol($t, $g)
	{
		$vp = new VarParser();
		$vp->Behavior->Bleed = false;
		$ret = '';
		$ix = 0;
		foreach ($this->cols as $id => $col)
		{
			$i = $this->curitem;
			$i['id'] = $ix;
			$i['data'] = @$this->curitem[$id];
			$color = @$i['col_'.$id];
			$i['class'] = 'mit-col'.$ix;
			if (isset($color)) $i['class'] .= ' col_'.$color;
			$ret .= $vp->ParseVars($g, $i);
			$ix++;
		}
		return $ret;
	}

	function Check($a)
	{
		global $_d;
		$ret = array();

		# Collect all mtime values of movie files.

		if (!empty($_d['config']['paths']['movie']))
		foreach ($_d['config']['paths']['movie'] as $p)
		foreach (glob($p.'/*') as $f)
		{
			if (is_dir($f)) continue;
			$mtimes[$f] = filemtime($f);
		}

		# Collect all database mtime values.

		$dbtimes = array();
		$q['codec.mtime']['$exists'] = 1;
		$cols['codec.mtime'] = 1;
		$cols['path'] = 1;
		foreach ($_d['entry.ds']->find($q, $cols) as $r)
			$dbtimes[$r['path']] = $r['codec']['mtime'];

		if (!empty($mtimes))
		foreach ($mtimes as $p => $t)
		if (!isset($dbtimes[$p]) || $dbtimes[$p] != $t)
		{
			U::VarInfo("Scanning codec info for {$p}");
			MediaInfo::Process($p);
		}

		return $ret;
	}

	function movie_cb_detail($item)
	{
		global $_d;
		MediaInfo::Process(str_replace('"', '\"', $item['fs_path']));
	}

	function Process($path)
	{
		global $_d;

		$cmd_path = escapeshellarg($path);
		$out = `mediainfo --Output=XML {$cmd_path}`;
		if (empty($out)) { var_dump('Error loading media info.'); return; }
		$sx = simplexml_load_string(preg_replace('/|/', '', $out));

		$tracks = $sx->File->track;

		$q['path'] = $path;
		$item = $_d['entry.ds']->findOne($q);

		if (!empty($tracks))
		{
			foreach ($tracks as $t)
			{
				$type = $t['type'];

				switch ($type)
				{
					case 'Video':
						$vq = ((int)str_replace(' ', '', $t->Bit_rate) * .00075);
						$stars = str_repeat('<img src="img/vote.png">', $vq * 5);
						$item['details']['Video Quality'] = $stars." ({$vq} of {$t->Bit_rate})";
						break;
					case 'Audio':
						$aq = ((int)str_replace(' ', '', $t->Bit_rate) * .005);
						$stars = str_repeat('<img src="img/vote.png">', $aq * 5);
						$item['details']['Audio Quality'] = $stars." ({$aq} of {$t->Bit_rate})";
						break;
				}

				foreach ($t as $n => $v)
				{
					$name = $type.'.'.$n;
					if (!array_key_exists($name, $this->cols)) continue;
					switch ($name)
					{
						case 'General.Overall_bit_rate':
						case 'Video.Bit_rate':
						case 'Video.Frame_rate':
						case 'Video.Width':
						case 'Video.Height':
						case 'Video.Resolution':
						case 'Audio.Bit_rate':
						case 'Audio.Channel_s_':
							$value = preg_replace('#(\s*|kbps|pixels|bits|fps|channels|channel)#i', '', $v);
							break;
						default: $value = (string)$v;
					}
					$item['codec'][$name] = $value;
				}
			}
			$item['codec']['mtime'] = filemtime($path);
			$item['codec']['fsize'] = filesize($path);
			$_d['entry.ds']->save($item, array('safe' => 1));
		}
	}
}

Module::Register('MediaInfo');

?>
