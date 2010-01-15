<?php

require_once('medialibrary.php');

class ModTVSeries extends MediaLibrary
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
		else if (@$_d['q'][1] == 'watch')
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
		else if (@$_d['q'][1] == 'series')
		{
			$series = GetVar('name');
			$m = new ModTVEpisode();
			$m->Series = $series;
			return $m->Get();
		}
		else if (@$_d['q'][1] == 'search')
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

class ModTVEpisode extends MediaLibrary
{
	function __construct()
	{
		$this->_template = 't_tv_series.xml';
		$this->_class = 'episode';
		$this->_fs_scrapes = array(
			//path/{series}/{series} - S{season}E{episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*-\s*S([0-9]+)E([0-9]+)\s*-\s*([^.]+)\.[^.]+$#' => array(
				1 => 'med_series',
				2 => 'med_series',
				3 => 'med_season',
				4 => 'med_episode',
				5 => 'med_title'),
			//path/{series}/{title} - S{season}E{episode}.ext
			'#/([^/]+)/([^/-]+)\s-\sS([0-9]+)E([0-9]+)\..*$#' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'),
			//path/{series}/S{season}E{episode} - {title}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\s-\s([^.]+).*$#' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title'),
			//path/{series}/S{season}E{episode}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\..*#' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode'),
			//path/{series}/{season}{episode} - {title}.ext
			'#/([^/]+)/([0-9]+)([0-9]{2})\s*-\s*(.+)\.[^.]*$#' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title'
			)
		);
	}
	
	function Get()
	{
		global $_d;

		$this->_items = glob($_d['config']['tv_path'].'/'.$this->Series.'/*');
		foreach ($this->_items as $i) $this->ScrapeFS($i);
		return parent::Get();
	}
}

?>
