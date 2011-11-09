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

				$t = new Template();
				if (!empty($ds['path']))
					$t->Set(new TVEpisodeEntry($ds['path']));
				$t->Set($ds);
				die($t->ParseFile(Module::L('tv/tv-episode-detail.xml')));
			}
		}
		else if (@$_d['q'][1] == 'getrss')
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
		else if (@$_d['q'][1] == 'rename')
		{
			$path = Server::GetVar('path');
			$target = Server::GetVar('target');

			$md = new TVEpisodeEntry($path);
			$md->CollectDS();

			if ($md->Rename($target)) die('Error!');
			else die('Done.');
		}
	}

	function Get()
	{
		global $_d;

		/*if (empty($_d['q'][0]))
		{
			$ret = '<link type="text/css" rel="stylesheet" href="modules/tv/css.css" />';

			$series = $size = $total = 0;

			if (!empty($_d['config']['paths']['tv-series']))
			foreach ($_d['config']['paths']['tv-series'] as $p)
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

		$this->ds = $this->CollectDS();
		$errors += $this->CheckFilesystem($msgs);
		$errors += $this->CheckDatabase($msgs);

		# Validate Database.

		/*$fs = $this->CollectFS();
		$ds = $this->CollectDS();

		# We'll collect our own data for more rigorous checks.

		$dseps = array();
		$cr = $_d['entry.ds']->find(array('type' => 'tv-episode'));

		foreach ($cr as $ent)
		{
			$s = $ent['series'];
			$se = $ent['season'];
			$ep = $ent['episode'];

			if (isset($dseps[$s][$se][$ep]))
			{
				$e1 = $dseps[$s][$se][$ep];
				$e2 = $ent;

				# Remove duplicates

				if (empty($e1['path'])) $id = $e1['_id'];
				else $id = $e2['_id'];
				$_d['entry.ds']->remove(array(
					'type' => 'tv-episode', '_id' => $id), array('safe' => 1));

				$msgs['Duplicates'][] = "Found duplicate: {$s} {$se} {$ep}. Removed {$id}";
			}

			$dseps[$s][$se][$ep] = $ent;
		}

		# Database checks

		foreach ($dseps as $s => $series)
		{
			foreach ($series as $se => $season)
			{
				foreach ($season as $ep => $episode)
				{
					if (!empty($episode['path']))
						if (!file_exists($episode['path']))
						{
							$msgs['Orphans'][] = "Removing {$episode['path']}";
							$_d['entry.ds']->remove(array('_id' => $episode['_id']));
						}
				}
			}
		}

		# Filesystem checks

		foreach ($fs as $p => $s)
		{
			$eps = array();

			$epfs = $s->CollectEpisodes();

			$this->CheckDatabaseSeries($msgs, $ds, $s);

			# Each Episode

			foreach (new FilesystemIterator($p,
			FilesystemIterator::SKIP_DOTS) as $fep)
			{
				if (substr($fep->GetFilename(), 0, 1) == '.') continue;

				$episode = str_replace('\\', '/', $fep->GetPathname());

				$ep = new TVEpisodeEntry($fep->GetPathname());
				$es = @$ep->Data['series'];
				$ese = @$ep->Data['season'];
				$eep = @$ep->Data['episode'];

				# Check Database Existence

				if (!empty($es) && empty($dseps[$es][$ese][$eep]['path']))
				{
					$msgs['TV/Metadata'][] = "Adding missing '{$ep->Path}' to database.";

					$ep->save_to_db();
				}

				if (empty($ep->Data['index'])) continue;

				if (!empty($_d['tv.cb.check.episode']))
				foreach ($_d['tv.cb.check.episode'] as $cb)
					$errors += call_user_func_array($cb, array(&$p, &$msgs));
			}


		}*/

		return $errors;
	}

	function CheckFilesystem(&$msgs)
	{
		global $_d;

		foreach ($_d['config']['paths']['tv-series']['paths'] as $p)
		{
			foreach (new FilesystemIterator($p) as $fep)
			{
				$p = $fep->GetPathname();
				$f = basename($p);
				if ($f[0] == '.') continue;

				$tvse = new TVSeriesEntry($p);

				# Series does not exist in database.

				if (empty($this->ds[$tvse->Title]))
				{
					$msgs['TV'][] = "Adding series {$tvse->Title} to database.";
					$tvse->save_to_db();
					return 1;
				}

				$tvse->CheckFilesystem($msgs);
			}
		}
	}

	function CheckDatabase(&$msgs) { }

	/**
	 * Check a tv series.
	 * @param array $ds Results from mogno find()
	 * @param TVSeriesEntry $s Entry to check.
	 */
	function CheckDatabaseSeries(&$msgs, &$ds, $s)
	{
		global $_d;

		if (!isset($ds[$s->Path]))
		{
			$_d['entry.ds']->save($s->Data, array('safe' => 1));
			$msgs['TV'][] = 'Adding missing series in database for '.$s->Title;
		}
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

		foreach ($_d['config']['paths']['tv-series']['paths'] as $p)
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

		$cr = $_d['entry.ds']->find(array('type' => 'tv-series'));

		$ret = array();

		foreach ($cr as $i)
			$ret[$i['title']]['series'] = $i;

		return $ret;
	}

	static function GetAllSeries()
	{
		global $_d;

		$ret = array();
		if (!empty($_d['config']['paths']['tv-series']['paths']))
		foreach ($_d['config']['paths']['tv-series']['paths'] as $p)
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

		$this->Data['parent'] = 'TV';

		global $_d;

		# Collect cover data
		$this->NoExt = File::GetFile($this->Filename);
		$thm_path = VarParser::Parse($_d['config']['paths']['tv-series']['meta'], $this);

		if (file_exists($thm_path))
			$this->Image = $_d['app_abs'].'/cover?path='.rawurlencode($thm_path);
		else
			$this->Image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/modules/tv/img/missing.jpg';
	}

	function CollectDS()
	{
		global $_d;

		$this->Data = $_d['entry.ds']->findOne(array(
			'type' => $this->Type,
			'path' => $this->Path
		));

		$eps = $_d['entry.ds']->find(array(
			'type' => 'tv-episode',
			'parent' => $this->Data['_id']
		));

		foreach ($eps as $ep)
			$this->ds[$ep['season']][$ep['episode']] = $ep;
	}

	function CollectFS()
	{
		foreach (new FilesystemIterator($this->Path,
		FilesystemIterator::SKIP_DOTS) as $fep)
		{
			$fn = $fep->GetFilename();
			if (substr($fn, 0, 1) == '.') continue;
			$p = $fep->GetPathname();

			$ep = new TVEpisodeEntry($p);
			$this->fs[$ep->Data['season']][$ep->Data['episode']] = $ep;
		}
	}

	function Check(&$msgs)
	{
		global $_d;

		$errors = 0;

		$series = $this->CollectDS();
		if (empty($series))
		{
			$errors += 1;
			$msgs['TV'][] = 'Adding missing series in database for '
				.$this->Title;
		}

		return $errors;
	}

	/**
	 * Check each episode in this series filesystem.
	 *
	 * @param array $msgs
	 */
	function CheckFilesystem(&$msgs)
	{
		global $_d;

		$this->CollectDS();
		$this->CollectFS();

		if (!empty($this->fs))
		foreach ($this->fs as $is => &$eps)
		{
			foreach ($eps as $ie => &$ep)
			{
				if (!isset($this->ds[$is][$ie]))
				{
					$ep->Data['parent'] = $this->Data['_id'];
					$ep->save_to_db();
					$msgs['TV'][] = "Adding {$this->Title} {$is}x{$ie} to database.";
				}
			}
		}

		if (!empty($_d['tv.cb.check.series']))
		foreach ($_d['tv.cb.check.series'] as $cb)
			call_user_func_array($cb, array(&$this, &$msgs));
	}

	static function CheckDS(&$msgs)
	{
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
			$dat = MediaEntry::ScrapeFS($path, TVEpisodeEntry::GetFSPregs());
			if (!empty($dat['title']))
				$this->Data['title'] = $dat['title'];
			if (!empty($dat['series']))
				$this->Data['series'] = MediaLibrary::CleanString($dat['series']);
			if (!empty($dat['season']))
				$this->Data['season'] = (int)$dat['season'];
			if (!empty($dat['episode']))
				$this->Data['episode'] = (int)$dat['episode'];
			if (!empty($dat['season']) && !empty($dat['episode']))
				$this->Data['index'] = sprintf('S%02dE%02d', $dat['season'],
					$dat['episode']);
		}
	}

	function CollectDS()
	{
		global $_d;

		$this->Data = $_d['entry.ds']->findOne(array(
			'type' => $this->Type,
			'path' => $this->Path
		));
	}

	static function GetFSPregs()
	{
		return array(
			# Includes Series, Season, Episode, Title

			# path/{series}/{series} - S{season}E{episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]+)E([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{series} Season {season} Episode {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]{1,3})\s+Episode ([0-9]{1,3})\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{series} Season {season} - {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]+)\s+-\s+([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{title} - S{season}E{episode}.ext
			'#/([^/]+)/([^/-]+)\s+-\s*S([0-9]{1,3})E([0-9]{1,3})\..*$#i' => array(
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

			# Includes Series, Season, Episode

			# path/{series}/{series} S{SSS}E{EEE}.ext
			'#/([^/]+)/[^/]+S(\d{1,3})E(\d{1,3}).*\.(.{3})$#' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'ext'),
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

			# Includes Series, Episode, Title

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
			$i = MediaEntry::ScrapeFS($p, TVEpisodeEntry::GetFSPregs());
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
