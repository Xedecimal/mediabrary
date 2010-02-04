<?php

define('TMDB_KEY', '263e2042d04c1989170721f79e675028');
define('TMDB_FIND', 'http://api.themoviedb.org/2.1/Movie.search/en/xml/'.TMDB_KEY.'/');
define('TMDB_INFO', 'http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/'.TMDB_KEY.'/');

//http://api.themoviedb.org/2.1/Movie.search/en/xml/263e2042d04c1989170721f79e675028/20,000%20Leagues%20Under%20Sea

class ModScrapeTMDB
{
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

	static function FindXML(&$title)
	{
		$reps = array('# -.*$#' => '', '#([.]{1} |\.|-)#' => ' ');
		$title = preg_replace(array_keys($reps), array_values($reps), $title);
		$title = urlencode(trim($title));
		$xml = file_get_contents(TMDB_FIND.$title);

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}

	static function TagCover($t, $g)
	{
		$vp = new VarParser();
		foreach ($t->_movie['thumbs'] as $t)
			@$ret .= $vp->ParseVars($g, array('thumb' => $t));
		return $ret;
	}

	static function Find($movie)
	{
		$GLOBALS['_movie'] = $movie;

		$sx_movies = ModScrapeTMDB::FindXML($movie['fs_title']);

		$t = new Template();
		$t->Rewrite('cover', array('ModScrapeTMDB', 'TagCover'));
		$ret = null;

		usort($sx_movies, array('ModScrapeTMDB', 'cmp_title'));

		foreach ($sx_movies as $sx_movie)
		{
			$m = ModScrapeTMDB::Decode($sx_movie);
			$t->_movie = $m;
			$t->Set($movie);
			$t->Set($m);
			$ret .= $t->ParseFile('modules/movie/t_movie_search_result.xml');
		}

		if (empty($ret))
		{
			$ret = "Nothing found for the query '{$movie['fs_title']}'";
		}

		return $ret;
	}

	static function cmp_title($cmp1, $cmp2)
	{
		global $_movie;

		similar_text($_movie['fs_title'], (string)$cmp1->name, $title1);
		similar_text($_movie['fs_title'], (string)$cmp2->name, $title2);
		if (isset($_movie['fs_date']))
		{
			similar_text($_movie['fs_date'], date('Y', MyDateTimestamp($cmp1->released)), $date1);
			similar_text($_movie['fs_date'], date('Y', MyDateTimestamp($cmp2->released)), $date2);
		}

		if ($title1 != $title2) return $title1 <= $title2;
		return $date1 <= $date2;
	}

	static function Scrape($movie, $id)
	{
		$xml = file_get_contents(TMDB_INFO.$id);
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

		foreach ($elcats as $e)
			$movie['med_cats'][] = $e['name'];

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

?>
