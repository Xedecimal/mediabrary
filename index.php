<?php

require_once('h_main.php');
Module::Initialize(true);
die(Module::Run('t.xml'));

?>
