<?php

require_once(dirname(__FILE__).'/../scrape/scrape.php');

define('TMDB_KEY', '263e2042d04c1989170721f79e675028');
define('TMDB_FIND', 'http://api.themoviedb.org/2.1/Movie.search/en/xml/'.TMDB_KEY.'/');
define('TMDB_INFO', 'http://api.themoviedb.org/2.1/Movie.getInfo/en/xml/'.TMDB_KEY.'/');

class TMDB extends Module implements Scraper
{
	public $Name = 'TMDB';
	public $Link = 'http://www.themoviedb.org/';
	public $Icon = 'modules/det_tmdb/icon.png';

	function __construct()
	{
		global $_d;

		$this->CheckActive($this->Name);
		$_d['search.cb.query'][] = array(&$this, 'cb_search_query');
	}

	# Module Extension

	function Link()
	{
		global $_d;

		$_d['movie.cb.query']['columns']["details.{$this->Name}.id"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.name"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.url"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.imdb_id"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.released"] = 1;
		$_d['movie.cb.query']['columns']["details.{$this->Name}.certification"] = 1;

		$_d['movie.cb.check'][$this->Name] = array($this, 'movie_cb_check');
		$_d['filter.cb.filters'][$this->Name] = array(&$this, 'filter_cb_filters');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');

			# Collect Information
			$xml = self::Details($id);
			$sx = simplexml_load_string($xml);
			$covers = $sx->xpath('//movies/movie/images/image[@size="cover"]');

			# Process Information
			$ret = array('id' => $this->Name);
			foreach ($covers as $c)
				$ret['covers'][] = (string)$c['url'];

			die(json_encode($ret));
		}
	}

	function Get()
	{
		global $_d;

		$r['head'] = '<script type="text/javascript" src="'.
			Module::P('det_tmdb/tmdb.js').'"></script>';

		if (@$_d['q'][1] == 'find2')
		{
			$title = MediaLibrary::SearchTitle(Server::GetVar('title'));
			$path = Server::GetVar('path');
			if (Server::GetVar('manual', 0) == 0
				&& preg_match('/.*\((\d+)\)\.\S{3}/', $path, $m))
				$title .= ' '.$m[1];

			die($this->Find($path, $title));
		}
		else if (@$_d['q'][1] == 'remove')
		{
			$_d['entry.ds']->remove(array('_id' => new MongoID(Server::GetVar('id'))));
			die();
		}
		else if (@$_d['q'][1] == 'cover')
		{
			$id = $_d['q'][2];
			$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
			$dst = $_d['config']['paths']['movie-meta'].'/thm_'
				.File::GetFile(basename($item['fs_path']));
			file_put_contents($dst, file_get_contents(Server::GetVar('image')));
			die(json_encode($item));
		}
		else if (@$_d['q'][1] == 'fixCover')
		{
			if ($this->FixCover($_POST['path'])) die('Fixed!');
		}

		return $r;
	}

	function movie_cb_check($md, &$msgs)
	{
		# Check for metadata.

		if (empty($md->Data['details'][$this->Name]))
		{
			$p = $md->Path;
			$uep = rawurlencode($p);
			$msgs["$this->Name/Metadata"][] = <<<EOF
<a href="scrape/scrape?type=movie&path=$uep"
	class="a-fix">Scrape</a> File {$p} has no {$this->Name} metadata.
EOF;
			return 1;
		}

		$errors = 0;

		# Check for certification.

		if (empty($md->Data['details'][$this->Name]['certification']))
		{
			$uep = urlencode($md->Path);
			$url = "{{app_abs}}/scrape/scrape?path={$uep}";
			$surl = $md->Data['details'][$this->Name]['url'];
			$imdbid = $md->Data['details'][$this->Name]['imdb_id'];

			$msgs["{$this->Name}/Certification"][] = <<<EOD
<a href="{$url}" class="a-fix">Scrape</a> No certification for {$md->Title}
- <a href="{$surl}" target="_blank">{$this->Name}</a>
- <a href="http://www.imdb.com/title/{$imdbid}" target="_blank">IMDB</a>
EOD;
			$errors++;
		}

		# Check filename compliance.

		$filetitle = Movie::CleanTitleForFile($md->Data['details'][$this->Name]['name']);

		if (!empty($md->Data['details'][$this->Name]['released']))
			$date = substr($md->Data['details'][$this->Name]['released'], 0, 4);
		else $date = '';

		$file = $md->Path;
		$ext = File::ext(basename($md->Path));

		$pqr = preg_quote($md->Root);

		# Part files need their CD#
		if (!empty($md->Data['part']))
		{
			$preg = '#^'.$pqr.'/'.preg_quote($filetitle, '#').'/'.preg_quote($filetitle, '#').' \('.$date.'\) CD'
				.$file['part'].'\.(\S+)$#';
			$target = "$filetitle ($date)/$filetitle ($date) CD{$md['part']}.$ext";
		}
		else
		{
			$preg = '#^'.$pqr.'/'.preg_quote($filetitle, '#').' \('.$date.'\)/'.preg_quote($filetitle, '#').' \('.$date.'\)\.(\S+)$#';
			$target = "$filetitle ($date)/$filetitle ($date).$ext";
		}

		if (!preg_match($preg, $file))
		{
			$urlfix = "movie/rename?path=".urlencode($file);
			$urlfix .= '&amp;target='.urlencode($md->Root.'/'.$target);
			$urlunfix = $this->Name."/remove?id={$md->Data['_id']}";
			$bn = basename($file);

			$tmdburl = $md->Data['details'][$this->Name]['url'];
			$imdbid = $md->Data['details'][$this->Name]['imdb_id'];

			$fulltarget = $md->Root.'/'.$target;

			$msgs['Filename Compliance/TMDB'][] = <<<EOD
<a href="{$urlfix}" class="a-fix">Fix</a>
<a href="{$urlunfix}" class="a-nogo">Unscrape</a>
File "$file" should be "$fulltarget".
- <a href="{$tmdburl}" target="_blank">{$this->Name}</a>
- <a href="http://www.imdb.com/title/{$imdbid}" target="_blank">IMDB</a>
EOD;
			$errors++;
		}

		# Check for cover.

		global $_d;

		if (empty($md->Image))
		{
			$urlunfix = $this->Name."/remove?id={$md->Data['_id']}";
			$msgs["{$this->Name}/Media"][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing cover for {$md->Path}
- <a href="http://www.themoviedb.org/movie/{$md->Data['details'][$this->Name]['id']}"
target="_blank">Reference</a>
EOD;
			$errors++;
		}

		return $errors;
	}

	function cb_search_query($q)
	{
		$ret['$or'][]["details.{$this->Name}.keywords.keyword.@attributes.name"] =
			new MongoRegex("/$q/i");
		$ret['$or'][]["details.{$this->Name}.name"] = new MongoRegex("/$q/i");
		return $ret;
	}

	function filter_cb_filters()
	{
		global $_d;

		$cols = array("details.{$this->Name}.categories.category.@attributes.name" => 1);

		foreach ($_d['entry.ds']->find(array(), $cols) as $i)
		{
			if (!empty($i['details'][$this->Name]['categories']['category']))
			foreach ($i['details'][$this->Name]['categories']['category'] as $c)
			if (isset($c['@attributes']))
			{
				$n = $c['@attributes']['name'];
				isset($cats[$n]) ? $cats[$n]++ : $cats[$n] = 0;
			}
		}

		$curcat = Server::GetVar('category');

		$sizes = Math::RespectiveSize($cats);

		foreach ($cats as $n => $c)
		{
			$d['cat_name'] = $n;
			$d['cat_size'] = $sizes[$n];
			$items[] = $d;
		}

		usort($items, function (&$a, &$b)
			{ return $a['cat_size'] < $b['cat_size']; });

		foreach ($items as $i)
		{
			# @TODO: This is a mess, clean it up.
			$ret["{$this->Name}/Categories/{$i['cat_name']}"] = array('href' =>
				'details.'.$this->Name.'.categories.category.@attributes.name": {"$all": ["'
				.$i['cat_name'].'"]}}',
				'style' => "font-size: {$sizes[$i['cat_name']]}px"
			);
		}

		$ret[$this->Name.'/Missing'] = '{"details.'.$this->Name.'": null}';
		return $ret;
	}

	# Scraper Implementation

	function GetName() { return 'The Movie DB'; }
	function CanAuto() { return true; }

	function FindXML($title)
	{
		$title = urlencode(trim($title));
		$xml = @file_get_contents(TMDB_FIND.$title);
		if (empty($xml)) return null;

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}

	function Find($path, $title)
	{
		global $_d;

		$md = new MovieEntry($path, MovieEntry::GetFSPregs());
		$item = $_d['entry.ds']->findOne(array('path' => $path));

		if (empty($title)) $title = $md->Title;

		if (empty($title) && !empty($item['details'][$this->Name]['name']))
			$title = $item['details'][$this->Name]['name'];
		else if (empty($title) && !empty($item['title']))
			$title = $item['title'];
		else if (empty($title))
			$fs = MediaEntry::ScrapeFS($path, MovieEntry::GetFSPregs());

		$url = TMDB_FIND.rawurlencode($title);
		if (!empty($date)) $url .= '+'.$date;
		$xml = file_get_contents($url);

		if (empty($xml)) return;

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		$ret = array();
		if (!empty($sx_movies))
		foreach ($sx_movies as $sx_movie)
		{
			$covers = array();
			foreach ($sx_movie->xpath('images/image[@size="cover"]') as $c)
				$covers[] = (string)$c['url'];

			$id = (string)$sx_movie->id;
			$ret[$id] = array(
				'id' => $id,
				'title' => $sx_movie->name,
				'date' => $sx_movie->released,
				'covers' => implode('|', $covers),
				'ref' => (string)$sx_movie->url
			);
		}

		return $ret;
	}

	function GetCovers($item) {}

	function Details($id)
	{
		return file_get_contents(TMDB_INFO.$id);
	}

	function Scrape(&$item, $id = null)
	{
		if ($id == null)
		{
			$keys = array_keys($this->Find($item['path']));
			if (empty($keys)) return $item;
			$id = $keys[0];
		}
		# Collect remote data
		$data = Arr::FromXML(self::Details($id));

		# @TODO: Some day do something with the cast maybe.
		unset($data['movies']['movie']['cast']);
		unset($data['movies']['movie']['images']);
		$item['details'][$this->Name] = $data['movies']['movie'];

		# Try to set the release date on the movie.
		if (!empty($data['movies']['movie']['released']))
		if (preg_match('/(\d{4})/', $data['movies']['movie']['released'], $m))
			$item['released'] = $m[1];

		global $_d;

		// @TODO: These values are bad, we need to fix this system.
		//$ra = $obj['value']['rateAvg'];
		//$va = $obj['value']['voteAvg'];
		//$v = $item['details'][$this->Name]['votes'];
		//$r = $item['details'][$this->Name]['rating'];

		# Badass algorithm here.
		//$item['details'][$this->Name]['score'] =
		//	(($va * $ra) + ($v * $r) / ($va + $v));

		return $item;
	}

	function GetDetails($t, $g, $a)
	{
		if (empty($t->vars['Data']['details'][$this->Name])) return;

		$td = &$t->vars['Data']['details'][$this->Name];

		$ret = array();

		if (!empty($td['url']))
		{
			$i['var'] = 'TMDB_URL';
			$i['val'] = '<a href="'.$td['url'].'" target="_blank">Visit</a>';
			$ret[] = $i;
		}

		if (!empty($td['trailer']))
		{
			preg_match('/\?v=([^&]+)/', $td['trailer'], $m);
			$v = $m[1];
			$i['var'] = 'TMDB_Trailer';
			$i['val'] = <<<EOF
<object width="640" height="360">
	<param name="movie" value="http://www.youtube.com/v/$v&hl=en_US&feature=player_embedded&version=3"></param>
	<param name="allowFullScreen" value="true"></param><param name="allowScriptAccess" value="always"></param>
	<embed src="http://www.youtube.com/v/$v&hl=en_US&feature=player_embedded&version=3" type="application/x-shockwave-flash" allowfullscreen="true" allowScriptAccess="always" width="640" height="360"></embed></object>
EOF;
			$ret[] = $i;
		}

		if (!empty($td['overview']))
		{
			$i['var'] = 'TMDB_Overview';
			$i['val'] = $td['overview'];
			$ret[] = $i;
		}

		/*if (!empty($td['votes']))
		{
			$i['var'] = 'TMDB_Votes';
			$i['val'] = $td['votes'].' votes to '.$td['rating']
				.' rating scores '.$td['score'];
			$ret[] = $i;
		}*/

		return VarParser::Concat($g, $ret);
	}
}

Module::Register('TMDB');
Scrape::Reg('movie', 'TMDB');

?>
