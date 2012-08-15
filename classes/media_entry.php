<?php

class MediaEntry
{
	# Filesystem
	public $Path;
	public $Filename;

	# Metadata
	public $Title;

	public static $Types;

	function __construct($path, $parses = null)
	{
		$this->Path = $path;
		$this->Filename = basename($path);
		$this->Ext = File::ext($this->Filename);

		if (!empty($parses))
		{
			$mx = 0;
			foreach ($parses as $preg => $matches)
			{
				if (preg_match($preg, $path, $m))
				{
					foreach ($matches as $idx => $col) $this->$col = $m[$idx];
					$this->Data['preg_matched'] = $DebugMatched = $mx;
					break;
				}
				$mx++;
			}
			if (!isset($this->DebugMatched)) $this->FailedFSPreg = 1;
		}
		else $this->Title = $this->Filename;

		if (!empty($path)) $this->Data['path'] = utf8_encode($path);
		if (!empty($this->Title)) $this->Data['title'] = utf8_encode($this->Title);
		if (!empty($this->Type)) $this->Data['type'] = $this->Type;
	}

	function LoadDS()
	{
		global $_d;
		$this->Data = $_d['entry.ds']->findOne(array(
			'path' => utf8_encode($this->Path)));
	}

	function SaveDS($bypass_id = false)
	{
		global $_d;
        
		if (!isset($this->Data['_id']) && !$bypass_id)
			throw new Exception("No ID set and not bypassing.");
		if (empty($this->Data['title']) && empty($this->Data['index']))
			throw new Exception('No title or index set!');

		return $_d['entry.ds']->save($this->Data, array('safe' => 1));
	}

	function Remove()
	{
		global $_d;

		if (!isset($this->Data['_id'])) throw new Exception('No ID set.');
		$_d['entry.ds']->remove(array('_id' => $this->Data['_id']));
	}

	function Rename($target)
	{
		if (!empty($this->Data['paths']))
			foreach ($this->Data['paths'] as $ix => $p)
				if ($p == $this->Data['path'])
					$this->Data['paths'][$ix] = utf8_encode($target);

		$ret = rename($this->Path, utf8_decode($target));
		$this->Data['path'] = utf8_encode($target);
		$this->SaveDS();
		return $ret;
	}

	function SaveCover($url)
	{
		throw new Exception('My overloading class should save this cover itself.');
	}

	static function FromID($id)
	{
		global $_d;

		$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
		$type = MediaEntry::$Types[$item['type']];
		$ret = new $type($item['path']);
		$ret->Data = $item;
		return $ret;
	}

	static function FromPath($path)
	{
		global $_d;

		$item = $_d['entry.ds']->findOne(array('path' => $path));
		$ret = new MediaEntry($path);
		$ret->Data = $item;
		return $ret;
	}

	static function ScrapeFS($path, $pregs)
	{
		// Collect path based metadata.

		$mx = 0;
		foreach ($pregs as $preg => $matches)
		{
			if (preg_match($preg, $path, $m))
			{
				foreach ($matches as $idx => $col)
					$ret[$col] = $m[$idx];
				$ret['debug_matched'] = $mx;
				break;
			}
			$mx++;
		}

		$ret['fs_path'] = $path;
		$ret['fs_filename'] = basename($path);

		return $ret;
	}

	static function RegisterType($type, $class)
	{
		MediaEntry::$Types[$type] = $class;
	}
}

?>
