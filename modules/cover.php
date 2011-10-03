<?php

class Cover extends Module
{
	public $Name = 'cover';

	function __construct()
	{
		$this->CheckActive($this->Name);
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		$path = Server::GetVar('path');

		header("Content-Type: image/jpeg");
		die(file_get_contents($path));
	}
}

Module::Register('Cover');

?>
