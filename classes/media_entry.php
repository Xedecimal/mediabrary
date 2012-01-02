<?php

class MediaEntry
{
	# Filesystem
	public $Path;
	public $Filename;

	# Metadata
	public $Title;

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

	function SaveDS()
	{
		global $_d;
		return $_d['entry.ds']->save($this->Data, array('safe' => 1));
	}

	function Rename($target)
	{
		rename($this->Data['path'], $target);

		if (!empty($this->Data['paths']))
			foreach ($this->Data['paths'] as $ix => $p)
				if ($p == $this->Data['path'])
					$this->Data['paths'][$ix] = $target;

		$this->Data['path'] = $target;
		$this->save_to_db();
	}

	static function FromID($id)
	{
		global $_d;

		$item = $_d['entry.ds']->findOne(array('_id' => new MongoID($id)));
		$ret = new MediaEntry($item['path']);
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
		}
	}

}

?>
