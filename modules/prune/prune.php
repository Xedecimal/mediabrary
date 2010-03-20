<?php

class ModLock extends Module
{
	function Link()
	{
		global $_d;

		$_d['movie.cb.buttons'][] = array($this, 'movie_cb_buttons');
	}

	function movie_cb_buttons()
	{
		return '<a href="movie/lock/{{fs_path}}"><img src="img/lock.png"
			alt="Lock" /></a>';
	}
}

Module::Register('ModLock');

?>
