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

		if (!empty($_d['q'][1]))
		{
			$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($_d['q'][1])));
			$path = dirname($item['path']).'/folder.jpg';
		}
		else $path = Server::GetVar('path');

		header("Content-Type: image/jpeg");
		die(file_get_contents($path));
	}
}

Module::Register('Cover');

?>
