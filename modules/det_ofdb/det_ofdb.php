<?php

require_once(dirname(__FILE__).'/../scrape/scrape.php');

# http://ofdbgw.org/search/hitch
# http://ofdbgw.org/movie/69878

# TODO: using http://ofdbgw.home-of-root.de because one of the hosts is
# throwing errors all over the place, when that is fixed we need to replace
# the urls with the round robin of http://ofdbgw.org/

define('OFDB_FIND', 'http://ofdbgw.home-of-root.de/search/');
define('OFDB_DETAIL', 'http://ofdbgw.home-of-root.de/movie_json/');

class OFDB extends Module implements Scraper
{
	# Module Related
	public $Name = 'OFDB';
	public $Link = 'http://www.ofdb.de';
	public $Icon = '';

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
			$res = json_decode($data, true);

			$ret['id'] = $this->Name;
			$ret['covers'][] = $res['ofdbgw']['resultat']['bild'];

			die(json_encode($ret));
		}
	}

	# Scraper Implementation

	function CanAuto() { return false; }
	function GetName() { return 'OFDB'; }

	function Find($path)
	{
		$md = new MovieEntry($path, MovieEntry::GetFSPregs());
		$title = Server::GetVar('title', $md->Title);

		$ctx = stream_context_create(array('http' => array('timeout' => 5)));
		$xml = @file_get_contents(OFDB_FIND.rawurlencode($title), false, $ctx);
		if (empty($xml)) return array();

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//resultat/eintrag');

		$ret = array();
		if (!empty($sx_movies))
		foreach ($sx_movies as $sx_movie)
		{
			$id = (string)$sx_movie->id;
			$ret[$id] = array(
				'id' => $id,
				'title' => $sx_movie->titel,
				'date' => $sx_movie->jahr,
				'covers' => $sx_movie->bild,
				'ref' => 'http://www.ofdb.de/film/'.$id
			);
		}

		return $ret;
	}

	function Details($id)
	{
		return file_get_contents(OFDB_DETAIL.$id);
	}

	function Scrape($item, $id = null)
	{
		$data = json_decode(self::Details($id), true);
		$item['details'][$this->Name] = $data['ofdbgw']['resultat'];
		return $item;
	}

	function GetDetails($t, $g, $a) { }
}

Module::Register('OFDB');
Scrape::Reg('movie', 'OFDB');

?>
