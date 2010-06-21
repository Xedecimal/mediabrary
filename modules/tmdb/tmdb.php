<?php

define('TMDB_KEY', '263e2042d04c1989170721f79e675028');
define('TMDB_FIND', 'http://api.themoviedb.org/2.1/Movie.search/en/xml/'.TMDB_KEY.'/');
define('TMDB_INFO', 'http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/'.TMDB_KEY.'/');

//http://api.themoviedb.org/2.1/Movie.search/en/xml/263e2042d04c1989170721f79e675028/Once

class ModTMDB extends Module
{
	# Module Extension

	function Link()
	{
		global $_d;

		$_d['movie.cb.buttons'][] = array($this, 'movie_cb_buttons');
		$_d['movie.cb.check'][] = array($this, 'movie_cb_check');
	}

	function Get()
	{
		global $_d;

		$_d['head'] .= '<script type="text/javascript" src="'.p('tmdb/tmdb.js').'"></script>';

		if (@$_d['q'][1] == 'find')
		{
			$d = $_d['movie.ds']->GetOne(array('match' => array(
				'med_path' => GetVar('path'))));
			die(ModTMDB::Find(GetVar('path'), GetVar('title')));
		}
		else if (@$_d['q'][1] == 'scrape')
		{
			# Collect Existing Information

			$target = stripslashes(GetVar('target'));
			$mm = new ModMovie();
			$item = $mm->ScrapeFS($target);

			$dsitem = $_d['movie.ds']->GetOne(array(
				'match' => array('med_path' => $target),
				'args' => GET_ASSOC
			));

			if (!empty($dsitem)) $item += $dsitem;

			# Create stub

			if (empty($dsitem)) $id = $_d['movie.ds']->Add($item, true);

			if (!empty($_d['movie.cb.prescrape']))
				$item = RunCallbacks($_d['movie.cb.prescrape'], $item);

			$cats = @$item['med_cats'];

			$media = ModMovie::GetMedia('movie', $item, 'modules/movie/img/missing.jpg');

			# Process information

			$item = ModTMDB::Scrape($item, GetVar('tmdb_id'));

			$item['med_path'] = $item['fs_path'];
			foreach (array_keys($item) as $k) if ($k[0] != 'm') unset($item[$k]);
			unset($item['med_thumb'], $item['med_cats']);

			# Save information

			$item['med_id'] = $_d['movie.ds']->Add($item, true);

			if (!empty($_d['movie.cb.postscrape']))
				$item = RunCallbacks($_d['movie.cb.postscrape'], $item);

			$_d['cat.ds']->Remove(array('cat_movie' => $item['med_id']));
			if (!empty($cats))
			foreach ($cats as $cat)
				$_d['cat.ds']->Add(array('cat_movie' => $item['med_id'], 'cat_name' => $cat));

			$p = $item['med_path'];
			//$this->_items[$p] = array_merge($item, $this->ScrapeFS($p));

			die(json_encode($item + $media));
		}
		else if (@$_d['q'][1] == 'remove')
		{
			$path = GetVar('path');
			if (!empty($path))
				$_d['movie.ds']->Remove(array('med_path' => $path));
		}
		else if (@$_d['q'][1] == 'covers')
		{
			$path = GetVar('path');
			$match = array('med_path' => $path);
			$item = $_d['movie.ds']->Get(array('match' => $match));

			if (empty($item) || empty($item[0]['med_tmdbid']))
				die("This movie doesn't seem fully scraped.");

			$covers = ModTMDB::GetCovers($item[0]['med_tmdbid']);
			foreach ($covers as $ix => $c) @$ret .= '<a href="'.$path.'" class="tmdb-aCover"><img src="'.$c.'" />';
			return $ret;
		}
		else if (@$_d['q'][1] == 'cover')
		{
			$dst = 'img/meta/movie/thm_'.filenoext(basename(GetVar('path'))).'.'.
				fileext(GetVar('img'));
			file_put_contents($dst, file_get_contents(GetVar('img')));
			die(json_encode(array('fs_path' => GetVar('path'), 'med_thumb' => $dst)));
		}
		else if (@$_d['q'][1] == 'fixCover')
		{
			$p = $_POST['path'];
			$md = $_d['movie.ds']->Get(array('match' => array('med_path' => $p)));
			$covers = ModTMDB::GetCovers($md[0]['med_tmdbid']);
			foreach ($covers as $c)
			{
				if (!$data = @file_get_contents($c)) continue;
				$src_pinfo = pathinfo($c);
				$dst_pinfo = pathinfo($p);
				$dst_pinfo['filename'] = filenoext($dst_pinfo['basename']);
				$dst = 'img/meta/movie/thm_'.$dst_pinfo['filename'].'.'.$src_pinfo['extension'];
				file_put_contents($dst, $data);
			}
			if (empty($data)) die("No data on any covers were found for <a href=\"http://www.themoviedb.org/movie/{$md[0]['med_tmdbid']}\">this</a>, sorry.\n");
			die('Fixed!');
		}
	}

