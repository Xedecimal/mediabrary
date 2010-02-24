<?php

session_start();

require_once('config.php');
require_once('xedlib/h_utility.php');
HandleErrors();
require_once('xedlib/h_module.php');
require_once('xedlib/h_data.php');

date_default_timezone_set('America/Los_Angeles');

$GLOBALS['__debfile'] = 'debug.txt';

$_d['app_abs'] = GetRelativePath(dirname(__FILE__));

$_d['db'] = new Database();
$_d['db']->Open($_d['config']['db_url']);

$_d['q'] = explode('/', GetVar('q'));
$_d['app_abs'] = GetRelativePath(dirname(__FILE__));
$_d['head'] = '';

class ModMain extends Module
{
	function Get()
	{
		global $_d;

		if (empty($_d['q'][0])) $_d['head'] .=
			'<link rel="stylesheet" type="text/css" href="_main.css" />';
	}
}

Module::RegisterModule('ModMain');

?>
