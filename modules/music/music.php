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

	function Check(&$msgs)
	{
		global $_d;

		$fs = Music::CollectFS();
		$ds = Music::CollectDS();

		foreach ($fs as $p => $e)
		{
			if (empty($ds[$p]))
			{
				$msgs['Music/Metadata'][] = "Adding missing metadata on {$p}";

				$_d['entry.ds']->save($e->Data, array('safe' => 1));
			}
		}
	}

	static function CollectFS()
	{
		global $_d;

		$ret = array();
		foreach ($_d['config']['paths']['music'] as $p)
		foreach (new FilesystemIterator($p, FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (!$f->isDir()) continue;

			$ae = new ArtistEntry($f->GetPathname());
			$ret[$ae->Path] = $ae;
			$ret += $ae->CollectFS($f);
		}

		return $ret;
	}

	static function CollectDS()
	{
		global $_d;

		$cr = $_d['entry.ds']->find(array('$or' => array(
			array('type' => 'music-artist'),
			array('type' => 'music-album'),
			array('type' => 'music-track')
		)));

		foreach ($cr as $i)
		{
			$ret[$i['path']] = $i;
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
		parent::__construct($path);

		$this->Data['type'] = 'music-artist';
		$this->Data['parent'] = 'Music';
	}

	function CollectFS($path)
	{
		# Collect Albums

		$ret = array();
		foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (!$f->isDir()) continue;

			$ae = new AlbumEntry($f->getPathname());
			$ae->Data['parent'] = $this->Title;
			$ret[$ae->Path] = $ae;
			$ret += $ae->CollectFS($ae->Path);
		}

		return $ret;
	}

	function CollectDS()
	{
	}
}

class AlbumEntry extends MediaEntry
{
	function __construct($path)
	{
		parent::__construct($path);

		$this->Data['type'] = 'music-album';
	}

	function CollectFS($path)
	{
		$ret = array();
		foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $f)
		{
			$te = new TrackEntry($f->getPathname());
			$te->Data['parent'] = $this->Title;
			$ret[$te->Path] = $te;
		}
		return $ret;
	}
}

class TrackEntry extends MediaEntry
{
	function __construct($path)
	{
		parent::__construct($path);

		$this->Data['type'] = 'music-track';
	}
}

Module::Register('Music');

?>
