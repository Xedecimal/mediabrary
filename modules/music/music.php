<?php

class ModMusic extends MediaLibrary
{
	function __construct()
	{
		parent::__construct();
		$this->CheckActive('music');
		
		global $_d;

		$this->_thumb_path = $_d['config']['paths']['music-meta'];
		$this->_missing_image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].
			'/modules/music/img/missing.jpg';
	}

	function Get()
	{
		global $_d;

		$r['head'] = '<link type="text/css" rel="stylesheet" href="modules/music/css.css" />';

		if (empty($_d['q'][0]))
		{
			$r['default'] = '<div id="divMainMusic" class="main-link"><a href="{{app_abs}}/music">Music</a></div>';
			return $r;
		}

		if (!$this->Active) return;

		$this->_items = ModMusic::CollectFS();

		if (@$_d['q'][1] == 'items')
		{
			$this->_template = Module::L('music/item.xml');
			die(parent::Get());
		}

		$this->_template = Module::L('music/template.xml');
		$t = new Template();
		$r['default'] = $t->ParseFile($this->_template);
		
		return $r;
	}
	
	static function CollectFS()
	{
		global $_d;
		
		//$pregs = self::GetFSPregs();

		$ret = array();
		foreach ($_d['config']['paths']['music'] as $p)
			foreach (new FilesystemIterator($p,
				FilesystemIterator::SKIP_DOTS) as $f)
				$ret[] = new ArtistEntry($f->GetPathname());
		return $ret;
	}

	static function GetFSPregs()
	{
		return array(
			# /path/<artist>/<date> - <album>/<artist> - <date> - <album> - <track> - <title>.<ext>
			'#/([^/]+)/(\d+) - ([^/]+)/[^-]+ - [^-]+ - [^-]+ - (\d+) - (.+)\.([^.]+)$#' => array(
				1 => 'fs_artist', 2 => 'fs_date', 3 => 'fs_album', 4 => 'fs_track', 5 => 'fs_title', 6 => 'fs_ext'),
			#Body Count/Body Count/11 The Winner Loses.mp3
			# /path/<artist>/<album>/<track> <title>.<ext>
			'#/([^/]+)/([^/]+)/(\d+) (.+)\.([^.]+)#' => array(
				1 => 'fs_artist', 2 => 'fs_album', 3 => 'fs_track', 4 => 'fs_title', 5 => 'fs_ext'),
		);
	}
}

class ArtistEntry extends MediaEntry
{
	public $Albums = array();
	
	function __construct($path)
	{
		parent::__construct($path);
	}
}

class AlbumEntry extends MediaEntry
{
	public $Tracks = array();
}

class TrackEntry extends MediaEntry
{
}

Module::Register('ModMusic');

?>
