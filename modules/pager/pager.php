<?php

class Pager extends Module
{
	function Get()
	{
		$js = Module::P('pager/pager.js');
		$ret['head'] = '<script type="text/javascript" src="'.$js.'" ></script>';
		return $ret;
	}
}

Module::Register('Pager');

?>
