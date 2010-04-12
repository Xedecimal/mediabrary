<?php

class ModScrapeTVDB
{
	const _tvdb_key = '138419DAB0A9141D';
	const _tvdb_find = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=';

	//http://www.thetvdb.com/api/138419DAB0A9141D/series/72167/all/en.zip

	static function Find($series)
	{
		$sid = ModScrapeTVDB::GetSID($series);
		$key = ModScrapeTVDB::_tvdb_key;

		file_put_contents('tmp.zip', file_get_contents("http://www.thetvdb.com/api/{$key}/series/{$sid}/all/en.zip"));

		$za = new ZipArchive;
		$za->open('tmp.zip');
		$xml = $za->getFromName('banners.xml');
		$za->close();

		$sx = simplexml_load_string($xml);
		list($ban) = $sx->xpath("//Banners/Banner[BannerType='series']/BannerPath");
		$pi = pathinfo($ban);
		file_put_contents("img/meta/tv/thm_$series.{$pi['extension']}",
			file_get_contents("http://www.thetvdb.com/banners/{$ban}"));

		return "Grabbed";
	}

	static function GetSID($series)
	{
		$url = ModScrapeTVDB::_tvdb_find.rawurlencode($series);
		$xml = file_get_contents($url);
		$sx = simplexml_load_string($xml);
		list($sid) = $sx->xpath('//Data/Series/seriesid');
		if (empty($sid)) return -1;
		return $sid;
	}
}

?>
