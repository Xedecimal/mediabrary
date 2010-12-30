<?php

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
				$_d['nav.links']['Video and Audio Statistics'] = 'mediainfo';
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
		{
			$stats[$r['cod_path']][$r['cod_name']] = $r['cod_value'];
			$stats[$r['cod_path']]['General.File_size'] = filesize($r['cod_path']);
		}

		foreach ($stats as $p => $v)
		{
			$gfss[$p] = @$v['General.File_size'];
			$vbrs[$p] = @$v['Video.Bit_rate'];
			$vrxs[$p] = @$v['Video.Width'];
			$vrys[$p] = @$v['Video.Height'];
			$vfrs[$p] = @$v['Video.Frame_rate'];
			$abrs[$p] = @$v['Audio.Bit_rate'];
			$achs[$p] = @$v['Audio.Channel_s_'];
		}

		$flat_stats['vbr'] = get_relative_sizes($vbrs, 1, 5);
		$flat_stats['vrx'] = get_relative_sizes($vrxs, 1, 5);
		$flat_stats['vry'] = get_relative_sizes($vrys, 1, 5);
		$flat_stats['vfr'] = get_relative_sizes($vfrs, 1, 5);
		$flat_stats['abr'] = get_relative_sizes($abrs, 1, 5);
		$flat_stats['gfs'] = get_relative_sizes($gfss, 1, 5);
		$flat_stats['ach'] = get_relative_sizes($achs, 1, 5);

		foreach ($flat_stats as $k => $ps)
			foreach ($ps as $p => $s)
				$stats[$p]['col_'.$k] = $s;

		ksort($stats);

		$vp = new VarParser();

		$vp->Behavior->SimpleVars = true;

		foreach ($stats as $p => $cod)
			@$ret .= $vp->ParseVars($g, $cod);

		return @$ret;
	}

	function Check()
	{
		global $_d;

		$ret = array();
		$cols = array('path' => Database::SqlUnquote('DISTINCT cod_path'));
		$dr = $_d['medinfo.ds']->Get(array('columns' => $cols));
		foreach ($dr as $r)
			if (!file_exists($r['path']))
			{
				$_d['medinfo.ds']->Remove(array('cod_path' => $r['path']));
				$ret['cleanup'][] = "Removed codec information for missing movie: {$r['path']}";
			}
		return $ret;
	}

	function movie_cb_detail($item)
	{
		global $_d;

		$target = str_replace('"', '\"', $item['fs_path']);
		$out = `mediainfo --Output=XML "{$target}"`;
		if (empty($out)) { echo "Error loading media info."; return $item; }
		$sx = simplexml_load_string(preg_replace('//', '', $out));

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
					case 'Video.Frame_rate':
					case 'Video.Width':
					case 'Video.Height':
					case 'Video.Resolution':
					case 'Audio.Bit_rate':
					case 'Audio.Channel_s_':
						$cod['cod_value'] = preg_replace('#(\s*|kbps|pixels|bits|fps|channels|channel)#i', '', $v);
						break;
					default: $cod['cod_value'] = (string)$v;
				}
				$_d['medinfo.ds']->Add($cod, true);
			}
		}

		return $item;
	}
}

Module::Register('ModMediaInfo');

?>
