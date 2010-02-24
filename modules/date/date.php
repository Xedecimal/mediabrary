<?php

require_once('h_main.php');

class ModDate extends Module
{
	function Prepare()
	{
		global $_d;

		if (@$_d['q'][1] == 'date')
		{
			SetVar('date.min', $_d['q'][2]);
			SetVar('date.max', $_d['q'][3]);
			$_d['movie.skipfs'] = true;
		}
	}

	function Link()
	{
		global $_d;
		$_d['movie.cb.head'][] = array(&$this, 'cb_movie_head');

		$min = GetVar('date.min');
		$max = GetVar('date.max');

		if (!empty($min)) $_d['movie.cb.query']['match']['med_date'] =
			SqlBetween($min, $max);
	}

	function cb_movie_head()
	{
		$t = new Template();
		return $t->ParseFile('modules/date/t_date.xml');
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'date') return;

		if ($_d['q'][1] == 'values')
		{
			$cols = array(
				'min' => SqlUnquote('year(min(med_date))'),
				'max' => SqlUnquote('year(max(med_date))')
			);
			$items = $_d['movie.ds']->Get(array('columns' => $cols));
			$items[0]['cmin'] = GetVar('date.min', 0);
			$items[0]['cmax'] = GetVar('date.max', 0);
			die(json_encode($items[0]));
		}
	}
}

Module::RegisterModule('ModDate');

?>