	# Check Extension

	function Check()
	{
		global $_d;

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
				case 'image/gif; charset=binary':
					$ext = 'gif';
					break;
				case 'application/x-empty; charset=binary':
					unlink($f);
					$ret['cleanup'][] = "Empty image {$f}, deleted.";
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

		$ret['Stats'][] = 'Checked '.count(glob('img/meta/movie/*')).' media files.';

		return $ret;
	}

	# Callbacks

	function movie_cb_buttons()
	{
		$ret = ' <a href="{{fs_path}}" id="tmdb-aSearch"><img src="img/find.png"
			alt="Find" /></a>';
		$ret .= ' <a href="{{fs_path}}" id="tmdb-aRemove"><img src="img/remove.png"
			alt="Remove" /></a>';
		$ret .= ' <a href="{{fs_path}}" id="tmdb-aCovers"><img src="modules/movie/img/covers.png"
			alt="Select New Cover" /></a>';
		return $ret;
	}

	function movie_cb_check($md)
	{
		$ret = array();

		$title = ModMovie::CleanTitleForFile($md['med_title']);
		$year = substr($md['med_date'], 0, 4);

		if (count(glob("img/meta/movie/thm_$title ($year).*")) < 1)
			$ret['Media'][] = 'Cover missing for '.$title.' ('.$year.') <a class="tmdb-aFixCover" href="'.$md['med_path'].'">Fix</a>';

		return $ret;
	}

	# Static Methods

	static function Decode($sx_movie)
	{
		$ret['id'] = (string)$sx_movie->id;
		$ret['score'] = (string)$sx_movie->score;
		$ret['popularity'] = (string)$sx_movie->popularity;
		$ret['name'] = (string)$sx_movie->name;
		$ret['released'] = (string)$sx_movie->released;
		$ret['overview'] = (string)$sx_movie->overview;
		$xp_thumbs = 'images/image[@type="poster"]';
		$sx_thumbs = $sx_movie->xpath($xp_thumbs);
		if (!empty($sx_thumbs))
		foreach ($sx_thumbs as $el_thumb)
		{
			$ret['thumbs'][] = (string)$el_thumb['url'];
		}
		else $ret['thumbs'][0] = 'modules/movie/img/missing.jpg';
		return $ret;
	}

	static function FindXML($title)
	{
		$reps = array(
			'#-.*$#' => '',
			'#\[[^\]]+\]#' => '',
			'#([.]{1} |\.|-|_)#' => ' ',
			'#(ac3|5,1|dvdrip|bdrip|unrated)#i' => '',
			'#\([^)]*\)#' => ''
		);

		$title = preg_replace(array_keys($reps), array_values($reps), $title);
		$title = urlencode(trim($title));
		$xml = file_get_contents(TMDB_FIND.$title);

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}

