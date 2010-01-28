<?php

require_once('scrape.tmdb.php');

class ModMovie extends MediaLibrary
{
	function __construct()
	{
		global $_d;
		$_d['movie.ds'] = new DataSet($_d['db'], 'movie', 'med_id');

		$this->_class = 'movie';
		$this->_missing_image = 'modules/movie/img/missing.jpg';
		$this->_fs_scrapes = array(
			'#/([^/]+) \((\d+)\)\.(\S+)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'),

			'#/([^/[]+)\[([0-9]{4})\].*\.(.*)$#' => array(
				1 => 'fs_title',
				2 => 'fs_date',
				3 => 'fs_ext'
			),
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
			$_d['head'] .= '<link type="text/css" rel="stylesheet" href="modules/movie/_movie.css" />';

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
			$m = array('med_path' => GetVar('path'));
			$t->Set($this->ScrapeFS(GetVar('path')));
			$dr = $_d['movie.ds']->GetOne(array('match' => $m));
			if (!empty($dr)) $t->Set($dr);
			die($t->ParseFile('modules/movie/t_movie_detail.xml'));
		}
		else if (@$_d['q'][1] == 'find')
		{
			$m = $this->ScrapeFS(GetVar('path'));
			$d = $_d['movie.ds']->GetOne(array('match' => array(
				'med_path' => GetVar('path'))));
			if (!empty($d)) $m += $d;
			die(ModScrapeTMDB::Find($m));
		}
		else if (@$_d['q'][1] == 'scrape')
		{
			$target = stripslashes(GetVar('target'));
			$item = $this->ScrapeFS($target);

			$dsitem = $_d['movie.ds']->GetOne(array(
				'match' => array('med_path' => $target),
				'args' => GET_ASSOC
			));

			if (!empty($dsitem))
			{
				//FSItem is going to have more accurate path, filename and ext.
				unset($dsitem['med_filename'], $dsitem['med_path'],
					$dsitem['ext']);
				$item = array_merge($item, $dsitem);
			}

			$item = ModScrapeTMDB::Scrape($item, GetVar('tmdb_id'));

			$cats = @$item['med_cats'];

			$item['med_path'] = $item['fs_path'];
			foreach (array_keys($item) as $k) if ($k[0] != 'm') unset($item[$k]);
			unset($item['med_thumb'], $item['med_cats']);

			$_d['movie.ds']->Add($item, true);
			if (!isset($item['med_id']))
				$id = $_d['movie.ds']->GetCustom('SELECT LAST_INSERT_ID()');
			else $id = $item['med_id'];

			$_d['cat.ds']->Remove(array('cat_movie' => $id));
			if (!empty($cats))
			foreach ($cats as $cat)
				$_d['cat.ds']->Add(array('cat_movie' => $id, 'cat_name' => $cat));

			$p = $item['med_path'];
			$this->_items[$p] = array_merge($item, $this->ScrapeFS($p));
			$this->GetMedia($this->_items[$p]);
			die(json_encode($this->_items[$p]));
		}
		else if (@$_d['q'][1] == 'remove')
		{
			$path = GetVar('path');
			if (!empty($path))
				$_d['movie.ds']->Remove(array('med_path' => $path));
		}
		else if (@$_d['q'][1] == 'fix')
		{
			// Collect Information
			$src = '/'.str_replace(':', '&', implode('/', array_splice($_d['q'], 2)));

			$pinfo = pathinfo($src);
			$meta = $this->ScrapeFS($src);
			$dr = $_d['movie.ds']->GetOne(array(
				'match' => array('med_path' => $src)
			));
			if (!empty($dr)) $meta = array_merge($meta, $dr);

			$ftitle = $meta['med_title'];
			$this->CleanTitleForFile($ftitle);
			$fyear = substr($meta['med_date'], 0, 4);

			$dst = "{$pinfo['dirname']}/{$ftitle} ({$fyear}).{$pinfo['extension']}";

			// Apply File Transformations

			rename($src, $dst);

			preg_rename(
				'img/meta/movie/*'.filenoext($pinfo['basename']).'*',
				'#img/meta/movie/(.*)'.preg_quote(filenoext($pinfo['basename'])).'(\..*)$#',
				'img/meta/movie/\1'.$meta['med_title'].' ('.$fyear.')\2');

			// Apply Database Transformations

			$_d['movie.ds']->Update(array('med_path' => $src),
				array('med_path' => $dst, 'med_filename' => basename($dst)));

			die('Fixed');
		}
		else
		{
			// Load up and present ourselves fully.

			$this->_template = 'modules/movie/t_movie.xml';
			$query = @$_d['movie.cb.query'];
			$this->_items = array();

			// Collect Filesystem Metadata

			if (empty($_d['movie.skipfs']))
			foreach (glob($_d['config']['movie_path'].'/*') as $f)
				$this->_items[$f] += $this->ScrapeFS($f);

			// Collect Database Metadata

			foreach ($_d['movie.ds']->Get($query) as $i)
			{
				if (@$_d['movie.skipfs'])
				{
					// We'll need to emulate a filesystem.
					$i['fs_path'] = $i['med_path'];
					$i['fs_title'] = $i['med_title'];
					$this->_items[$i['med_path']] = $i;
				}
				else $this->_items[$i['med_path']] += $i;
			}

			foreach ($this->_items as $i) $this->GetMedia($i);

			return parent::Get();
		}
	}

	function Check()
	{
		global $_d;

		// Collect known data

		foreach(glob($_d['config']['movie_path'].'/*') as $f)
			$this->_items[$f] = $this->ScrapeFS($f);

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

			if ($md['fs_ext'] != 'avi')
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

			$title = $md['med_title'];

			$this->CleanTitleForFile($title);

			// Validate strict naming conventions.
			if (!preg_match('#/'.preg_quote($title).' \('.$year.'\)\.([a-z0-9]+)$#', $md['med_path']))
			{
				$dst = str_replace(' ', '%20', $md['fs_path']);
				$dst = str_replace('&', ':', $dst);
				$ret['StrictNames'][] = "File {$md['fs_path']} has invalid name, should be".
					" \"{$title} ({$year}).{$md['med_ext']}\"".
					' <a href="movie/fix/'.$dst.'" class="a-fix">Fix</a>';
			}

			// Media Related

			if (count(glob("img/meta/movie/thm_{$title} ($year).*")) < 1)
				$ret['Media'][] = "Cover missing for {$title} ({$year})";
		}

		// Process all cached media

		$fi = new finfo(FILEINFO_MIME);
		foreach (glob('img/meta/movie/*') as $f)
		{
			if ($fi)
			switch ($mt = $fi->file($f))
			{
				case 'image/jpeg; charset=binary':
					$ext = 'jpg';
					break;
				case 'image/png; charset=binary':
					$ext = 'png';
					break;
				default:
					varinfo($mt);
			}

			$fname = basename($f);

			// Backdrop
			if (preg_match('#(.*bd_)([^/]+)\.(.*)$#', $fname, $m))
			{
				if ($m[3] != $ext)
				{
					rename($f, dirname($f).'/'.$m[1].$m[2].'.'.$ext);
					$ret['cleanup'][] = "Renamed {$f}: invalid extension "
						."{$m[2]} should be {$ext}";
				}
				if (count(glob($_d['config']['movie_path'].'/'.$m[2].'*')) < 1)
				{
					unlink($f);
					$ret['cleanup'][] = 'Removed backdrop for missing movie: '.$f;
				}
			}
			else if (preg_match('#(.*thm_)([^/]+)\.(.*)$#', $fname, $m))
			{
				if ($m[3] != $ext)
				{
					rename($f, dirname($f).'/'.$m[1].$m[2].'.'.$ext);
					$ret['cleanup'][] = "Renamed {$f}: invalid extension {$m[2]} should be {$ext}";
				}
				if (count(glob($_d['config']['movie_path'].'/'.$m[2].'*')) < 1)
				{
					unlink($f);
					$ret['cleanup'][] = 'Removed thumbnail for missing movie: '.$f;
				}
			}
			else
			{
				unlink($f);
				$ret['cleanup'][] = 'Removed unassociated file: '.$f;
			}
		}

		$ret['Stats'][] = 'Checked '.count($this->_items).' known movie files.';
		$ret['Stats'][] = 'Checked '.count(glob('img/meta/movie/*')).' media files.';

		return $ret;
	}

	function CleanTitleForFile(&$title)
	{
		// Clean up actual title -> filename characters.
		$title = str_replace(': ', ' - ', $title);
		$title = str_replace('?', '', $title);

		// Transpose 'The {title} - {subtitle}
		if (preg_match('/^(the) ([^-]+) - (.*)/i', $title, $m))
			$title = $m[2].', '.$m[1].' - '.$m[3];

		// Transpose 'The {title}'
		else if (preg_match('/^(the) (.*)/i', $title, $m))
			$title = $m[2].', '.$m[1];
	}
}

Module::RegisterModule('ModMovie');

?>
