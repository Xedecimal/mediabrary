<?php

function t($str)
{
	if (isset($GLOBALS['_d']['i18n'][$str]))
		return $GLOBALS['_d']['i18n'][$str];
	return $str;
}

?>
