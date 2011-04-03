<?php

class Pager extends Module
{
	function Get()
	{
		$r['head'] = '<script type="text/javascript"
			src="{{app_abs}}/modules/pager/pager.js" ></script>';

		return $r;
	}
}

Module::Register('Pager');

?>
