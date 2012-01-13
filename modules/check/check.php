<?php

class ModCheck extends Module
{
	public $Block = 'foot';
	public $Name = 'check';

	function __construct()
	{
		global $_d;

		$_d['state.ds'] = $_d['db']->state;
		$_d['state.ds']->ensureIndex(array('module' => 1),
			array('unique' => 1, 'dropDups' => 1, 'safe' => 1));

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

			$state['module'] = $this->Name;
			$state['index'] = 0;
			$state['start'] = time();
			$_d['state.ds']->remove(array('module' => $this->Name));
			$_d['state.ds']->save($state, array('safe' => 1));

			die();
		}

		if (@$_d['q'][1] == 'one')
		{
			global $mods;

			# This will free up the server for further requests.
			session_write_close();

			$state = $_d['state.ds']->findOne(array('module' => $this->Name));

			while (!empty($state['mods']))
			{
				$keys = array_keys($state['mods']);
				if ($state['index'] >= count($keys)) $state['index'] = 0;
				$ix = $state['index'];

				$n = $keys[$ix];
				$m = $mods[$n];

				$ret['source'] = $n;
				try { $ret = $m->Check(); }
				catch (CheckException $e)
				{
					if (!empty($e->source)) $ret['source'] = $e->source;
					$ret['msg'] = $e->msg;
				}
				if (++$state['index'] >= count($keys)) $state['index'] = 0;

				if (empty($ret['msg']))
				{
					unset($state['mods'][$n]);
					$ret['msg'] = "$n is done with it's job.";
					$ret['source'] = $this->Name;
				}
				$_d['state.ds']->save($state);

				die(json_encode($ret));
			}

			$time = U::GetDateOffset($state['start']);
			die(json_encode(array('source' => $this->Name,
				'msg' => "Scanned {$_d['entry.ds']->find()->count()} files in $time.", 'stop' => 1)));
		}
	}

	function Get()
	{
		global $_d;

		$r = array();
		$q['errors']['$exists'] = 1;
		$c = $_d['entry.ds']->find($q)->count();
		if ($c > 0)
			$r['notify'] = "<a href=\"{{app_abs}}/check\"><img
			src=\"{{app_abs}}/img/exclamation.png\" alt=\"Error\"
			style=\"vertical-align: bottom\" /> {$c} media files have errors
			that require your attention.</a>";

		if ($_d['q'][0] != 'check') return $r;

		$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/check/check.css" />';
		global $mods;

		$errors = 0;

		$t = new Template();
		$t->ReWrite('group', array(&$this, 'TagGroup'));
		$r['check'] = $t->ParseFile('modules/check/t_check.xml');
		return $r;
	}

	function TagGroup($t, $g)
	{
		global $_d;

		$q['errors']['$exists'] = 1;
		$cols['path'] = 1;
		$cols['errors'] = 1;

		$t = new Template();
		$t->ReWrite('error', array(&$this, 'TagError'));
		$items = $_d['entry.ds']->find($q, $cols);
		foreach ($items as $ix => $i)
		{
			if (empty($i['errors']))
			{
				unset($i['errors']);
				# Path is the only column available here!
				#$_d['entry.ds']->save($i);
			}
		}
		return $t->Concat($g, $items);
	}

	function TagError($t, $g)
	{
		if (!empty($t->vars['errors']))
			return VarParser::Concat($g, $t->vars['errors']);
	}
}

Module::Register('ModCheck');

?>
