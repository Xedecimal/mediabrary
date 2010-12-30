<?php

session_start();

# Libraries
require_once('xedlib/classes/Server.php');
Server::HandleErrors();
Server::SanitizeEnvironment();
require_once('xedlib/classes/Module.php');
require_once('xedlib/classes/HM.php');
require_once('xedlib/classes/Math.php');
require_once('xedlib/classes/Arr.php');
require_once('xedlib/classes/data/Database.php');
require_once('xedlib/classes/data/DataSet.php');

# Third party
require_once('xedlib/3rd/spyc.php');

# Local requirements
require_once('modules/medialibrary.php');

date_default_timezone_set('America/Los_Angeles');

if (!file_exists('config/config.yml'))
	copy('config/default.yml', 'config/config.yml');

$_d['config'] = spyc_load_file('config/config.yml');
$_d['db'] = new Database();
$_d['db']->Open($_d['config']['db']);

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

Module::Register('ModMain');

?>
