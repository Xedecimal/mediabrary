<?php

require_once('h_main.php');

class MediaLibrary extends Module
{
	function __construct() { $this->_vars = array(); }

	function Get()
	{
		if (!empty($this->_items))
		{
			ksort($this->_items);
			$t = new Template();
			$t->ReWrite('item', array($this, 'TagItem'));
			$t->Set($this->_vars);
			return $t->Parsefile($this->_template);
		}
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
			foreach ($this->_fs_scrapes as $preg => $matches)
			{
				if (preg_match($preg, $path, $m))
				{
					//varinfo("ScrapeFS matched preg: {$preg}");
					foreach ($matches as $idx => $col)
						$this->_items[$path][$col] = $m[$idx];
					break;
				}
			}

			$this->_items[$path]['fs_path'] = $path;
			$this->_items[$path]['fs_filename'] = basename($path);
		}

		return $this->_items[$path];
	}

	function GetMedia(&$item)
	{
		// Collect the cover

		$path = $item['fs_path'];
		$pinfo = pathinfo($path);
		$pinfo['filename'] = filenoext($pinfo['basename']);

		$images = glob("img/meta/{$this->_class}/thm_{$pinfo['filename']}.*");
		if (!empty($images)) $item['med_thumb'] = URL($images[0]);
		else $item['med_thumb'] = $this->_missing_image;

		$images = glob("img/meta/{$this->_class}/bd_{$pinfo['filename']}.*");
		if (!empty($images)) $item['med_bd'] = URL($images[0]);
	}

	function Check() { return array(); }
}

?>
