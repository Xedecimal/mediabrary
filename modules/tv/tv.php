<?php

require_once(Module::L('tv/scrape.tvdb.php'));
require_once(Module::L('tv/scrape.tvrage.php'));

class ModTVSeries extends MediaLibrary
{
	static $scrapers = array('ModScrapeTVDB', 'ModScrapeTVRage');

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

		$this->CheckActive('tv');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'getrss')
		{
			$max_date = 0;

			// Get up to this date.
			if (file_exists('rss_check.txt'))
				$stop_date = file_get_contents('rss_check.txt');
			else $stop_date = 0;

			foreach ($_d['config']->feeds->feed as $f)
			{
				$url = $f->attributes()->href;
				$date = $this->GetFeed($url, $stop_date);
				if ($date > $max_date) $max_date = $date;
			}

			// Do not re-check after this date.
			file_put_contents('rss_check.txt', $max_date);

			die();
		}
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/tv/css.css" />';

			foreach ($_d['config']->paths->path as $p)
			{
				if ($p->attributes()->type != 'tv') continue;

				$cp = $p->attributes()->path;

				$series = count(glob("$cp/*", GLOB_ONLYDIR));

				$total = $size = 0;
				foreach (Comb($cp, '#downloads#i', OPT_FILES) as $f)
				{
					$size += filesize($f);
					$total++;
				}
			}
			$size = GetSizeString($size);
			$text = "{$size} of {$series} Series in {$total} Episodes";

			return '<div id="divMainTV" class="main-link"><a href="tv" id="a-tv">'.$text.'</a></div>';
		}

		if (@$_d['q'][0] != 'tv') return;

		else if (@$_d['q'][1] == 'series')
		{
			$series = Server::GetVar('name');
			$m = new ModTVEpisode();
			$m->_vars['med_path'] = $series;
			$m->_vars['med_title'] = basename($series);
			$m->Series = $series;
			die($m->Get());
		}
		else if (@$_d['q'][1] == 'search')
		{
			return ModScrapeTVDB::Find(Server::GetVar('series'));
			die();
		}
		else if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/tv/t_item.xml';
			$this->_missing_image = 'modules/tv/img/missing.jpg';

			foreach ($_d['config']->xpath('paths/path[@type="tv"]') as $p)
			{
				$r = $p->attributes()->path;
				$dp = opendir($r);
				while ($f = readdir($dp))
				{
					if ($f[0] == '.') continue;
					if (!is_dir($r.'/'.$f)) continue;
					$dirs[] = $r.'/'.$f;
				}
			}

			asort($dirs);

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
			if (!rename(Server::GetVar('src'), Server::GetVar('dst'))) die('Error!');
			else die('Done.');
		}
		else if (@$_d['q'][1] == 'grab')
		{
			if (ModTVSeries::GrabEpisode(Server::GetVar('series'), Server::GetVar('season'),
				Server::GetVar('episode')))
				return "Successful!";
			return "Failure!";
		}
		else
		{
			$missings = array();
			// += overlaps episodes, combine instead.
			foreach (ModTVSeries::GetAllSeries() as $series)
			{
				$add = ModTVEpisode::GetMissingEpisodes($series);
				if (!empty($add)) $missings = array_merge($missings, $add);
			}

			$needed = null;
			foreach ($missings as $missing)
				$needed .= "<div>Missing: $missing</div>";

			$this->_template = 'modules/tv/t_tv.xml';
			$t = new Template();
			$t->Set($this->_vars);
			$t->Set('needed', $needed);
			return $t->ParseFile($this->_template);
		}
	}

	function Check()
	{
		global $_d;

		$ret = array();
		$mte = new ModTVEpisode;

		foreach ($_d['config']->xpath('paths/path[@type="tv"]') as $p)
		foreach (glob($p->attributes()->path.'/*') as $series)
		{
			foreach (ModTVSeries::$scrapers as $s)
				$eps = $s::GetEps($series);

			foreach (glob($series.'/*') as $episode)
			{
				if (is_dir($episode)) continue;

				$info = $mte->ScrapeFS($episode);
				if (empty($info['med_season']))
				{
					varinfo("Cannot recognize: $episode");
					continue;
				}

				$sn = (int)$info['med_season'];
				$en = (int)$info['med_episode'];
				$epname = '';
				if (!empty($eps[$sn][$en]['title']))
					$epname = $eps[$sn][$en]['title'];
				if (!empty($epname))
					$epname = MediaLibrary::CleanTitleForFile($epname, false);
				if (!empty($eps['series']))
					$eps['series'] = MediaLibrary::CleanTitleForFile($eps['series'], false);

				# <series> / <series> - S<season>E<episode> - <title>.avi
				if (!preg_match("@([^/]+)/({$eps['series']}) - S([0-9]{2})E([0-9]{2}) - ".preg_quote($epname).'\.([^.]+)$@', $episode))
				{
					$info['med_season'] = sprintf('%02d', $info['med_season']);
					$info['med_episode'] = sprintf('%02d', $info['med_episode']);
					$fname = "{$eps['series']} - S{$info['med_season']}E{$info['med_episode']} - {$epname}";
					$url = Module::L('tv/rename?src='.urlencode($episode).'&dst='.urlencode(dirname($episode).'/'.$fname.'.'.fileext($episode)));
					$ret['File Name Compliance'][] = "<a href=\"$url\" class=\"a-fix\">Fix</a> File $episode has invalid name, should be \"$fname\"";
				}
			}
		}

		return $ret;
	}

	function GetFeed($url, $stop_date)
	{
		$xml = file_get_contents($url);
		$xs = simplexml_load_string($xml);

		$max_date = 0;
		foreach ($xs->entry as $e)
		{
			$date = strtotime($e->updated);
			$href = @(string)$e->link->attributes()->href;
			$fname = basename($href);
			if (empty($fname)) continue;
			if ($date > $max_date) $max_date = $date;
			if ($date > $stop_date)
			{
				echo "Getting: {$fname}";
				file_put_contents("/data/nas/transfer/autoload/{$fname}",
					file_get_contents($href));
			}
		}

		return $max_date;
	}

	static function GetDownloadingEpisodes($series)
	{
		global $_d;

		/*require_once('File/Bittorrent2/Decode.php');
		$btd = new File_Bittorrent2_Decode;
		$mtve = new ModTVEpisode;
		$ret = '';
		foreach (glob('/data/nas/torrent-files/*.torrent') as $f)
		{
			try { $tinfo = $btd->decode(file_get_contents($f)); }
			catch (File_Bittorrent2_Exception $fbte)
			{
				unlink($f);
				echo "$f is messed up. $fbte";
				continue;
			}

			$files = array();
			if (!empty($tinfo['info']['files']))
				foreach ($tinfo['info']['files'] as $f) $files[] = $f['path'][0];
			else $files[] = $tinfo['info']['name'];

			foreach ($files as $f)
			{
				$info = $mtve->ScrapeFS($_d['config']['tv_path'].'/'.$f);
				if (!empty($info['med_title']))
				{
					$cleantitle = MediaLibrary::CleanTitleForFile($info['med_title']);
					$s = number_format($info['med_season']);
					$e = number_format($info['med_episode']);
					$ret[$cleantitle][$s][$e] = $info;
				}
			}
		}

		return $ret;*/
	}

	static function GetAllSeries()
	{
		global $_d;

		$ret = array();
		foreach ($_d['config']->xpath('paths/path[@type="tv"]') as $p)
			foreach (glob($p->attributes()->path.'/*') as $fx)
				$ret[] = $fx;

		return $ret;
	}

	static function GrabEpisode($series, $season, $episode)
	{
		$file_rss = "$series/.ezrss-title.txt";
		$file_title = "$series/.title.txt";

		if (file_exists($file_rss))
			$name = file_get_contents($file_rss);
		else if (file_exists($file_title))
			$name = file_get_contents($file_title);
		else $name = basename($series);

		$url = 'http://ezrss.it/search/index.php?show_name='
			.rawurlencode($name).'&mode=rss'
			."&season={$season}&episode=".
			$episode;
		varinfo($url);
		$xml = file_get_contents($url);
		varinfo($xml);
		$sx = simplexml_load_string($xml);
		$link = $sx->xpath('//channel/item/link');
		if (empty($link)) return false;
		file_put_contents('/data/nas/torrent-files/'.basename($link[0]),
			file_get_contents($link[0]));
		return true;
	}

	static function GetInfo($series)
	{
		$eps = array();
		foreach (ModTVSeries::$scrapers as $s)
		{
			$neps = $s::GetInfo($series);
			$eps = array_replace_recursive($eps, $neps);
		}
		/*if ($series == '/data/nas/TV/House')
			varinfo($eps);*/
		return $eps;
	}
}

