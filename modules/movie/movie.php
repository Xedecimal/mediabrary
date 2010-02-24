<?php

class ModMovie extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;
		$_d['movie.ds'] = new DataSet($_d['db'], 'movie', 'med_id');

		$this->_class = 'movie';
		$this->_missing_image = 'modules/movie/img/missing.jpg';
		$this->_fs_scrapes = array(
			# title [date].ext
			'#/([^/[\]]+)\s*\[([0-9]{4})\].*\.([^.]*)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'
			),

			# title[date].ext
			/*'#([^/\[]+)\[([0-9]{4})\].*\.([^.]+)#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'
			),*/

			# title (date).ext
			'#/([^/]+) \((\d+)\)\.([^.]+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'),

			# title date.ext
			'#([^/]+)\s*([0-9]{4}).*\.([^.]+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'
			),

			# title [strip].ext
			'#([^/\[]+?)\s*\[.*\.([^.]+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_ext'
			),

			# title.ext
			'#([^/]+?)(ac3|dvdrip|xvid|limited|dvdscr).*\.([^.]+)$#i' => array(
				1 => 'fs_title',
				3 => 'fs_ext'
			),

			# title.ext
			'#([^/]+)\s*\.(\S+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_ext'
			)
		);
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/movie/css.css" />';

			$total = $size = 0;
			foreach (glob($_d['config']['movie_path'].'/*') as $f)
			{
				$size += filesize($f);
				$total++;
			}
			$size = GetSizeString($size);
			$text = "{$size} of {$total} Movies";

			return '<a href="{{app_abs}}/movie" id="a-movie">'.$text.'</a>';
		}

		if (@$_d['q'][0] != 'movie') return;

		if (@$_d['q'][1] == 'play')
		{
			$file = filenoext($_d['q'][2]);

			$url = 'http://'.GetVar('HTTP_HOST').'/nas/Movies/'.rawurlencode($_d['q'][2]);
			$data = <<<EOF
#EXTINF:-1,{$file}
{$url}
EOF;

			SendDownloadStart("{$file}.m3u");
			die($data);
		}
		else if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();
			$item = $this->ScrapeFS(GetVar('path'));
			$m = array('med_path' => $item['fs_path']);
			$item = array_merge($item, $_d['movie.ds']->GetOne(array('match' => $m)));
			if (!empty($_d['movie.cb.detail']))
				$item = RunCallbacks($_d['movie.cb.detail'], $item);
			$item += MediaLibrary::GetMedia('movie', $item);
			$t->Set($item);
			$this->_item = $item;
			$t->ReWrite('item', array($this, 'TagDetailItem'));
			die($t->ParseFile('modules/movie/t_movie_detail.xml'));
		}
		else if (@$_d['q'][1] == 'fix')
		{
			// Collect Information
			//$src = '/'.implode('/', array_splice($_d['q'], 2));
			$src = GetVar('path');
			varinfo($src);

			$pinfo = pathinfo($src);
			$meta = $this->ScrapeFS($src);
			$dr = $_d['movie.ds']->GetOne(array(
				'match' => array('med_path' => str_replace('&', ':', $src))
			));
			if (!empty($dr)) $meta = array_merge($meta, $dr);

			$ftitle = $meta['med_title'];
			$ftitle = $this->CleanTitleForFile($ftitle);
			$fyear = substr($meta['med_date'], 0, 4);

			$dst = "{$pinfo['dirname']}/{$ftitle} ({$fyear}).".strtolower($pinfo['extension']);

			// Apply File Transformations

			rename($src, $dst);

			preg_rename(
				'img/meta/movie/*'.filenoext($pinfo['basename']).'*',
				'#img/meta/movie/(.*)'.preg_quote(str_replace('#', '/', filenoext($pinfo['basename']))).'(\..*)$#i',
				'img/meta/movie/\1'.$ftitle.' ('.$fyear.')\2');

			// Apply Database Transformations

			$_d['movie.ds']->Update(array('med_path' => $src), array('med_path' => $dst));

			die('Fixed');
		}
		else if (@$_d['q'][1] == 'items')
		{
			// Load up and present ourselves fully.

			$this->_template = 'modules/movie/t_movie_item.xml';
			$query = @$_d['movie.cb.query'];
			$this->_items = array();

			// Collect Filesystem Metadata

			if (empty($_d['movie.skipfs']))
			foreach (glob($_d['config']['movie_path'].'/*') as $f)
			{
				if (is_dir($f)) continue;
				$this->_items[$f] += $this->ScrapeFS($f);
			}

			// Collect Database Metadata

			foreach ($_d['movie.ds']->Get($query) as $i)
			{
				if (!empty($_d['movie.skipds']) && !empty($i['med_tmdbid']))
				{
					unset($this->_items[$i['med_path']]);
					continue;
				}
				if (empty($i['med_path'])) continue;

				// Emulate a file system if we're not indexing it.
				if (@$_d['movie.skipfs'] || !isset($this->_items[$i['med_path']]))
				{
					$i['fs_path'] = $i['med_path'];
					$i['fs_title'] = $i['med_title'];
					$this->_items[$i['med_path']] = $i;
				}
				else $this->_items[$i['med_path']] += $i;
			}

			foreach ($this->_items as &$i)
				$i += $this->GetMedia('movie', $i);

			die(parent::Get());
		}
		else
		{
			$this->_template = 'modules/movie/t_movie.xml';
			$t = new Template();
			return $t->ParseFile($this->_template);
		}
	}

	function TagDetailItem($t, $g, $a)
	{
		$vp = new VarParser();
		if (!empty($this->_item['details']))
		foreach ($this->_item['details'] as $n => $v)
		{
			@$ret .= $vp->ParseVars($g, array('name' => $n, 'value' => $v));
		}
		return @$ret;
	}

	function Check()
	{
		global $_d;

		// Collect known data

		foreach(glob($_d['config']['movie_path'].'/*') as $f)
		{
			if (is_dir($f)) continue;
			$this->_items[$f] = $this->ScrapeFS($f);
		}

		foreach ($_d['movie.ds']->Get() as $dr)
		{
			$p = $dr['med_path'];
			if (!isset($this->_items[$p])) $this->_items[$p] = array();
			$this->_items[$p] += $dr;
		}

		$ret = array();

		// Walk through all known movies.

		foreach ($this->_items as $md)
		{
			// Filesystem based checks

			if (@$md['fs_ext'] != 'avi')
				$ret['extension'][] = "File {$md['fs_path']} has a bad extension.";

			// Metadata Related

			if (!file_exists($md['fs_path']))
			{
				$ret['cleanup'][] = "Removed database entry for non-existing '"
					.$md['med_path']."'";
				$_d['movie.ds']->Remove(array('med_path' => $md['med_path']));
			}

			// Check if this movie has been scraped.

			if (empty($md['med_date']))
			{
				$ret['Scrape'][] = "File {$md['fs_path']} needs to be scraped.<br/>\n";
				continue;
			}

			// Date Related

			$date = $md['med_date'];
			$year = substr($date, 0, 4);

			// Missing month and day.

			if (strlen($date) < 10)
			{
				$ret['Scrape'][] = "File {$md['med_path']} has incorrectly
					scraped year \"{$year}\"";
			}

			// Title Related

			$title = ModMovie::CleanTitleForFile($md['med_title']);

			// Validate strict naming conventions.
			if (!preg_match('#/'.preg_quote($title).' \('.$year.'\)\.([a-z0-9]+)$#', $md['med_path']))
			{
				$dst = urlencode($md['fs_path']);
				$ret['StrictNames'][] = "File {$md['fs_path']} has invalid name, should be".
					" \"{$title} ({$year}).".strtolower($md['fs_ext'])."\"".
					' <a href="movie/fix?path='.$dst.'" class="a-fix">Fix</a>';
			}

			$ret = array_merge_recursive($ret, RunCallbacks($_d['movie.cb.check'], $md));
		}

		$ret['Stats'][] = 'Checked '.count($this->_items).' known movie files.';

		return $ret;
	}

	static function CleanTitleForFile($title)
	{
		// Clean up actual title -> filename characters.
		$reps = array('/' => ' ', ': ' => ' - ', ':' => '-', '?' => '');

		$ret = str_replace(array_keys($reps), array_values($reps), $title);

		// Transpose 'The {title} - {subtitle}
		if (preg_match('/^(the) ([^-]+) - (.*)/i', $ret, $m))
			$ret = $m[2].', '.$m[1].' - '.$m[3];

		// Transpose 'The {title}'
		else if (preg_match('/^(the) (.*)/i', $ret, $m))
			$ret = $m[2].', '.$m[1];

		return $ret;
	}
}

Module::RegisterModule('ModMovie');

?>
