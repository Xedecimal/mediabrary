<?php

class Movie extends MediaLibrary
{
	public $Name = 'movie';

	function __construct()
	{
		parent::__construct();

		global $_d;

		$_d['fs.ds'] = $_d['db']->fs;
		$_d['fs.ds']->ensureIndex(array('path' => 1),
			array('unique' => 1, 'dropDups' => 1));

		$_d['movie.source'] = 'file';
		$_d['movie.cb.query']['columns']['path'] = 1;
		$_d['movie.cb.query']['columns']['paths'] = 1;
		$_d['movie.cb.query']['columns']['title'] = 1;

		$_d['movie.cb.query']['match'] = array();

		$_d['entry-types']['movie'] = array('text' => 'Movie',
			'icon' => '<img src="'.Module::P('movie/img/movie.png').'" />');

		$this->_items = array();
		$this->_class = 'movie';

		$this->CheckActive('movie');
	}

	function Link()
	{
		global $_d;

		$_d['nav.links']['Media/Movies'] = '{{app_abs}}/movie';
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();

			$id = $_d['q'][2];
			$data = $_d['entry.ds']->findOne(array('_id' => new MongoId($id)));
			$item = new MovieEntry($data['path'], MovieEntry::GetFSPregs());
			$item->Data += $data;

			$t->Set($item);
			$this->_item = $item;
			$t->ReWrite('item', array($this, 'TagDetailItem'));
			die($t->ParseFile('modules/movie/t_movie_detail.xml'));
		}

		else if (@$_d['q'][1] == 'fix')
		{
			if ($_d['q'][2] == 'bad_filename')
			{
				$me = MovieEntry::FromID($_d['q'][3]);

				$errs = $me->Data['errors'];
				if (empty($errs['bad_filename']['from'])
				|| $me->Data['path'] != $errs['bad_filename']['from'])
				{
					unset($me->Data['errors']['bad_filename']);
					$me->SaveDS();
					die(json_encode(array('msg' => 'Already fixed.')));
				}
			}

			# Collect generic information.
			/*$src = Server::GetVar('path');
			preg_match('#^(.*?)([^/]*)\.(.*)$#', $src, $m);

			# Collect information on this path.
			$item = $_d['entry.ds']->findOne(array('paths' => $src));

			foreach ($item['paths'] as &$p)
			{
				# Figure out what the file should be named.
				$ftitle = $this->CleanTitleForFile($item['title']);
				$fyear = substr($item['released'], 0, 4);

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

			/*$_d['entry.ds']->update(array('_id' => $item['_id']),
				$item);*/

			die('Fixer has been unfixed.');
		}

