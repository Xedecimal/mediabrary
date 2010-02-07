<?php

//general: duration ... video: bitrate, resolution, codec id, aspect ratio, framerate ... audio: bitrate, format, channels, sampling rate

class ModMediaInfo extends Module
{
	var $Block = 'foot';

	function __construct()
	{
		global $_d;

		$_d['medinfo.ds'] = new Dataset($_d['db'], 'codec', 'cod_id');
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.detail'][] = array($this, 'movie_cb_detail');
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]) || $_d['q'][0] == 'mediainfo')
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/mediainfo/css.css" />';
			if (empty($_d['q'][0]))
				return '<a href="mediainfo" id="a-mediainfo">Video and Audio Statistics</a>';
		}

		if ($_d['q'][0] != 'mediainfo') return;

		$t = new Template();
		$t->ReWrite('item', array($this, 'TagItem'));
		return $t->ParseFile('modules/mediainfo/t.xml');
	}

	function TagItem($t, $g)
	{
		global $_d;

		$dr = $_d['medinfo.ds']->Get();

		foreach ($dr as $r)
			$stats[$r['cod_path']][$r['cod_name']] = $r['cod_value'];

		$vp = new VarParser();

		foreach ($stats as $p => $cod)
			@$ret .= $vp->ParseVars($g, $cod);

		return @$ret;
	}

	function movie_cb_detail($item)
	{
		global $_d;

		$target = str_replace('"', '\"', $item['fs_path']);
		$out = `/usr/local/bin/mediainfo --Output=XML "{$target}"`;
		$sx = simplexml_load_string($out);

		$tracks = $sx->xpath('//File/track');

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
				$cod['cod_path'] = $item['fs_path'];
				$cod['cod_name'] = $type.'.'.$n;
				switch ($cod['cod_name'])
				{
					case 'General.Overall_bit_rate':
					case 'Video.Bit_rate':
					case 'Audio.Bit_rate':
					case 'Video.Frame_rate':
					case 'Video.Width':
					case 'Video.Height':
					case 'Video.Resolution':
						$cod['cod_value'] = preg_replace('#(\s*|kbps|pixels|bits|fps)#i', '', $v);
						break;
					default: $cod['cod_value'] = (string)$v;
				}
				$_d['medinfo.ds']->Add($cod, true);
			}
		}

		return $item;
	}
}

Module::RegisterModule('ModMediaInfo');

?>
