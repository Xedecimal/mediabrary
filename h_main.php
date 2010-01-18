<?php

require_once('config.php');
require_once('xedlib/h_utility.php');
//HandleErrors();
require_once('xedlib/h_module.php');
require_once('xedlib/h_data.php');

date_default_timezone_set('America/Los_Angeles');

$_d['app_abs'] = GetRelativePath(dirname(__FILE__));

$_d['db'] = new Database();
$_d['db']->Open($_d['config']['db_url']);

$_d['q'] = explode('/', GetVar('q'));

function has_roman($title)
{
	$v = preg_match('/\s+M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})\s+/', ' '.$title.' ', $ms);
	foreach ($ms as $m) if (strlen(trim($m)) > 0) return 1;
}

?>