		else if (@$_d['q'][1] == 'rename')
		{
			$me = new MovieEntry(Server::GetVar('path'));
			$me->LoadDS();
			$me->Rename(Server::GetVar('target'));
			die();
		}
	}

	function Get()
	{
		global $_d;

		# Main Page

		$css = Module::P('modules/movie/movie.css');
		$ret['head'] = '<link type="text/css" rel="stylesheet" href="'.$css.'" />';
		$js = Module::P('modules/movie/movie.js');
		$ret['head'] .= '<script type="text/javascript" src="'.$js.'"></script>';

		if (!$this->Active) return $ret;

		$_d['movie.cb.query']['limit'] = 50;
		$query = $_d['movie.cb.query'];
		$this->_items = $this->CollectDS();

		if (!empty($_d['movie.cb.filter']))
			$this->_items = U::RunCallbacks($_d['movie.cb.filter'],
				$this->_items);

		if (!empty($_d['movie.cb.fsquery']['limit']))
		{
			$l = $_d['movie.cb.fsquery']['limit'];
			$this->_items = array_splice($this->_items, $l[0], $l[1]);
		}

		$this->_vars['total'] = count($this->_items);

		if (@$_d['q'][1] == 'items')
		{
			$this->_template = 'modules/movie/t_movie_item-grid.xml';
			die(parent::Get());
		}

		$this->_template = 'modules/movie/t_movie.xml';
		$t = new Template();
		$ret['movie'] = $t->ParseFile($this->_template);

		return $ret;
	}

	function TagDetailItem($t, $g, $a)
	{
		$vp = new VarParser();
		$ret = '';
		if (!empty($this->details))
		foreach ($this->details as $n => $v)
		{
			$ret .= $vp->ParseVars($g, array('name' => $n, 'value' => $v));
		}
		return $ret;
	}

	function CheckPrepare()
	{
		global $_d;
	}

	function Check()
	{
		global $_d;

		$this->fs = $this->CollectFS();

		$this->ds = $_d['entry.ds']->find(array('type' => 'movie'),
			array('path' => 1));

		### Remove database entries that do not exist on the filesystem.

		# @TODO: This does not prune files, only directories of entries.
		$prunes = array();

		foreach ($this->ds as $i)
		{
			$dp = dirname($i['path']);
			if (in_array($dp, $_d['config']['paths']['movie']['paths']))
				$prunes[$i['path']] = $i['_id'];
			else $prunes[$dp] = $i['_id'];
		}

		foreach ($this->fs as $p => $up) unset($prunes[$p]);

		foreach ($prunes as $path => $id)
		{
			$_d['entry.ds']->remove(array('_id' => new MongoID($id)));
		}

		### Ignore all clean items.

		foreach (array_keys($this->fs) as $f)
		{
			$filename = $f;

			$item = $_d['entry.ds']->findOne(array(
				'path' => new MongoRegex('/^'.preg_quote($filename).'/')
			));

			if (!empty($item['clean'])) unset($this->fs[$f]);
		}

		$ret = array();

		# General Cleanup

		$status = $this->CheckFS();

		#return;
		# @TODO: Bring this back to life.
		#throw new CheckException("Avoiding the next checks.");

		# Collect database information
		/*$this->_ds = array();
		$q['type'] = 'movie';
		$q['clean']['$exists'] = 0;
		foreach ($_d['entry.ds']->find($q, $_d['movie.cb.query']['columns']) as $dr)
		{
			$paths = isset($dr['paths']) ? $dr['paths'] : array();
			$paths[] = $dr['path'];

			foreach ($paths as $p)
			{
				# Remove missing items
				if (empty($p) || !file_exists($p))
				{
					$_d['entry.ds']->remove(array('_id' => $dr['_id']));
					throw new Exception("Removed database entry for non-existing '"
						.$p."'");
				}

				$this->_ds[$p] = $dr;
			}
		}*/

		# Iterate all known combined items.
		/*foreach ($this->_files as $p => $movie)
		{
			# If a provider reports errors on this entry, we do not want to
			# mark it as clean so it will continue to be checked.
			$errors = 0;

			# Database available, run additional checks.
			if (isset($this->_ds[$p]))
			{
				$md = $this->_files[$p];
				$md->Data = $this->_ds[$p];
				$errors += $this->CheckDatabase($p, $md, $msgs);
				$errors += $this->CheckFilename($p, $md, $msgs);
			}

			if ($toterrs += $errors > 100) break;

			# If we can, mark this movie clean to skip further checks.
			if (empty($errors) && isset($md) && @$file['fs_part'] < 2)
			{
				$_d['entry.ds']->update(array('_id' => $md->Data['_id']),
					array('$set' => array('mov_clean' => true))
				);
			}
		}*/

		//$this->CheckOrphanMedia($msgs);

		//$msgs['Stats'][] = 'Checked '.count($this->_ds).' known movie files.';
	}

	function CheckFS()
	{
		global $_d;

		if (empty($this->fs)) return;
		foreach (array_keys($this->fs) as $filename)
		{
			# See if we need to do anything with this entry.
			$me = new MovieEntry($filename, MovieEntry::GetFSPregs());
			$me->Data['root'] = dirname($filename);
			$this->CheckDatabaseExistence($filename, $me);
			$me->LoadDS();

			$clean = true;

			if (!$this->CheckFile($me)) $clean = false;

			foreach ($_d['movie.cb.check'] as $cb)
				if (!call_user_func_array($cb, array(&$me))) $clean = false;

			if ($clean)
			{
				echo "<p>Marking '{$me->Path}' as clean.</p>\r\n";
				flush();
				$me->Data['clean'] = true;
				$me->SaveDS();
			}
		}
	}

	/**
	 * Check if a given item exists in the database.
	 * @param MovieEntry $movie
	 * @return array Array of error messages.
	 */
	function CheckDatabaseExistence($file, $md)
	{
		global $_d;

		$ret = array();

		# This is multipart, we only keep track of the first item.
		if (!empty($md->Part)) return $ret;

		$item = $_d['entry.ds']->findOne(array(
			'path' => Str::MakeUTF8($md->Path)));
		if (!empty($item)) return $ret;

		if (empty($md->Path)) return;
		if (!isset($this->_ds[$md->Path]))
		{
			#$p = Str::MakeUTF8($md->Path);
			#$md = new MovieEntry($p);
			#file_put_contents('debug.txt', print_r($md->Data), FILE_APPEND);
			$md->SaveDS(true);
			echo "<p>Added new movie '{$md->Path}' to database.</p>";
			flush();
		}

		return $ret;
	}

	function CheckDatabase($p, $md, &$msgs)
	{
		global $_d;

		# The database does not match the filename.
		if (!empty($md->Title) && $md->Title != @$md->Data['title'])
		{
			# Save the file title to the database.
			$md->Data['title'] = $md->Title;
			$md->SaveDS();
		}

		# The database does not have a release date.
		if (!empty($md->Released) && $md->Released != @$md->Data['released'])
		{
			$md->Data['released'] = $md->Released;
			$md->SaveDS();
		}

		# The database has no parent set for a given movie, has to be Movie.
		if (!empty($md->Data['_id']) && empty($md->Data['parent']))
		{
			$md->Data['parent'] = 'Movie';
			$md->SaveDS();
		}
	}

	function CheckFile($me)
	{
		global $_d;

		$clean = true;

		#$me = MovieEntry::FromPath($path);
		#$me = new MovieEntry($path);
		$ext = File::ext($me->Path);

		if (empty($me->Path)) return false;

		# Filename related
		if (array_search($ext, MovieEntry::GetExtensions()) === false)
		{
			echo "<p>File {$me->Path} has an unknown extension. ($ext)</p>\r\n";
			flush();
			$clean = false;
		}

		# Title Related
		$title = $me->Title;

		$title = Movie::CleanTitleForFile($title);

		# Validate strict naming conventions.

		$next = basename($me->Path, $ext);
		if (isset($me->Released))
		{
			$date = $me->Released;
			$year = substr($date, 0, 4);
		}
		else $year = 'Unknown';

		# Part files need their CD#
		if (!empty($me->Part))
		{
			$preg = '#/'.preg_quote($title, '#').' \('.$year.'\) CD'
				.$path['fs_part'].'\.(\S+)$#';
			$target = "$title ($year) CD{$me['part']}.$ext";
		}
		else
		{
			$preg = '#/'.preg_quote($title, '#').' \('.$year.'\)\.(\S+)$#';
			$target = "$title ($year).$ext";
		}

		if (!preg_match($preg, $me->Path))
		{
			$urlfix = "movie/fix?path=".urlencode($me->Path);
			$bn = basename($me->Path);
			echo "File '$bn' should be '$target'";
			flush();
			$clean = false;
		}

		return $clean;
	}

	function CollectSingleFileGroup()
	{
		global $_d;

		foreach ($_d['config']['paths']['movie']['paths'] as $p)
		{
			$paths = scandir($p);
			foreach ($paths as $i)
			{
				if ($i == '.' || $i == '..') continue;

				$path = urlencode($p.'/'.$i);

				$add = array();
				$add['path'] = $path;
				$_d['movie_fs.ds']->update($add, $add,
					array('upsert' => 1));
			}
		}
	}

	function CollectFS()
	{
		global $_d;

		foreach ($_d['config']['paths']['movie']['paths'] as $p)
		{
			$files = scandir($p);
			foreach ($files as $f)
			{
				if ($f == '.' || $f == '..') continue;
				$ret[$p.'/'.$f] = 1;
			}
		}

		return $ret;
	}

	function CollectDS()
	{
		global $_d;

		if (empty($_d['movie.cb.query']['order']))
			$_d['movie.cb.query']['order'] = array('obtained' => -1);

		$query = $_d['movie.cb.query'];

		if (!empty($_d['movie.cb.lqc']))
			$query = U::RunCallbacks($_d['movie.cb.lqc'], $query);

		$query = array();
		$ret = array();

		# Filtration
		$m = is_array($_d['movie.cb.query']['match']) ?
			$_d['movie.cb.query']['match'] : array();

		# Collect our data
		$cur = $_d['entry.ds']->find($m, $_d['movie.cb.query']['columns']);

		# Sort Order
		if (!empty($_d['movie.cb.query']['order']))
			$cur->sort($_d['movie.cb.query']['order']);

		# Amount of results
		$p = Server::GetVar('page');
		if (!empty($_d['movie.cb.query']['limit']))
		{
			$l = $_d['movie.cb.query']['limit'];
			if (!empty($p)) $cur->skip($p*$l);
			$cur->limit($l);
		}

		foreach ($cur as $i)
		{
			if (empty($i['path'])) continue;
			try
			{
				$add = new MovieEntry($i['path']);
				$add->Data = $i;
				$ret[$i['paths'][0]] = $add;
			}
			catch (Exception $ex) { }
		}

		return $ret;
	}

	static function Rename($src, $dst)
	{
		$me->Rename($dst);
	}
}

