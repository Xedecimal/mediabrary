<?php

class ModSearch extends Module
{
	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array($this, 'movie_cb_head');
	}

	function movie_cb_head()
	{
		return 'Search: <input type="text" id="movie-search" />';
	}
}

Module::RegisterModule('ModSearch');

?>
