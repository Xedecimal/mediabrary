<?php

class TV extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$this->_class = 'tv';

		$_d['tv.cb.ignore'][] = '/^\.$/';
		$_d['tv.cb.ignore'][] = '/^\.\.$/';
		$_d['tv.cb.ignore'][] = '/folder\.jpg/';
		$_d['tv.cb.ignore'][] = '/backdrop\.jpg/';
		$_d['tv.cb.ignore'][] = '/\.regions\.yml/';

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

			$md = new MediaEntry($path);
			$md->LoadDS();

			if (!$md->Rename($target)) die('Error!');
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

		$missings = array();
		// += overlaps episodes, combine instead.
		# @TODO: Missing episodes should be in check.
		/*foreach (TV::GetAllSeries() as $series)
		{
			$add = ModTVEpisode::GetMissingEpisodes($series);
			if (!empty($add)) $missings = array_merge($missings, $add);
		}*/

		$needed = null;
		foreach ($missings as $missing)
			$needed .= "<div>Missing: $missing</div>\r\n";

		$this->_template = 'modules/tv/t_tv.xml';
		$t = new Template();
		$t->Set($this->_vars);
		$t->Set('needed', $needed);
		return $t->ParseFile($this->_template);
	}

	# Checks

	function Check()
	{
		$this->CheckFilesystem();
		$this->CheckDatabase();
	}

	function CheckFilesystem()
	{
		$this->fs = $this->CollectFS();
		foreach ($this->fs as $tvse) $tvse->Check();
	}

	function CheckDatabase()
	{
		global $_d;

		$ds = $this->CollectDS();

		# Database checks

		foreach ($ds as $series)
		{
			if (!file_exists($series->Path)
			|| basename(realpath($series->Path)) != basename($series->Path))
			{
				TV::OutErr("Removing orphan series: {$series->Path}");
				$series->Remove();
			}

			if (!empty($series->ds))
			foreach ($series->ds as $season)
			{
				foreach ($season as $episode)
				{
					if (!empty($episode['path']))
					if (!file_exists($episode['path']))
					{
						TV::OutErr("Removing {$episode['path']}");
						$_d['entry.ds']->remove(array('_id' => $episode['_id']));
					}
				}
			}
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

		foreach ($_d['config']['paths']['tv'] as $p)
		foreach (scandir($p) as $f)
		{
			if ($f == '.' || $f == '..') continue;
			$sp = $p.'/'.$f;
			$se = new TVSeriesEntry($sp);
			$ret[$sp] = $se;
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

	static function OutErr($msg)
	{
		echo "<p>$msg</p>\r\n";
		flush();
	}
}

Module::Register('TV');

class TVSeriesEntry extends MediaEntry
{
	public $Type = 'tv-series';
	public $Name = 'TVSeries';

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
		$this->CollectDS();

		$t = new Template();
		$t->ReWrite('item', array(&$this, 'TagItem'));
		$t->Set($this);
		return $t->ParseFile(Module::L('tv/t_tv_series.xml'));
	}

	function CollectDS()
	{
		global $_d;

		# Collect this series.
		$this->Data = $_d['entry.ds']->findOne(array(
			'type' => $this->Type,
			'path' => $this->Path
		));

		# Collect all these episodes.
		$eps = $_d['entry.ds']->find(array(
			'type' => 'tv-episode',
			'parent' => $this->Data['_id']
		))->sort(array('index' => 1));

		foreach ($eps as $ep) $this->ds[$ep['season']][$ep['episode']] = $ep;
	}

	function CollectFS()
	{
		global $_d;

		$ignores = $_d['tv.cb.ignore'];

		$ret = array();
		foreach (scandir($this->Path) as $fn)
		{
			$skip = false;
			foreach ($ignores as $i)
				if (preg_match($i, $fn)) { $skip = true; break; }
			if ($skip) continue;

			$p = $this->Path.'/'.$fn;

			$ep = new TVEpisodeEntry(utf8_encode($p));

			# Possibly Metadata or unknown file.
			if (empty($ep->Data['season']))
			{
				TV::OutErr("Unable to identify episode: {$ep->Path}", $ep);
				continue;
			}

			$ret[$ep->Data['season']][$ep->Data['episode']] = $ep;
		}
		return $ret;
	}

	function Check()
	{
		$this->fs = $this->CollectFS();
		$this->CollectDS();

		$this->CheckSelf();
		$this->CheckOrphans();
		$this->CheckFilesystem();
		$this->CheckDataset();

		return;
	}

	function CheckSelf()
	{
		global $_d;

		# Has this series already been added to db?
		if (empty($this->Data))
		{
			$this->Data = array(
				'path' => $this->Path,
				'title' => $this->Filename,
				'type' => 'tv-series'
			);
			$_d['entry.ds']->save($this->Data, array('safe' => 1));
			TV::OutErr('Adding missing series in database for '
				.$this->Title);
		}
	}

	function CheckOrphans()
	{
		$this->prunes = array();
		$changed = false;

		if (!empty($this->ds))
		foreach ($this->ds as $season)
		{
			foreach ($season as $ep)
			{
				# Check filesystem for this db entry.
				if (!empty($ep['path']))
				{
					$this->prunes[$ep['path']] = 1;
					/*if (!file_exists($ep['path']))
					{
						TV::OutErr("Removed orphan {$ep['path']}");
						$_d['entry.ds']->remove(array('_id' => $ep['_id']));
						$changed = true;
					}*/
				}
			}
		}

		# Something changed, reload the dataset.
		if ($changed) $this->CollectDS();
	}

	/**
	 * Check each episode in this series filesystem.
	 *
	 * @param array $msgs
	 */
	function CheckFilesystem()
	{
		global $_d;

		if (empty($this->fs))
		{
			ModCheck::Out("Empty series '{$this->Path}'.");
			return;
		}

		foreach ($this->fs as $is => &$eps)
		{
			foreach ($eps as $ie => &$ep)
			{
				if (!isset($this->ds[$is][$ie]))
				{
					$ep->Data['parent'] = $this->Data['_id'];
					ModCheck::Out("Adding {$this->Title} {$is}x{$ie} to database.");
					$ep->SaveDS(true);
					$this->ds[$is][$ie] = $ep->Data;
				}
				else if (empty($this->ds[$is][$ie]['path']))
				{
					$dep = new TVEpisodeEntry($ep->Path);
					$dep->CollectDS();
					$dep->Data['path'] = $ep->Path;
					$dep->SaveDS(true);
					ModCheck::Out("Updated path {$ep->Path} on existing entry.");
				}

				else unset($this->prunes[$ep->Data['path']]);
			}
		}

		foreach (array_keys($this->prunes) as $p)
		{
			$_d['entry.ds']->remove(array('path' => $p));
		}

		if (!empty($_d['tv.cb.check.series']))
		foreach ($_d['tv.cb.check.series'] as $cb)
			call_user_func_array($cb, array(&$this));
	}

	function CheckDataset()
	{
	}

	function TagItem($t, $g, $a)
	{
		$items = array();

		if (!empty($this->ds))
		{
			foreach ($this->ds as $ss)
			foreach ($ss as $es)
			{
				$es['url'] = !empty($es['path']) ? urlencode($es['path']) : '';
				$items[] = $es;
			}
		}
		else $items[] = array('season' => 0, 'episode' => 0, 'title' => 'No data gathered');
		return VarParser::Concat($g, $items);
	}

	static function FromID($id, $type = 'TVSeriesEntry')
	{
		return MediaEntry::FromID($id, $type);
	}

	function SaveCover($url) {
		file_put_contents($this->Path.'/folder.jpg', file_get_contents($url));
	}

	# MediaEntry Overloads

	function Remove()
	{
		global $_d;

		# Remove all episodes for this series.
		$_d['entry.ds']->remove(array(
			'parent' => new MongoID($this->Data['_id'])));

		parent::Remove();
	}
}

MediaEntry::RegisterType('tv-series', 'TVSeriesEntry');

class TVEpisodeEntry extends MediaEntry
{
	public $Type = 'tv-episode';

	function __construct($path)
	{
		parent::__construct($path, TVEpisodeEntry::GetFSPregs());

		if (!empty($path))
		{
			$dat = MediaEntry::ScrapeFS($path, TVEpisodeEntry::GetFSPregs());
			$this->Data['path'] = $path;
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

		$dat = $_d['entry.ds']->findOne(array(
			'type' => $this->Type,
			'series' => $this->series,
			'season' => (int)$this->season,
			'episode' => (int)$this->episode
		));
		if (!empty($dat)) $this->Data = $dat;
	}

	static function GetFSPregs()
	{
		return array(
			# Includes Series, Season, Episode, Title

			# path/{series}/{series} - S{season}E{episode} - {title}.ext
			'#/([^/]+)/[^/-]+\s+-\s*S([0-9]+)E([0-9\-]+)\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'title'),
			# path/{series}/{series} Season {season} Episode {episode} - {title}.ext
			'#/([^/]+)/([^/-]+)\s*Season ([0-9]{1,3})\s+Episode ([0-9]{1,3})\s*-\s*(.*)\.[^.]+$#i' => array(
				1 => 'series',
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
			# path/{series}/{series} - S##.E## - {title}
			'#/([^/]+)/[^/]+\s+-\s+S(\d{2})\.E(\d{2})\s+-\s+(.*)\.([^.]+)#' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'title',
				5 => 'ext'),
			# path/{series}/series - S##xE## - {title}
			'#/([^/]+)/[^/]+(\d{1,2})x(\d{1,2})#' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode'),

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
			# path/{series}/S{SS}E{EE}{title}.ext
			'#/([^/]+)/S(\d{2})E(\d{2})(.*)\..*$#' => array(
				1 => 'series',
				2 => 'season',
				3 => 'episode',
				4 => 'title'
			),
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

MediaEntry::RegisterType('tv-episode', 'TVEpisodeEntry');

class ModTVEpisode extends MediaLibrary
{
	function __construct()
	{
		$this->_template = 'modules/tv/t_tv_series.xml';
		$this->_class = 'episode';
	}

	static function GetExistingEpisodes($series)
	{
		$exts = TVEpisodeEntry::GetExtensions();

		$ret = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
			$series, FilesystemIterator::KEY_AS_PATHNAME
			| FilesystemIterator::SKIP_DOTS)) as $p => $f)
		{
			#$p = $f->getPathname();
			var_dump($p);
			if (substr($f->GetFilename(), 0, 1) == '.') continue;

			$pi = pathinfo($p);

			if (!in_array($pi['extension'], $exts)) continue;

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
