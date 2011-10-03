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

		$this->Parent = 'Unknown';
		$this->Data = array('path' => $path, 'title' => $this->Title);
	}
}

?>
