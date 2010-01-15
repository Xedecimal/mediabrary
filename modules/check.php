<?php

class ModCheck extends Module
{
	function Get()
	{
		global $_d;
		
		if ($_d['q'][0] != 'check') return;

		global $mods;

		$msgs = array();

		foreach ($mods as $m)
			if (method_exists($m, 'Check'))
				$msgs = array_merge($msgs, $m->Check());
		
		foreach ($msgs as $t => $m)
		{
			@$ret .= $m;
		}

		return $ret;
	}
}

Module::RegisterModule('ModCheck');

?>
