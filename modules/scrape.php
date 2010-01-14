<?php

class ModScrape
{
	static function Find($movie)
	{
		$res = ModScrapeTMDB::Find($movie);
		return $res;
	}

	static function Scrape($movie)
	{
		// TITLE (DATE).EXT
		if (preg_match('/^([^\(]+)\((\d+)\)\.(\S+)$/', @$movie['mov_filename'], $m))
		{
			list($n, $movie['med_title'], $movie['med_date'],
				$movie['med_ext']) = $m;
		}
		// TITLE.EXT
		else if (preg_match('/([^.]+)\.(\S+)$/', @$movie['mov_filename'], $m))
		{
			$movie['med_title'] = $m[1];
			$movie['med_ext'] = $m[2];
		}

		return $movie;
	}
}

?>
