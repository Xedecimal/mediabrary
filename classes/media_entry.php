<?php

class MediaEntry
{
	# Filesystem
	public $Path;
	public $Filename;

	# Metadata
	public $Title;

	function __construct($path, $parses = null, $bypass_checks = false)
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
					$this->DebugMatched = $mx;
					break;
				}
				$mx++;
			}
			if (!isset($this->DebugMatched)) $this->FailedFSPreg = 1;
		}
		else $this->Title = $this->Filename;

		if (!empty($path)) $this->Data['path'] = Str::MakeUTF8($path);
		if (!empty($this->Title)) $this->Data['title'] = Str::MakeUTF8($this->Title);
		if (!empty($this->Type)) $this->Data['type'] = $this->Type;
	}

	function LoadDS()
	{
		global $_d;
		$this->Data = $_d['entry.ds']->findOne(array(
			'path' => Str::MakeUTF8($this->Path)));
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
					$this->Data['paths'][$ix] = $target;

		$ret = rename($this->Data['path'], $target);
		$this->Data['path'] = $target;
		$this->SaveDS();
		return $ret;
	}

	function SaveCover($url)
	{
		throw new Exception('I have no idea where to save a cover for this type.');
	}

	static function FromID($id, $type = 'MediaEntry')
	{
		global $_d;

		$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
		$ret = new $type($item['path'], null, true);
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

	static function GetEntryByType($path, $type)
	{
		switch ($type)
		{
			case 'movie':
				return new MovieEntry($path, MovieEntry::GetFSPregs());
			case 'tv-series':
				return new TVSeriesEntry($path);
		}
	}
}

?>
