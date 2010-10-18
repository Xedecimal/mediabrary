<?php

class ModSeriesly extends Module
{
	function Get()
	{
		if (empty($_d['q'][0]))
		{
			return 'For Seriesly support Add the following web hook: ';
		}
	}
}

Module::Register('ModSeriesly');

?>
