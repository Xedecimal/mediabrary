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

class RottenTomatoes extends Module
{
	# Module Related
	public static $Name = 'RottenTomatoes';

	function __construct()
	{
		$this->CheckActive(RottenTomatoes::$Name);
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');

			$data = RottenTomatoes::Details($id);

			$res = json_decode($data, true);
			$ret['id'] = self::$Name;
			$ret['covers'][] = $res['posters']['detailed'];

			die(json_encode($ret));
		}

		if (@$_d['q'][1] == 'scrape')
		{
			$path = Server::GetVar('path');
			$id = Server::GetVar('id');

			# Collect remote data
			
			$data = json_decode(self::Details($id), true);

			# Pull our local copy.

			$q['fs_path'] = $path;
			$item = $_d['entry.ds']->findOne($q);

			# Update our local copy
			$item['details'][self::$Name] = $data;

			# Save our local copy.
			$_d['entry.ds']->save($item);

			die(json_encode($item));
		}
	}

	# Scraper Related
	public static $Link = 'http://www.rottentomatoes.com';
	public static $Icon = 'modules/det_rt/icon.png';

	static function GetName() { return 'Rotten Tomatoes'; }

	static function Find($title)
	{
		# Collect data.
		if (!file_exists('det_rt.txt'))
			file_put_contents('det_rt.txt',
				file_get_contents(det_rt_find.rawurlencode($title)));
		$dat = json_decode(file_get_contents('det_rt.txt'), true);

		# Prepare results.
		$ret = array();
		foreach ($dat['movies'] as $m)
		{
			$ret[$m['id']] = array(
				'title' => $m['title'],
				'date' => $m['year'],
				'cover' => $m['posters']['detailed']);
		}

		return $ret;
	}

	static function Details($id)
	{
		# Collect Information
		if (!file_exists('det_rt_info.txt'))
			file_put_contents('det_rt_info.txt',
				file_get_contents(det_rt_info.$id.'.json?apikey='.det_rt_key));
		return file_get_contents('det_rt_info.txt');
	}
}

Module::Register('RottenTomatoes');
Scrape::RegisterScraper('RottenTomatoes');

?>
