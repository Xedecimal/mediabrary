<?php

require_once(dirname(__FILE__).'/../xedlib/classes/present/form.php');

class Config extends Module
{
	function __construct()
	{
		$this->CheckActive('configuration');
	}

	function Link()
	{
		global $_d;

		$_d['nav.links'][t('Tools').'/'.t('Configuration')] = '{{app_abs}}/configuration';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'save')
		{
			$out = Server::GetVar('nc');
			$out['paths']['movie'] = explode("\r\n", trim($out['paths']['movie']));
			$out['paths']['tv'] = explode("\r\n", trim($out['paths']['tv']));
			$_d['config'] = array_merge($_d['config'], $out);
			file_put_contents('config/config.yml', Spyc::YAMLDump($_d['config']));
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		foreach (new FilesystemIterator('lang', FileSystemIterator::SKIP_DOTS) as $f)
		{
			$n = substr($f->getFilename(), 0, -4);
			$langs[$n] = $n;
		}

		global $_d;

		# Default Language
		$langs = FormOption::FromArray($langs);
		if (isset($_d['config']['lang']))
			$langs[$_d['config']['lang']]->selected = true;

		# Configuration Form
		$frm = new Form('frmConfig');
		$frm->AddInput('General');
		$frm->AddInput(new FormInput('Language', 'select', 'nc[lang]', $langs));
		$frm->AddInput(new FormInput('Database Location', 'text', 'nc[db]',
			$_d['config']['db']));
		$frm->AddInput('Paths');
		$frm->AddInput(new FormInput('Movies', 'area', 'nc[paths][movie][paths]',
			implode("\r\n", $_d['config']['paths']['movie']['paths'])));
		$frm->AddInput(new FormInput('Movie Metadata', 'text',
			'nc[paths][movie][meta]', $_d['config']['paths']['movie']['meta']));
		$frm->AddInput(new FormInput('Shows', 'area', 'nc[paths][tv][paths]',
			implode("\r\n", $_d['config']['paths']['tv']['paths'])));
		$frm->AddInput(new FormInput('Show Metadata', 'text',
			'nc[paths][tv][meta]', $_d['config']['paths']['tv']['meta']));
		$frm->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Save'));
		return $frm->Get(array('method' => 'post', 'action' => '{{app_abs}}/configuration/save'));
	}
}

Module::Register('Config');

?>
