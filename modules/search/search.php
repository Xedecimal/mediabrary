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
			#$_d['movie.cb.query']['match']['fs_title'] = new MongoRegex("/$q/i");
			$_d['movie.cb.query']['match']['$or'][]['title'] = new MongoRegex("/$q/i");
			foreach ($_d['search.cb.query'] as $cb)
				$_d['movie.cb.query']['match'] =
					array_merge_recursive($_d['movie.cb.query']['match'],
						call_user_func($cb, $q));
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
