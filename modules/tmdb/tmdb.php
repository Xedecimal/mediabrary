<?php

define('TMDB_KEY', '263e2042d04c1989170721f79e675028');
define('TMDB_FIND', 'http://api.themoviedb.org/2.1/Movie.search/en/xml/'.TMDB_KEY.'/');
define('TMDB_INFO', 'http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/'.TMDB_KEY.'/');

// http://api.themoviedb.org/2.1/Movie.search/en/xml/263e2042d04c1989170721f79e675028/Once
// http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/263e2042d04c1989170721f79e675028/9473

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

		$r['head'] = '<script type="text/javascript" src="'.
			Module::P('tmdb/tmdb.js').'"></script>';

		if (@$_d['q'][1] == 'find')
		{
			$d = $_d['movie.ds']->GetOne(array('match' => array(
				'mov_path' => Server::GetVar('path'))));

			$title = Server::GetVar('title');
			$path = Server::GetVar('path');
			if (Server::GetVar('manual', 0) == 0 && preg_match('/.*\((\d+)\)\.\S{3}/', $path, $m))
				$title .= ' '.$m[1];

			die(ModTMDB::Find($path, $title));
		}
		else if (@$_d['q'][1] == 'scrape')
		{
			$movie = ModMovie::GetMovie(Server::GetVar('target'));

			if (empty($movie['mov_title']))
				$movie['mov_title'] = $movie['fs_title'];
			if (empty($movie['mp_path']))
				$movie['mp_path'] = $movie['fs_path'];

			# Fast scrape doesn't come with tmdbid.
			if (Server::GetVar('fast') == 1)
			{
				$title = MediaLibrary::UncleanTitle($movie['mov_title']).' '
					.@$movie['fs_date'];
				$sx_movies = ModTMDB::FindXML($title);
				if (empty($sx_movies)) die('Found nothing for "'.$title.'"');
				$tmdbid = (string)$sx_movies[0]->id;
			}
			else $tmdbid = Server::GetVar('tmdb_id');

			# Do the actual scrape.
			$movie = ModTMDB::Scrape($movie, $tmdbid);

			# Collect updated information.
			$media = ModMovie::GetMedia('movie', $movie,
				Module::P('movie/img/missing.jpg'));

			if (empty($movie)) die(json_encode(array('error' => 'Not found',
				'mov_path' => Server::GetVar('target'))));

			# Store paths for later insertion.
			$paths = $movie['paths'];

			# Pop off anything that doesn't go in the database.
			foreach ($movie as $k => $v) if (substr($k, 0, 3) != 'mov')
				unset($movie[$k]);

			# Update the database
			$added = $_d['movie.ds']->Add($movie, true);

			if (empty($movie['mov_id'])) $movie['mov_id'] = $added;

			# Now that we have something to attach to, lets add all the paths
			# for this movie.
			foreach ($paths as $p)
				$_d['movie_path.ds']->Add(array(
					'mp_movie' => $movie['mov_id'],
					'mp_path' => $p
				), true);

			# Run all module callbacks
			U::RunCallbacks($_d['tmdb.cb.postscrape'], $movie);
			if (Server::GetVar('fast') == 1) die('Fixed!');
			die(json_encode($movie + $media));
		}
		else if (@$_d['q'][1] == 'remove')
		{
			$_d['movie.ds']->Remove(array('mov_id' => Server::GetVar('id')));
		}
		else if (@$_d['q'][1] == 'covers')
		{
			$path = Server::GetVar('path');
			$match = array('mp_path' => $path);
			$item = $_d['movie.ds']->Get(array('match' => $match));

			if (empty($item) || empty($item[0]['mov_tmdbid']))
				die("This movie doesn't seem fully scraped.");

			$covers = ModTMDB::GetCovers($item[0]['mov_tmdbid']);
			$ret = '';
			foreach ($covers as $ix => $c)
				$ret .= '<a href="'.$path.'" class="tmdb-aCover"><img src="'.$c.'" /></a>';
			die($ret);
		}
		else if (@$_d['q'][1] == 'cover')
		{
			$dst = 'img/meta/movie/thm_'.File::GetFile(basename(Server::GetVar('path')));
			file_put_contents($dst, file_get_contents(Server::GetVar('img')));
			die(json_encode(array('fs_path' => Server::GetVar('path'), 'med_thumb' => $dst)));
		}
		else if (@$_d['q'][1] == 'fixCover')
		{
			if ($this->FixCover($_POST['path'])) die('Fixed!');
		}

		return $r;
	}

	# Check Extension

	function Check2()
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
					varinfo("Renamed {$f}: invalid extension {$m[2]} now {$ext}");
				}
				if (count(glob($_d['config']['movie_path'].'/'.$m[2].'*')) < 1)
				{
					unlink($f);
					varinfo("Removed backdrop for missing movie: $f");
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

	function movie_cb_buttons($t)
	{
		$ret = '<a href="{{fs_path}}" id="tmdb-aSearch"><img src="img/find.png"
			alt="Find" /></a>';
		if (!empty($t->vars['mov_date']))
		{
			$ret .= '<a href="{{fs_path}}" id="tmdb-aRemove"><img src="img/database_delete.png"
				alt="Remove" /></a>';
			$ret .= '<a href="{{fs_path}}" id="tmdb-aCovers"><img src="modules/movie/img/images.png"
				alt="Select New Cover" /></a>';
			$ret .= '<a href="http://www.themoviedb.org/movie/{{mov_tmdbid}}" target="_blank"><img src="modules/tmdb/img/tmdb.png" alt="tmdb" /></a>';
		}
		return $ret;
	}

	function movie_cb_check($md)
	{
		$ret = array();
	
		if ($md['mov_clean'] == 0) return $ret;
		
		$title = ModMovie::CleanTitleForFile($md['mov_title']);
		$year = substr($md['mov_date'], 0, 4);

		if (count(glob("img/meta/movie/thm_$title ($year).*")) < 1)
		{
			varinfo("Fixing cover for $title ($year).");
			$this->FixCover($md['mov_path']);
			flush();
		}

		return $ret;
	}

	# Static Methods

	#TODO: Deprecated ?
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
			'#\[[^\]]+\]#' => '',
			'#([.]{1} |\.|-|_)#' => ' ',
			'#\([^)]*\)#' => '',
			"#cd\d+#i" => ''
		);

		$title = preg_replace(array_keys($reps), array_values($reps), $title);
		$title = urlencode(trim($title));
		$xml = @file_get_contents(TMDB_FIND.$title);
		if (empty($xml)) return null;

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}

	static function Find($path, $title)
	{
		$t = new Template();
		$args = array(
			'path' => $path,
			'title' => $title
		);

		$t->Set($args);
		$t->ReWrite('result', array('ModTMDB', 'TagResult'), $args);
		return $t->ParseFile('modules/tmdb/t_find_result.xml');
	}
	
	static function TagResult($t, $g, $a, $tag, $args)
	{
		$vp = new VarParser();
		$sx_movies = ModTMDB::FindXML($args['title']);
		#usort($sx_movies, array('ModTMDB', 'cmp_title'));

		$ret = null;
		if (!empty($sx_movies))
		foreach ($sx_movies as $sx_movie)
		{
			$m = ModTMDB::Decode($sx_movie);
			$m += $args;
			$ret .= $vp->ParseVars($g, $m);
		}
		if (empty($ret)) $ret = 'Nothing found.';

		return $ret;
	}

	#TODO: Fails on damn near everything, been ruled out.
	static function cmp_title($cmp1, $cmp2)
	{
		return (double)$cmp1->score < (double)$cmp2->score;

		global $_movie;

		similar_text($_movie['fs_title'], (string)$cmp1->name, $title1);
		similar_text($_movie['fs_title'], (string)$cmp2->name, $title2);
		if (isset($_movie['fs_date']))
		{
			similar_text($_movie['fs_date'], date('Y', Database::MyDateTimestamp($cmp1->released)), $date1);
			similar_text($_movie['fs_date'], date('Y', Database::MyDateTimestamp($cmp2->released)), $date2);
		}

		if ($title1 != $title2) return $title1 <= $title2;
		if (isset($_movie['fs_date'])) return $date1 <= $date2;
		return 0;
	}

	static function Scrape($movie, $id)
	{
		global $_d;

		session_write_close();

		$ctx_timeout = stream_context_create(array('http' =>
			array('timeout' => 1)));

		$xml = @file_get_contents(TMDB_INFO.$id, 0, $ctx_timeout);
		if (empty($xml)) return $movie;
		if (!empty($_d['tmdb.cb.scrape']))
			U::RunCallbacks($_d['tmdb.cb.scrape'], $movie, $xml);
		$sx = simplexml_load_string($xml);

		# Scrape some general information

		$movie['mov_tmdbid'] = $id;
		$movie['mov_title'] = trim((string)$sx->movies->movie->name);
		$movie['mov_date'] = trim((string)$sx->movies->movie->released);

		$dst_pinfo = pathinfo($movie['fs_path']);
		$dst_pinfo['filename'] = File::GetFile($dst_pinfo['basename']);

		# Scrape a cover thumbnail

		# Don't re-scrape a cover and backdrop.
		$media = ModMovie::GetMedia('movie', $movie, null);
		if (!empty($media['med_thumb']) && !empty($media['med_bd']))
			return $movie;

		# Collect covers
		$xp_thumb = '//movies/movie/images/image[@type="poster"][@size="cover"]';
		$urls = xpath_attrs($sx, $xp_thumb, 'url');

		# Prepare our meta folder for movies.
		if (!file_exists('img/meta/movie')) File::MakeFullDir('img/meta/movie');

		# Covers are available to grab
		if (!empty($urls))
		{
			$url = (string)$urls[0];
			$data = @file_get_contents($url, 0, $ctx_timeout);

			if (!empty($data))
			{
				# Clean up existing covers
				$unlink = glob('img/meta/movie/thm_'.$dst_pinfo['filename'].'.*');
				if (count($unlink) > 2) die('Something just tried to delete more than 2 covers.');
				foreach ($unlink as $f) unlink($f);

				# Place new cover
				$src_pinfo = pathinfo($url);
				$movie['med_thumb'] = "img/meta/movie/thm_".
					$dst_pinfo['filename'];

				if (!file_put_contents($movie['med_thumb'], $data))
					trigger_error("Cannot write the cover image.", ERR_FATAL);
			}

			# Scrape a large backdrop
			$xp_back = '//movies/movie/images/image[@type="backdrop"][@size="poster"]';
			$url = xpath_attr($sx, $xp_back, 'url');
			if (!empty($url))
			{
				$src_pinfo = pathinfo($url);
				$data = @file_get_contents($url, 0, $ctx_timeout);
				if (!empty($data))
					file_put_contents("img/meta/movie/bd_{$dst_pinfo['filename']}", $data);
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

	static function FixCover($p)
	{
		global $_d;

		$md = $_d['movie.ds']->Get(array('match' => array('mov_path' => $p)));
		$covers = ModTMDB::GetCovers($md[0]['mov_tmdbid']);
		foreach ($covers as $c)
		{
			if (!$data = @file_get_contents($c)) continue;
			$src_pinfo = pathinfo($c);
			$dst_pinfo = pathinfo($p);
			$dst_pinfo['filename'] = File::GetFile($dst_pinfo['basename']);
			$dst = 'img/meta/movie/thm_'.$dst_pinfo['filename'].'.'.$src_pinfo['extension'];
			varinfo("Writing cover: '{$dst}'.");
			file_put_contents($dst, $data);
			break;
		}
		if (empty($data)) varinfo("No data on any covers were found for"
			." <a href=\"http://www.themoviedb.org/movie/{$md[0]['mov_tmdbid']}\">"
			."this</a>, sorry.\n");
		return 1;
	}
}

Module::Register('ModTMDB');

?>
