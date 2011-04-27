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
	public static $Name = 'OFDB';
	public static $Link = 'http://www.ofdb.de';
	public static $Icon = '';

	function __construct()
	{
		$this->CheckActive(self::$Name);
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

			$ret['id'] = self::$Name;
			$ret['covers'][] = $res['ofdbgw']['resultat']['bild'];

			die(json_encode($ret));
		}
	}

	static function CanAuto() { return false; }
	static function GetName() { return 'OFDB'; }

	static function Find($title, $date)
	{
		$xml = file_get_contents(OFDB_FIND.rawurlencode($title));

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
			);
		}

		return $ret;
	}
	
	static function Details($id)
	{
		return file_get_contents(OFDB_DETAIL.$id);
	}

	static function Scrape($item, $id = null)
	{
		$data = json_decode(self::Details($id), true);
		$item['details'][self::$Name] = $data['ofdbgw']['resultat'];
		return $item;
	}
	
	static function GetDetails($details, $item)
	{
		return $details;
	}
}

Module::Register('OFDB');
Scrape::RegisterScraper('OFDB');

?>
