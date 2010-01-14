<?php

class ModScrapeTVDB
{
	const _tvdb_key = '138419DAB0A9141D';
	const _tvdb_find = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=';

	//http://www.thetvdb.com/api/138419DAB0A9141D/series/72167/all/en.zip

	static function Find($series)
	{
		$url = ModScrapeTVDB::_tvdb_find.rawurlencode($series);
		$xml = file_get_contents($url);
		$sx = simplexml_load_string($xml);
		list($sid) = $sx->xpath('//Data/Series/seriesid');

		if (empty($sid))
		{
			return "No such series found: {$series}<br/>\n";
		}
		
		$key = ModScrapeTVDB::_tvdb_key;

		$url = "http://www.thetvdb.com/api/{$key}/series/{$sid}/all/en.zip/banners.xml";

		require_once "File/Archive.php";
		$archive = File_Archive::read($url);
		$xml = $archive->getData();
		varinfo($xml);
		$sx = simplexml_load_string($xml);
		list($ban) = $sx->xpath("//Banners/Banner[BannerType='series']/BannerPath");
		$pi = pathinfo($ban);
		file_put_contents("img/meta/tv/thm_$series.{$pi['extension']}",
			file_get_contents("http://www.thetvdb.com/banners/{$ban}"));
	}
}

?>
