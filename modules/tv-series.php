<?php

require_once('mediadisplay.php');

class ModTVSeries extends MediaDisplay
{
	function __construct()
	{
		global $_d;

		$_d['tv.ds'] = new DataSet($_d['db'], 'tv');

		$this->_class = 'tv';

		$this->_fs_scrapes = array(
			'#/([^/]+)/([^/]+)$#' => array(
				1 => 'med_path',
				2 => 'med_title'
			)
		);
	}

	function Get()
	{
		global $_d;

		if (@$_d['q'][0] != 'tv') return;
		else if ($_d['q'][1] == 'watch')
		{
			$series = $_d['q'][2];
			$file = $_d['q'][3];

			$url = $_d['config']['tv_url'].'/'.rawurlencode($series).'/'.
				rawurlencode($file);
			$data = <<<EOF
#EXTINF:-1,{$file}
{$url}
EOF;

			SendDownloadStart(filenoext($file).'.m3u');
			die($data);
		}
		else if ($_d['q'][1] == 'series')
		{
			$series = GetVar('name');
			$m = new ModTVEpisode();
			return $m->Get($series);
		}
		else if ($_d['q'][1] == 'search')
		{
			return ModScrapeTVDB::Find($_d['q'][2]);
		}
		else
		{
			$this->_template = 't_tv.xml';
			$this->_metadata = DataToArray($_d['tv.ds']->Get(), 'tv_path');
			$this->_items = Comb($_d['config']['tv_path'], null, OPT_DIRS);
			unset($this->_items[0]);
			foreach ($this->_items as $i) $this->ScrapeFS($i);
			$this->_img_missing = 'img/missing-tv.png';

			return parent::Get();
		}
	}
}

Module::RegisterModule('ModTVSeries');

?>
