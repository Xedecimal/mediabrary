<?php

class TVRage extends Module implements Scraper
{
	public $Name = 'TVRage';

	const _tvrage_key = 'ouF0qPaRHNf7MXPMrQZv';
	const _tvrage_find = 'http://services.tvrage.com/myfeeds/search.php?key=ouF0qPaRHNf7MXPMrQZv&show=';
	const _tvrage_info = 'http://services.tvrage.com/myfeeds/showinfo.php?key=ouF0qPaRHNf7MXPMrQZv&sid=';
	const _tvrage_list = 'http://services.tvrage.com/myfeeds/episode_list.php?key=ouF0qPaRHNf7MXPMrQZv&sid=';

	# Module extension

	function Link()
	{
		global $_d;

		$_d['tv.cb.check.series'] = array(&$this, 'cb_tv_check');
	}

	# Scraper implementation

	public $Link = 'http://www.tvrage.com';
	public $Icon = 'modules/det_tvrage/img/tv-rage.png';

	function Find($path, $full = false)
	{
		$sid = $this->GetSID($path, $full);
		if ($full) $this->GetXML($path, true);
	}

	static function GetSID($path, $full = false)
	{
		if (is_file($path)) $p = dirname($path);
		else $p = $path;
		$sc = "$p/.tvrage.season.xml";

		# Overridable title
		$file_title = "$path/.title.txt";
		if (file_exists($file_title)) $realname = file_get_contents($file_title);
		else $realname = basename($path);

		# Cache data
		if (!file_exists($sc) || $full)
		{
			$url = TVRage::_tvrage_find.rawurlencode($realname);
			$sx = simplexml_load_string(file_get_contents($url));
			$iurl = TVRage::_tvrage_info.rawurlencode($sx->show->showid);
			@file_put_contents($sc, file_get_contents($iurl));
		}

		# Process data
		$sx = simplexml_load_string(file_get_contents($sc));
		return (int)$sx->showid;
	}

	static function GetXML($series, $download = false)
	{
		global $_d;

		$sid = $this->GetSID($series);
		if ($sid == -1) {
			echo "Could not locate this series $series";
			return null;
		}

		$infoloc = "$series/.tvrage.series.xml";
		if ($download || !file_exists($infoloc))
		{
			$url = $this->_tvrage_list.$sid;
			$out = file_get_contents($url);
			@file_put_contents($infoloc, $out);
		}

		return simplexml_load_string(file_get_contents($infoloc));
	}

	static function GetInfo($series)
	{
		$ret = array();

		$sx = $this->GetXML($series);
		$ret['series'] = trim((string)$sx->name);
		foreach ($sx->xpath('//Season') as $s)
		{
			$sn = (int)$s->attributes()->no;
			foreach ($s->xpath('episode') as $ep)
			{
				// Blank date.
				if ($ep->airdate != '0000-00-00' && !preg_match('/\d{4}-00-00/', $ep->airdate))
					$eout['aired'] = Database::MyDateTimestamp($ep->airdate);
				if (!empty($ep->title)) $eout['title'] = (string)$ep->title;
				$eout['links']['TVRage'] = (string)$ep->link;
				$en = (int)$ep->seasonnum;
				$ret['eps'][$sn][$en] = $eout;
			}
		}

		return $ret;
	}

	public function CanAuto() {

	}

	public function Details($id) {

	}

	public function GetDetails($t, $g, $a) {

	}

	public function GetName() {

	}

	public function Scrape($item, $id = null) {

	}
}

Scrape::Reg('tv', 'TVRage');
Scrape::Reg('tv-series', 'TVRage');
Scrape::Reg('tv-episode', 'TVRage');

?>
