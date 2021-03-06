<?php

require_once(dirname(__FILE__).'/../scrape/scrape.php');

define('TMDB_FILE_CONF', '.tmdb.config.json');
define('TMDB_KEY', '263e2042d04c1989170721f79e675028');
define('TMDB_CONFIG', 'http://api.themoviedb.org/3/configuration');
define('TMDB_FIND', 'http://api.themoviedb.org/3/search/movie');
define('TMDB_INFO', 'http://api.themoviedb.org/3/movie/');

define('TMDB_MOVIE', 'http://www.themoviedb.org/movie/');

class TMDB extends Module implements Scraper
{
	public $Name = 'TMDB';
	public $Link = 'http://www.themoviedb.org';
	public $Icon = 'modules/det_tmdb/img/tmdb.png';

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
		# @TODO: This happens too often.
		#$_d['movie.cb.check_complete'][$this->Name] = array($this, 'movie_cb_check_complete');
		$_d['movie.cb.move'][$this->Name] = array(&$this, 'movie_cb_move');
		$_d['cb.detail.buttons'][$this->Name] = array(&$this, 'cb_detail_buttons');
		$_d['cb.detail.head'][$this->Name] = array(&$this, 'cb_detail_head');

		//$_d['filter.cb.filters'][$this->Name] = array(&$this, 'filter_cb_filters');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');

			# Collect Information

			/*$md = MediaEntry::FromID($id);
			$json = $this->GetCache($md->Path);*/

			$q['append_to_response'] = 'images';
			$dat = json_decode(TMDB::TMDBMovie($id, $q));

			foreach ($dat->images->posters as $img)
				$ret['covers'][] = TMDB::TMDBImageURL($img);

