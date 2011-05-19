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
		}
		else $this->Title = $this->Filename;
	}
}

?>
