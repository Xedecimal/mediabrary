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

		$_d['movie.cb.query']['columns']['details.TMDB.id'] = 1;
		$_d['movie.cb.query']['columns']['details.TMDB.name'] = 1;
		$_d['movie.cb.query']['columns']['details.TMDB.url'] = 1;
		$_d['movie.cb.query']['columns']['details.TMDB.imdb_id'] = 1;
		$_d['movie.cb.query']['columns']['details.TMDB.released'] = 1;
		$_d['movie.cb.query']['columns']['details.TMDB.certification'] = 1;
		$_d['movie.cb.check'][] = array($this, 'movie_cb_check');

		$_d['movie.cb.query']['order']['details.TMDB.score'] = -1;

		$_d['filter.cb.filters']['tmdb'] = array(&$this, 'filter_cb_filters');
	}

	function Prepare()
	{
		# Precompile stats

		/*$map = <<<EOF
function () {
	var e = {
		voteAvg: 0,
		rateAvg: 0,
		votes: this.details.TMDB ? parseInt(this.details.TMDB.votes) : 0,
		rating: this.details.TMDB ? parseFloat(this.details.TMDB.rating) : 0
	}

	emit(0, e);
}
EOF;
		$reduce = <<<EOF
function (k, vs) {
	var ix = 0;
	var ravg = 0;
	var vavg = 0;

	vs.forEach(function (v) {
		ravg = (ravg + v.rating) / 2;
		vavg = (vavg + v.votes) / 2;
	});

	return {
		rateAvg: ravg,
		voteAvg: vavg,
		votes: vs.votes,
		rating: vs.rating
	};
}
EOF;

		global $_d;

		$_d['db']->command(array(
			'mapreduce' => 'entry',
			'out' => 'tmdbcache',
			'map' => new MongoCode($map),
			'reduce' => new MongoCode($reduce),
		));

		$obj = $_d['db']->tmdbcache->findOne();
		$ra = $obj['value']['rateAvg'];
		$va = $obj['value']['voteAvg'];

		$ds = $_d['entry.ds'];
		$all = $ds->find();
		foreach ($all as $ent)
		{
			if (empty($ent['details']['TMDB'])) continue;

			$v = $ent['details']['TMDB']['votes'];
			$r = $ent['details']['TMDB']['rating'];
			$ent['details']['TMDB']['score'] = (($va * $ra) + ($v * $r) / ($va + $v));
			#$ds->save($ent);
		}*/

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

			die(TMDB::Find($path, $title));
		}
		/*else if (@$_d['q'][1] == 'scrape')
		{
			$p = Server::GetVar('target');
			$movie = Movie::GetMovie($p);

			if (empty($movie['title'])) $movie['title'] = $movie['fs_title'];
			if (empty($movie['date'])) $movie['date'] = @$movie['fs_date'];

			# Fast scrape doesn't come with tmdbid.
			if (Server::GetVar('fast') == 1)
			{
				$title = MediaLibrary::SearchTitle($movie['title']).' '
					.@$movie['date'];
				$sx_movies = TMDB::FindXML($title);
				if (empty($sx_movies)) die('Found nothing for "'.$title.'"');
				$tmdbid = (string)$sx_movies[0]->id;
			}
			else $tmdbid = Server::GetVar('tmdb_id');

			# Do the actual scrape.
			$movie = TMDB::Scrape($movie, $tmdbid);

			# Collect updated information.
			$media = Movie::GetMedia('movie', $movie,
				Module::P('movie/img/missing.jpg'));

			if (empty($movie)) die(json_encode(array('error' => 'Not found',
				'fs_path' => Server::GetVar('target'))));

			# Run all module callbacks
			$movie = U::RunCallbacks($_d['tmdb.cb.postscrape'], $movie);

			# Update the database
			$res = $_d['db']->command(array('findAndModify' => 'entry',
				'query' => array('title' => $movie['title'], 'date' => $movie['date']),
				'update' => $movie, 'new' => 1, 'upsert' => 1));

			if (Server::GetVar('fast') == 1) die('Fixed!');
			die(json_encode($movie + $media));
		}*/
		else if (@$_d['q'][1] == 'remove')
		{
			$_d['entry.ds']->remove(array('_id' => new MongoID(Server::GetVar('id'))));
			die();
		}
		/*else if (@$_d['q'][1] == 'covers')
		{
			$id = $_d['q'][2];

			$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));

			if (empty($item) || empty($item['tmdbid']))
				die("This movie doesn't seem fully scraped.");

			$covers = TMDB::GetCovers($item['tmdbid']);
			$ret = '';
			foreach ($covers as $ix => $c)
				$ret .= '<a href="'.$id.'" class="tmdb-aCover"><img src="'.$c.'" /></a>';
			die($ret);
		}*/
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
		# Check for TMDB metadata.

		if (empty($md->Data['details']['TMDB'])
		|| empty($md->Data['details']['TMDB']['released']))
		{
			$p = $md->Path;
			$uep = rawurlencode($p);
			$msgs['TMDB/Metadata'][] = <<<EOF
<a href="scrape/scrape?path=$uep"
	class="a-fix">Scrape</a> File {$p} has no TMDB metadata.
EOF;
			return 1;
		}

		$errors = 0;

		# Check for certification.

		if (empty($md->Data['details']['TMDB']['certification']))
		{
			$uep = urlencode($md->Path);
			$url = "{{app_abs}}/scrape/scrape?path={$uep}";
			$tmdburl = $md->Data['details']['TMDB']['url'];
			$imdbid = $md->Data['details']['TMDB']['imdb_id'];

			$msgs['TMDB/Certification'][] = <<<EOD
<a href="{$url}" class="a-fix">Scrape</a> No certification for {$md->Title}
- <a href="{$tmdburl}" target="_blank">TMDB</a>
- <a href="http://www.imdb.com/title/{$imdbid}" target="_blank">IMDB</a>
EOD;
		}

		# Check filename compliance.

		$filetitle = Movie::CleanTitleForFile($md->Data['details']['TMDB']['name']);

		$date = substr($md->Data['details']['TMDB']['released'], 0, 4);
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
			$urlunfix = "tmdb/remove?id={$md->Data['_id']}";
			$bn = basename($file);

			$tmdburl = $md->Data['details']['TMDB']['url'];
			$imdbid = $md->Data['details']['TMDB']['imdb_id'];

			$msgs['TMDB/Filename Compliance'][] = <<<EOD
