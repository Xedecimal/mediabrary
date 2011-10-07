<?php

class Discogs extends Module implements Scraper
{
	public $Name = 'Discogs';
	public $Link = 'http://www.discogs.com/';
	public $Icon = 'modules/det_discogs/icon.png';

	const HTTP_HEADER = "User-Agent: Mediabrary/0.1 +http://code.google.com/p/mediabrary\r\nAccept-Encoding: gzip\r\n";
	const URL_SEARCH = 'http://api.discogs.com/search?f=json&type=artists&q=';
	const URL_ARTIST = 'http://api.discogs.com/artist/{{id}}?f=json';

	function __construct()
	{
		$this->CheckActive($this->Name);
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');

			$data = self::Details($id);

			$ret['id'] = $this->Name;

			foreach ($data['images'] as $img)
				$ret['covers'][] = $img['uri'];

			die(json_encode($ret));
		}
	}

	# Scraper Implementation

	function GetName() { return $this->Name; }
	function CanAuto() { return false; }

	function Find($path)
	{
		$ae = new ArtistEntry($path);
		$opts['http']['header'] = Discogs::HTTP_HEADER;
		$cx = stream_context_create($opts);
		$data = file_get_contents(Discogs::URL_SEARCH.rawurlencode($ae->Title), null, $cx);
		$data = json_decode($data);

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
		//TODO: We need to cover music-artist, music-album and music-track here.

		$url = VarParser::Parse(Discogs::URL_ARTIST, array('id' => rawurlencode($id)));

		$opts['http']['header'] = Discogs::HTTP_HEADER;
		$cx = stream_context_create($opts);
		$res = json_decode(file_get_contents($url, null, $cx), true);

		return $res['resp']['artist'];
	}

	function Scrape($data, $id = null)
	{
		if ($data['type'] == 'music-artist')
		{
			$data['details'][$this->Name] = $this->Details($id);
		}
		return $data;
	}

	function GetDetails($t, $g, $a)
	{
		return <<<EOF
<p>Discogs Name: {$data['details'][$this->Name]['name']}</p>
EOF;
	}
}

Module::Register('Discogs');
Scrape::Reg('music-artist', 'Discogs');

?>