<?php

class ModTVEpisode extends MediaDisplay
{
	function __construct()
	{
		$this->_template = 't_tv_series.xml';
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
	
	function Get($series)
	{
		global $_d;

		$this->_items = glob($_d['config']['tv_path'].'/'.$series.'/*');
		foreach ($this->_items as $i) $this->ScrapeFS($i);
		return parent::Get();
	}
}

?>
