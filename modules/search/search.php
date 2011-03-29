<?php

class ModSearch extends Module
{
	function Prepare()
	{
		global $_d;

		if ($_d['q'][0] != 'search') return;

		$_SESSION['search.query'] = Server::GetVar('term');
		die();
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array($this, 'movie_cb_head');

		$q = Server::GetVar('search.query');
		if (!empty($q))
		{
			$_d['movie.cb.query']['match']['title'] = new MongoRegex("/$q/i");
		}
	}

	function movie_cb_head()
	{
		$t = new Template();
		$t->Set('query', Server::GetVar('search.query', ''));
		return $t->ParseFile('modules/search/t.xml');
	}
}

Module::Register('ModSearch');

?>
