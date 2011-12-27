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

		$title = MediaLibrary::SearchTitle($title);

		# Collect data.
		$dat = json_decode(file_get_contents(det_rt_find.rawurlencode($title)),
			true);

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
		# Collect Information
		return file_get_contents(det_rt_info.$id.'.json?apikey='.det_rt_key);
	}

	function Scrape(&$item, $id = null)
	{
		# Auto scrape, try to find a good id.
		if (empty($id))
		{
			$me = new MediaEntry($item['path'], MovieEntry::GetFSPregs());
			$items = $this->Find($item['path'], $me->Title);
			$ids = array_keys($items);
			$id = $ids[0];
		}
		$item['details'][$this->Name] = json_decode(self::Details($id), true);
		sleep(2);
	}

	function GetDetails($t, $g, $a)
	{
		if (!isset($item->Data['details'][$this->Name])) return;

		$cc = @$item->Data['details'][$this->Name]['critics_consensus'];
		if (!empty($cc)) return "Rotten Tomatoes: $cc";
	}


	# Callbacks

	function cb_movie_check($md, &$msgs)
	{
		$errors = 0;

		# Check for RottenTomatoes metadata.

		if (empty($md->Data['details'][$this->Name]))
		{
			$p = $md->Path;
			$uep = rawurlencode($p);
			$msgs['RottenTomatoes/Metadata'][] = <<<EOF
<a href="scrape/scrape?type=movie&scraper={$this->Name}&path=$uep"
	class="a-fix">Scrape</a> File {$p} has no {$this->Name} metadata.
EOF;
			return 1;
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
			$errors++;
		}
	}
}

Module::Register('RottenTomatoes');
Scrape::Reg('movie', 'RottenTomatoes');

?>
