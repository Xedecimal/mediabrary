<?php

class ModCheck extends Module
{
	public $Block = 'foot';

	function Link()
	{
		global $_d;

		if (empty($_d['q'][0]))
			$_d['nav.links'][t('Check_Your_Library')] = 'check';
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
			$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/check/css.css" />';

		if ($_d['q'][0] != 'check') return;

		global $mods;

		$this->_msgs = array();

		session_write_close();

		$errors = 0;
		// Collect check messages
		foreach ($mods as $m)
			if (method_exists($m, 'Check'))
				$m->Check($this->_msgs);

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