Module::Register('ModTVSeries');

class ModTVEpisode extends MediaLibrary
{
	function __construct()
	{
		$this->_template = 'modules/tv/t_tv_series.xml';
		$this->_class = 'episode';
		$this->_fs_scrapes = array(
			# path/{series}/{series} Season {season} - {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]+)\s+-\s+([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_series',
				3 => 'med_season',
				4 => 'med_episode',
				5 => 'med_title'),
			# path/{series}/{series} - S{season}E{episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_series',
				3 => 'med_season',
				4 => 'med_episode',
				5 => 'med_title'),
			# path/{series}/{title} - S{season}E{episode}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9]+)\..*$#i' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'),
			# path/{series}/S{season}E{episode} - {title}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\s-\s(.+)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title'),
			//path/{series}/S{season}E{episode}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\.[^.]+$#i' => array(
				0 => 'med_title', # Substituted as filename
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode'),
			//path/{series}/{season}{episode} - {title}.ext
			'#/([^/]+)/(\d+)(\d{2})\s*-\s*(.+)\.[^.]+$#i' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title'),
			//path/{series}/{title}S{season}E{episode}
			'#/([^/]+)/([^/]+)S(\d+)E(\d+)(.*)#i' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'),
			//path/{series}/{title}{S+}{EE}
			'#/([^/]+)/([^/]+)(\d+)(\d{2}).*#' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'),
			# path/{series}/{S}{EE} {title}.ext
			'#/([^/]+)/(\d+)(\d{2}) (.*)\..*$#' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode',
				4 => 'med_title',
			),
			# path/{series}/{Season}x{Episode}
			'#/([^/]+)/[^/]+(\d+)x(\d+).*#' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode')
		);
	}

	function Get()
	{
		global $_d;
		$this->_items = ModTVEpisode::GetExistingEpisodes($this->_vars['med_path']);

		$sx = ModScrapeTVDB::GetXML($this->_vars['med_path']);
		if (!empty($sx))
		{
			$elEps = $sx->xpath('//Episode');
			foreach ($elEps as $elEp)
			{
				$s = (int)$elEp->SeasonNumber;
				if (empty($s)) continue;
				$e = (int)$elEp->EpisodeNumber;
				$this->_items[$s][$e]['med_season'] = sprintf('%02d', $s);
				$this->_items[$s][$e]['med_episode'] = sprintf('%02d', $e);
				$this->_items[$s][$e]['med_title'] = (string)$elEp->EpisodeName;
				$this->_items[$s][$e]['med_date'] = (string)$elEp->FirstAired;
				$this->_items[$s][$e]['have'] = isset($this->_items[$s][$e]['fs_path']) ? 1 : 0;
			}
		}

		$t = new Template();
		$t->ReWrite('item', array(&$this, 'TagItem'));
		$t->Set($this->_vars);
		return $t->ParseFile($this->_template);
		return parent::Get();
	}

	function TagItem($t, $g)
	{
		$vp = new VarParser();

		$ret = null;
		foreach ($this->_items as $s => $ss)
			foreach ($ss as $e => $es)
			{
				if (isset($es['fs_path']))
					$es['url'] = urlencode($es['fs_path']);
				$ret .= $vp->ParseVars($g, $es);
			}

		return $ret;
	}

	static function GetExistingEpisodes($series)
	{
		global $_d;
		$tvi = new ModTVEpisode;

		$ret = array();
		foreach (glob($series.'/*') as $f)
		{
			if (is_dir($f)) continue;
			$i = $tvi->ScrapeFS($f);

			if (!isset($i['med_episode']))
			{
				varinfo('Missing episode on this...');
				varinfo($i);
			}
			// Multi-episode file
			if (preg_match('/([0-9]+)-([0-9]+)/', $i['med_episode'], $m))
			{
				for ($ix = $m[1]; $ix <= $m[2]; $ix++)
				{
					$i['med_episode'] = $ix;
					$snf = number_format($i['med_season']);
					$enf = number_format($i['med_episode']);
					$ret[$snf][$enf] = $i;
				}
			}
			else
			{
				$snf = number_format($i['med_season']);
				$enf = number_format($i['med_episode']);
				$ret[$snf][$enf] = $i;
			}
		}

		arksort($ret);
		return $ret;
	}

	static function GetMissingEpisodes($series)
	{
		$eps = ModTVEpisode::GetExistingEpisodes($series);
		//$eps += ModTVSeries::GetDownloadingEpisodes($series);
		$down = ModTVSeries::GetDownloadingEpisodes($series);
		if (isset($down[$series]))
			$eps += $down[$series];

		# All Episodes
		$aeps = ModTVSeries::GetInfo($series);

		$ret = array();
		foreach ($aeps['eps'] as $sn => $season)
		foreach ($season as $en => $ep)
		{
			$snp = sprintf('%02d', $sn);
			$enp = sprintf('%02d', $en);
			if (empty($sn) || empty($en) || empty($ep['aired'])) continue;

			if ($ep['aired'] < time())
			{
				if (!isset($eps[$sn][$en]))
				{
					$sname = basename($series);
					$query = rawurlencode("$sname S{$snp}E{$enp}");
					$ser = rawurlencode($series);
					$aired = date('m/d/Y', $ep['aired']);
					$ret[] = "<a href=\"http://www.torrentz.com/search?q=$query\" target=\"_blank\">
						$series S{$snp}E{$enp}</a> - {$aired} <a
						href=\"{{app_abs}}/tv/grab?series=$ser&season=$snp&episode=$enp\"
						target=\"_blank\">Attempt quick torrent grab</a>";
				}
			}
			else if ($ep['aired'] < strtotime('next week'))
			{
				$ret[] = "Next week: $series $snp $enp";
			}
		}

		return $ret;
	}
}

?>
