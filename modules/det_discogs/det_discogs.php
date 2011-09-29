<?php

class Discogs extends Module implements Scraper
{
	public $Name = 'Discogs';
	public $Link = 'http://www.discogs.com/';
	public $Icon = 'modules/det_discogs/icon.png';

	const URL_ARTIST = 'http://api.discogs.com/search?f=json&type=artists&q=';

	# Scraper Implementation

	function GetName() { return $this->Name; }
	function CanAuto() { return false; }

	function Find($path)
	{
		$ae = new ArtistEntry($path);
		$opts['http']['header'] = "User-Agent: Mediabrary/0.1 +http://code.google.com/p/mediabrary\r\nAccept-Encoding: gzip\r\n";
		$cx = stream_context_create($opts);
		$data = file_get_contents(Discogs::URL_ARTIST.rawurlencode($ae->Title), null, $cx);
		$data = json_decode($data);
		//var_dump($data);

		if (!empty($data->resp->search->exactresults))
			$ress = $data->resp->search->exactresults;
		else if (!empty($data->resp->search->searchresults->results))
			$ress = $data->resp->search->searchresults->results;

		foreach ($ress as $res)
		{
			$ret['id'] = $res->title;
			$ret['title'] = $res->title;
			if (!empty($res->thumb))
				$ret['covers'] = $res->thumb;
			$ret['ref'] = $res->uri;

			$rets[$res->title] = $ret;
		}

		return $rets;
	}

	function Details($id)
	{
		die('Ouch.');
	}
	function Scrape($item, $id = null)
	{
		die('Ouch.');
	}
	function GetDetails($details, $item)
	{
		die('Ouch.');
	}
}

Scrape::RegisterScraper('music', 'Discogs');

?>