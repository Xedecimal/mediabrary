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

		$this->_class = 'tv';
		$this->_thumb_path = $_d['config']['paths']['tv-meta'];

		$this->CheckActive('tv');
	}

	static function GetFSPregs()
	{
		return array(
			'#/([^/]+)/([^/]+)$#' => array(
				1 => 'med_path',
				2 => 'med_title'
			)
		);
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
			$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/tv/css.css" />';

			$series = $size = $total = 0;

			if (!empty($_d['config']['paths']['tv']))
			foreach ($_d['config']['paths']['tv'] as $p)
			{
				$series = count(glob("$p/*", GLOB_ONLYDIR));

				//$total = $size = 0;
				//foreach (File::Comb($p, '#downloads#i', SCAN_FILES) as $f)
				//{
				//	$size += filesize($f);
				//	$total++;
				//}
			}
			$size = File::SizeToString($size);
			$text = "{$size} of {$series} Series in {$total} Episodes";

			$r['default'] = '<div id="divMainTV" class="main-link"><a href="tv" id="a-tv">'.$text.'</a></div>';
			return $r;
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
			$ret = '';
			foreach (ModTVSeries::$scrapers as $s)
				$ret .= call_user_func(array($s, 'Find'), Server::GetVar('series'), true);
			return $ret;
			die();
		}
		else if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/tv/t_item.xml';
			$this->_missing_image = 'modules/tv/img/missing.jpg';

			if (!empty($_d['config']['paths']['tv']))
			foreach ($_d['config']['paths']['tv'] as $p)
			{
				$dp = opendir($p);
				while ($f = readdir($dp))
				{
					if ($f[0] == '.') continue;
					if (!is_dir($p.'/'.$f)) continue;
					$dirs[] = $p.'/'.$f;
				}
			}

			if (is_array(@$dirs)) asort($dirs);

			$ep_pregs = ModTVSeries::GetFSPregs();
			if (!empty($dirs))
			foreach ($dirs as $f)
			{
				$this->_items[$f] = MediaLibrary::ScrapeFS($f, $ep_pregs);
				$this->_items[$f] += $this->GetMedia('tv', $this->_items[$f],
					$this->_missing_image);
			}

			# Root directories are not included.
			if (!empty($_d['config']['paths']['tv']))
			foreach ($_d['config']['paths']['tv'] as $p)
				unset($this->_items[$p]);

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

		$pregs = ModTVEpisode::GetFSPregs();

		if (!empty($_d['config']['paths']['tv']))
		foreach ($_d['config']['paths']['tv'] as $p)
		foreach (new FilesystemIterator($p,
		FilesystemIterator::SKIP_DOTS) as $fser)
		{
			$series = $fser->GetPathname();
			foreach (ModTVSeries::$scrapers as $s)
				$eps = call_user_func(array($s, 'GetInfo'), $series);

			foreach (new FilesystemIterator($series,
			FilesystemIterator::SKIP_DOTS) as $fep)
			{
				if (substr($fep->GetFilename(), 0, 1) == '.') continue;
				$episode = str_replace('\\', '/', $fep->GetPathname());

				$info = MediaLibrary::ScrapeFS($episode, $pregs);
				if (empty($info['med_season']))
				{
					var_dump("Cannot recognize: $episode");
					continue;
				}

				$sn = (int)$info['med_season'];
				$en = (int)$info['med_episode'];
				$epname = '';
				if (!empty($eps['eps'][$sn][$en]['title']))
					$epname = $eps['eps'][$sn][$en]['title'];
				if (!empty($epname))
					$epname = MediaLibrary::CleanTitleForFile($epname, false);
				if (!empty($eps['series']))
					$eps['series'] = MediaLibrary::CleanTitleForFile($eps['series'], false);

				# <series> / <series> - S<season>E<episode> - <title>.avi
				if (!preg_match("@([^/]+)/({$eps['series']}) - S([0-9]{2})E([0-9]{2}) - ".preg_quote($epname).'\.([^.]+)$@', $episode))
				{
					$ext = File::ext($episode);
					$dir = dirname($episode);
					$info['med_season'] = sprintf('%02d', $info['med_season']);
					$info['med_episode'] = sprintf('%02d', $info['med_episode']);
					$fname = "{$eps['series']} - S{$info['med_season']}E{$info['med_episode']} - {$epname}";
					$url = Module::L('tv/rename?src='.urlencode($episode).'&amp;dst='.urlencode("$dir/$fname.$ext"));
					$ret['File Name Compliance'][] = "<a href=\"$url\" class=\"a-fix\">Fix</a> File $episode has invalid name, should be \"$dir/$fname.$ext\"";
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

	static function GetAllSeries()
	{
		global $_d;

		$ret = array();
		if (!empty($_d['config']['paths']['tv']))
		foreach ($_d['config']['paths']['tv'] as $p)
			foreach (glob($p.'/*') as $fx)
				$ret[] = $fx;

		return $ret;
	}

	static function GetInfo($series)
	{
		$eps = array();
		foreach (ModTVSeries::$scrapers as $s)
		{
			$neps = call_user_func(array($s, 'GetInfo'), $series);
			$eps = array_replace_recursive($eps, $neps);
		}
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

	static function GetFSPregs()
	{
		return array(
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
				3 => 'med_episode'),
			# path/{series}/{series} - {episode} - {title}.ext
			'#/([^/]+)/[^-]+\s*-\s*(\d+)\s*-\s*(.*)\.([^.]+)$#' => array(
				1 => 'med_series',
				2 => 'med_episode',
				3 => 'med_title',
				4 => 'med_extension'
			)
		);
	}

	static function GetExistingEpisodes($series)
	{
		global $_d;
		$tvi = new ModTVEpisode;

		$ret = array();
		foreach (new FilesystemIterator($series,
		FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (substr($f->GetFilename(), 0, 1) == '.') continue;

			$p = $f->GetPathname();
			$i = MediaLibrary::ScrapeFS($p, ModTVEpisode::GetFSPregs());
			#$i = $tvi->ScrapeFS($f);

			if (!isset($i['med_episode']))
			{
				U::VarInfo('Missing episode on this...');
				U::VarInfo($i);
				continue;
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

				$snf = isset($i['med_season'])
					? number_format($i['med_season'])
					: 1;
				$enf = number_format($i['med_episode']);
				$ret[$snf][$enf] = $i;
			}
		}

		Arr::ARKSort($ret);
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
					$rout = "<a href=\"http://www.torrentz.com/search?q=$query\" target=\"_blank\">
						$series S{$snp}E{$enp}</a> - {$aired} <a
						href=\"{{app_abs}}/tv/grab?series=$ser&season=$snp&episode=$enp\"
						target=\"_blank\">Attempt quick torrent grab</a>";
					if (!empty($ep['links']))
					foreach ($ep['links'] as $n => $l)
					{
						$rout .= " - <a href=\"$l\" target=\"_blank\">$n</a>";
					}
					$ret[] = $rout;
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
