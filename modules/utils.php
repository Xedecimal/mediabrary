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

function xpath_attrs($s, $xpath, $attr)
{
	$es = $s->xpath($xpath);
	$ret = array();
	if (!empty($es))
		foreach ($es as $e) $ret[] = $e[$attr];
	return $ret;
}

function xpath_value($s, $xpath)
{
	$res = $s->xpath($xpath);
	if (!empty($res)) return $res[0];
}

function bin_safe_store($dat) { return base64_encode($dat); }
function bin_safe_load($dat) { return base64_decode($dat); }

?>