class MovieEntry extends MediaEntry
{
	public $Type = 'movie';
	public $Name = 'MovieEntry';

	function __construct($path, $bypass_checks = false)
	{
		if (!file_exists($path) && !$bypass_checks)
			throw new Exception('File not found.');

		# This is a movie folder
		if (is_dir($path)) $path = $this->LoadDir($path);

		# @TODO: Way too much processing on something that needs to be light
		# weight.

		$pregs = $this->GetFSPregs();
		parent::__construct($path, $pregs);

		foreach ($this->ScrapeFS($path, $pregs) as $n => $v)
			$this->$n = $v;

		global $_d;

		# This is a part, lets try to find the rest of them.
		if (!empty($this->Part))
		{
			$qg = File::QuoteGlob($this->Path);
			$search = preg_replace('/CD\d+/i', '[Cc][Dd]*', $qg);
			$files = glob($search);
			foreach ($files as $f) $this->Paths[] = Str::MakeUTF8($f);
		}
		# Just a single movie
		else $this->Paths[] = Str::MakeUTF8($this->Path);

		# Default data values for every movie entry.
		$this->Data['title'] = Str::MakeUTF8($this->Title);
		$this->Data['paths'] = $this->Paths;
		$this->Data['path'] = Str::MakeUTF8($this->Path);
		if (file_exists($this->Path))
			$this->Data['obtained'] = filemtime($this->Path);
		$this->Data['parent'] = 2;
		$this->Data['class'] = 'object.item.videoItem.movie';
		if (isset($this->Released))
			$this->Data['released'] = $this->Released;
		$this->parent = 'Movie';

		# Collect cover data
		$this->NoExt = File::GetFile($this->Filename);

		$thm_path = dirname($this->Path).'/folder.jpg';

		if (file_exists($thm_path))
			$this->Image = $_d['app_abs'].'/cover?path='.rawurlencode($thm_path);
	}

