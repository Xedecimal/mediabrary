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

	function Link()
	{
		global $_d;

		$_d['movie.cb.query']['columns']["details.{$this->Name}.id"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.year"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.title"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.links.alternate"] = 1;

		$_d['movie.cb.move'][$this->Name] = array($this, 'cb_movie_move');
		$_d['movie.cb.check'][$this->Name] = array($this, 'cb_movie_check');
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

	function Get()
	{
		global $_d;

		$ret['head'] = '<script type="text/javascript" src="modules/det_rt/rt_check.js"></script>';

		if (@$_d['q'][0] == 'check') return $ret;
	}

	# Scraper implementation

	public $Link = 'http://www.rottentomatoes.com';
	public $Icon = 'modules/det_rt/icon.png';

	function CanAuto() { return false; }

	function GetName() { return 'Rotten Tomatoes'; }

	function Find($path, $title)
	{
		global $_d;

		$md = new MovieEntry($path, MovieEntry::GetFSPregs());
		$item = $_d['entry.ds']->findOne(array('path' => $path));

		if (empty($title)) $title = $md->Title;

		if (empty($title) && !empty($item['title'])) $title = $item['title'];
		else if (empty($title))
			$fs = MediaEntry::ScrapeFS($path, MovieEntry::GetFSPregs());

		# Collect data.
		$ctx = stream_context_create(array('http' => array('timeout' => 3)));
		$dat = json_decode(file_get_contents(det_rt_find.rawurlencode($title),
			false, $ctx), true);

		# Prepare results.
		$ret = array();
		if (!empty($dat['movies']))
		foreach ($dat['movies'] as $m)
		{
			$ret[$m['id']] = array(
				'id' => $m['id'],
				'title' => $m['title'],
				'date' => $m['year'],
				'cover' => $m['posters']['detailed'],
				'ref' => $m['links']['alternate']);
		}

		return $ret;
	}

	function GetCovers($item) {}

	function Details($id)
	{
		$ctx = stream_context_create(array('http' => array('timeout' => 3)));
		return file_get_contents(det_rt_info.$id.'.json?apikey='.det_rt_key,
			false, $ctx);
	}

	function Scrape(&$me, $id = null)
	{
		# Auto scrape, try to find a good id.
		if (empty($id))
		{
			$me = new MediaEntry($item['path'], MovieEntry::GetFSPregs());
			$items = $this->Find($item['path'], $me->Title);
			$ids = array_keys($items);
			$id = $ids[0];
		}

		$json_data = self::Details($id);

		# Cache remote info.
		$cache_file = dirname($me->Path).'/.rt_cache.json';
		if (dirname($me->Path) != $me->Data['root'])
			file_put_contents($cache_file, $json_data);

		$me->Data['details'][$this->Name] = json_decode($json_data, true);

		$me->SaveDS();
	}

	function GetDetails($t, $g, $a)
	{
		if (!isset($item->Data['details'][$this->Name])) return;

		$cc = @$item->Data['details'][$this->Name]['critics_consensus'];
		if (!empty($cc)) return "Rotten Tomatoes: $cc";
	}

	# Callbacks

	function cb_movie_move($src_dir, $dst_dir)
	{
		$src_cache = $src_dir.'/.rt_cache.json';
		$dst_cache = $src_dir.'/.rt_cache.json';
		if (file_exists($src_cache)) rename($src_cache, $dst_cache);
	}

	function cb_movie_check(&$md)
	{
		if (empty($md->Data['title'])) return;

		# Check for RottenTomatoes metadata.

		# Do we have a cache?
		if (empty($md->Data['details'][$this->Name]))
		{
			$cache_file = dirname($md->Path).'/.rt_cache.json';
			if (file_exists($cache_file))
				$md->Data['details'][$this->Name] =
					json_decode(file_get_contents($cache_file));
		}

		if (empty($md->Data['details'][$this->Name]))
		{
			if (!empty($md->Data['errors']['rt_meta'])) return;

			$p = $md->Path;
			$uep = rawurlencode($p);
			# @TODO: Clean up ziss mess!
			$st = MediaLibrary::SearchTitle($md->Data['title']);
			$results = $this->Find($md->Path, $st);
			foreach ($results as $ix => $r)
			{
				# Year may be off by one.
				if ($r['date'] < $md->Data['released']-1
					|| $r['date'] > $md->Data['released']+1) unset($results[$ix]);
				else if (strtolower($r['title']) == strtolower($st)) { $results = array($ix => $r); break; }
			}
			$result = array_keys($results);
			if (count($result) == 1) $this->Scrape($md, $result[0]);
			else
			{
				#var_dump("ST ({$st}) Date ({$md->Data['released']})");
				#var_dump($results);
				$err = array(
					'source' => $this->Name,
					'type' => 'rt_meta',
					'msg' => "Cannot locate metadata for this entry.");
				$md->Data['errors']['rt_meta'] = $err;
				$md->SaveDS();
				throw new CheckException("File {$p} has no {$this->Name} metadata.", 'rt_meta', $this->Name);
			}
		}

		# Check filename compliance.

		$filetitle = Movie::CleanTitleForFile($md->Data['details'][$this->Name]['title']);

		$date = $md->Data['details'][$this->Name]['year'];
		$file = $md->Path;
		$ext = File::ext(basename($md->Path));

		# Part files need their CD#
		if (!empty($md->Data['part']))
		{
			$preg = '#/'.preg_quote($filetitle, '#').' \('.$date.'\) CD'
				.$file['part'].'\.(\S+)$#';
			$target = "$filetitle ($date) CD{$md['part']}.$ext";
		}
		else
		{
			$preg = '#/'.preg_quote($filetitle, '#').' \('.$date.'\)\.(\S+)$#';
			$target = "$filetitle ($date).$ext";
		}

		if (!preg_match($preg, $file))
		{
			$urlfix = "movie/rename?path=".urlencode($file);
			$urlfix .= '&amp;target='.dirname($file).'/'.urlencode($target);
			$urlunfix = "{$this->Name}/remove?id={$md->Data['_id']}";
			$bn = basename($file);

			$url = $md->Data['details'][$this->Name]['links']['alternate'];

			$msgs['Filename Compliance'][] = <<<EOD
<a href="{$urlfix}" class="a-fix">Fix</a>
<a href="{$urlunfix}" class="a-nogo">Unscrape</a>
File "$bn" should be "$target".
- <a href="{$url}" target="_blank">{$this->Name}</a>
EOD;
		}
	}
}

Module::Register('RottenTomatoes');
Scrape::Reg('movie', 'RottenTomatoes');

?>
