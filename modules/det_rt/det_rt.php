<?php

require_once(dirname(__FILE__).'/../scrape/scrape.php');

# API KEY: 6psypq3q5u3wf9f2be38t5fd

# http://api.rottentomatoes.com/api/public/v1.0.json?apikey=6psypq3q5u3wf9f2be38t5fd
# http://api.rottentomatoes.com/api/public/v1.0/movies.json
# http://api.rottentomatoes.com/api/public/v1.0/movies.json?apikey=6psypq3q5u3wf9f2be38t5fd&q=Hitch
# http://api.rottentomatoes.com/api/public/v1.0/movies/<id>.json

define('det_rt_key', '6psypq3q5u3wf9f2be38t5fd');
define('det_rt_url', 'http://api.rottentomatoes.com/api/public/v1.0/movies');
define('det_rt_find', det_rt_url.'.json?apikey='.det_rt_key.'&q=');
define('det_rt_info', det_rt_url.'/');

class RottenTomatoes extends Module implements Scraper
{
	# Module Related
	public $Name = 'RottenTomatoes';

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
			$ret['covers'][] = $res['posters']['detailed'];

			die(json_encode($ret));
		}
	}

	# Scraper Related
	public $Link = 'http://www.rottentomatoes.com';
	public $Icon = 'modules/det_rt/icon.png';

	function CanAuto() { return false; }

	function GetName() { return 'Rotten Tomatoes'; }

	function Find($path)
	{
		global $_d;

		$item = $_d['entry.ds']->findOne(array('path' => $path));
		if (!empty($item['title'])) $title = $item['title'];
		else
		{
			$fs = MediaLibrary::ScrapeFS($path, Movie::GetFSPregs());
			var_dump($fs);
		}

		$title = MediaLibrary::SearchTitle($title);

		# Collect data.
		$dat = json_decode(file_get_contents(det_rt_find.rawurlencode($title)),
			true);

		# Prepare results.
		$ret = array();
		foreach ($dat['movies'] as $m)
		{
			$ret[$m['id']] = array(
				'title' => $m['title'],
				'date' => $m['year'],
				'cover' => $m['posters']['detailed'],
				'ref' => $m['links']['alternate']);
		}

		return $ret;
	}

	function Details($id)
	{
		# Collect Information
		return file_get_contents(det_rt_info.$id.'.json?apikey='.det_rt_key);
	}

	function Scrape($item, $id = null)
	{
		$item['details'][$this->Name] = json_decode(self::Details($id), true);
		return $item;
	}

	function GetDetails($details, $item)
	{
		if (!isset($item->Data['details'][$this->Name])) return $details;

		$cc = @$item->Data['details'][$this->Name]['critics_consensus'];
		if (!empty($cc)) $details['Rotten Tomatoes'] = $cc;

		return $details;
	}
}

Module::Register('RottenTomatoes');
Scrape::RegisterScraper('movie', 'RottenTomatoes');

?>
