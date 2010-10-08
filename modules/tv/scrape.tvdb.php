<?php

class ModScrapeTVDB
{
	const _tvdb_key = '138419DAB0A9141D';
	const _tvdb_find = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=';

	//http://www.thetvdb.com/api/138419DAB0A9141D/series/75897/all/en.zip

	static function Find($series, $path)
	{
		global $_d;

		$sid = ModScrapeTVDB::GetSID($series, $path);
		$key = ModScrapeTVDB::_tvdb_key;

		#@TODO: Don't be referencing config, the whole path should have already come through here.
		#$dst = $_d['config']['tv_path']."/$series/.info.zip";
		#file_put_contents($dst, file_get_contents("http://www.thetvdb.com/api/{$key}/series/{$sid}/all/en.zip"));
		$dst = $path.'/.info.zip';
		file_put_contents($dst, file_get_contents("http://www.thetvdb.com/api/{$key}/series/{$sid}/all/en.zip"));

		$za = new ZipArchive;
		$za->open($dst);
		$xml = $za->getFromName('banners.xml');
		$za->close();

		$sx = simplexml_load_string($xml);
		list($ban) = $sx->xpath("//Banners/Banner[BannerType='series']/BannerPath");
		$pi = pathinfo($ban);
		file_put_contents("img/meta/tv/thm_$series.{$pi['extension']}",
			file_get_contents("http://www.thetvdb.com/banners/{$ban}"));

		return "Grabbed";
	}

	static function GetSID($series, $path)
	{
		global $_d;

		$sc = "$series/.info.dat";
		if (file_exists($sc))
		{
			$info = unserialize(file_get_contents($sc));
			if (isset($info['sid'])) return $info['sid'];
		}
		else $info = array();
		$url = ModScrapeTVDB::_tvdb_find.rawurlencode($series);
		$sx = simplexml_load_string(file_get_contents($url));
		$sids = $sx->xpath('//Data/Series/seriesid');
		if (empty($sids)) return -1;
		$sid = (int)$sids[0];
		if (empty($sid)) return -1;
		$info['sid'] = $sid;
		file_put_contents($path.'/.info.dat', serialize($info));
		return $sid;
	}

	static function GetXML($series, $path, $download = false)
	{
		global $_d;

		$sid = ModScrapeTVDB::GetSID($series, $path);
		if ($sid == -1) {
			echo "Could not locate this series $series";
			return null;
		}

		#@TODO: Don't reference config here.
		$infoloc = "$path/.info.zip";
		if ($download || !file_exists($infoloc))
		{
			$url = 'http://www.thetvdb.com/api/138419DAB0A9141D/series/'
				.$sid.'/all/en.zip';
			file_put_contents($infoloc, file_get_contents($url));
		}
		$za = new ZipArchive;
		$za->open($infoloc);
		$xml = $za->getFromName('en.xml');
		$za->close();
		return simplexml_load_string($xml);
	}
}

?>