			die(json_encode($ret));
		}

		if (@$_d['q'][1] == 'backdrops')
		{
			$id = $_d['q'][2];

			$md = MediaEntry::FromID($id);
			$json = $this->GetCache($md->Path);

			if (isset($md->Data['details'][$this->Name]['backdrop']))
				$ret['backdrop-sel'] = $md->Data['details'][$this->Name]['backdrop'];
			else $ret['backdrop-sel'] = 0;

			foreach ($json['images']['image'] as $img)
			{
				if ($img['@attributes']['type'] == 'backdrop'
				&& $img['@attributes']['size'] == 'w1280')
					$ret['backdrops'][] = $img['@attributes']['url'];
			}

			die(json_encode($ret));
		}

		if (@$_d['q'][1] == 'fix')
		{
			$type = $_d['q'][2];
			$me = MovieEntry::FromID($_d['q'][3], 'MovieEntry');
			if ($type == 'tmdb_bad_filename')
			{
				$to = $me->Data['errors'][$type]['to'];
				# This is no longer available.
				if (!file_exists($me->Data['path'])) $me->Remove();
				else if ($me->Rename($to))
				{
					$q['_id'] = $me->Data['_id'];
					$u['$set']['path'] = $to;
					$u['$unset']['errors'][$type] = 1;
					$_d['entry.ds']->update($q, $u);
					die(json_encode(array('result' => 'success')));
				}
			}
			die();
		}
	}

	function Get()
	{
		global $_d;

		$js = Module::P('det_tmdb/tmdb.js');
		$r['js'] = '<script type="text/javascript" src="'.$js.'"></script>';

		if (@$_d['q'][0] == 'check')
		{
			$jsc = Module::P('det_tmdb/tmdb_check.js');
			$r['js'] .= '<script type="text/javascript" src="'.$jsc.'"></script>';
		}
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
		/*else if (@$_d['q'][1] == 'cover')
		{
			$id = $_d['q'][2];
			$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
			$dst = $_d['config']['paths']['movie-meta'].'/thm_'
				.File::GetFile(basename($item['fs_path']));
			file_put_contents($dst, file_get_contents(Server::GetVar('image')));
			die(json_encode($item));
		}*/
		else if (@$_d['q'][1] == 'fixCover')
		{
			if ($this->FixCover($_POST['path'])) die('Fixed!');
		}

		return $r;
	}

	function movie_cb_move($src, $dst_dir)
	{
		$src_dir = dirname($src);
		@rename($src_dir.'/.tmdb_cache.json', $dst_dir.'/.tmdb_cache.json');
		@rename($src.'.tmdb_cache.json', $dst_dir.'/.tmdb_cache.json');
	}

	function movie_cb_check(&$md)
	{
		# Check for metadata.

		$clean = true;

		if (empty($md->Data['title'])) return false;

		# Do we have a cache?
		if (empty($md->Data['details'][$this->Name]))
		{
			$cache_file = dirname($md->Path).'/.tmdb_cache.json';
			if (file_exists($cache_file))
			{
				$md->Data['details'][$this->Name] =
					json_decode(file_get_contents($cache_file), true);
				$this->Cleanup($md->Data['details'][$this->Name]);
			}
		}

		if (empty($md->Data['details'][$this->Name]))
		{
			$st = MediaLibrary::SearchTitle($md->Data['title']);
			$results = $this->Find($md->Data, $st);
			if (!empty($results))
			foreach ($results as $ix => $r)
			{
				# Year may be off by one.
				if (!empty($md->Data['released']) && ($r['date'] < $md->Data['released']-1
					|| $r['date'] > $md->Data['released']+1)) unset($results[$ix]);
				else if (strtolower(MediaLibrary::SearchTitle($r['title'])) ==
					strtolower($st))
				{
					$results = array($ix => $r);
					break;
				}
			}

			if (count($results) == 1)
			{
				$keys = array_keys($results);
				$this->Scrape($md, $keys[0]);
			}
			else
			{
				$url = "movie/detail/{$md->Data['_id']}?path={$md->Path}";
				echo "<p>Entry <a class=\"a-movie-item\" href=\"$url\">{$md->Path}</a> has no {$this->Name} metadata.</p>\r\n";
				flush();
				return false;
			}
		}

		# Check filename compliance.

		if (!empty($md->Data['details'][$this->Name]['name']))
			$filetitle = Movie::CleanTitleForFile($md->Data['details'][$this->Name]['name']);
		else
			$filetitle = Movie::CleanTitleForFile($md->Title);

		if (!empty($md->Data['details'][$this->Name]['release_date']))
			$date = substr($md->Data['details'][$this->Name]['release_date'], 0, 4);
		else $date = '';

		$file = $md->Data['path'];
		$ext = File::ext(basename($file));

		if (empty($md->Data['root'])) { var_dump($md->Root); }
		$pqr = preg_quote($md->Data['root']);

		# Part files need their CD#
		if (!empty($md->Data['part']))
		{
			$preg = '#^'.$pqr.'/'.preg_quote($filetitle, '#').'/'.preg_quote($filetitle, '#').' \('.$date.'\) CD'
				.$file['part'].'\.(\S+)$#';
			$target = "$filetitle ($date)/$filetitle ($date) CD{$md->Data['part']}.$ext";
		}
		else
		{
			$preg = '#^'.$pqr.'/'.preg_quote($filetitle, '#').' \('.$date.'\)/'.preg_quote($filetitle, '#').' \('.$date.'\)\.(\S+)$#';
			$target = "$filetitle ($date)/$filetitle ($date).$ext";
		}

		global $_d;

		if (!preg_match($preg, $file))
		{
			$urlfix = $_d['app_abs']."/movie/rename?path=".urlencode($file);
			$urlfix .= '&amp;target='.urlencode($md->Data['root'].'/'.$target);
			$urlunfix = $this->Name."/remove?id={$md->Data['_id']}";

			$fulltarget = $md->Data['root'].'/'.$target;

			$this->OutErr("File '$file' should be '$fulltarget'", $md, array(
				'Fix' => $urlfix,
				'Unfix' => $urlunfix
			));

			$clean = false;
		}

		# Check for cover.

		if (!$this->CheckCover($md)) $clean = false;

		# Set the score.

		/*if (!empty($md->Data['details'][$this->Name]['votes']))
			$md->Data['details'][$this->Name]['score'] =
				$this->GetScore($md->Data['details'][$this->Name]);*/

		return $clean;
	}

	function movie_cb_check_complete()
	{
		global $_d;

		# If something changed, we need to recalculate ALL scores!

		$avgs = $this->GetScoreAverages();

		$q['type'] = 'movie';
		$cols['title'] = 1;
		$cols['details.TMDB.score'] = 1;
		$cols['details.TMDB.rating'] = 1;
		$cols['details.TMDB.votes'] = 1;
		$col = $_d['entry.ds']->find($q, $cols);
		foreach ($col as $item)
		{
			if (empty($item['details'][$this->Name]['rating'])) continue;
			if (empty($item['details'][$this->Name]['votes'])) continue;

			$score = $this->GetScore(
				$item['details'][$this->Name]['rating'],
				$item['details'][$this->Name]['votes'],
				$avgs['ra'],
				$avgs['va']);

			if (!empty($item['details'][$this->Name]['score']))
				$os = $item['details'][$this->Name]['score'];

			if ($score != $os)
			{
				$qc['_id'] = $item['_id'];
				$ci = $_d['entry.ds']->findOne($qc);
				$ci['details'][$this->Name]['score'] = $score;
				$_d['entry.ds']->save($ci);
				ModCheck::Out('Updated score '.$ci['title']." from $os to $score");
			}
		}
	}

	function CheckCover(&$me)
	{
		if (empty($me->Image))
		{
			if (dirname($me->Path) == $me->Data['root'])
			{
				echo "<p>Can't write TMDB cover for {$me->Path} because it's in the root.</p>";
				return;
			}

			$cdat = $this->LoadCache($me->Path);

			if (empty($cdat['images']['image']))
			{
				global $_d;

				$urlunfix = "{$_d['app_abs']}/$this->Name/remove?id={$me->Data['_id']}";
				$butunfix = '<a href="'.$urlunfix.'" class="a-fix button">Unfix</a>';
				$this->OutErr("{$butunfix} Could not locate a cover for {$me->Path}.", $me);
				return false;
			}

			$images = $cdat['images']['image'];

			if (!empty($images))
			foreach ($images as $img)
			{
				$atrs = $img['@attributes'];
				$types[$atrs['type']][$atrs['size']][] = $atrs;
			}

			if (!empty($types['poster']['cover']))
			{
				$poster = file_get_contents($types['poster']['cover'][0]['url']);
				if (!empty($poster))
				{
					file_put_contents(dirname($me->Path).'/folder.jpg',
						$poster);

					return true;
				}
			}
			else
			{
				echo "<p>Cannot find a cover for {$me->Path}</p>";
				return false;
			}
		}
		return true;
	}

	function cb_search_query($q)
	{
		$ret['$or'][]["details.{$this->Name}.keywords.keyword.@attributes.name"] =
			new MongoRegex("/$q/i");
		$ret['$or'][]["details.{$this->Name}.name"] = new MongoRegex("/$q/i");
		return $ret;
	}

	function cb_detail_buttons($t, $g)
	{
		if (empty($t->vars['Data']['details'][$this->Name])) return;

		$td = $t->vars['Data']['details'][$this->Name];

		return '<a href="'.TMDB_MOVIE.$td['id'].'" target="_blank"><img src="'.Module::P($this->Icon).'" alt="'.$this->Name.'" /></a>';
	}

	function cb_detail_head($t, $g)
	{
		$css = Module::P('det_tmdb/tmdb_movie_details.css');
		$js = Module::P('det_tmdb/tmdb_movie_details.js');

		return '<link type="text/css" rel="stylesheet" href="'.$css.'" />'
			.'<script type="text/javascript" src="'.$js.'"></script>';
	}

	function filter_cb_filters()
	{
		global $_d;

		$cols = array("details.{$this->Name}.categories.category.@attributes.name" => 1);

		$cats = array();
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

		if (!empty($cats)) $sizes = Math::RespectiveSize($cats);

		$items = array();
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

	function CanAuto() { return true; }

	# This is meant to send a proper acceptable request to TMDB in leu of file_get_contents.

	static function TMDBQuery($url, $uri)
	{
		$opts['http']['header'] = 'Accept: application/json';
		$ctx = stream_context_create($opts);
		$uri = '?'.http_build_query($uri);
		return file_get_contents($url.$uri, false, $ctx);
	}

	static function TMDBFind($query, $year = null)
	{
		$vars['api_key'] = TMDB_KEY;
		$vars['query'] = $query;
		if (!empty($year)) $vars['year'] = $year;
		return TMDB::TMDBQuery(TMDB_FIND, $vars);
	}

	static function TMDBMovie($id, $append)
	{
		$append['api_key'] = TMDB_KEY;
		return TMDB::TMDBQuery(TMDB_INFO.$id, $append);
	}

	static function TMDBConfig()
	{
		$grab = function()
		{
			$data = json_decode(TMDB::TMDBQuery(TMDB_CONFIG, array('api_key' => TMDB_KEY)));
			$data->updated = time();
			file_put_contents(TMDB_FILE_CONF, json_encode($data));
			return $data;
		};

		# Grab a new configuration
		if (file_exists(TMDB_FILE_CONF))
			$conf = json_decode(file_get_contents(TMDB_FILE_CONF));
		else
			$conf = $grab();

		# Week
		if (time() - $conf->updated >= 604800) $conf = $grab();

		return $conf;
	}

	static function TMDBImageURL($img)
	{
		$config = TMDB::TMDBConfig();
		return $config->images->base_url.'w185'.$img->file_path;
	}

	function FindXML($title)
	{
		$title = urlencode(trim($title));
		$vars['key'] = TMDB_KEY;
		$vars['query'] = $title;
		$xml = @file_get_contents(VarParser::Parse(TMDB_FIND, $vars));
		if (empty($xml)) return null;

		$sx = simplexml_load_string($xml);
		$sx_movies = $sx->xpath('//movies/movie');

		return $sx_movies;
	}

	function Find(&$md, $title = null)
	{
		global $_d;

		if (empty($title)) $title = $md->Title;

		if (empty($title) && !empty($item['details'][$this->Name]['name']))
			$title = $item['details'][$this->Name]['name'];
		else if (empty($title) && !empty($item['title']))
			$title = $item['title'];
		else if (empty($title))
			$fs = MediaEntry::ScrapeFS($md->Path, MovieEntry::GetFSPregs());

		$json = $this->TMDBFind(rawurlencode($title));

		if (empty($json)) return;

		$data = json_decode($json);

		$ret = array();
		foreach ($data->results as $result)
		{
			$ret[$result->id] = array(
				'id' => $result->id,
				'title' => $result->title,
				'date' => $result->release_date,
				'covers' => $result->poster_path,
				'ref' => 'http://www.themoviedb.org/movie/'.$result->id
			);
		}

		return $ret;
	}

	function GetCovers($item)
	{
		$ret = array();

		$json = $this->GetCache($item->Path);

		foreach ($json['images']['image'] as $i)
			if ($i['@attributes']['type'] == 'poster' &&
				$i['@attributes']['size'] == 'cover')
				$ret[] = $i['@attributes']['url'];

		return $ret;
	}

	function GetCache($path)
	{
		return json_decode(file_get_contents(dirname($path).'/.tmdb_cache.json'), true);
	}

	function Details($id)
	{
		$q['api_key'] = TMDB_KEY;
		return TMDB::TMDBQuery(TMDB_INFO.$id, $q);
	}

	function Scrape(&$me, $id = null)
	{
		# Automatically scraping
		if ($id == null)
		{
			$keys = array_keys($this->Find($me->Data['path']));
			if (empty($keys)) return $me;
			$id = $keys[0];
		}

		# Collect remote data
		$data = json_decode(self::Details($id), true);

		# Cache remote info.
		if (dirname($me->Path) == $me->Data['root'])
			$cache_file = $me->Path.'.tmdb_cache.json';
		else
			$cache_file = dirname($me->Path).'/.tmdb_cache.json';

		file_put_contents($cache_file, json_encode($data));

		$this->Cleanup($data);

		$me->Data['details'][$this->Name] = $data;

		# Try to set the release date on the movie.
		if (!empty($data['release_date']))
		if (preg_match('/(\d{4})/', $data['release_date'], $m))
			$me->Data['release_date'] = (int)$m[1];

		$me->SaveDS();

		return $me;
	}

	private function Cleanup(&$data)
	{
		# Collect a detailed score for this item.
		#$data['score'] = $this->GetScore($data);
	}

	function GetDetails($t, $g, $a)
	{
		global $_d;

		if (empty($t->vars['Data']['details'][$this->Name])) return;

		$td = &$t->vars['Data']['details'][$this->Name];

		$ret = array();

		$ret[] = array(
			'var' => 'tmdb-cover',
			'val' => '<a href="'.$t->vars['Data']['_id'].'" class="a-scrape-covers"><img src="'.@$t->vars['Image'].'" alt="Cover" id="tmdb-cover-image" /></a>'
		);

		if (!empty($td['trailer']))
		{
			preg_match('/\?v=([^&]+)/', $td['trailer'], $m);
			$v = $m[1];
			$i['var'] = 'tmdb-trailer';
			$i['val'] = <<<EOF
<a href="#" id="tmdb-trailer-toggle">Trailer</a>
<div id="tmdb-trailer-video" style="display: none">
	<object width="640" height="360">
	<param name="movie" value="http://www.youtube.com/v/$v&hl=en_US&feature=player_embedded&version=3"></param>
	<param name="allowFullScreen" value="true"></param><param name="allowScriptAccess" value="always"></param>
	<embed src="http://www.youtube.com/v/$v&hl=en_US&feature=player_embedded&version=3" type="application/x-shockwave-flash" allowfullscreen="true" allowScriptAccess="always" width="640" height="360"></embed></object>
</div>
EOF;
			$ret[] = $i;
		}

		if (!empty($td['overview']))
		{
			$i['var'] = 'TMDB-Overview';
			$i['val'] = '<p>'.$td['overview'].'</p>';
			$ret[] = $i;
		}

		if (!empty($td['score']))
		{
			//$score = $this->GetScore($td);

			$i['var'] = 'TMDB_Votes';
			$i['val'] = $td['votes'].' votes to '.$td['rating']
				.' rating scores '.$td['score'];
			$ret[] = $i;
		}

		return VarParser::Concat($g, $ret);
	}

	private function OutErr($msg, $me, $links = array())
	{
		echo $msg;

		if (!empty($me->Data['details'][$this->Name]['url']))
			echo ' - <a class="button" href="'
				.$me->Data['details'][$this->Name]['url']
				.'" target="_blank">TMDB</a>';
		if (!empty($me->Data['details'][$this->Name]['imdb_id']))
			echo ' - <a class="button" href="http://www.imdb.com/title/'
				.$me->Data['details'][$this->Name]['imdb_id']
				.'" target="_blank">IMDB</a>';
		if (!empty($me->Data['_id']))
			echo ' - <a class="button a-movie-item" href="movie/detail/'
				.$me->Data['_id'].'?path='.$me->Path.'">Details</a>';
		foreach ($links as $t => $l)
		{
			echo ' - <a class="a-fix button" href="'.$l.'">'.$t.'</a>';
		}
		echo "</p>\r\n";
		flush();
	}

	private function LoadCache($path)
	{
		$cpath = dirname($path).'/.tmdb_cache.json';
		if (!file_exists($cpath))
			$cpath = $path.'.tmdb_cache.json';
		if (!file_exists($cpath))
			return false;

		return json_decode(file_get_contents($cpath), true);
	}

	private function GetScoreAverages()
	{
		global $_d;

		$keys['type'] = 1;
		$init['avgRate'] = 0;
		$init['avgVote'] = 0;
		$init['count'] = 0;
		$redu = new MongoCode('function (doc, out) {
			out.avgRate = ((out.avgRate * out.count) + parseFloat(doc.details.TMDB.rating)) / (out.count+1);
			out.avgVote = ((out.avgVote * out.count) + parseInt(doc.details.TMDB.votes)) / (out.count+1);
			out.count++;
		}');
		$opts['condition'] = array('details.TMDB.rating' => array('$exists' => 1));
		$res = $_d['entry.ds']->group($keys, $init, $redu, $opts);

		return array(
			'ra' => $res['retval'][0]['avgRate'],
			'va' => $res['retval'][0]['avgVote']
		);
	}

	private function GetScore($r, $v, $ra, $va)
	{
		return (($va * $ra) + ($v * $r) / ($va + $v));
	}
}

Module::Register('TMDB');
Scrape::Reg('movie', 'TMDB');
