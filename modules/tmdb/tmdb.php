<?php

require_once(dirname(__FILE__).'/../scrape/scrape.php');

define('TMDB_KEY', '263e2042d04c1989170721f79e675028');
define('TMDB_FIND', 'http://api.themoviedb.org/2.1/Movie.search/en/xml/'.TMDB_KEY.'/');
define('TMDB_INFO', 'http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/'.TMDB_KEY.'/');

class TMDB extends Module implements Scraper
{
	public static $Name = 'TMDB';
	public static $Link = 'http://www.themoviedb.org/';
	public static $Icon = 'modules/tmdb/icon.png';

	function __construct()
	{
		global $_d;

		$this->CheckActive(self::$Name);
		$_d['search.cb.query'][] = array(&$this, 'cb_search_query');
	}

	# Module Extension

	function Link()
	{
		global $_d;
		$_d['movie.cb.check'][] = array($this, 'movie_cb_check');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;
		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');

			# Collect Information
			$xml = self::Details($id);
			$sx = simplexml_load_string($xml);
			$covers = $sx->xpath('//movies/movie/images/image[@size="cover"]');

			# Process Information
			$ret = array('id' => self::$Name);
			foreach ($covers as $c)
				$ret['covers'][] = (string)$c['url'];

			die(json_encode($ret));
		}
	}

	function Get()
	{
		global $_d;

		$r['head'] = '<script type="text/javascript" src="'.
			Module::P('tmdb/tmdb.js').'"></script>';

		if (@$_d['q'][1] == 'find2')
		{
			$title = MediaLibrary::SearchTitle(Server::GetVar('title'));
			$path = Server::GetVar('path');
			if (Server::GetVar('manual', 0) == 0
				&& preg_match('/.*\((\d+)\)\.\S{3}/', $path, $m))
				$title .= ' '.$m[1];

			die(TMDB::Find($path, $title));
		}
		/*else if (@$_d['q'][1] == 'scrape')
		{
			$p = Server::GetVar('target');
			$movie = Movie::GetMovie($p);

			if (empty($movie['title'])) $movie['title'] = $movie['fs_title'];
			if (empty($movie['date'])) $movie['date'] = @$movie['fs_date'];

			# Fast scrape doesn't come with tmdbid.
			if (Server::GetVar('fast') == 1)
			{
				$title = MediaLibrary::SearchTitle($movie['title']).' '
					.@$movie['date'];
				$sx_movies = TMDB::FindXML($title);
				if (empty($sx_movies)) die('Found nothing for "'.$title.'"');
				$tmdbid = (string)$sx_movies[0]->id;
			}
			else $tmdbid = Server::GetVar('tmdb_id');

			# Do the actual scrape.
			$movie = TMDB::Scrape($movie, $tmdbid);

			# Collect updated information.
			$media = Movie::GetMedia('movie', $movie,
				Module::P('movie/img/missing.jpg'));

			if (empty($movie)) die(json_encode(array('error' => 'Not found',
				'fs_path' => Server::GetVar('target'))));

			# Run all module callbacks
			$movie = U::RunCallbacks($_d['tmdb.cb.postscrape'], $movie);

			# Update the database
			$res = $_d['db']->command(array('findAndModify' => 'entry',
				'query' => array('title' => $movie['title'], 'date' => $movie['date']),
				'update' => $movie, 'new' => 1, 'upsert' => 1));

			if (Server::GetVar('fast') == 1) die('Fixed!');
			die(json_encode($movie + $media));
		}*/
		else if (@$_d['q'][1] == 'remove')
		{
			$_d['entry.ds']->remove(array('_id' => new MongoID(Server::GetVar('id'))));
			die();
		}
		/*else if (@$_d['q'][1] == 'covers')
		{
			$id = $_d['q'][2];

			$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));

			if (empty($item) || empty($item['tmdbid']))
				die("This movie doesn't seem fully scraped.");

			$covers = TMDB::GetCovers($item['tmdbid']);
			$ret = '';
			foreach ($covers as $ix => $c)
				$ret .= '<a href="'.$id.'" class="tmdb-aCover"><img src="'.$c.'" /></a>';
			die($ret);
		}*/
		else if (@$_d['q'][1] == 'cover')
		{
			$id = $_d['q'][2];
			$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
			$dst = $_d['config']['paths']['movie-meta'].'/thm_'
				.File::GetFile(basename($item['fs_path']));
			file_put_contents($dst, file_get_contents(Server::GetVar('image')));
			die(json_encode($item));
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
		foreach (glob($_d['config']['paths']['movie-meta'].'/*') as $f)
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

		return $ret;
	}

	function movie_cb_check($md)
	{
		$ret = array();

		if (empty($md['details']['TMDB']))
		{
			$p = $md['fs_path'];
			$uep = rawurlencode($p);
			$ret['Scrape'][] = <<<EOF
<a href="scrape/scrape?path=$uep"
	class="a-fix">Fix</a> File {$p} needs to be scraped
EOF;
		}
		return $ret;
	}

	function cb_search_query($q)
	{
		return array('details.TMDB.keywords.keyword.@attributes.name' =>
			new MongoRegex("/$q/i"));
	}

	# Static Methods

	static function GetName() { return 'The Movie DB'; }
	static function CanAuto() { return true; }

	static function FindXML($title)
	{
		$title = urlencode(trim($title));
		$xml = @file_get_contents(TMDB_FIND.$title);
		if (empty($xml)) return null;

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}

	static function Find($title, $date)
	{
		$xml = file_get_contents(TMDB_FIND.rawurlencode($title));

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		$ret = array();
		if (!empty($sx_movies))
		foreach ($sx_movies as $sx_movie)
		{
			foreach ($sx_movie->xpath('images/image[@size="cover"]') as $c)
				$covers[] = (string)$c['url'];

			$id = (string)$sx_movie->id;
			$ret[$id] = array(
				'id' => $id,
				'title' => $sx_movie->name,
				'date' => $sx_movie->released,
				'covers' => implode('|', $covers),
				'ref' => (string)$sx_movie->url
			);
		}

		return $ret;
	}

	static function Details($id)
	{
		return file_get_contents(TMDB_INFO.$id);
	}

	static function Scrape($item, $id = null)
	{
		if ($id == null)
		{
			$keys = array_keys(TMDB::Find($item['fs_title'], $item['fs_date']));
			$id = $keys[0];
		}
		# Collect remote data
		$data = Arr::FromXML(self::Details($id));
		$item['details'][self::$Name] = $data['movies']['movie'];

		return $item;
	}

	static function GetDetails($details, $item)
	{
		if (!isset($item->Data['details'][self::$Name])) return $details;

		$trailer = $item->Data['details'][self::$Name]['trailer'];
		if (!empty($trailer))
		{
			preg_match('/\?v=([^&]+)/', $trailer, $m);
			$v = $m[1];
			$details['Trailer'] = <<<EOF
<object width="580" height="360"><param name="movie" value="http://www.youtube.com/v/$v&amp;hl=en_US&amp;fs=1?color1=0x3a3a3a&amp;color2=0x999999&amp;hd=1&amp;border=1"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/$v&amp;hl=en_US&amp;fs=1?color1=0x3a3a3a&amp;color2=0x999999&amp;hd=1&amp;border=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="580" height="360"></embed></object>
EOF;
		}
		return $details;
	}

	# DEPRECATED OLD SYSTEM

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
	
	static function TagResult($t, $g, $a, $tag, $args)
	{
		$vp = new VarParser();

		$ret = null;
		if (!empty($sx_movies))
		foreach ($sx_movies as $sx_movie)
		{
			$m = TMDB::Decode($sx_movie);
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

	static function FixCover($p)
	{
		global $_d;

		$md = $_d['movie.ds']->Get(array('match' => array('fs_path' => $p)));
		$covers = TMDB::GetCovers($md[0]['tmdbid']);
		foreach ($covers as $c)
		{
			if (!$data = @file_get_contents($c)) continue;
			$src_pinfo = pathinfo($c);
			$dst_pinfo = pathinfo($p);
			$dst_pinfo['filename'] = File::GetFile($dst_pinfo['basename']);
			$dst = $_d['config']['paths']['movie-meta'].'/thm_'
				.$dst_pinfo['filename'].'.'.$src_pinfo['extension'];
			varinfo("Writing cover: '{$dst}'.");
			file_put_contents($dst, $data);
			break;
		}
		if (empty($data)) varinfo("No data on any covers were found for"
			." <a href=\"http://www.themoviedb.org/movie/{$md[0]['tmdbid']}\">"
			."this</a>, sorry.\n");
		return 1;
	}

	static function GetCovers($id)
	{
		$xml = file_get_contents(TMDB_INFO.$id);
		$sx = simplexml_load_string($xml);
		$xp_thumb = '//movies/movie/images/image[@type="poster"][@size="cover"]';
		$el = $sx->xpath($xp_thumb);
		return (string)$el['url'];
	}
}

Module::Register('TMDB');
Scrape::RegisterScraper('TMDB');

?>

