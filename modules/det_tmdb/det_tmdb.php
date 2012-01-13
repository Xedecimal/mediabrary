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
		$_d['movie.cb.move'][$this->Name] = array($this, 'movie_cb_move');

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

		if (@$_d['q'][1] == 'fix')
		{
			$type = $_d['q'][2];
			$me = MovieEntry::FromID($_d['q'][3], 'MovieEntry');
			if ($type == 'tmdb_bad_filename')
			{
				# This is no longer available.
				if (!file_exists($me->Data['path'])) $me->Remove();
				else if ($me->Rename($me->Data['errors'][$type]['to']))
				{
					unset($me->Data['errors'][$type]);
					$me->SaveDS();
					die(json_encode(array('result' => 'success')));
				}
			}
			die();
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

	function movie_cb_move($src_dir, $dst_dir)
	{
		$src_cache = $src_dir.'/.tmdb_cache.json';
		$dst_cache = $src_dir.'/.tmdb_cache.json';
		if (file_exists($src_cache)) rename($src_cache, $dst_cache);
	}

	function movie_cb_check(&$md)
	{
		# Check for metadata.

		if (empty($md->Data['title'])) return;

		# Do we have a cache?
		if (empty($md->Data['details'][$this->Name]))
		{
			$cache_file = dirname($md->Path).'/.tmdb_cache.json';
			if (file_exists($cache_file))
				$md->Data['details'][$this->Name] =
					$this->Cleanup(json_decode(file_get_contents($cache_file)));
		}

		if (empty($md->Data['details'][$this->Name]))
		{
			if (!empty($md->Data['errors']['tmdb_meta'])) return;

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
				$err = array(
					'source' => $this->Name,
					'type' => 'tmdb_meta',
					'msg' => "Cannot locate metadata for this entry.");
				$md->Data['errors']['tmdb_meta'] = $err;
				$md->SaveDS();

				throw new CheckException("File {$md->Path} has no {$this->Name} metadata.",
					'tmdb_metadata', $this->Name);
			}
		}

		# Check for certification.

		if (empty($md->Data['details'][$this->Name]['certification']))
		{
			$uep = urlencode($md->Data['path']);
			$url = "{{app_abs}}/scrape/scrape?path={$uep}";
			$surl = $md->Data['details'][$this->Name]['url'];
			$imdbid = $md->Data['details'][$this->Name]['imdb_id'];

			$msgs["{$this->Name}/Certification"][] = <<<EOD
<a href="{$url}" class="a-fix">Scrape</a> No certification for {$md->Title}
- <a href="{$surl}" target="_blank">{$this->Name}</a>
- <a href="http://www.imdb.com/title/{$imdbid}" target="_blank">IMDB</a>
EOD;
		}

		# Check filename compliance.

		$filetitle = Movie::CleanTitleForFile($md->Data['details'][$this->Name]['name']);

		if (!empty($md->Data['details'][$this->Name]['released']))
			$date = substr($md->Data['details'][$this->Name]['released'], 0, 4);
		else $date = '';

		$file = $md->Data['path'];
		$ext = File::ext(basename($file));

		$pqr = preg_quote($md->Root);

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

		if (!preg_match($preg, $file))
		{
			$urlfix = "movie/rename?path=".urlencode($file);
			$urlfix .= '&amp;target='.urlencode($md->Root.'/'.$target);
			$urlunfix = $this->Name."/remove?id={$md->Data['_id']}";
			$bn = basename($file);

			$tmdburl = $md->Data['details'][$this->Name]['url'];
			$imdbid = $md->Data['details'][$this->Name]['imdb_id'];

			$fulltarget = $md->Root.'/'.$target;

			$err = array(
				'source' => $this->Name,
				'type' => 'tmdb_bad_filename',
				'from' => $file,
				'to' => $fulltarget,
				'msg' => "File '$file' should be '$fulltarget'");
			$md->Data['errors'][$err['type']] = $err;

			$md->SaveDS();
			throw new CheckException($err['msg'], $err['type'], $this->Name);
		}

		# Check for cover.

		global $_d;

		if (empty($md->Image))
		{
			if (dirname($md->Path) == $md->Root)
				throw new CheckException("Can't write cover for {$md->Path}", 'tmdb_cover', $this->Name);

			if (empty($md->Data['details'][$this->Name]['images']['image']))
				throw new CheckException("Could not locate an image for {$md->Path}.", 'tmdb_image', $this->Name);
			$images = $md->Data['details'][$this->Name]['images']['image'];

			if (!empty($images))
			foreach ($images as $img)
			{
				$atrs = $img['@attributes'];
				$types[$atrs['type']][$atrs['size']][] = $atrs;
			}

			foreach ($types as $type => &$sizes)
				foreach ($sizes as $size => &$imgs)
					usort($imgs, function ($a, $b) {
						return ($a['width']+$a['height']) <
							($b['width']+$b['height']);
					});

			if (!empty($types['poster']['cover']))
			{
				$poster = file_get_contents($types['poster']['cover'][0]['url']);
				if (!empty($poster))
					file_put_contents(dirname($md->Path).'/folder.jpg',
						$poster);
			}
			else throw new CheckException("Cannot find a cover for {$md->Path}", 'tmdb_cover', $this->Name);
		}
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

		if (!empty($cats))
		$sizes = Math::RespectiveSize($cats);

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

	function Find($md, $title)
	{
		global $_d;

		if (empty($title)) $title = $md->Title;

		if (empty($title) && !empty($item['details'][$this->Name]['name']))
			$title = $item['details'][$this->Name]['name'];
		else if (empty($title) && !empty($item['title']))
			$title = $item['title'];
		else if (empty($title))
			$fs = MediaEntry::ScrapeFS($path, MovieEntry::GetFSPregs());

		$url = TMDB_FIND.rawurlencode($title);
		try { $xml = file_get_contents($url);}
		catch (Exception $ex)
		{
			die(json_encode(array('msg' => 'Exceptions work!')));
		}

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

	function GetCovers($item)
	{
		$ret = array();

		foreach ($item->Data['details'][$this->Name]['images']['image'] as $i)
			if ($i['@attributes']['type'] == 'poster')
				$ret[] = $i['@attributes']['url'];

		return $ret;
	}

	function Details($id)
	{
		$ctx = stream_context_create(array('http' => array('timeout' => 3)));
		return file_get_contents(TMDB_INFO.$id, false, $ctx);
	}

	function Scrape(&$me, $id = null)
	{
		if ($id == null)
		{
			$keys = array_keys($this->Find($me->Data['path']));
			if (empty($keys)) return $me;
			$id = $keys[0];
		}
		# Collect remote data
		$data = Arr::FromXML(self::Details($id));

		# Cache remote info.
		$cache_file = dirname($me->Path).'/.tmdb_cache.json';
		if (dirname($me->Path) != $me->Root)
			file_put_contents($cache_file, json_encode($data));

		$this->Cleanup($data);

		$me->Data['details'][$this->Name] = $data;

		# Try to set the release date on the movie.
		if (!empty($data['movies']['movie']['released']))
		if (preg_match('/(\d{4})/', $data['movies']['movie']['released'], $m))
			$me->Data['released'] = $m[1];

		$me->SaveDS();

		// @TODO: These values are bad, we need to fix this system.
		//$ra = $obj['value']['rateAvg'];
		//$va = $obj['value']['voteAvg'];
		//$v = $item['details'][$this->Name]['votes'];
		//$r = $item['details'][$this->Name]['rating'];

		# Badass algorithm here.
		//$item['details'][$this->Name]['score'] =
		//	(($va * $ra) + ($v * $r) / ($va + $v));

		return $me;
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
