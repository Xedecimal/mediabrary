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

			$series = count(glob("{$_d['config']['tv_path']}/*", GLOB_ONLYDIR));

			$total = $size = 0;
			foreach (Comb($_d['config']['tv_path'], '#downloads#i', OPT_FILES) as $f)
			{
				$size += filesize($f);
				$total++;
			}
			$size = GetSizeString($size);
			$text = "{$size} of {$series} Series in {$total} Episodes";

			return '<a href="tv" id="a-tv" class="main-link">'.$text.'</a>';
		}
		if (@$_d['q'][0] != 'tv') return;
		else if (@$_d['q'][1] == 'watch')
		{
			$series = $_d['q'][2];

			# Entire Season
			if (empty($_d['q'][3]))
			{
				$ret = '';
				$files = glob('/data/nas/TV/'.$series.'/*.avi');
				foreach ($files as $ix => $f)
				{
					$ret .= "#EXTINF:,$ix,".basename($f)."\r\n";
					$ret .= 'http://'.GetVar('HTTP_HOST').'/nas/TV/'.rawurlencode($series).'/'.rawurlencode(basename($f))."\r\n";
				}
				SendDownloadStart($series.'.m3u');
				die($ret);
			}

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
			$m->_vars['med_title'] = $series;
			$m->Series = $series;
			die($m->Get());
		}
		else if (@$_d['q'][1] == 'search')
		{
			require_once('scrape.tvdb.php');
			return ModScrapeTVDB::Find($_d['q'][2]);
			die();
		}
		else if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/tv/t_item.xml';
			$this->_missing_image = 'modules/tv/img/missing.jpg';

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
		else if (@$_d['q'][1] == 'grab')
		{
			if (ModTVSeries::GrabEpisode(GetVar('series'), GetVar('season'),
				GetVar('episode')))
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
				if (empty($info['med_season'])) { varinfo("Cannot recognize: $fy"); continue; }
				$epname = '';
				if (!empty($sx))
				{
					$ep = $sx->xpath("//Episode[SeasonNumber={$info['med_season']}][EpisodeNumber={$info['med_episode']}]");
					if (!empty($ep)) $epname = MediaLibrary::CleanTitleForFile($ep[0]->EpisodeName, false);
				}
				# <series> / <series> - S<season>E<episode> - <title>.avi
				if (!preg_match('@([^/]+)/([^/]+) - S([0-9]{2})E([0-9]{2}) - '.preg_quote($epname).'\.avi@', $fy))
				{
					$info['med_season'] = sprintf('%02d', $info['med_season']);
					$info['med_episode'] = sprintf('%02d', $info['med_episode']);
					$fname = "{$info['med_series']} - S{$info['med_season']}E{$info['med_episode']} - {$epname}";
					$url = l('tv/rename?src='.urlencode($fy).'&dst='.urlencode(dirname($fy).'/'.$fname.'.'.fileext($fy)));
					$ret['StrictNames'][] = "<div>File $fy has invalid name, should be \"$fname\" <a href=\"$url\" class=\"a-fix\">Fix</a></div>";
				}
			}
		}

		return $ret;
	}

	static function GetDownloadingEpisodes($series)
	{
		global $_d;

		require_once('File/Bittorrent2/Decode.php');
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

		return $ret;
	}

	static function GetAllSeries()
	{
		global $_d;

		foreach (glob($_d['config']['tv_path'].'/*') as $fx)
			$ret[] = basename($fx);

		return $ret;
	}

	static function GrabEpisode($series, $season, $episode)
	{
		$url = 'http://ezrss.it/search/index.php?show_name='
			.rawurlencode($series).'&show_name_exact=true&mode=rss'
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
}

Module::Register('ModTVSeries');

class ModTVEpisode extends MediaLibrary
{
	function __construct()
	{
		$this->_template = 'modules/tv/t_tv_series.xml';
		$this->_class = 'episode';
		$this->_fs_scrapes = array(
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
				4 => 'med_title'
			),
			//path/{series}/{title}S{season}E{episode}
			'#/([^/]+)/([^/]+)S(\d+)E(\d+)(.*)#i' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'
			),
			//path/{series}/{title}{S+}{EE}
			'#/([^/]+)/([^/]+)(\d+)(\d{2}).*#' => array(
				1 => 'med_series',
				2 => 'med_title',
				3 => 'med_season',
				4 => 'med_episode'
			),
			# path/{series}/{Season}x{Episode}
			'#/([^/]+)/[^/]+(\d+)x(\d+).*#' => array(
				1 => 'med_series',
				2 => 'med_season',
				3 => 'med_episode'
			)
		);
	}

	function Get()
	{
		require_once('scrape.tvdb.php');

		global $_d;
		$this->_items = ModTVEpisode::GetExistingEpisodes($this->_vars['med_title']);

		$sx = ModScrapeTVDB::GetXML($this->_vars['med_title']);
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
				$ret .= $vp->ParseVars($g, $es);

		return $ret;
	}

	static function GetExistingEpisodes($series)
	{
		global $_d;
		$tvi = new ModTVEpisode;

		$ret = array();
		foreach (glob($_d['config']['tv_path'].'/'.$series.'/*') as $f)
		{
			$i = $tvi->ScrapeFS($f);

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
		return $ret;
	}

	static function GetMissingEpisodes($series)
	{
		require_once(l('tv/scrape.tvdb.php'));
		$eps[$series] = ModTVEpisode::GetExistingEpisodes($series);
		//$eps += ModTVSeries::GetDownloadingEpisodes($series);
		$down = ModTVSeries::GetDownloadingEpisodes($series);
		if (isset($down[$series]))
			$eps[$series] += $down[$series];

		$sx = ModScrapeTVDB::GetXML($series);
		if (empty($sx)) return;
		$elEps = $sx->xpath('//Episode');

		$ret = array();
		foreach ($elEps as $elEp)
		{
			$s = number_format((int)$elEp->SeasonNumber);
			$ss = sprintf('%02d', $s);
			$e = number_format((int)$elEp->EpisodeNumber);
			$ee = sprintf('%02d', $e);
			if (empty($s) || empty($e) || empty($elEp->FirstAired)) continue;

			if (MyDateTimestamp($elEp->FirstAired) < time())
			{
				if (!isset($eps[$series][$s][$e]))
				{
					$query = rawurlencode("$series S{$ss}E{$ee}");
					$ret[] = "<a href=\"http://www.torrentz.com/search?q=$query\" target=\"_blank\">
						$series S{$ss}E{$ee}</a> - {$elEp->FirstAired} <a
						href=\"{{app_abs}}/tv/grab?series=$series&season=$s&episode=$e\"
						target=\"_blank\">Attempt quick torrent grab</a>";
				}
			}
			else if (MyDateTimestamp($elEp->FirstAired) < strtotime('next week'))
			{
				$ret[] = "Next week: $series $ss $ee";
			}
		}

		return $ret;
	}
}

?>
