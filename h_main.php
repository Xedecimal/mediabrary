<?php

session_start();

require_once('xedlib/h_utility.php');
HandleErrors();
SanitizeEnvironment();
require_once('xedlib/h_module.php');
require_once('xedlib/h_data.php');

require_once('modules/medialibrary.php');

date_default_timezone_set('America/Los_Angeles');

if (!file_exists('config/config.yml'))
	copy('config/default.yml', 'config/config.yml');

$_d['config'] = simplexml_load_file('config.xml');
$_d['db'] = new Database();
$_d['db']->Open($_d['config']->database->attributes()->url);

require_once('xedlib/modules/nav.php');

class ModMain extends Module
{
	function Get()
	{
		global $_d;

		if (!empty($_d['q'][0])) return;

		$r['head'] =
			'<link rel="stylesheet" type="text/css" href="css/_main.css" />';

		return $r;
	}
}

if (!file_exists('config.xml')) copy('config.default.xml', 'config.xml');

Module::Register('ModMain');

?>
