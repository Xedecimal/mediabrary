<?php

class Movie extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();

		global $_d;

		$_d['movie.source'] = 'file';
		$_d['movie.cb.query'] = array();

		$this->_items = array();
		$this->_class = 'movie';
		$this->_thumb_path = $_d['config']['paths']['movie-meta'];
		$this->_missing_image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].
			'/modules/movie/img/missing.jpg';

		$this->CheckActive('movie');
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();

			$item = $this->ScrapeFS(Server::GetVar('path'),
				Movie::GetFSPregs());

			if (!empty($_d['q'][2]))
			{
				$id = $_d['q'][2];
				$item += $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
			}

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

			# Collect information on this path.
			$item = $_d['entry.ds']->findOne(array('paths' => $src));

			foreach ($item['paths'] as &$p)
			{
				# Figure out what the file should be named.
				$ftitle = $this->CleanTitleForFile($item['title']);
				$fyear = substr($item['date'], 0, 4);

				$dstf = "{$m[1]}{$ftitle} ({$fyear})";
				if (!empty($item['fs_part']))
					$dstf .= " CD{$item['fs_part']}";
				$dstf .= '.'.strtolower($m[3]);

				# Apply File Transformations
				$p = $dstf;
				if (!rename($src, $dstf))
					die("Unable to rename file.");
				@touch($dstf);
			}

			$item['fs_path'] = $item['paths'][0];
			$item['fs_filename'] = basename($item['fs_path']);

			# Rename covers and backdrops as well.
			
			$md = $_d['config']['paths']['movie-meta'];
			$cover = "$md/thm_".File::GetFile($m[2]);
			$backd = "$md/bd_".File::GetFile($m[2]);

			if (file_exists($cover)) rename($cover, "$md/thm_{$ftitle} ({$fyear})");
			if (file_exists($backd)) rename($backd, "$md/bd_{$ftitle} ({$fyear})");

			# Apply Database Transformations

			$_d['entry.ds']->update(array('_id' => $item['_id']),
				$item);

			die('Fixed');
		}
	}

	function Get()
	{
		global $_d;

		# Main Page

		$r['head'] = '<link type="text/css" rel="stylesheet"
			href="modules/movie/css.css" />';

		if (empty($_d['q'][0]))
		{
			$text = "Movies";

			$r['default'] = '<div class="main-link" id="divMainMovies"><a
				href="{{app_abs}}/movie" id="a-movie">'.$text.'</a></div>';
			return $r;
		}

		if (!$this->Active) return;

		$query = $_d['movie.cb.query'];
		$this->_files = $this->CollectFS();
		$this->_items = $this->CollectDS();

		if (!empty($_d['movie.cb.filter']))
			$this->_items = U::RunCallbacks($_d['movie.cb.filter'],
				$this->_items, $this->_files);

		if (!empty($_d['movie.cb.fsquery']['limit']))
		{
			$l = $_d['movie.cb.fsquery']['limit'];
			$this->_items = array_splice($this->_items, $l[0], $l[1]);
		}

		$this->_vars['total'] = count($this->_items);

		if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/movie/t_movie_item.xml';

			foreach ($this->_items as &$i)
				$i += MediaLibrary::GetMedia('movie', $i, $this->_missing_image);

			die(parent::Get());
		}

		$this->_template = 'modules/movie/t_movie.xml';
		$t = new Template();
		$r['default'] = $t->ParseFile($this->_template);
		return $r;
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
		foreach(new FilesystemIterator($p, FilesystemIterator::SKIP_DOTS) as $fsi)
		{
			$f = str_replace('\\', '/', $fsi->GetPathname());
			$this->_files[$f] = Movie::GetMovie($f);
			$ext = File::ext($f);
			$filelist[] = basename($f, '.'.$ext);
		}

		# Collect database information
		$this->_ds = array();
		foreach ($_d['entry.ds']->find() as $dr)
		foreach ($dr['paths'] as $p)
		{
			# Remove missing items
			if (empty($p) || !file_exists($p))
			{
				$ret['cleanup'][] = "Removed database entry for non-existing '"
					.$p."'";
				$_d['entry.ds']->remove(array('_id' => $dr['_id']));
			}

			# This one is already clean, skip it.
			if (!empty($dr['clean']))
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
				$rep = array_merge_recursive($rep, $this->CheckDates($p, $md));
				$rep = array_merge_recursive($rep, $this->CheckFilename($p, $md));
				$rep = array_merge_recursive($rep, $this->CheckMedia($p, $md));
				$rep = array_merge_recursive($rep,
					U::RunCallbacks($_d['movie.cb.check'], $md));
			}

			# If we can, mark this movie clean to skip further checks.
			if (empty($rep) && isset($md) && @$file['fs_part'] < 2)
			{
				$_d['entry.ds']->update(array('_id' => $md['_id']),
					array('$set' => array('mov_clean' => true))
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
<a href="movie/scrape?target=$uep&amp;fast=1"
	class="a-fix">Fix</a> File {$p} needs to be scraped
EOF;
		}

		return $ret;
	}

	/**
	 * Check all date parameters.
	 * 
	 * @param array $file Result of Movie::GetMovie
	 * @param array $dbentry Database entry for this movie.
	 */
	function CheckDates($p, $md)
	{
		global $_d;

		$ret = array();

		# Date Related
		$date = $md['date'];
		$year = substr($date, 0, 4);

		# Missing month and day.
		if (strlen($date) < 10)
		{
			$_d['entry.ds']->remove(array('_id' => $md['_id']));
			$ret['Scrape'][] = "File {$p} has incorrectly
				scraped date \"{$year}\"";
		}

		return $ret;
	}

	function CheckFilename($file, $md)
	{
		$ret = array();

		$ext = File::ext($file);

		# Filename related
		if (array_search($ext, array('avi', 'mkv', 'mp4')) === false)
		{
			$ret['File Name Compliance'][] = "File {$file} has an unknown extension. ($ext)";
		}

		# Title Related
		$title = Movie::CleanTitleForFile($md['title']);

		# Validate strict naming conventions.

		$next = basename($file, $ext);
		$date = $md['date'];
		$year = substr($date, 0, 4);

		# Part files need their CD#
		if (!empty($md['part']))
		{
			$preg = '#/'.preg_quote($title).' \('.$year.'\) CD'
				.$file['fs_part'].'\.(\S+)$#';
			$target = "$title ($year) CD{$md['part']}.$ext";
		}
		else
		{
			$preg = '#/'.preg_quote($title).' \('.$year.'\)\.(\S+)$#';
			$target = "$title ($year).$ext";
		}

		if (!preg_match($preg, $file))
		{
			$urlfix = "movie/fix?path=".urlencode($file);
			# TODO: Do not directly reference tmdb here!
			$urlunfix = "tmdb/remove?id={$md['_id']}";
			$bn = basename($file);

			$tmdbid = @$md['tmdbid'];
			$ret['File Name Compliance'][] = <<<EOD
<a href="{$urlfix}" class="a-fix">Fix</a>
<A href="{$urlunfix}" class="a-nogo">Unscrape</a>
File "$bn" should be "$target".
- <a href="http://www.themoviedb.org/movie/{$tmdbid}"
target="_blank">Reference</a>
EOD;
		}

		return $ret;
	}

	function CheckMedia($file, $md)
	{
		global $_d;

		$ret = array();

		$ext = File::ext($file);
		$next = basename($file, '.'.$ext);
		$mp = $_d['config']['paths']['movie-meta'];
		if (!empty($md['part'])) return $ret;

		# Look for cover or backdrop.
		if (!file_exists("$mp/thm_$next"))
		{
			$urlunfix = "tmdb/remove?id={$md['_id']}";
			$ret['Media'][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing cover for {$md['fs_path']}
- <a href="http://www.themoviedb.org/movie/{$md['tmdbid']}"
target="_blank">Reference</a>
EOD;
		}

		if (!file_exists("$mp/bd_$next"))
		{
				$urlunfix = "tmdb/remove?id={$md['_id']}";
				$ret['Media'][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing backdrop for {$md['fs_path']}
EOD;
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

		$mp = $_d['config']['paths']['movie-meta'];
		foreach (glob("$mp/movie/*") as $p)
		{
			$f = basename($p);

			# Proper named thumbnail or backdrop.
			if (preg_match('/(thm_|bd_)(.*)/', $f, $m))
			{
				if (array_search($m[2], $filelist) !== false) continue;

				if (!unlink($p))
					$ret['Media'][] = "Could not unlink: $p";
				else $ret['Media'][] = "Removed orphan cover $p";
			}
			else
			{
				if (!unlink($p))
					$ret['Media'][] = "Could not unlink: $p";
				else $ret['Media'][] = "Removed irrelevant cover: {$p}";
			}
		}
		return $ret;
	}

	function CollectFS()
	{
		global $_d;

		$ret = array();

		$pregs = Movie::GetFSPregs();

		if (!empty($_d['config']['paths']['movie']))
		foreach ($_d['config']['paths']['movie'] as $p)
		foreach (new FilesystemIterator($p, FileSystemIterator::SKIP_DOTS) as $f)
		{
			$path = str_replace('\\', '/', $f->GetPathname());
			$mov = Movie::GetMovie($path);
			if (@$mov['fs_part'] > 1) continue;
			$ret[$path] = $mov;
		}

		return $ret;
	}

	function CollectDS()
	{
		global $_d;

		if (empty($_d['movie.cb.query']['limit']) && empty($_d['movie.cb.nolimit']))
			$_d['movie.cb.query']['limit'] = 50;
		if (empty($_d['movie.cb.query']['match']))
			$_d['movie.cb.query']['order'] = array('details.obtained' => -1);

		$query = $_d['movie.cb.query'];

		if (!empty($_d['movie.cb.lqc']))
			$query = U::RunCallbacks($_d['movie.cb.lqc'], $query);

		$query = array();
		$ret = array();

		$m = !empty($_d['movie.cb.query']['match']) ? $_d['movie.cb.query']['match'] : array();
		#var_dump($m);
		$cur = $_d['entry.ds']->find($m);
		$p = Server::GetVar('page');
		if (!empty($_d['movie.cb.query']['limit']))
		{
			$l = $_d['movie.cb.query']['limit'];
			if (!empty($p)) $cur->skip($p*$l);
			$cur->limit($l);
		}
		if (!empty($_d['movie.cb.query']['order']))
			$cur->sort($_d['movie.cb.query']['order']);
		foreach ($cur as $i)
		{
			$i['url'] = urlencode($i['paths'][0]);
			$ret[$i['paths'][0]] = $i;
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

		$ret = MediaLibrary::ScrapeFS($path, Movie::GetFSPregs());

		# This is a part, lets try to find the rest of them.
		if (!empty($ret['fs_part']))
		{
			$qg = File::QuoteGlob($ret['fs_path']);
			$search = preg_replace('/CD\d+/i', '[Cc][Dd]*', $qg);
			$files = glob($search);
			$ret['paths'] = $files;
		}
		# Just a single movie
		else $ret['paths'][] = $ret['fs_path'];

		return $ret;
	}
}

Module::Register('Movie');

?>
