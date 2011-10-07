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

		if (!empty($path)) $this->Data['path'] = $path;
		if (!empty($this->Title)) $this->Data['title'] = $this->Title;
		if (!empty($this->Type)) $this->Data['type'] = $this->Type;
	}

	function save_to_db()
	{
		global $_d;
		return $_d['entry.ds']->save($this->Data, array('safe' => 1));
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
}

?>
