<?php

class MediaEntry
{
	# Filesystem
	public $Path;
	public $Filename;

	# Metadata
	public $Title;
	
	function __construct($path)
	{
		$this->Path = $path;
		$this->Title = $this->Filename = basename($path);
	}
}

?>
