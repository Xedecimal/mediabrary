<?php

class ModMovie extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$_d['movie.source'] = 'file';
		$_d['movie.ds'] = new DataSet($_d['db'], 'movie', 'mov_id');
		$mpds = $_d['movie_path.ds'] = new DataSet($_d['db'],
			'movie_path', 'mp_id');
		$_d['movie.ds']->AddJoin(new Join($mpds, 'mp_movie = mov_id',
			'LEFT JOIN'));

		$this->_class = 'movie';
		$this->_missing_image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/modules/movie/img/missing.jpg';
	}

	function Prepare()
	{
		global $_d;

		$query = $_d['movie.cb.query'];
		$this->_items = array();

		$this->_files = $this->CollectFS();
		$this->_items = $this->CollectDS();

		if (!empty($_d['movie.cb.filter']))
			$this->_items = U::RunCallbacks($_d['movie.cb.filter'], $this->_items,
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

		# Main Page

		if (empty($_d['q'][0]))
		{
			$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/movie/css.css" />';

			$total = $size = 0;

			if (!empty($_d['config']['paths']['movie']))
			foreach ($_d['config']['paths']['movie'] as $p)
			{
				foreach (glob($p.'/*') as $f)
				{
					$size += filesize($f);
					$total++;
				}
			}
			$size = File::SizeToString($size);
			$text = "{$size} of {$total} Movies";

			$r['default'] = '<div class="main-link" id="divMainMovies"><a href="{{app_abs}}/movie" id="a-movie">'.$text.'</a></div>';
			return $r;
		}

		if (@$_d['q'][0] != 'movie') return;

		if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();
			$item = $this->ScrapeFS(Server::GetVar('path'),
				ModMovie::GetFSPregs());
			$query = $_d['movie.cb.query'];
			$query['match'] = array('mp_path' => $item['fs_path']);

			$item = array_merge($item, $_d['movie.ds']->GetOne($query));
			if (!empty($_d['movie.cb.detail']))
				foreach ($_d['movie.cb.detail'] as $cb)
					$item = call_user_func($cb, $item);
			$item += MediaLibrary::GetMedia('movie', $item, $this->_missing_image);
			$item['fs_filename'] = basename(Server::GetVar('path'));
			$t->Set($item);
			$this->_item = $item;
			$t->ReWrite('item', array($this, 'TagDetailItem'));
			die($t->ParseFile('modules/movie/t_movie_detail.xml'));
		}
		else if (@$_d['q'][1] == 'fix')
		{
			# Collect generic information.
			$src = Server::GetVar('path');
			preg_match('#^(.*?)([^/]*)\.(.*)$#', $src, $m);

			# Collect filesystem information on this path.
			$meta = $this->ScrapeFS($src, ModMovie::GetFSPregs());

			# Append database information on this path.
			$q['match']['mp_path'] = $src;
			$dr = $_d['movie.ds']->GetOne($q);
			if (!empty($dr)) $meta = array_merge($meta, $dr);

			# Figure out what the file should be named.
			$ftitle = $this->CleanTitleForFile($meta['mov_title']);
			$fyear = substr($meta['mov_date'], 0, 4);

			if (!empty($meta['fs_part']))
				$dstf = "{$m[1]}{$ftitle} ({$fyear}) CD{$meta['fs_part']}";
			else $dstf = "{$m[1]}{$ftitle} ({$fyear})";
			$dst = "$dstf.".strtolower($m[3]);

			# Apply File Transformations

			rename($src, $dst);
			@touch($dst);

			# Rename covers and backdrops as well.
			
			$md = 'img/meta/movie';
			$cover = "$md/thm_".File::GetFile($m[2]);
			$backd = "$md/bd_".File::GetFile($m[2]);
			if (file_exists($cover)) rename($cover, "$md/thm_{$ftitle} ({$fyear})");
			if (file_exists($backd)) rename($backd, "$md/bd_{$ftitle} ({$fyear})");

			# Apply Database Transformations

			@$_d['movie_path.ds']->Update(array('mp_path' => $src),
				array('mp_path' => $dst));

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

		# This will be used later to hunt for things that a file doesn't exist
		# for.
		$filelist = array();

		# Collect known filesystem data
		if (!empty($_d['config']['paths']['movie']))
		foreach ($_d['config']['paths']['movie'] as $p)
		foreach(glob($p.'/*') as $f)
		{
			if (is_dir($f)) continue;
			$this->_files[$f] = ModMovie::GetMovie($f);
			$ext = File::ext($f);
			$filelist[] = basename($f, '.'.$ext);
		}

		# Collect database information
		$this->_ds = array();
		foreach ($_d['movie.ds']->Get() as $dr)
		{
			$p = $dr['mp_path'];

			# Remove missing items
			if (empty($dr['mp_path']) || !file_exists($dr['mp_path']))
			{
				$ret['cleanup'][] = "Removed database entry for non-existing '"
					.$p."'";
				$_d['movie.ds']->Remove(array('mov_id' => $dr['mov_id']));
			}

			# This one is already clean, skip it.
			if (!empty($dr['mov_clean']))
			{
				unset($this->_files[$p]);
				continue;
			}

			$this->_ds[$p] = $dr;
		}

		# Iterate all known combined items.
		foreach ($this->_files as $p => $file)
		{
			$uep = urlencode($p);

			# We reported information in here to place in $ret later.
			$rep = array();

			$rep = array_merge_recursive($rep,
				$this->CheckDatabaseExistence($file));

			# Database available, run additional checks.
			if (isset($this->_ds[$p]))
			{
				$md = $this->_ds[$p];
				$rep = array_merge_recursive($rep, $this->CheckDates($md));
				$rep = array_merge_recursive($rep,
					$this->CheckFilename($file, $md));
				$rep = array_merge_recursive($rep,
					$this->CheckMedia($file, $md));
				$rep = array_merge_recursive($rep,
					U::RunCallbacks($_d['movie.cb.check'], $md));
			}

			# If we can, mark this movie clean to skip further checks.
			if (empty($rep) && isset($md) && $file['fs_part'] < 2)
			{
				$_d['movie.ds']->Update(
					array('mov_id' => $md['mov_id']),
					array('mov_clean' => 1)
				);
			}
			else $ret = array_merge_recursive($ret, $rep);
		}

		$ret = array_merge_recursive($ret, $this->CheckOrphanMedia($filelist));

		$ret['Stats'][] = 'Checked '.count($this->_items).' known movie files.';

		return $ret;
	}

	/**
	 * Check if a given item exists in the database.
	 * @param <type> $file
	 * @return array Array of error messages.
	 */
	function CheckDatabaseExistence($file)
	{
		$ret = array();

		# This is multipart, we only keep track of the first item.
		if (@$file['fs_part'] > 1) return $ret;

		$p = $file['fs_path'];
		if (!isset($this->_ds[$p]))
		{
			$uep = urlencode($p);

			$ret['Scrape'][] = <<<EOF
<a href="movie/scrape?target=$uep&fast=1"
	class="a-fix">Fix</a> File {$p} needs to be scraped
EOF;
		}

		return $ret;
	}

	/**
	 * Check all date parameters.
	 * 
	 * @param array $file Result of ModMovie::GetMovie
	 * @param array $dbentry Database entry for this movie.
	 */
	function CheckDates($md)
	{
		$ret = array();

		# Date Related
		$date = $md['mov_date'];
		$year = substr($date, 0, 4);

		# Missing month and day.
		if (strlen($date) < 10)
		{
			$ret['Scrape'][] = "File {$md['mov_path']} has incorrectly
				scraped date \"{$year}\"";
		}

		return $ret;
	}

	function CheckFilename($file, $md)
	{
		$ret = array();

		$p = $md['mp_path'];
		$ext = File::ext($p);

		# Filename related
		if ($ext != 'avi')
		{
			$ret['File Name Compliance'][] = "File {$file['fs_path']} has a
				bad extension.";
		}

		# Title Related
		$title = ModMovie::CleanTitleForFile($md['mov_title']);

		# Validate strict naming conventions.

		$next = basename($p, $ext);
		$date = $md['mov_date'];
		$year = substr($date, 0, 4);

		# Part files need their CD#
		if (!empty($file['fs_part']))
		{
			$preg = '#/'.preg_quote($title).' \('.$year.'\) CD'
				.$file['fs_part'].'\.(\S+)$#';
			$target = "$title ($year) CD{$file['fs_part']}.$ext";
		}
		else
		{
			$preg = '#/'.preg_quote($title).' \('.$year.'\)\.(\S+)$#';
			$target = "$title ($year).$ext";
		}

		if (!preg_match($preg, $md['mp_path']))
		{
			$urlfix = "movie/fix?path=".urlencode($p);
			# TODO: Do not directly reference tmdb here!
			$urlunfix = "tmdb/remove?id={$md['mov_id']}";
			$bn = basename($p);

			$ret['File Name Compliance'][] = <<<EOD
<a href="{$urlfix}" class="a-fix">Fix</a>
<A href="{$urlunfix}" class="a-nogo">Unscrape</a>
File "$bn" should be "$target".
- <a href="http://www.themoviedb.org/movie/{$md['mov_tmdbid']}"
target="_blank">Reference</a>
EOD;
		}

		return $ret;
	}

	function CheckMedia($file, $md)
	{
		$ret = array();

		$p = $md['mp_path'];
		$ext = File::ext($p);
		$next = basename($p, '.'.$ext);
		if (empty($file['fs_part']) || $file['fs_part'] < 2)
		{
			# Look for cover or backdrop.
			if (!file_exists("img/meta/movie/thm_$next"))
			{
				$urlunfix = "tmdb/remove?id={$md['mov_id']}";
				$ret['Media'][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing cover for {$md['mp_path']}
- <a href="http://www.themoviedb.org/movie/{$md['mov_tmdbid']}"
target="_blank">Reference</a>
EOD;
			}

			if (!file_exists("img/meta/movie/bd_$next"))
			{
				$urlunfix = "tmdb/remove?id={$md['mov_id']}";
				$ret['Media'][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing backdrop for {$md['mp_path']}
EOD;
			}
		}

		return $ret;
	}

	/**
	 * Check for orphaned meta images.
	 */
	function CheckOrphanMedia($filelist)
	{
		global $_d;

		$ret = array();

		foreach (glob('img/meta/movie/*') as $p)
		{
			$f = basename($p);

			# Proper named thumbnail or backdrop.
			if (preg_match('/(thm_|bd_)(.*)/', $f, $m))
			{
				$found = false;

				if (array_search($m[2], $filelist))
					continue;

				#if (!unlink($p))
					$ret['Media'][] = "Could not unlink: $p";
				#else $ret['Media'][] = "Removed orphan cover $p";
			}
			else
			{
				#if (!unlink($p))
					$ret['Media'][] = "Could not unlink: $p";
				#else $ret['Media'][] = "Removed irrelevant cover: {$p}";
			}
		}
		return $ret;
	}

	function CollectFS()
	{
		global $_d;

		$ret = array();

		$pregs = ModMovie::GetFSPregs();

		if (!empty($_d['config']['paths']['movie']))
		foreach ($_d['config']['paths']['movie'] as $p)
		foreach (glob($p.'/*') as $f)
		{
			if (is_dir($f)) continue;
			$mov = ModMovie::GetMovie($f);
			if (@$mov['fs_part'] > 1) continue;
			$ret[$f] = $mov;
		}

		return $ret;
	}

	function CollectDS()
	{
		global $_d;

		if (empty($_d['movie.cb.query']['limit']) && empty($_d['movie.cb.nolimit']))
			$_d['movie.cb.query']['limit'] = array(0, 50);
		if (empty($_d['movie.cb.query']['match']))
		{
			$_d['movie.cb.query']['match']['md_name'] = 'obtained';
			$_d['movie.cb.query']['order'] = 'md_value DESC';
		}

		$query = $_d['movie.cb.query'];

		if (!empty($_d['movie.cb.lqc']))
			$query = U::RunCallbacks($_d['movie.cb.lqc'], $query);

		$query['group'] = 'mov_id';
		$ret = array();

		$movies = $_d['movie.ds']->Get($query);
		if (!empty($movies))
		foreach ($movies as $i)
		{
			$i['url'] = urlencode($i['mp_path']);
			// Emulate a file system if we're not indexing it.
			if (!isset($ret[$i['mp_path']]))
			{
				$i['fs_path'] = $i['mp_path'];
				$i['fs_filename'] = basename($i['mp_path']);
				$i['fs_title'] = $i['mov_title'];
				$ret[$i['mp_path']] = $i;
			}
			else $ret[$i['mp_path']] += $i;
		}

		return $ret;
	}

	static function GetFSPregs()
	{
		return array(
			# title [date].ext
			'#/([^/[\]]+)\s*\[([0-9]{4})\].*\.([^.]*)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'),

			# title[date].ext
			/*'#([^/\[]+)\[([0-9]{4})\].*\.([^.]+)#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'),*/

			# title (date).ext
			'#/([^/]+)\s*\((\d{4})\)\.([^.]+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'),

			# title (date) CDnum.ext
			'#/([^/]+)\s*\((\d{4})\)\s*cd(\d+)\.([^.]+)$#i' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_part',
				4 => 'fs_ext'
			),

			# title CDnum.ext
			'#/([^/]+).*cd(\d+)\.([^.]+)$#i' => array(
				1 => 'fs_title',
				2 => 'fs_part',
				3 => 'fs_ext'
			),

			# title [strip].ext
			'#/([^/]+)\s*\[.*\.([^.]+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_ext'),

			# title.ext
			'#([^/(]+?)[ (.]*(ac3|dvdrip|xvid|limited|dvdscr).*\.([^.]+)$#i' => array(
				1 => 'fs_title',
				3 => 'fs_ext'),

			# title.ext
			'#([^/]+)\s*\.(\S+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_ext')
		);
	}

	static function GetMovie($path)
	{
		global $_d;

		$q['mp_path'] = $path;

		$ret = MediaLibrary::ScrapeFS($path, ModMovie::GetFSPregs());

		# This is a part, lets try to find the rest of them.
		if (!empty($ret['fs_part']))
		{
			$qg = File::QuoteGlob($ret['fs_path']);
			$search = preg_replace('/CD\d+/i', '[Cc][Dd]*',
				$qg);
			$files = glob($search);
			$ret['paths'] = $files;
		}
		# Just a single movie
		else $ret['paths'][] = $ret['fs_path'];

		return $ret;
	}
}

Module::Register('ModMovie');

?>
