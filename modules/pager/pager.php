<?php

class Pager extends Module
{
	function Get()
	{
		$ret['head'] = '<script type="text/javascript" src="modules/pager/pager.js" ></script>';
		return $ret;
	}
}

Module::Register('Pager');

?>
