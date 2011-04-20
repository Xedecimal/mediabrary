<?php

if (empty($_d['scraper.scrapers'])) $_d['scrape.scrapers'] = array();

class Scrape extends Module
{
	function Link()
	{
		global $_d;

		$_d['movie.cb.buttons'][] = array(&$this, 'movie_cb_buttons');
	}

	function Prepare()
	{
		global $_d;

		# Collecting a list of possibilities.
		if (@$_d['q'][1] == 'find')
		{
			$t = new Template;
			$t->ReWrite('scraper', array(&$this, 'TagFindScraper'));
			die($t->ParseFile(Module::L('scrape/find.xml')));
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
		$ret = '<a href="{{fs_path}}" id="a-scrape-find"><img src="img/find.png"
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
		$res = $s::Find(Server::GetVar('title'));
		return VarParser::Concat($g, $res);
	}

	static function RegisterScraper($class)
	{
		global $_d;
		$_d['scrape.scrapers'][$class] = $class;
	}
}

Module::Register('Scrape');

?>
