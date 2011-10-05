<?php

class TV extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$this->_class = 'tv';

		$this->CheckActive(array('tv', 'tv-episode'));
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

	function Link()
	{
		global $_d;

		$_d['nav.links']['Media/TV'] = '{{app_abs}}/tv';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][0] == 'tv-episode')
		{
			if (@$_d['q'][1] == 'detail')
			{
				$ds = $_d['entry.ds']->findOne(array('_id' => new MongoId($_d['q'][2])));
				$i = new TVEpisodeEntry($ds['path']);
				$t = new Template();
				$t->Set($i);
				$t->Set($ds);
				die($t->ParseFile(Module::L('tv/tv-episode-detail.xml')));
			}
		}

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

		/*if (empty($_d['q'][0]))
		{
			$ret = '<link type="text/css" rel="stylesheet" href="modules/tv/css.css" />';

			$series = $size = $total = 0;

			if (!empty($_d['config']['paths']['tv']))
			foreach ($_d['config']['paths']['tv'] as $p)
				$series = count(glob("$p/*", GLOB_ONLYDIR));

			$size = File::SizeToString($size);
			$text = "{$size} of {$series} Series in {$total} Episodes";

			$ret .= '<div id="divMainTV" class="main-link"><a href="tv" id="a-tv">'.$text.'</a></div>';
			return die($ret);
		}*/

		if (@$_d['q'][0] != 'tv') return;

		$this->_items = TV::CollectFS();

		if (@$_d['q'][1] == 'series')
		{
			$series = Server::GetVar('name');
			$m = new ModTVEpisode();
			$m->_vars['Path'] = $series;
			$m->_vars['Title'] = basename($series);
			$m->Series = $series;
			die($m->Get());
		}
		else if (@$_d['q'][1] == 'search')
		{
			$ret = '';
			session_write_close();
			foreach (TV::$scrapers as $s)
				$ret .= call_user_func(array($s, 'Find'), Server::GetVar('series'), true);
			return $ret;
			die();
		}
		else if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/tv/t_item.xml';
			$this->_missing_image = 'modules/tv/img/missing.jpg';

			die(parent::Get());
		}
		else if (@$_d['q'][1] == 'rename')
		{
			if (!rename(Server::GetVar('src'), Server::GetVar('dst'))) die('Error!');
			else die('Done.');
		}
		else
		{
			$missings = array();
			// += overlaps episodes, combine instead.
			foreach (TV::GetAllSeries() as $series)
			{
				$add = ModTVEpisode::GetMissingEpisodes($series);
				if (!empty($add)) $missings = array_merge($missings, $add);
			}

			$needed = null;
			foreach ($missings as $missing)
				$needed .= "<div>Missing: $missing</div>\r\n";

			$this->_template = 'modules/tv/t_tv.xml';
			$t = new Template();
			$t->Set($this->_vars);
			$t->Set('needed', $needed);
			return $t->ParseFile($this->_template);
		}
	}

	# Checks

	function Check(&$msgs)
	{
		global $_d;

		$errors = 0;

		$fs = $this->CollectFS();
		$ds = $this->CollectDS();

		# Each Series

		foreach ($fs as $p => $s)
		{
			$eps = array();

			$epfs = $s->CollectEpisodes();

			# Each Episode

			foreach (new FilesystemIterator($p,
			FilesystemIterator::SKIP_DOTS) as $fep)
			{
				if (substr($fep->GetFilename(), 0, 1) == '.') continue;

				$episode = str_replace('\\', '/', $fep->GetPathname());

				$ep = new TVEpisodeEntry($fep->GetPathname());

				#  Check Database Existence

				if (empty($ds[$ep->Data['series']][$ep->Data['season']][$ep->Data['episode']]))
				{
					$msgs['TV/Metadata'][] = "Adding missing '{$ep->Path}' to database.";
					$ep->save_to_db();
				}

				if (empty($ep->Data['index']))
				{
					var_dump("Cannot recognize: $episode");
					continue;
				}

				if (!empty($_d['tv.cb.check.episode']))
				foreach ($_d['tv.cb.check.episode'] as $cb)
					$errors += call_user_func_array($cb, array(&$p, &$msgs));
			}

			if (!empty($_d['tv.cb.check.series']))
			foreach ($_d['tv.cb.check.series'] as $cb)
				$errors += call_user_func_array($cb, array(&$p, &$msgs));
		}

		return $errors;
	}

	# Others

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

	static function CollectFS()
	{
		global $_d;

		$ret = array();

		# Existing series filesystem entries

		foreach ($_d['config']['paths']['tv']['paths'] as $p)
			foreach (new FilesystemIterator($p,
				FilesystemIterator::SKIP_DOTS) as $f)
		{
			$se = new TVSeriesEntry($f->GetPathname());
			$ret[$f->GetPathname()] = $se;
		}

		ksort($ret);
		return $ret;
	}

	static function CollectDS()
	{
		global $_d;

		$cr = $_d['entry.ds']->find(array('$or' => array(
			array('type' => 'tv-series'),
			array('type' => 'tv-episode'),
			array('type' => 'tv-season')
		)));

		$ret = array();

		foreach ($cr as $i)
			$ret[$i['series']][$i['season']][$i['episode']] = $i;

		return $ret;
	}

	static function GetAllSeries()
	{
		global $_d;

		$ret = array();
		if (!empty($_d['config']['paths']['tv']['paths']))
		foreach ($_d['config']['paths']['tv']['paths'] as $p)
			foreach (glob($p.'/*') as $fx)
				$ret[] = $fx;

		return $ret;
	}
}

Module::Register('TV');

class TVSeriesEntry extends MediaEntry
{
	public $Type = 'tv-series';

