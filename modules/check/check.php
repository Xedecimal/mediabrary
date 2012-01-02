<?php

class ModCheck extends Module
{
	public $Block = 'foot';

	function __construct()
	{
		$this->state_file = dirname(__FILE__).'/check.dat';
		$this->CheckActive('check');
	}

	function Link()
	{
		global $_d;

		$_d['nav.links'][t('Tools').'/'.t('Check_Everything')] = 'check';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'prepare')
		{
			global $mods;

			foreach ($mods as $n => $m)
			{
				if (method_exists($m, 'CheckPrepare')) $m->CheckPrepare();
				if (method_exists($m, 'Check')) $state['mods'][$n] = 1;
			}

			$state['index'] = 0;
			file_put_contents($this->state_file, serialize($state));

			die();
		}

		if (@$_d['q'][1] == 'one')
		{
			global $mods;

			if (file_exists($this->state_file))
				$state = unserialize(file_get_contents($this->state_file));

			while (!empty($state['mods']))
			{
				$keys = array_keys($state['mods']);
				if ($state['index'] >= count($keys)) $state['index'] = 0;
				$ix = $state['index'];

				$n = $keys[$ix];
				$m = $mods[$n];
				try { $ret = $m->Check(); }
				catch (CheckException $e) { $ret['msg'] = $e->getMessage(); }
				$ret['source'] = $n;
				if (++$state['index'] >= count($keys)) $state['index'] = 0;

				if (empty($ret['msg'])) { unset($state['mods'][$n]); continue; }
				file_put_contents($this->state_file, serialize($state));
				die(json_encode($ret));
			}

			die();
		}
	}

	function Get()
	{
		global $_d;

		$q['errors']['$exists'] = 1;
		$c = $_d['entry.ds']->find($q)->count();
		if ($c > 0)
			$r['notify'] = "<a href=\"{{app_abs}}/check\"><img src=\"{{app_abs}}/img/exclamation.png\" alt=\"Error\" style=\"vertical-align: bottom\" /> {$c} media files have errors that require your attention.</a>";

		if ($_d['q'][0] != 'check') return $r;

		$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/check/check.css" />';
		global $mods;

		$this->_msgs = array();

		session_write_close();

		$errors = 0;

		# Collect check messages
		//foreach ($mods as $m)
		//	if (method_exists($m, 'Check')) $m->Check($this->_msgs);

		$t = new Template();
		$t->ReWrite('group', array(&$this, 'TagGroup'));
		$r['check'] = $t->ParseFile('modules/check/t_check.xml');
		return $r;
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
