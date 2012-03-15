<?php

require_once(dirname(__FILE__).'/../../3rd/getid3/getid3.php');

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

			'duration' => array('short' => 'D',
				'title' => 'Duration'),
			'fileformat' => array('short' => 'Format',
				'title' => 'Internal File Format'),
			'bitrate' => array('short' => 'OBR',
				'title' => 'Overall Bit Rate'),

			'video.bits_per_sample' => array('short' => 'BPS',
				'title' => 'Bits per Sample'),
			'video.bitrate' => array('short' => 'VBR',
				'title' => 'Video Bit Rate'),
			'video.codec' => array('short' => 'CID',
				'title' => 'Video Codec ID'),
			'video.pixel_aspect_ratio' => array('short' => 'AR',
				'title' => 'Aspect Ratio'),
			'video.dataformat' => array('short' => 'VF',
				'title' => 'Video Format'),
			'video.frame_rate' => array('short' => 'FR',
				'title' => 'Frame Rate'),
			'video.resolution_y' => array('short' => 'Y',
				'title' => 'Vertical Resolution'),
			'video.resolution_x' => array('short' => 'X',
				'title' => 'Horizontal Resolution'),

			'Audio.Bit_rate' => array('short' => 'ABR',
				'title' => 'Audio Bit Rate'),
			'Audio.Bit_rate_mode' => array('short' => 'ABRM',
				'title' => 'Audio Bit Rate Mode'),
			'Audio.Channel_s_' => array('short' => 'AC',
				'title' => 'Audio Channels'),
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

		$_d['cb.detail.entry']['mediainfo'] = array(&$this, 'cb_detail_entry');
		$_d['nav.links']['Tools/Stats'] = 'mediainfo';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'process')
		{
			$q['_id'] = new MongoId($_d['q'][2]);
			$res = $_d['entry.ds']->findone($q);
			$this->Process($res);
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
<script type="text/javascript" src="modules/mediainfo/mediainfo.js"></script>
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
		$stats = array();
		if (!empty($dr))
		foreach ($dr as $r)
		{
			$stat = $r['codec'];
			$stat['path'] = $r['path'];
			$stat['_id'] = $r['_id'];

			$stats[$r['path']] = $stat;
		}

		# Catalog data we will colorize into it's own arrays.
		$nums = array();
		foreach ($stats as $p => $v)
			foreach ($this->colorize as $col)
				$nums[$col][$p] = @$v[$col];

		# Decide on what color each item will be.
		$flat_stats = array();
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

	function Check()
	{
		global $_d;

		$q['codec.mtime']['$exists'] = 0;
		$q['path']['$exists'] = 1;
		$q['$or'][]['type'] = 'movie';
		$q['$or'][]['type'] = 'tv-episode';
		$q['$or'][]['type'] = 'music-track';
		$cols['path'] = 1;

		$ents = $_d['entry.ds']->find($q);

		foreach ($ents as $entry)
		{
			MediaInfo::Process($entry);
			echo "<p>Processed codec data on {$entry['path']}</p>";
			flush();
		}

		# Collect all database mtime values.
		$dbtimes = array();

		foreach ($_d['entry.ds']->find($q, $cols) as $r)
			$dbtimes[$r['path']] = @$r['codec']['mtime'];

		if (!empty($mtimes))
		foreach ($mtimes as $p => $t)
		if (!isset($dbtimes[$p]) || $dbtimes[$p] != $t)
		{
			echo "<p>TODO: Scan changed codec info for {$p}</p>";
			flush();
		}
	}

	function cb_detail_entry($t, $g, $a)
	{
		global $_d;
		MediaInfo::Process($t->vars['Data']);
	}

	static function Process($item)
	{
		if (!is_file($item['path'])) return;

		global $_d;

		$getid3 = new GetID3;

		$out = $getid3->analyze($item['path']);
		if (empty($out)) { echo 'Error loading media info.'; return; }

		if (empty($out) || !empty($out['error']))
		{
			echo "<p>Bad codec data in file '{$item['path']}'.</p>";
			flush();
			return;
			#$item['codec']['mtime'] = filemtime($item['path']);
			#$_d['entry.ds']->save($item, array('safe' => 1));
		}

		$item['details']['Audio Quality'] = @$out['audio']['bitrate'] * .005;
		$item['details']['Video Quality'] = @$out['video']['bitrate'] * .00075;

		if (empty($out['audio']))
			var_dump($out);
		$res['audio'] = $out['audio'];
		$res['video'] = $out['video'];
		$res['duration'] = $out['playtime_seconds'];
		$res['fileformat'] = $out['fileformat'];
		$res['bitrate'] = $out['bitrate'];

		$item['codec'] = $res;
		$item['codec']['mtime'] = filemtime($item['path']);
		$item['codec']['fsize'] = filesize($item['path']);
		$_d['entry.ds']->save($item, array('safe' => 1));
	}
}

Module::Register('MediaInfo');

?>