	function __construct($path)
	{
		parent::__construct($path);

		global $_d;

		# Collect cover data
		$this->NoExt = File::GetFile($this->Filename);
		$thm_path = VarParser::Parse($_d['config']['paths']['tv']['meta'], $this);

		if (file_exists($thm_path))
			$this->Image = $_d['app_abs'].'/cover?path='.rawurlencode($thm_path);
		else
			$this->Image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/modules/tv/img/missing.jpg';
	}

	function CollectEpisodes()
	{
		$ret = array();

		/* @var $f FilesystemIterator */
		foreach (new FilesystemIterator($this->Path,
			FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (substr($f->getFilename(), 0, 1) == '.') continue;
			$p = $f->GetPathname();
			$ret[$p] = new TVEpisodeEntry($p);
		}

		return $ret;
	}
}

class TVEpisodeEntry extends MediaEntry
{
	public $Type = 'tv-episode';

	function __construct($path)
	{
		parent::__construct($path);

		if (!empty($path))
		{
			$dat = MediaLibrary::ScrapeFS($path, TVEpisodeEntry::GetFSPregs());
			if (!empty($dat['title']))
				$this->Data['title'] = $dat['title'];
			if (!empty($dat['series']))
				$this->Data['series'] = $dat['series'];
			if (!empty($dat['season']))
				$this->Data['season'] = (int)$dat['season'];
			if (!empty($dat['episode']))
				$this->Data['episode'] = (int)$dat['episode'];
			$this->Data['parent'] = $dat['series'];
			$this->Data['index'] = 'S'.(int)$dat['season'].'E'.(int)$dat['episode'];
		}
	}

	static function GetFSPregs()
	{
		return array(
			# path/{series}/{series} Season {season} - {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]+)\s+-\s+([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{series} - S{season}E{episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{title} - S{season}E{episode}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9]+)\..*$#i' => array(
				1 => 'series',
				2 => 'title',
				3 => 'season',
				4 => 'episode'),
			# path/{series}/S{season}E{episode} - {title}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\s-\s(.+)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'title'),
			//path/{series}/S{season}E{episode}.ext
			'#/([^/]+)/S([0-9]+)E([0-9]+)\.[^.]+$#i' => array(
				0 => 'title', # Substituted as filename
				1 => 'series',
				2 => 'season',
				3 => 'episode'),
			//path/{series}/{season}{episode} - {title}.ext
			'#/([^/]+)/(\d+)(\d{2})\s*-\s*(.+)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'title'),
			//path/{series}/{title}S{season}E{episode}
			'#/([^/]+)/([^/]+)S(\d+)E(\d+)(.*)#i' => array(
				1 => 'series',
				2 => 'title',
				3 => 'season',
				4 => 'episode'),
			//path/{series}/{title}{S+}{EE}
			'#/([^/]+)/([^/]+)(\d+)(\d{2}).*#' => array(
				1 => 'series',
				2 => 'title',
				3 => 'season',
				4 => 'episode'),
			# path/{series}/{S}{EE} {title}.ext
			'#/([^/]+)/(\d+)(\d{2}) (.*)\..*$#' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'title'),
			# path/{series}/{Season}x{Episode}
			'#/([^/]+)/[^/]+(\d+)x(\d+).*#' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode'),
			# path/{series}/{series} - {episode} - {title}.ext
			'#/([^/]+)/[^-]+\s*-\s*(\d+)\s*-\s*(.*)\.([^.]+)$#' => array(
				1 => 'series',
				2 => 'episode',
				3 => 'title',
				4 => 'extension')
		);
	}
}

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
		$this->_items = ModTVEpisode::GetExistingEpisodes($this->_vars['Path']);

		/*$sx = ModScrapeTVDB::GetXML($this->_vars['Path']);
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
		}*/

		$t = new Template();
		$t->ReWrite('item', array(&$this, 'TagItem'));
		$t->Set($this->_vars);
		return $t->ParseFile($this->_template);
		return parent::Get();
	}

	function TagItem($t, $g, $a)
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
		foreach (new FilesystemIterator($series,
		FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (substr($f->GetFilename(), 0, 1) == '.') continue;

			$p = $f->GetPathname();
			$i = MediaLibrary::ScrapeFS($p, TVEpisodeEntry::GetFSPregs());
			#$i = $tvi->ScrapeFS($f);

			if (!isset($i['episode']))
			{
				U::VarInfo('Missing episode on this...');
				U::VarInfo($i);
				continue;
			}
			// Multi-episode file
			if (preg_match('/([0-9]+)-([0-9]+)/', $i['episode'], $m))
			{
				for ($ix = $m[1]; $ix <= $m[2]; $ix++)
				{
					$i['episode'] = $ix;
					$snf = number_format($i['season']);
					$enf = number_format($i['episode']);
					$ret[$snf][$enf] = $i;
				}
			}
			else
			{

				$snf = isset($i['season'])
					? number_format($i['season'])
					: 1;
				$enf = number_format($i['episode']);
				$ret[$snf][$enf] = $i;
			}
		}

		Arr::ARKSort($ret);
		return $ret;
	}

	static function GetMissingEpisodes($series)
	{
		$eps = ModTVEpisode::GetExistingEpisodes($series);

		# All Episodes
		//$aeps = TV::GetInfo($series);
		$aeps = array('eps' => array());

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
					$rout = "$series S{$snp}E{$enp} - {$aired}";
					$rout .= ' - <a href="http://www.torrentz.eu/search?q='.$query.'" target="_blank">TZ</a>';
					$rout .= ' - <a href="http://www.kat.ph/search/'.$query.'/" target="_blank">KT</a>';
					$rout .= ' - <a href="http://www.google.com/search?q=filetype%3Atorrent+'.$query.'" target="_blank">G</a>';
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
