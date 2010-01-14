<?php

class ModMain extends Module
{
	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$t = new Template();
			return $t->ParseFile('t_main.xml');
		}
	}
}

Module::RegisterModule('ModMain');

?>