	static function Find($path, $title)
	{
		if (empty($title))
		{
			$m = new ModMovie();
			$d = $m->ScrapeFS($path);
			$title = $d['fs_title'];
		}

		$sx_movies = ModTMDB::FindXML($title);

		$t = new Template();
		$ret = null;

		usort($sx_movies, array('ModTMDB', 'cmp_title'));

		foreach ($sx_movies as $sx_movie)
		{
			$m = ModTMDB::Decode($sx_movie);
			$t->_movie = $m;
			$t->Set('fs_path', $path);
			$t->Set($m);
			$ret .= $t->ParseFile('modules/tmdb/t_find_result.xml');
		}

		if (empty($ret)) $ret = 'Nothing found.';

		$ret .= 'Search for the query <input type="text" value="'
				.$title.'" id="inTitle" /> <input type="button" id="tmdb-butFind"
				value="Search Again" />.';

		return $ret;
	}

	static function cmp_title($cmp1, $cmp2)
	{
		return (int)$cmp1->score < (int)$cmp2->score;

		global $_movie;

		similar_text($_movie['fs_title'], (string)$cmp1->name, $title1);
		similar_text($_movie['fs_title'], (string)$cmp2->name, $title2);
		if (isset($_movie['fs_date']))
		{
			similar_text($_movie['fs_date'], date('Y', MyDateTimestamp($cmp1->released)), $date1);
			similar_text($_movie['fs_date'], date('Y', MyDateTimestamp($cmp2->released)), $date2);
		}

		if ($title1 != $title2) return $title1 <= $title2;
		if (isset($_movie['fs_date'])) return $date1 <= $date2;
		return 0;
	}

	static function Scrape($movie, $id)
	{
		global $_d;

		$xml = file_get_contents(TMDB_INFO.$id);
		if (!empty($_d['tmdb.cb.scrape']))
			RunCallbacks($_d['tmdb.cb.scrape'], $movie, $xml);
		$sx = simplexml_load_string($xml);

		# Scrape some general information

		$movie['med_tmdbid'] = $id;
		list($movie['med_title']) = $sx->xpath('//movies/movie/name');
		$movie['med_title'] = trim((string)$movie['med_title']);
		list($movie['med_date']) = $sx->xpath('//movies/movie/released');

		$dst_pinfo = pathinfo($movie['fs_path']);
		$dst_pinfo['filename'] = filenoext($dst_pinfo['basename']);

		# Scrape Categories

		$elcats = $sx->xpath('//movies/movie/categories/category');

		foreach ($elcats as $e) $movie['med_cats'][] = $e['name'];

		# Scrape a cover thumbnail

		$xp_thumb = '//movies/movie/images/image[@type="poster"][@size="cover"]';
		$urls = xpath_attrs($sx, $xp_thumb, 'url');

		// Covers are available to grab

		if (!empty($urls))
		{
			// Collect all cover sizes

			foreach ($urls as $url)
			{
				$size = @getimagesize($url);
				$sizes[(string)$url] = $size[0]*$size[1];
			}

			arsort($sizes);

			// Find a size that is available.
			foreach (array_keys($sizes) as $url)
				if ($data = file_get_contents($url)) break;

			if (!empty($data))
			{
				// Clean up existing covers

				foreach (glob('img/meta/movie/thm_'.$dst_pinfo['filename'].'.*') as $f)
					unlink($f);

				$src_pinfo = pathinfo($url);
				$dst = "img/meta/movie/thm_".
					"{$dst_pinfo['filename']}.{$src_pinfo['extension']}";

				if (!file_put_contents($dst, $data))
					trigger_error("Cannot write the cover image.", ERR_FATAL);
			}

			# Scrape a large backdrop

			$xp_back = '//movies/movie/images/image[@type="backdrop"][@size="poster"]';
			$url = xpath_attr($sx, $xp_back, 'url');
			if (!empty($url))
			{
				$src_pinfo = pathinfo($url);
				$data = file_get_contents($url);
				file_put_contents("img/meta/movie/bd_{$dst_pinfo['filename']}.".
					"{$src_pinfo['extension']}", $data);
			}
		}

		return $movie;
	}

	static function GetCovers($id)
	{
		$xml = file_get_contents(TMDB_INFO.$id);
		$sx = simplexml_load_string($xml);
		$xp_thumb = '//movies/movie/images/image[@type="poster"][@size="cover"]';
		return xpath_attrs($sx, $xp_thumb, 'url');
	}
}

Module::Register('ModTMDB');

?>
