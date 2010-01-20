<?php

define('TMDB_KEY', 'test');
define('TMDB_FIND', 'http://api.themoviedb.org/2.1/Movie.search/en/xml/'.TMDB_KEY.'/');
define('TMDB_INFO', 'http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/'.TMDB_KEY.'/');

class ModScrapeTMDB
{
	static function Decode($sx_movie)
	{
		#varinfo($sx_movie);
		$ret['id'] = (string)$sx_movie->id;
		$ret['score'] = (string)$sx_movie->score;
		$ret['popularity'] = (string)$sx_movie->popularity;
		$ret['name'] = (string)$sx_movie->name;
		$ret['released'] = (string)$sx_movie->released;
		$ret['overview'] = (string)$sx_movie->overview;
		$xp_thumb = 'images/image[@type="poster"][@size="cover"]';
		$sx_thumb = $sx_movie->xpath($xp_thumb);
		if (empty($sx_thumb))
			$ret['thumb'] = 'img/missing-movie.jpg';
		else
		{
			$el_thumb = array_pop($sx_thumb);
			$ret['thumb'] = $el_thumb['url'];
		}
		return $ret;
	}

	static function FindXML(&$title)
	{
		$reps = array('/ -.*$/' => '', '/the/i' => '', '/[.]+/' => ' ', '/[,]/' => '');
		$title = preg_replace(array_keys($reps), array_values($reps), $title);
		$title = urlencode(trim($title));
		$xml = file_get_contents(TMDB_FIND.$title);

		if (preg_match('/Nothing found/m', $xml))
			if (preg_match('/.*[0-9]+.*/', $title))
				$xml = file_get_contents(TMDB_FIND.preg_replace('/[0-9]/',
					'', $title));

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}
	
	static function Find($movie)
	{
		$GLOBALS['_movie'] = $movie;

		$sx_movies = ModScrapeTMDB::FindXML($movie['med_title']);

		$t = new Template();
		$ret = null;

		usort($sx_movies, array('ModScrapeTMDB', 'cmp_title'));

		foreach ($sx_movies as $sx_movie)
		{
			$m = ModScrapeTMDB::Decode($sx_movie);
			$t->Set($movie);
			$t->Set($m);
			$ret .= $t->ParseFile('modules/movie/t_movie_search_result.xml');
		}
		
		if (empty($ret))
		{
			$ret = "Nothing found for the query '{$movie['med_title']}'";
		}

		return $ret;
	}

	static function cmp_title($cmp1, $cmp2)
	{
		similar_text($GLOBALS['_movie']['med_title'], (string)$cmp1->name, $title1);
		similar_text($GLOBALS['_movie']['med_date'], date('Y', MyDateTimestamp($cmp1->released)), $date1);

		similar_text($GLOBALS['_movie']['med_title'], (string)$cmp2->name, $title2);
		similar_text($GLOBALS['_movie']['med_date'], date('Y', MyDateTimestamp($cmp2->released)), $date2);

		if ($title1 != $title2) return $title1 <= $title2;
		return $date1 <= $date2;
	}

	static function Scrape($movie, $id)
	{
		$xml = file_get_contents(TMDB_INFO.$id);

		$sx = simplexml_load_string($xml);

		# Scrape some general information

		list($movie['med_title']) = $sx->xpath('//movies/movie/name');
		$movie['med_title'] = trim((string)$movie['med_title']);
		list($movie['med_date']) = $sx->xpath('//movies/movie/released');
	
		$dst_pinfo = pathinfo($movie['med_path']);
		$dst_pinfo['filename'] = filenoext($dst_pinfo['basename']);

		# Scrape Categories
		
		$elcats = $sx->xpath('//movies/movie/categories/category');
		
		foreach ($elcats as $e)
			$movie['med_cats'][] = $e['name'];

		# Scrape a cover thumbnail

		$xp_thumb = '//movies/movie/images/image[@type="poster"][@size="cover"]';
		$urls = xpath_attrs($sx, $xp_thumb, 'url');
		foreach ($urls as $url)
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

		return $movie;
	}
}

?>
