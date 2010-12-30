<?php

require_once('h_main.php');
Module::Initialize(dirname(__FILE__), true);
die(Module::Run('t.xml'));

?>
