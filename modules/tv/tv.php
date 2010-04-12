<?php

class ModTVSeries extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

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

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/tv/css.css" />';
			return '<a href="tv" id="a-tv">Television</a>';
		}
		if (@$_d['q'][0] != 'tv') return;
		else if (@$_d['q'][1] == 'watch')
		{
			$series = $_d['q'][2];
			$file = $_d['q'][3];

			$url = 'http://'.GetVar('HTTP_HOST').'/nas/TV/'.rawurlencode($series).'/'.
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
			$m->_vars['series'] = $series;
			$m->Series = $series;
			return $m->Get();
		}
		else if (@$_d['q'][1] == 'search')
		{
			require_once('scrape.tvdb.php');
			return ModScrapeTVDB::Find($_d['q'][2]);
		}
		else if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/tv/t_item.xml';
			$this->_missing_image = 'modules/tv/img/missing.jpg';

			$this->_items = DataToArray($_d['tv.ds']->Get(), 'tv_path');
			$r = $_d['config']['tv_path'];
			$dp = opendir($r);
			while ($f = readdir($dp))
			{
				if ($f[0] == '.') continue;
				if (!is_dir($r.'/'.$f)) continue;
				$dirs[] = $r.'/'.$f;
			}

			foreach ($dirs as $f)
			{
				$this->_items[$f] = $this->ScrapeFS($f);
				$this->_items[$f] += $this->GetMedia('tv', $this->_items[$f], $this->_missing_image);
			}

			# Root directory is not included.
			unset($this->_items[$_d['config']['tv_path']]);

			die(parent::Get());
		}
		else if (@$_d['q'][1] == 'rename')
		{
			if (!rename(GetVar('src'), GetVar('dst'))) die('Error!');
			else die('Done.');
		}
		else
		{
			$this->_template = 'modules/tv/t_tv.xml';
			$t = new Template();
			return $t->ParseFile($this->_template);
		}
	}

	function Check()
	{
		global $_d;

		$ret = array();
		$mte = new ModTVEpisode;

		foreach (glob($_d['config']['tv_path'].'/*') as $fx)
		{
			$infozip = $fx.'/.info.zip';
			if (file_exists($infozip))
			{
				$za = new ZipArchive;
				$za->open($infozip);
				$sx = simplexml_load_string($za->getFromName('en.xml'));
				$za->close();
			}
			else $sx = null;

			foreach (glob($fx.'/*') as $fy)
			{
				$info = $mte->ScrapeFS($fy);
				if (empty($info['med_season'])) { varinfo($fy); continue; }
				$epname = '';
				if (!empty($sx))
				{
					$ep = $sx->xpath("//Episode[SeasonNumber={$info['med_season']}][EpisodeNumber={$info['med_episode']}]");
					if (!empty($ep)) $epname = MediaLibrary::CleanTitleForFile($ep[0]->EpisodeName, false);
				}
				# <series> / <series> - S<season>E<episode> - <title>.avi
				if (!preg_match('@([^/]+)/([^/]+) - S([0-9]+)E([0-9]+) - '.preg_quote($epname).'\.avi@', $fy))
				{
					$fname = "{$info['med_series']} - S{$info['med_season']}E{$info['med_episode']} - {$epname}";
					$url = l('tv/rename?src='.urlencode($fy).'&dst='.urlencode(dirname($fy).'/'.$fname.'.'.fileext($fy)));
					$ret['StrictNames'][] = "<div>File $fy has invalid name, should be \"$fname\" <a href=\"$url\" class=\"a-fix\">Fix</a></div>";
				}
			}
		}

		return $ret;
	}
}

Module::Register('ModTVSeries');

class ModTVEpisode extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		$this->_template = 'modules/tv/t_tv_series.xml';
		$this->_class = 'episode';
		$this->_fs_scrapes = array(
			//path/{series}/{series} - S{season}E{episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9]+)\s*-\s*([^.]+)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_series',
				3 => 'med_season',
				4 => 'med_episode',
				5 => 'med_title'),
			//path/{series}/{title} - S{season}E{episode}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9]+)\..*$#i' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'),
			//path/{series}/S{season}E{episode} - {title}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\s-\s(.+)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title'),
			//path/{series}/S{season}E{episode}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\.[^.]+$#i' => array(
				0 => 'med_title',
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode'),
			//path/{series}/{season}{episode} - {title}.ext
			'#/([^/]+)/([0-9]+)([0-9]{2})\s*-\s*(.+)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title'
			),
			//path/{series}/{title}S{season}E{episode}
			'#/([^/]+)/([^/]+)S([0-9]+)E([0-9]+)(.*)#i' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'
			)
		);
	}

	function Get()
	{
		global $_d;
		$this->_items = ModTVEpisode::GetEpisodes($_d['config']['tv_path'].'/'.$this->_vars['series']);
		return parent::Get();
	}

	static function GetEpisodes($path)
	{
		$tvi = new ModTVEpisode;
		$ret = array();
		foreach (glob($path.'/*') as $f) $ret[$f] = $tvi->ScrapeFS($f);
		return $ret;
	}
}

?>
