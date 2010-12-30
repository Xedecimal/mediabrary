<?php

class ModMusic extends MediaLibrary
{
	var $_template = 't_music.xml';

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/music/css.css" />';
			$r['default'] = '<div id="divMainMusic" class="main-link"><a href="{{app_abs}}/music">Music</a></div>';
			return $r;
		}
	}
}

Module::Register('ModMusic');

?>
