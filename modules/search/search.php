<?php

class ModSearch extends Module
{
	function Prepare()
	{
		global $_d;

		if ($_d['q'][0] != 'search') return;

		$_SESSION['search.query'] = $_d['q'][1];
		die();
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array($this, 'movie_cb_head');

		$q = GetVar('search.query');
		if (!empty($q))
		{
			$_d['movie.skipfs'] = true;
			$_d['movie.cb.query']['match']['med_title'] = SqlLike("%{$q}%");
		}
	}

	function movie_cb_head()
	{
		$t = new Template();
		return $t->ParseFile('modules/search/t.xml');
	}
}

Module::Register('ModSearch');

?>
