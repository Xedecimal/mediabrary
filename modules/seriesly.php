<?php

class ModSeriesly extends Module
{
	function __construct()
	{
		$this->CheckActive('seriesly');
	}

	function Get()
	{
		if (empty($_d['q'][0]))
		{
			return "For Seriesly support Add the following web hook: http://{$_SERVER['HTTP_HOST']}{{app_abs}}/seriesly";
		}
	}

	function Prepare()
	{
		if (!$this->Active) return;
		$xml = file_get_contents('php://input');
		//$xml = file_get_contents('seriesly2.txt');
		$sx = simplexml_load_string($xml);
		foreach ($sx->xpath('//release[quality="HDTV"]/url') as $url)
		{
			$fname = basename($url);
			file_put_contents("/data/nas/torrent-files/$fname",
				file_get_contents($url));
			//varinfo((string)$url);
		}
		die();
	}
}

Module::Register('ModSeriesly');

?>
