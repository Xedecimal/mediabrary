<?php

class ModPics extends MediaLibrary
{
	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/pics/css.css" />';
			return '<div class="main-link" id="divMainPics"><a href="{{app_abs}}/pic" id="a-pics">Pics</a></div>';
		}
	}
}

Module::Register('ModPics');

?>
