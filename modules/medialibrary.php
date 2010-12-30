<?php

require_once('h_main.php');

class MediaLibrary extends Module
{
	function __construct() { $this->_vars = array(); }

	function Get()
	{
		//if (!empty($this->_items)) ksort($this->_items);
		$t = new Template();
		$t->ReWrite('item', array($this, 'TagItem'));
		$t->Set($this->_vars);
		return $t->Parsefile($this->_template);
	}

	function TagItem($t, $g)
	{
		global $_d;

		$vp = new VarParser();
		$ret = null;
		$scraped = false;

		foreach ($this->_items as $i)
		{
			foreach (array_keys($i) as $k) $i[$k] = htmlspecialchars($i[$k]);
			$ret .= $vp->ParseVars($g, $i);
		}

		return $ret;
	}

	function ScrapeFS($path)
	{
		// Collect path based metadata.

		if (!isset($this->_items[$path]))
		{
			$mx = 0;
			foreach ($this->_fs_scrapes as $preg => $matches)
			{
				if (preg_match($preg, $path, $m))
				{
					foreach ($matches as $idx => $col)
						$this->_items[$path][$col] = $m[$idx];
					$this->_items[$path]['debug_matched'] = $mx;
					break;
				}
				$mx++;
			}

			$this->_items[$path]['fs_path'] = $path;
			$this->_items[$path]['fs_filename'] = basename($path);
		}

		return $this->_items[$path];
	}

	static function GetMedia($type, $item, $default_thumb)
	{
		global $_d;

		// Collect the cover

		$path = $item['fs_path'];
		$pinfo = pathinfo($path);

		$query = preg_quote("img/meta/{$type}/thm_{$pinfo['filename']}");
		$images = glob($query.'.*');
		// Do not encode this, javascript cant decode it. Encode in php
		// elsewhere.
		if (!empty($images)) $ret['med_thumb'] = str_replace("'", "%27",
			'http://'.$_SERVER['HTTP_HOST'].$_d['app_abs'].'/'.$images[0]);
		else $ret['med_thumb'] = $default_thumb;

		$images = glob("img/meta/{$type}/bd_{$pinfo['filename']}.*");
		if (!empty($images)) $ret['med_bd'] = HM::URL($images[0]);

		return $ret;
	}

	static function CleanTitleForFile($title, $trans_the = true)
	{
		// Regular expression cleanups.
		$preps = array('/\.$/' => '');
		$ret = preg_replace(array_keys($preps), array_values($preps), $title);

		// Literal cleanups.
		$reps = array('/' => ' ', ': ' => ' - ', ':' => '-', '?' => '');

		$ret = str_replace(array_keys($reps), array_values($reps), $ret);

		if ($trans_the)
		{
			// Transpose 'The {title} - {subtitle}
			if (preg_match('/^(the) ([^-]+) - (.*)/i', $ret, $m))
				$ret = $m[2].', '.$m[1].' - '.$m[3];

			// Transpose 'The {title}'
			else if (preg_match('/^(the) (.*)/i', $ret, $m))
				$ret = $m[2].', '.$m[1];
		}

		return $ret;
	}

	function Check() { return array(); }
}

?>
