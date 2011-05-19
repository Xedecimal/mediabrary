<?php

if (empty($_d['scrape.scrapers'])) $_d['scrape.scrapers'] = array();

class Scrape extends Module
{
	function Link()
	{
		global $_d;

		$_d['movie.cb.buttons']['scrape'] = array(&$this, 'movie_cb_buttons');
		$_d['movie.cb.detail']['scrape'] = array(&$this, 'movie_cb_details');
	}

	function Prepare()
	{
		global $_d;

		$this->CheckActive('scrape');
		if (!$this->Active) return;

		# Collecting a list of possibilities.
		if (@$_d['q'][1] == 'find')
		{
			$t = new Template;
			$t->ReWrite('scraper', array(&$this, 'TagFindScraper'));
			$p = Server::GetVar('path');
			$item = new MovieEntry($p);

			$q['fs_path'] = $p;
			$item->Data = $_d['entry.ds']->findOne($q);

			$t->Set($item);
			die($t->ParseFile(Module::L('scrape/find.xml')));
		}

		if (@$_d['q'][1] == 'scrape')
		{
			$ids = Server::GetVar('ids');
			$path = Server::GetVar('path');

			# This is automated
			if (empty($ids))
			{
				$auto = true;
				$mov = Movie::GetMovie($path);
				foreach ($_d['scrape.scrapers'] as $s)
					if ($s::CanAuto()) $ids[$s] = null;
			}

			# Collect generic information
			$q['fs_path'] = $path;
			$item = new MovieEntry($path, Movie::GetFSPregs());
			$item->Data = $_d['entry.ds']->findOne($q);

			$item->Data['title'] = $item->Title;
			$item->Data['released'] = $item->Released;
			$item->Data['paths'] = $item->Paths;
			$item->Data['path'] = $item->Path;

			# Collect scraper information
			foreach ($ids as $sc => $ix) $item->Data = $sc::Scrape($item->Data, $ix);

			# Save details
			var_dump($item->Data);
			$_d['entry.ds']->save($item->Data, array('safe' => 1));

			#TODO: When auto-scraping, we need to store first cover.
			$auto = false;
			# Save cover
			if (!$auto)
			{
				$filename = basename($item->Filename, '.'.$item->Ext);
				$ct = "{$_d['config']['paths']['movie-meta']}/thm_{$filename}";
				file_put_contents($ct, file_get_contents(Server::GetVar('cover')));
			}

			die(json_encode($item));
		}
	}

	function Get()
	{
		$js = Module::P('scrape/scrape.js');
		$css = Module::P('scrape/scrape.css');
		$r['head'] = <<<EOF
<script type="text/javascript" src="$js"></script>
<link type="text/css" rel="stylesheet" href="$css" />
EOF;

		return $r;
	}

	# Callbacks

	function movie_cb_buttons($t)
	{
		$ret = '<a href="{{Path}}" id="a-scrape-find"><img src="img/find.png"
			alt="Find" /></a>';

		if (!empty($t->vars['date']))
		{
			$ret .= <<<EOF
<a href="{{_id}}" id="a-scrape-remove"><img src="img/database_delete.png"
	alt="Remove" /></a><a href="{{_id}}" id="a-scrape-covers"><img
	src="modules/movie/img/images.png" alt="Select New Cover" /></a><a
	href="http://www.themoviedb.org/movie/{{tmdbid}}" target="_blank"><img
	src="modules/tmdb/img/tmdb.png" alt="tmdb" /></a>
EOF;
		}
		return $ret;
	}

	function movie_cb_details($details, $item)
	{
		global $_d;

		foreach ($_d['scrape.scrapers'] as $s)
			$details = $s::GetDetails($details, $item);

		return $details;
	}

	# Tags

	function TagFindScraper($t, $g)
	{
		global $_d;

		$t->ReWrite('result', array(&$this, 'TagFindResult'));

		$ret = '';
		foreach ($_d['scrape.scrapers'] as $s)
		{
			$this->_scraper = $s;
			$t->Set('Name', $s::$Name);
			$t->Set('Link', $s::$Link);
			$t->Set('Icon', $s::$Icon);
			$ret .= $t->GetString($g);
		}

		return $ret;
	}

	function TagFindResult($t, $g)
	{
		$s = $this->_scraper;
		$res = $s::Find(Server::GetVar('title'), Server::GetVar('date'));
		return VarParser::Concat($g, $res);
	}

	# Static Tooling

	static function RegisterScraper($class)
	{
		global $_d;
		$_d['scrape.scrapers'][$class] = $class;
	}
}

interface Scraper
{
	static function GetName();
	static function CanAuto();
	static function Find($title, $date);
	static function Details($id);
	static function Scrape($item, $id = null);
	static function GetDetails($details, $item);
}

Module::Register('Scrape');

?>
