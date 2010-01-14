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

	static function Find($movie)
	{
		$GLOBALS['_movie'] = $movie;

		$xml = file_get_contents(TMDB_FIND.urlencode($movie['med_title']));
		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		$t = new Template();
		$ret = null;

		usort($sx_movies, array('ModScrapeTMDB', 'cmp_title'));

		foreach ($sx_movies as $sx_movie)
		{
			$m = ModScrapeTMDB::Decode($sx_movie);
			$t->Set($m);
			$ret .= $t->ParseFile('t_movie_search_result.xml');
		}
		
		if (empty($ret))
		{
			$ret = "Nothing found for the query '{$movie['med_title']}'";
		}

		return $ret;
	}

	static function cmp_title($title1, $title2)
	{
		similar_text($GLOBALS['_movie']['med_title'], (string)$title1->name, $t11);
		similar_text($GLOBALS['_movie']['med_date'], date('Y', MyDateTimestamp($title1->released)), $t12);

		$cmp1 = $t11+$t12;

		similar_text($GLOBALS['_movie']['med_title'], (string)$title2->name, $t21);
		similar_text($GLOBALS['_movie']['med_date'], date('Y', MyDateTimestamp($title2->released)), $t22);
		$cmp2 = $t21+$t22;

		return $cmp1 <= $cmp2;
	}

	static function Scrape($movie, $id)
	{
		$xml = file_get_contents(TMDB_INFO.$id);

		$sx = simplexml_load_string($xml);

		// Scrape some general information
		list($movie['med_title']) = $sx->xpath('//movies/movie/name');
		list($movie['med_date']) = $sx->xpath('//movies/movie/released');
	
		$dst_pinfo = pathinfo($movie['med_path']);
		$dst_pinfo['filename'] = filenoext($dst_pinfo['basename']);

		# Scrape a cover thumbnail

		$xp_thumb = '//movies/movie/images/image[@type="poster"][@size="cover"]';
		$url = xpath_attr($sx, $xp_thumb, 'url');
		$src_pinfo = pathinfo($url);
		$data = file_get_contents($url);
		file_put_contents("img/meta/movie/thm_{$dst_pinfo['filename']}.{$src_pinfo['extension']}", $data);

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
