<?php

class Music extends MediaLibrary
{
	public $Name = 'music';
	public $Names = array('music', 'music-artist', 'music-album', 'music-track');

	function __construct($path)
	{
		parent::__construct($path);
		$this->CheckActive($this->Names);

		global $_d;

		$_d['entry-types']['music-artist'] = array('text' => 'Music Artist',
			'icon' => '<img src="'.Module::P('music/img/music-artist.png').'" />');
		$_d['entry-types']['music-album'] = array('text' => 'Music Album',
			'icon' => '<img src="'.Module::P('music/img/music-album.png').'" />');
		$_d['entry-types']['music-track'] = array('text' => 'Music Track',
			'icon' => '<img src="'.Module::P('music/img/music-track.png').'" />');

		# No music artists configured, we're done here.
		if (empty($_d['config']['paths']['music-artist'])) return;

		$this->_missing_image = 'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].
			'/modules/music/img/missing.jpg';
	}

	function Link()
	{
		global $_d;

		$_d['nav.links']['Media/Music'] = '{{app_abs}}/music/grid';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'detail')
		{
			$ae = ArtistEntry::FromID($_d['q'][2]);
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
			$this->_items = ArtistEntry::CollectDS();
			$this->_template = Module::L('music/item-grid.xml');
			die(parent::Get());
		}

		$this->_template = Module::L('music/music.xml');
		$t = new Template();
		return $t->ParseFile($this->_template);
	}

	function Check()
	{
		global $_d;

		$fs = Music::CollectFS();
		$ds = Music::CollectDS();

		foreach ($fs as $p => $e)
		{
			if (empty($ds[$p]))
			{
				$_d['entry.ds']->save($e->Data, array('safe' => 1));
				ModCheck::Out("Added missing metadata on {$p}");
			}

			$e->Check();
		}
	}

	static function CollectFS()
	{
		global $_d;

		$ret = array();

		# No music paths configured, we're done here.
		if (empty($_d['config']['paths']['music'])) return $ret;

		foreach ($_d['config']['paths']['music'] as $p)
		foreach (new FilesystemIterator($p, FilesystemIterator::SKIP_DOTS) as $f)
		{
			if (!$f->isDir()) continue;

			$ae = new ArtistEntry($f->GetPathname());
			$ret[$ae->Path] = $ae;
			//$ret += $ae->CollectFS($f);
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

		$ret = array();
		foreach ($cr as $i) $ret[$i['path']] = $i;
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

Module::Register('Music');

class ArtistEntry extends MediaEntry
{
	public $Type = 'music-artist';
	public $Albums = array();

	function __construct($path)
	{
		global $_d;

		parent::__construct($path);

		$this->Data['type'] = 'music-artist';
		$this->Data['parent'] = 'Music';

		$thm = $this->Path.'/cover.jpg';
		if (file_exists($thm)) $this->Image = $_d['app_abs'].'/cover?path='.urlencode($thm);
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

	static function CollectDS()
	{
		global $_d;

		$cr = $_d['entry.ds']->find(array('type' => 'music-artist'));

		$ret = array();
		foreach ($cr as $i)
		{
			$ae = new ArtistEntry($i['path']);
			$ae->Data = $i;
			$ret[$i['path']] = $ae;
		}
		return $ret;
	}

	function Check()
	{
		global $_d;

		if (!empty($_d['music.cb.check.artist']))
		foreach ($_d['music.cb.check.artist'] as $cb)
			call_user_func_array($cb, array(&$this));
	}

	# MediaEntry
	function SaveCover($url)
	{
		file_put_contents($this->Data['path'].'/cover.jpg', file_get_contents($url));
	}
}

MediaEntry::RegisterType('music-artist', 'ArtistEntry');

class AlbumEntry extends MediaEntry
{
	public $Type = 'music-album';

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

MediaEntry::RegisterType('music-album', 'AlbumEntry');

class TrackEntry extends MediaEntry
{
	public $Type = 'music-track';

	function __construct($path)
	{
		parent::__construct($path);

		$this->Data['type'] = 'music-track';
	}
}

MediaEntry::RegisterType('music-trac', 'TrackEntry');

?>
