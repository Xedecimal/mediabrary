<?php

class ModCheck extends Module
{
	public $Block = 'foot';

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/check/css.css" />';
			return '<a href="check" id="a-check">Click here to check your library.</a>';
		}
		if ($_d['q'][0] != 'check') return;

		// Allow realtime output
		@apache_setenv('no-gzip', 1);
   		@ini_set('zlib.output_compression', 0);
   		@ini_set('implicit_flush', 1);
		for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
   		ob_implicit_flush(1);

		global $mods;

		$msgs = array();

		// Collect check messages
		foreach ($mods as $m)
			if (method_exists($m, 'Check'))
				$msgs = array_merge_recursive($msgs, $m->Check());

		$msgout = '';

		foreach ($msgs as $t => $ms)
			foreach ($ms as $m)
				$msgout .= "<div>{$m}</div>";

		$t = new Template();
		$t->Set('msgs', $msgout);
		return $t->ParseFile('modules/check/t_check.xml');
	}
}

Module::Register('ModCheck');

?>
