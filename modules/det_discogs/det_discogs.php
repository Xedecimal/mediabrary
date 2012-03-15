<?php

# Depends on on scraper module
require_once(dirname(__FILE__).'/../scrape/scrape.php');

class Discogs extends Module implements Scraper
{
	public $Name = 'Discogs';
	public $Link = 'http://www.discogs.com/';
	public $Icon = 'modules/det_discogs/img/discogs.png';

	const HTTP_HEADER = "User-Agent: Mediabrary/0.1 +http://code.google.com/p/mediabrary\r\n";
	const URL_SEARCH = 'http://api.discogs.com/search?f=json&type=artists&q=';
	const URL_ARTIST = 'http://api.discogs.com/artist/{{id}}?f=json';

	function __construct()
	{
		$this->CheckActive($this->Name);
	}

	function Link()
	{
		global $_d;

		$_d['music.cb.check.artist']['discogs'] = array(&$this, 'cb_check_artist');
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
				$ret['covers'][] = 'http://'.$_SERVER['HTTP_HOST'].
					$_d['app_abs'].'/Discogs/cover?cover='.
						urlencode($img['uri']);

			die(json_encode($ret));
		}

		if (@$_d['q'][1] == 'cover')
		{
			//file_get_contents($_GET['cover']);
			die(file_get_contents($_GET['cover']));
		}
	}

	# Scraper Implementation

	function GetName() { return $this->Name; }
	function CanAuto() { return false; }

	function Find(&$ae, $title)
	{
		global $_d;

		$opts['http']['header'] = Discogs::HTTP_HEADER;
		$cx = stream_context_create($opts);
		$t = !empty($title) ? $title : $ae->Title;
		$data = file_get_contents(Discogs::URL_SEARCH.rawurlencode($t), null, $cx);
		$data = json_decode($data);

		if (!empty($data->resp->search->exactresults))
			$ress = $data->resp->search->exactresults;
		else if (!empty($data->resp->search->searchresults->results))
			$ress = $data->resp->search->searchresults->results;

		foreach ($ress as $res)
		{
			$ret['id'] = $res->title;
			$ret['title'] = $res->title;
			if (!empty($res->thumb)) $ret['covers'] = $_d['app_abs'].'/Discogs/cover?cover='.urlencode($res->thumb);
			$ret['ref'] = $res->uri;

			$rets[$res->title] = $ret;
		}

		return $rets;
	}

	function GetCovers($item) { }

	function Details($id)
	{
		//TODO: We need to cover music-artist, music-album and music-track here.

		$url = VarParser::Parse(Discogs::URL_ARTIST, array('id' => rawurlencode($id)));

		$opts['http']['header'] = Discogs::HTTP_HEADER;
		$cx = stream_context_create($opts);
		$res = json_decode(file_get_contents($url, null, $cx), true);

		return $res['resp']['artist'];
	}

	function Scrape(&$ae, $id = null)
	{
		if ($ae->Data['type'] == 'music-artist')
		{
			$ae->Data['details'][$this->Name] = $this->Details($id);
		}
	}

	function GetDetails($t, $g, $a)
	{
		$data = $t->vars['Data'];
		if (empty($data['details'][$this->Name])) return;

		return <<<EOF
<p>Discogs Name: {$data['details'][$this->Name]['name']}</p>
EOF;
	}

	function cb_check_artist($ae)
	{
		var_dump($ae);
		flush();
		die();
	}
}

Module::Register('Discogs');
Scrape::Reg('music-artist', 'Discogs');

?>