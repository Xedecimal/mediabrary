<?php

class Music extends MediaLibrary
{
	public $Name = 'music';

	function __construct()
	{
		parent::__construct();
		$this->CheckActive($this->Name);

		global $_d;

		$this->_thumb_path = $_d['config']['paths']['music-artist']['meta'];
		$this->_missing_image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].
			'/modules/music/img/missing.jpg';
	}

	function Link()
	{
		global $_d;

		$_d['nav.links']['Media/Music/Grid'] = '{{app_abs}}/music/grid';
		$_d['nav.links']['Media/Music/List'] = '{{app_abs}}/music/list';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'detail')
		{
			$p = $_GET['path'];
			$ae = new ArtistEntry($p);
			$t = new Template();
			$t->Set($ae);
			die($t->ParseFile(Module::L('music/detail.xml')));
		}
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'items')
		{
			$this->_items = $this->CollectFS();
			$type = $_d['q'][2];
			$this->_template = Module::L('music/item-'.$type.'.xml');
			die(parent::Get());
		}

		$this->_template = Module::L('music/music.xml');
		$t = new Template();
		return $t->ParseFile($this->_template);
	}

	static function CollectFS()
	{
		global $_d;

		//$pregs = self::GetFSPregs();

		$ret = array();
		foreach ($_d['config']['paths']['music'] as $p)
		foreach (new FilesystemIterator($p, FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (!$f->isDir()) continue;
			$ret[] = new ArtistEntry($f->GetPathname());
		}
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
		global $_d;

		parent::__construct($path);

		# Collect Albums
		foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $f)
			$ret[] = new AlbumEntry($f->GetPathname());

		$ent = $_d['entry.ds']->findOne(array('path' => $path));
		if (!empty($ent)) $this->Data += $ent;
	}
}

class AlbumEntry extends MediaEntry
{
	public $Tracks = array();
}

class TrackEntry extends MediaEntry
{
}

Module::Register('Music');

?>
