<?php

class TV extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$this->_class = 'tv';
		$this->state_file = dirname(__FILE__).'/state.dat';

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

		$_d['entry-types']['tv-series'] = array('text' => 'TV Series',
			'icon' => '<img src="'.Module::P('tv/img/tv-series.png').'" />');

		$_d['entry-types']['tv-episode'] = array('text' => 'TV Episode',
			'icon' => '<img src="'.Module::P('tv/img/tv-episode.png').'" />');
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

		if (@$_d['q'][0] != 'tv' && @$_d['q'][0] != 'tv-series') return;

		$this->_items = TV::CollectDS();

		if (@$_d['q'][1] == 'detail')
		{
			$m = TVSeriesEntry::FromID($_d['q'][2]);
			$m->_vars['Path'] = $m->Path;
			$m->_vars['Title'] = $m->Title;
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

	function CheckPrepare()
	{
		global $_d;

		$this->_state['path'] = 0;

		foreach ($_d['config']['paths']['tv-series']['paths'] as $p)
		{
			$files = scandir($p);
			foreach ($files as $f)
			{
				if ($f[0] == '.') continue;
				$this->_state['files'][] = $p.'/'.$f;
			}
		}
		file_put_contents($this->state_file, serialize($this->_state));
	}

	function Check()
	{
		global $_d;

		$this->_state = unserialize(file_get_contents($this->state_file));

		try
		{
			$this->ds = $this->CollectDS();
			$this->CheckFilesystem($msgs);
			$this->CheckDatabase($msgs);
		}
		catch (Exception $ex)
		{
			file_put_contents($this->state_file, serialize($this->_state));
			throw $ex;
		}

		/*# Filesystem checks

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
	}

	function CheckFilesystem(&$msgs)
	{
		global $_d;

		# No configured tv series paths, we're done here.
		if (empty($this->_state['files'])) return;

		$p = array_pop($this->_state['files']);

		$f = basename($p);
		if ($f[0] == '.') continue;

		$tvse = new TVSeriesEntry($p);

		$tvse->Check();
	}

	function CheckDatabase(&$msgs)
	{
		global $_d;

		$ds = $this->CollectDS();

		# Validate Database.

		/*$fs = $this->CollectFS();

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
		}*/

		# Database checks

		foreach ($ds as $s => $series)
		{
			$series->CollectDS();

			if (!file_exists($series->Path))
			{
				$_d['entry.ds']->remove(array('_id' => $series->Data['_id']));
			}

			if (!empty($series->ds))
			foreach ($series->ds as $se => $season)
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
	}

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
		{
			$tvs = new TVSeriesEntry($i['path']);
			$tvs->Data = $i;
			$ret[$i['title']] = $tvs;
		}

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
		$thm_path = $this->Path.'/folder.jpg';

		if (file_exists($thm_path))
			$this->Image = $_d['app_abs'].'/cover?path='.rawurlencode($thm_path);
		else
			$this->Image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/modules/tv/img/missing.jpg';
	}

	function Get()
	{
		global $_d;

		$this->CollectDS();

		$t = new Template();
		$t->ReWrite('item', array(&$this, 'TagItem'));
		$t->Set($this);
		return $t->ParseFile(Module::L('tv/t_tv_series.xml'));
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
		))->sort(array('index' => 1));

		foreach ($eps as $ep)
			$this->ds[$ep['season']][$ep['episode']] = $ep;
	}

	function CollectFS()
	{
		foreach (scandir($this->Path) as $fn)
		{
			if (substr($fn, 0, 1) == '.') continue;
			$p = $this->Path.'/'.$fn;

			$ep = new TVEpisodeEntry($p);
			# Possibly Metadata or unknown file.
			if (empty($ep->Data['season']))
				throw new CheckException("Unable to identify episode: {$ep->Path}");

			$this->fs[$ep->Data['season']][$ep->Data['episode']] = $ep;
		}
	}

	function Check()
	{
		global $_d;

		/*$series = $this->CollectDS();
		if (empty($series))
		{
			$this->Data = array(
				'path' => $this->Path,
				'title' => $this->Filename,
				'type' => 'tv-series'
			);
			$_d['entry.ds']->save($this->Data, array('safe' => 1));
			throw new Exception('Adding missing series in database for '
				.$this->Title);
		}*/

		$this->CheckFilesystem();

		return;
	}

	/**
	 * Check each episode in this series filesystem.
	 *
	 * @param array $msgs
	 */
	function CheckFilesystem()
	{
		global $_d;

		$this->CollectDS();
		$this->CollectFS();

		if (empty($this->fs))
			throw new Exception("Empty series '{$this->Path}'.");

		foreach ($this->fs as $is => &$eps)
		{
			foreach ($eps as $ie => &$ep)
			{
				if (!isset($this->ds[$is][$ie]))
				{
					$ep->Data['parent'] = $this->Data['_id'];
					$ep->SaveDS();
					throw new CheckException("Adding {$this->Title} {$is}x{$ie} to database.");
				}

				else if (empty($this->ds[$is][$ie]['path']))
				{
					$this->ds[$is][$ie]['path'] = $ep->Path;
					$_d['entry.ds']->save($this->ds[$is][$ie],
						array('safe' => 1));
					throw new CheckException("Updated path {$ep->Path} on existing entry.");
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

	function TagItem($t, $g, $a)
	{
		$vp = new VarParser();

		if (!empty($this->ds))
		{
			foreach ($this->ds as $s => $ss)
			foreach ($ss as $e => $es)
			{
				$es['url'] = !empty($es['path']) ? urlencode($es['path']) : '';
				$items[] = $es;
			}
		}
		else $items[] = array('season' => 0, 'episode' => 0, 'title' => 'No data gathered');
		return VarParser::Concat($g, $items);
	}

	static function FromID($id)
	{
		global $_d;

		$data = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
		$tvse = new TVSeriesEntry($data['path']);
		$tvse->Data = $data;
		return $tvse;
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
				$this->Data['title'] = Str::MakeUTF8($dat['title']);
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
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{series} Season {season} Episode {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]{1,3})\s+Episode ([0-9]{1,3})\s*-\s*(.*)\.[^.]+$#i' => array(
				2 => 'series',
				3 => 'season',
				4 => 'episode',
				5 => 'title'),
			# path/{series}/{series} Season {season} - {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]+)\s+-\s+([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
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
			'#/[^/]+/([^/]+)S(\d{1,3})E(\d{1,3}).*\.(.{3})$#' => array(
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

	static function GetExtensions()
	{
		return array('avi');
	}
}

class ModTVEpisode extends MediaLibrary
{
	function __construct()
	{
		$this->_template = 'modules/tv/t_tv_series.xml';
		$this->_class = 'episode';
	}

	static function GetExistingEpisodes($series)
	{
		global $_d;
		$tvi = new ModTVEpisode;

		$exts = TVEpisodeEntry::GetExtensions();

		$ret = array();
		foreach (new FilesystemIterator($series,
		FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (substr($f->GetFilename(), 0, 1) == '.') continue;

			$pi = pathinfo($f->GetPathname());

			if (!in_array($pi['extension'], $exts)) continue;

			$p = $f->GetPathname();
			$i = MediaEntry::ScrapeFS($p, TVEpisodeEntry::GetFSPregs());

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
