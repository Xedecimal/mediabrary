<?php

require_once('h_main.php');

$_d['nav.links']['Home'] = '{{app_abs}}/';

Module::Initialize(dirname(__FILE__), true);
die(Module::Run('t.xml'));

?>
