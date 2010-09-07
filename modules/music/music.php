<?php

class ModMusic extends MediaLibrary
{
	var $_template = 't_music.xml';

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/music/css.css" />';
			return '<div id="divMainMusic" class="main-link"><a href="{{app_abs}}/music">Music</a></div>';
		}
	}
}

Module::Register('ModMusic');

?>
