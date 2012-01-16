<?php

class MediaLibrary extends Module
{
	function __construct() { $this->_vars = array(); }

	function Get()
	{
		$t = new Template($GLOBALS['_d']);
		$t->ReWrite('item', array($this, 'TagItem'));
		$t->Set($this->_vars);
		return $t->Parsefile($this->_template);
	}

	function TagItem($t, $g, $a)
	{
		global $_d;

		$vp = new VarParser();
		$ret = '';

		$ix = 0;
		if (!empty($this->_items))
		foreach ($this->_items as $i)
		{
			$ix++;

			# URL friendly path
			$i->PathEncoded = rawurlencode($i->Path);

			$i->RuePath = rawurlencode($i->Path);
			$ret .= $vp->ParseVars($g, $i);
		}

		return $ret;
	}

	static function GetMedia($parent_dir, $item, $default_thumb)
	{
		global $_d;

		// Collect the cover

		$path = $item->Path;
		$pinfo = pathinfo($path);
		if (is_file($path))
			$fname = basename($pinfo['basename'], '.'.$pinfo['extension']);
		else $fname = $pinfo['basename'];

		$cover = $parent_dir."/thm_{$fname}";

		if (file_exists($cover)) $ret['med_thumb'] = str_replace("'", "%27",
			'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/'.$cover);
		else $ret['med_thumb'] = $default_thumb;

		$images = glob($parent_dir."/bd_{$fname}.*");
		if (!empty($images)) $ret['med_bd'] = HM::URL($images[0]);

		return $ret;
	}

	static function CleanTitleForFile($title, $trans_the = true)
	{
		// Regular expression cleanups.
		#$preps = array('/\.$/' => '');
		#$ret = preg_replace(array_keys($preps), array_values($preps), $title);

		// Literal cleanups.
		$reps = array('/' => ' ', ': ' => ' - ', ':' => '-', '?' => '_', '*' => '_', '"' => "'");

		$ret = str_replace(array_keys($reps), array_values($reps), $title);

		if ($trans_the)
		{
			// Transpose 'The {title} - {subtitle}'
			if (preg_match('/^(the) ([^-]+) - (.*)/i', $ret, $m))
				$ret = $m[2].', '.$m[1].' - '.$m[3];

			// Transpose 'The {title}'
			else if (preg_match('/^(the) (.*)/i', $ret, $m))
				$ret = $m[2].', '.$m[1];
		}

		return trim($ret);
	}

	static function SearchTitle($title)
	{
		$reps = array(
			'/(.*), The/' => 'The \1',
			'#\[[^\]]+\]#' => '',
			'#([.]{1} |\.| - |_|brrip)#i' => ' ',
			'#\([^)]*\)#' => '',
			"#cd\d+#i" => '');

		return trim(preg_replace(array_keys($reps), array_values($reps), $title));
	}

	static function TitleMatch($title1, $title2)
	{
		similar_text('A Christmas Carol', "Disney's A Christmas Carol", $p);
		return ($p > 74.9);
	}

	static function CleanString($str)
	{
		return trim($str);
	}
}

?>
