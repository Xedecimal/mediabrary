<?php

class ModMovie extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$_d['movie.source'] = 'file';
		$_d['movie.ds'] = new DataSet($_d['db'], 'movie', 'mov_id');

		$this->_class = 'movie';
		$this->_missing_image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/modules/movie/img/missing.jpg';
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
			'#([^/{]+)[{]*([0-9]{4}).*\.([^.]+)$#' => array(
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
			'#([^/(]+?)[ (.]*(ac3|dvdrip|xvid|limited|dvdscr).*\.([^.]+)$#i' => array(
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

		if (!empty($_d['movie.cb.fsquery']['limit']))
		{
			$l = $_d['movie.cb.fsquery']['limit'];
			$this->_items = array_splice($this->_items, $l[0], $l[1]);
		}

		$this->_vars['total'] = count($this->_items);
	}

	function Get()
	{
		global $_d;

		if (empty($_d['q'][0]))
		{
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/movie/css.css" />';

			$total = $size = 0;

			foreach ($_d['config']->paths->path as $p)
			{
				if ($p->attributes()->type != 'movie') continue;
				foreach (Comb($p->attributes()->path, '/downloads/i', OPT_FILES) as $f)
				{
					$size += filesize($f);
					$total++;
				}
			}
			$size = GetSizeString($size);
			$text = "{$size} of {$total} Movies";

			return '<div class="main-link" id="divMainMovies"><a href="{{app_abs}}/movie" id="a-movie">'.$text.'</a></div>';
		}

		if (@$_d['q'][0] != 'movie') return;

		if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();
			$item = $this->ScrapeFS(GetVar('path'));
			$query = $_d['movie.cb.query'];
			$query['match'] = array('mov_path' => $item['fs_path']);

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
				'match' => array('mov_path' => str_replace('&', ':', $src))
			));
			if (!empty($dr)) $meta = array_merge($meta, $dr);

			$ftitle = $meta['mov_title'];
			$ftitle = $this->CleanTitleForFile($ftitle);
			$fyear = substr($meta['mov_date'], 0, 4);

			$dst = "{$m[1]}{$ftitle} ({$fyear}).".strtolower($m[3]);

			// Apply File Transformations

			rename($src, $dst);
			touch($dst);

			preg_rename('img/meta/movie/*'.filenoext($m[2]).'*',
				'#img/meta/movie/(.*)'.preg_quote(str_replace('#', '/',
					filenoext($m[2]))).'(\..*)$#i',
				'img/meta/movie/\1'.$ftitle.' ('.$fyear.')\2');

			// Apply Database Transformations

			$_d['movie.ds']->Update(array('mov_path' => $src),
				array('mov_path' => $dst));

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

		$ret = array();

		# Collect known filesystem data

		foreach ($_d['config']->xpath('paths/path[@type="movie"]') as $p)
		foreach(glob($p->attributes()->path.'/*') as $f)
		{
			if (is_dir($f)) continue;
			$this->_files[$f] = $this->ScrapeFS($f);
		}

		# Collect database information

		$this->_ds = array();
		foreach ($_d['movie.ds']->Get() as $dr)
		{
			$p = $dr['mov_path'];

			# This one is already clean, skip it.

			if (!empty($dr['mov_clean']))
			{
				unset($this->_files[$p]);
				continue;
			}

			if (!file_exists($dr['mov_path']))
			{
				$ret['cleanup'][] = "Removed database entry for non-existing '"
					.$p."'";
				$_d['movie.ds']->Remove(array('mov_path' => $p));
			}

			$this->_ds[$p] = $dr;
		}

		# Walk through all known movies.

		foreach ($this->_files as $p => $file)
		{
			# This file not exist in the database.
			$uep = urlencode($p);

			if (!isset($this->_ds[$p]))
			{
				$ret['Scrape'][] = <<<EOF
<a href="movie/scrape?target=$uep&fast=1"
	class="a-fix">Fix</a> File {$p} needs to be scraped
EOF;
				continue;
			}

			$md = $this->_ds[$p];
			$clean = true;

			# Filename related

			if (!empty($md['fs_path']) && fileext($md['fs_path']) != 'avi')
			{
				$ret['File Name Compliance'][] = "File {$file['fs_path']} has a bad extension.";
				$clean = false;
			}

			# Date Related

			$date = $md['mov_date'];
			$year = substr($date, 0, 4);

			# Missing month and day.

			if (strlen($date) < 10)
			{
				$ret['Scrape'][] = "File {$md['mov_path']} has incorrectly
					scraped year \"{$year}\"";
				$clean = false;
			}

			# Title Related

			$title = ModMovie::CleanTitleForFile($md['mov_title']);

			# Validate strict naming conventions.

			if (!preg_match('#/'.preg_quote($title).' \('.$year.'\)\.([a-z0-9]+)$#', $md['mov_path']))
			{
				$url = "movie/fix?path=$uep";
				$ext = strtolower(fileext($file['fs_filename']));
				$bn = basename($p);
				$ret['File Name Compliance'][] = <<<EOD
<a href="{$url}" class="a-fix">Fix</a> File "$bn" should be "$title ($year).$ext".
EOD;
				$clean = false;
			}

			$_d['movie.ds']->Update(
				array('mov_id' => $md['mov_id']),
				array('mov_clean' => $clean)
			);

			$ret = array_merge_recursive($ret, RunCallbacks($_d['movie.cb.check'], $md));
		}

		$ret['Stats'][] = 'Checked '.count($this->_items).' known movie files.';

		return $ret;
	}

	function CollectFS()
	{
		global $_d;

		$ret = array();

		foreach ($_d['config']->paths->path as $p)
		{
			if ($p->attributes()->type != 'movie') continue;
			foreach (glob($p->attributes()->path.'/*') as $f)
			{
				if (is_dir($f)) continue;
				$ret[$f] = $this->ScrapeFS($f);
			}
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

		$query['group'] = 'mov_id';
		$ret = array();

		$movies = $_d['movie.ds']->Get($query);
		foreach ($movies as $i)
		{
			$i['url'] = urlencode($i['mov_path']);
			// Emulate a file system if we're not indexing it.
			if (!isset($ret[$i['mov_path']]))
			{
				$i['fs_path'] = $i['mov_path'];
				$i['fs_filename'] = basename($i['mov_path']);
				$i['fs_title'] = $i['mov_title'];
				$ret[$i['mov_path']] = $i;
			}
			else $ret[$i['mov_path']] += $i;
		}

		return $ret;
	}
}

Module::Register('ModMovie');

?>
