<?php

session_start();

# Libraries
require_once('xedlib/classes/server.php');
Server::HandleErrors();
Server::SanitizeEnvironment();
require_once('xedlib/classes/module.php');
require_once('xedlib/classes/hm.php');
require_once('xedlib/classes/math.php');
require_once('xedlib/classes/arr.php');
require_once('xedlib/classes/data/database.php');
require_once('xedlib/classes/data/data_set.php');

# Third party
require_once('xedlib/3rd/spyc.php');

# Local requirements
require_once('modules/medialibrary.php');
require_once('classes/media_entry.php');

date_default_timezone_set('America/Los_Angeles');

if (!file_exists('config/config.yml'))
	copy('config/default.yml', 'config/config.yml');

$_d['config'] = spyc_load_file('config/config.yml');
$lang = !empty($_d['config']['lang']) ? $_d['config']['lang'] : 'en';
$_d['i18n'] = spyc_load_file("lang/{$lang}.yml");

$_d['module.disable']['MediaInfo'] = 1;

$mongo = new Mongo;
$_d['db'] = $mongo->mediabrary;
$_d['entry.ds'] = $_d['db']->entry;
$_d['entry.ds']->ensureIndex(array('path' => 1),
	array('unique' => true, 'dropDups' => true));
$_d['entry.ds']->ensureIndex(array('obtained' => 1));

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
