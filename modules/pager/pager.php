<?php

class Pager extends Module
{
	function Link()
	{
		global $_d;

		if (empty($_d['movie.add'])) $_d['movie.add'] = '';

		# @TODO: Add pagination for other sections as well.
		$_d['movie.add'] .= '<script type="text/javascript" src="modules/pager/pager.js" ></script>';
	}
}

Module::Register('Pager');

?>