<a href="{$urlfix}" class="a-fix">Fix</a>
<a href="{$urlunfix}" class="a-nogo">Unscrape</a>
File "$bn" should be "$target".
- <a href="{$tmdburl}" target="_blank">TMDB</a>
- <a href="http://www.imdb.com/title/{$imdbid}" target="_blank">IMDB</a>
EOD;
		}

		# Check for cover.

		global $_d;

		if (empty($md->Image))
		{
			$urlunfix = "tmdb/remove?id={$md->Data['_id']}";
			$msgs['TMDB/Media'][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing cover for {$md->Path}
- <a href="http://www.themoviedb.org/movie/{$md->Data['details']['TMDB']['id']}"
target="_blank">Reference</a>
EOD;
		}

		/*if (!file_exists("$mp/bd_$next"))
		{
				$urlunfix = "tmdb/remove?id={$md['_id']}";
				$ret['Media'][] = <<<EOD
<a href="$urlunfix" class="a-nogo">Unscrape</a> Missing backdrop for {$md['fs_path']}
EOD;
		}*/
		return $errors;
	}

	function cb_search_query($q)
	{
		$ret['$or'][]['details.TMDB.keywords.keyword.@attributes.name'] =
			new MongoRegex("/$q/i");
		$ret['$or'][]['details.TMDB.name'] = new MongoRegex("/$q/i");
		return $ret;
	}

	function filter_cb_filters()
	{
		global $_d;

		$cols = array('details.TMDB.categories.category.@attributes.name' => 1);

		foreach ($_d['entry.ds']->find(array(), $cols) as $i)
		{
			if (!empty($i['details']['TMDB']['categories']['category']))
			foreach ($i['details']['TMDB']['categories']['category'] as $c)
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
			$ret['TMDB/Categories/'.$i['cat_name']] = array('href' =>
				'{"details.TMDB.categories.category.@attributes.name": {"$all": ["'.
				$i['cat_name'].'"]}}', 'style' => "font-size: {$sizes[$i['cat_name']]}px");
		}

		$ret['TMDB/Missing'] = '{"details.TMDB": null}';
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

	function Find($path)
	{
		global $_d;

		$item = $_d['entry.ds']->findOne(array('path' => $path));
		if (!empty($item['details']['TMDB']['name']))
			$title = $item['details']['TMDB']['name'];
		else if (!empty($item['title']))
			$title = $item['title'];
		else
		{
			$fs = MediaLibrary::ScrapeFS($path, Movie::GetFSPregs());
			var_dump($fs);
		}

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

	function Details($id)
	{
		return file_get_contents(TMDB_INFO.$id);
	}

	function Scrape($item, $id = null)
	{
		if ($id == null)
		{
			$keys = array_keys(TMDB::Find(MediaLibrary::SearchTitle($item['title']), @$item['released']));
			if (empty($keys)) return $item;
			$id = $keys[0];
		}
		# Collect remote data
		$data = Arr::FromXML(self::Details($id));
		$item['details'][$this->Name] = $data['movies']['movie'];

		global $_d;

		$obj = $_d['db']->tmdbcache->findOne();
		$ra = $obj['value']['rateAvg'];
		$va = $obj['value']['voteAvg'];

		$v = $item['details'][$this->Name]['votes'];
		$r = $item['details'][$this->Name]['rating'];

		# Badass algorithm here.
		$item['details'][$this->Name]['score'] =
			(($va * $ra) + ($v * $r) / ($va + $v));

		return $item;
	}

	function GetDetails($details, $item)
	{
		if (!isset($item->Data['details'][$this->Name])) return $details;

		$td = &$item->Data['details'][$this->Name];

		$ret = "TMDB/URL: ".'<a href="'.
			$td['url'].'" target="_blank">Visit</a>';

		return $ret;

		$trailer = $td['trailer'];
		if (!empty($trailer))
		{
			preg_match('/\?v=([^&]+)/', $trailer, $m);
			$v = $m[1];
			$details['Trailer'] = <<<EOF
<object width="580" height="360"><param name="movie" value="http://www.youtube.com/v/$v&amp;hl=en_US&amp;fs=1?color1=0x3a3a3a&amp;color2=0x999999&amp;hd=1&amp;border=1"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/$v&amp;hl=en_US&amp;fs=1?color1=0x3a3a3a&amp;color2=0x999999&amp;hd=1&amp;border=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="580" height="360"></embed></object>
EOF;
		}
		$details['TMDB/Oveview'] = $td['overview'];
		$details['TMDB/Score'] = $td['votes'].' votes to '.$td['rating']
			.' rating scores '.$td['score'];

		return $details;
	}
}

Module::Register('TMDB');
Scrape::Reg('movie', 'TMDB');

?>