	function LoadDir($path)
	{
		global $_d;

		$mtime = filemtime($path);
		if (isset($mtime)) # Use cache
		{
			$dat = $_d['fs.ds']->findOne(array('path' => urlencode($path)));
			if (!empty($dat))
			{
				$dat['path'] = urlencode($path);
				if ($dat['mtime'] == $mtime)
					return urldecode($dat['files'][0]);
			}
		}
		$exts = MovieEntry::GetExtensions();
		$files = array();
		$sf = scandir($path);

		foreach ($sf as &$f)
		{
			$pos = strrpos($f, '.');
			if ($pos === false) continue;
			if (in_array(substr($f, $pos+1), $exts))
				$files[] = urlencode($path.'/'.$f);
		}

		if (!empty($dat))
		{
			$dat['mtime'] = $mtime;
			$dat['files'] = $files;
			$_d['fs.ds']->save($dat);
		}

		if (empty($files))
		{
			if (count(scandir($path)) < 3)
			{
				rmdir($path);
				echo "<p>Removed empty movie folder $path.</p>\r\n";
			}
			else echo "<p>Not enough video files in this folder $path.</p>\r\n";
		}
		else if (count($files) > 1)
		{
			echo "<p>Too many video files in folder: {$path}</p>";
			flush();
		}

		if (!empty($files)) return urldecode($files[0]);
	}

