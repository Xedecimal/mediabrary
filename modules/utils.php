<?php

function get_xpath($node, $xpath)
{
	return $node->xpath($xpath);
}

function xpath_attr($s, $xpath, $attr)
{
	$es = $s->xpath($xpath);
	if (!empty($es))
	{
		$e = array_shift($es);
		return $e[$attr];
	}
}

function bin_safe_store($dat)
{
	return base64_encode($dat);
}

function bin_safe_load($dat)
{
	return base64_decode($dat);
}

?>
