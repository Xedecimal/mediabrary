<?php

class ModCheck extends Module
{
	public $Block = 'foot';
	public $Name = 'check';

	function __construct()
	{
		global $_d;

		$this->CheckActive('check');
	}

	function Link()
	{
		global $_d;

		$_d['nav.links'][t('Tools').'/'.t('Check')] = '{{app_abs}}/check';

		global $mods;
		foreach ($mods as $n => $m)
		{
			if (method_exists($m, 'Check'))
				$_d['nav.links'][t('Tools').'/'.t('Check').'/'.$n] = '{{app_abs}}/check/'.$n;
		}
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'run')
		{
			global $mods;

			set_time_limit(120);
			session_write_close();
			echo str_repeat(' ', 1024)."\r\n";
			flush();

			if (!empty($_d['q'][2])) $checkers[$_d['q'][2]] = $mods[$_d['q'][2]];
			else foreach ($mods as $n => $m) $checkers[$n] = $m;

			if (@$_d['q'][3] == 'clean') $clean = true;
			else $clean = false;

			foreach ($checkers as $n => $m)
			{
				if (method_exists($m, 'Check')) $m->Check($clean);
				flush();
			}

			foreach ($checkers as $n => $m)
			{
				if (method_exists($m, 'CheckComplete')) $m->CheckComplete();
				flush();
			}

			die();
		}

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
					$ret['msg'] = urlencode($e->msg);
				}
				if (++$state['index'] >= count($keys)) $state['index'] = 0;

				if (empty($ret['msg']))
				{
					unset($state['mods'][$n]);
					$ret['msg'] = "$n is done with it's job.";
					$ret['source'] = $this->Name;
				}
				$_d['state.ds']->save($state);

				$end = json_encode($ret);
				die($end);
			}

			$time = U::GetDateOffset($state['start']);
			die(json_encode(array('source' => $this->Name,
				'msg' => "Scanned {$_d['entry.ds']->find()->count()} files in $time.", 'stop' => 1)));
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'check') return;

		$css = Module::P('modules/check/check.css');
		$r['head'] = '<link type="text/css" rel="stylesheet" href="'.$css.'" />';
		global $mods;

		$t = new Template();
		$t->ReWrite('group', array(&$this, 'TagGroup'));
		$type = $_d['app_abs'].'/check/run';
		$t->Set('check_type', $type.(!empty($_d['q'][1]) ? '/'.$_d['q'][1] : ''));
		$r['check'] = $t->ParseFile('modules/check/t_check.xml');
		return $r;
	}

	function TagError($t, $g)
	{
		if (!empty($t->vars['errors']))
			return VarParser::Concat($g, $t->vars['errors']);
	}

	static function Out($msg)
	{
		echo '<p>'.$msg."</p>\r\n";
		flush();
	}
}

Module::Register('ModCheck');
