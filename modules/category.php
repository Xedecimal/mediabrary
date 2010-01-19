<?php

class ModCategory extends MediaLibrary
{
	function __construct()
	{
		global $_d;

		$_d['cat.ds'] = new DataSet($_d['db'], 'category');
	}
}

Module::RegisterModule('ModCategory');

?>
