<?php

require_once('medialibrary.php');
require_once('h_main.php');

class ModCategory extends MediaLibrary
{
	function __construct()
	{
		global $_d;
		$_d['cat.ds'] = new DataSet($_d['db'], 'category');
		$this->_template = 't_category.xml';
	}
}

Module::RegisterModule('ModCategory');

?>
