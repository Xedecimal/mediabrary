<?php

class ModCheck extends Module
{
	function Get()
	{
		global $_d;
		
		if (empty($_d['q'][0])) return '<a href="check" id="a-check">Click here to check your library.</a>';
		if ($_d['q'][0] != 'check') return;

		global $mods;

		$msgs = array();

		// Collect check messages
		foreach ($mods as $m)
			if (method_exists($m, 'Check'))
				$msgs = array_merge($msgs, $m->Check());

		$msgout = '';

		foreach ($msgs as $t => $ms)
			foreach ($ms as $m)
				$msgout .= "<div>{$m}</div>";

		$t = new Template();
		$t->Set('msgs', $msgout);
		return $t->ParseFile('t_check.xml');
	}
}

Module::RegisterModule('ModCheck');

?>
