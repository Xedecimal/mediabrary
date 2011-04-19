<?php

class Pager extends Module
{
	function Get()
	{
		global $_d;

		# TODO: Add pagination for other sections as well.
		if (@$_d['q'][0] == 'movie')
		{
			$r['head'] = '<script type="text/javascript"
				src="{{app_abs}}/modules/pager/pager.js" ></script>';
			return $r;
		}
	}
}

Module::Register('Pager');

?>
