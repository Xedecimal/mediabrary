<?php

class ModMovie extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$_d['movie.source'] = 'file';
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

	function Prepare()
	{
		global $_d;

		$query = $_d['movie.cb.query'];
		$this->_items = array();

		$this->_files = $this->CollectFS();
		$this->_items = $this->CollectDS();

		if (!empty($_d['movie.cb.filter']))
			$this->_items = RunCallbacks($_d['movie.cb.filter'], $this->_items,
				$this->_files);

		$this->_vars['total'] = count($this->_items);
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/movie/css.css" />';

			$total = $size = 0;

			foreach (Comb($_d['config']['movie_path'], '/downloads/i', OPT_FILES) as $f)
			{
				$size += filesize($f);
				$total++;
			}
			$size = GetSizeString($size);
			$text = "{$size} of {$total} Movies";

			return '<a href="{{app_abs}}/movie" id="a-movie" class="main-link">'.$text.'</a>';
		}

		if (@$_d['q'][0] != 'movie') return;

		if (@$_d['q'][1] == 'play')
		{
			$file = filenoext($_d['q'][2]);


			$fasturl = '\\\\networkstorage\\nas\\Movies\\'.$_d['q'][2];
			$url = 'http://'.GetVar('HTTP_HOST').'/nas/Movies/'.rawurlencode($_d['q'][2]);
			$data = <<<EOF
#EXTINF:-1,{$file}
{$fasturl}
{$url}
EOF;

			SendDownloadStart("{$file}.m3u");
			die($data);
		}
		else if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();
			$item = $this->ScrapeFS(GetVar('path'));
			$query = $_d['movie.cb.query'];
			$query['match'] = array('med_path' => $item['fs_path']);
			$item = array_merge($item, $_d['movie.ds']->GetOne($query));
			if (!empty($_d['movie.cb.detail']))
				foreach ($_d['movie.cb.detail'] as $cb)
					$item = call_user_func($cb, $item);
			$item += MediaLibrary::GetMedia('movie', $item, $this->_missing_image);
			$item['fs_filename'] = basename(GetVar('path'));
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
			preg_match('#^(.*?)([^/]*)\.(.*)$#', $src, $m);

			//$pinfo = pathinfo($src);
			$meta = $this->ScrapeFS($src);
			$dr = $_d['movie.ds']->GetOne(array(
				'match' => array('med_path' => str_replace('&', ':', $src))
			));
			if (!empty($dr)) $meta = array_merge($meta, $dr);

			$ftitle = $meta['med_title'];
			$ftitle = $this->CleanTitleForFile($ftitle);
			$fyear = substr($meta['med_date'], 0, 4);

			$dst = "{$m[1]}{$ftitle} ({$fyear}).".strtolower($m[3]);

			// Apply File Transformations

			rename($src, $dst);
			touch($dst);

			preg_rename('img/meta/movie/*'.filenoext($m[2]).'*',
				'#img/meta/movie/(.*)'.preg_quote(str_replace('#', '/',
					filenoext($m[2]))).'(\..*)$#i',
				'img/meta/movie/\1'.$ftitle.' ('.$fyear.')\2');

			// Apply Database Transformations

			$_d['movie.ds']->Update(array('med_path' => $src),
				array('med_path' => $dst));

			die('Fixed');
		}
		else if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/movie/t_movie_item.xml';

			foreach ($this->_items as &$i)
				$i += MediaLibrary::GetMedia('movie', $i, $this->_missing_image);

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

			if (!empty($md['fs_path']) && @$md['fs_ext'] != 'avi')
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

	function CollectFS()
	{
		global $_d;

		$ret = array();

		foreach (glob($_d['config']['movie_path'].'/*') as $f)
		{
			if (is_dir($f)) continue;
			$ret[$f] = $this->ScrapeFS($f);
		}

		return $ret;
	}

	function CollectDS()
	{
		global $_d;

		if (empty($_d['movie.cb.query']['match']))
			$_d['movie.cb.query']['limit'] = array(0, 50);

		$query = $_d['movie.cb.query'];

		if (!empty($_d['movie.cb.lqc']))
			$query = RunCallbacks($_d['movie.cb.lqc'], $query);

		$query['group'] = 'med_id';
		$ret = array();

		foreach ($_d['movie.ds']->Get($query) as $i)
		{
			// Emulate a file system if we're not indexing it.
			if (!isset($ret[$i['med_path']]))
			{
				$i['fs_path'] = $i['med_path'];
				$i['fs_filename'] = basename($i['med_path']);
				$i['fs_title'] = $i['med_title'];
				$ret[$i['med_path']] = $i;
			}
			else $ret[$i['med_path']] += $i;
		}

		return $ret;
	}
}

Module::Register('ModMovie');

?>
