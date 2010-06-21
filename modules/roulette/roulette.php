<?php

class ModRoulette extends Module
{
	function Link()
	{
		global $_d;

		$_d['movie.cb.head']['roulette'] = array(&$this, 'cb_movie_head');
	}

	function cb_movie_head()
	{
		$t = new Template();
		return $t->ParseFile(l('roulette/t.xml'));
	}
}

Module::Register('ModRoulette');

?>
