<?php

class ModMusic extends MediaLibrary
{
	var $_template = 't_music.xml';

	function __construct()
	{
	}
	
	function Get()
	{
		global $_d;
		
		if (empty($_d['q'][0])) return '<a href="{{app_abs}}/music" id="a-music">Music</a>';
	}
}

Module::RegisterModule('ModMusic');

?>
