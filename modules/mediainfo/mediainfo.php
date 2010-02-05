<?php

//general: duration ... video: bitrate, resolution, codec id, aspect ratio, framerate ... audio: bitrate, format, channels, sampling rate

class ModMediaInfo extends Module
{
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
				$cod['cod_value'] = (string)$v;
				$_d['medinfo.ds']->Add($cod, true);
			}
		}

		return $item;
	}
}

Module::RegisterModule('ModMediaInfo');

?>
