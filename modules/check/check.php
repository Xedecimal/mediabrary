<?php

class ModCheck extends Module
{
	public $Block = 'foot';

	function Link()
	{
		global $_d;

		if (empty($_d['q'][0]))
			$_d['nav.links']['Check Your Library'] = 'check';
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/check/css.css" />';
			#return '<a href="check" id="a-check"></a>';
		}

		if ($_d['q'][0] != 'check') return;

		// Allow realtime output
		@apache_setenv('no-gzip', 1);
   		@ini_set('zlib.output_compression', 0);
   		@ini_set('implicit_flush', 1);
		for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
   		ob_implicit_flush(1);

		global $mods;

		$this->_msgs = array();

		// Collect check messages
		foreach ($mods as $m)
			if (method_exists($m, 'Check'))
				$this->_msgs = array_merge_recursive($this->_msgs, $m->Check());

		$t = new Template();
		$t->ReWrite('group', array(&$this, 'TagGroup'));
		return $t->ParseFile('modules/check/t_check.xml');
	}

	function TagGroup($t, $g)
	{
		$vp = new VarParser();

		$ret = null;
		$ix = 0;
		foreach ($this->_msgs as $t => $ms)
		{
			$msgs = null;
			foreach ($ms as $m)
				$msgs .= '<p>'.$m.'</p>';
			$ret .= $vp->ParseVars($g, array(
				'id' => $ix,
				'title' => $t,
				'msgs' => $msgs,
				'count' => count($ms)
			));
			$ix++;
		}
		return $ret;
	}
}

Module::Register('ModCheck');

?>