	static function GetFSPregs()
	{
		return array(
			# title[date].ext
			'#/([^/]+)\[(\d{4})\][^/]*\.([^.]{3})$#' => array(
				1 => 'Title', 2 => 'Released', 3 => 'Ext'),

			# title (date) CDnum.ext
			'#/([^/]+)\s*\((\d{4})\).*cd(\d+)\.([^.]+)$#i' => array(
				1 => 'Title', 2 => 'Released', 3 => 'Part', 4 => 'Ext'),

			# title (date).ext
			'#/([^/]+)\s+\((\d{4})\).*\.([^.]+)$#' => array(
				1 => 'Title', 2 => 'Released', 3 => 'Ext'),

			# title DDDD.ext
			'#([^/]+)(\d{4}).*\.([^.]{3})$#i' => array(
				1 => 'Title', 2 => 'Released', 3 => 'Ext'),

			# title CDnum.ext
			'#/([^/]+).*cd(\d+)\.([^.]+)$#i' => array(
				1 => 'Title', 2 => 'Part', 3 => 'Ext'),

			# title [strip].ext
			'#/([^/]+)\s*\[.*\.([^.]+)$#' => array(
				1 => 'Title', 2 => 'Ext'),

			# title.ext
			'#([^/(]+?)[ (.]*(ac3|dvdrip|xvid|limited|dvdscr).*\.([^.]+)$#i' => array(
				1 => 'Title', 3 => 'Ext'),

			# title.ext
			'#([^/]+)\.([^.]{3})#' => array(1 => 'Title', 2 => 'Ext')
		);
	}

	static function GetExtensions()
	{
		return array('avi', 'mkv', 'divx', 'mp4');
	}

	/**
	 * This method will rename just the folder and move caches of this entry.
	 *
	 * @global array $_d General data
	 * @param string $dst Destination path.
	 * @return boolean Successful (1) or not (0).
	 */
	function Rename($dst)
	{
		global $_d;

		# Update covers or backdrops.

		$src = $this->Data['path'];
		$pisrc = pathinfo($src);
		$pidst = pathinfo($dst);

		# Make the destination folder
		if (!file_exists($pidst['dirname']))
			mkdir($pidst['dirname'], 0777, true);

		# Rename without case sensitivity
		else if ($pisrc['dirname'] != $pidst['dirname'])
			rename($pisrc['dirname'], $pidst['dirname']);

		foreach ($_d['movie.cb.move'] as $cb) call_user_func_array($cb,
			array($pisrc['dirname'], $pidst['dirname']));

		$cover = "$pisrc[dirname]/folder.jpg";
		$backd = "$pisrc[dirname]/backdrop.jpg";

		if (file_exists($cover))
			rename($cover, "$pidst[dirname]/folder.jpg");
		if (file_exists($backd))
			rename($backd, "$pidst[dirname]/backdrop.jpg");

		return parent::Rename($dst);
	}

	function SaveCover($url)
	{
		if ($this->Data['root'] != dirname($this->Path))
			file_put_contents(dirname($this->Path).'/folder.jpg',
				file_get_contents($url));
	}
}

Module::Register('Movie');

?>
