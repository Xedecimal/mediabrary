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

		$tvse = new TVSeriesEntry($path);

		$url = TVRage::_tvrage_find.rawurlencode($tvse->Title);
		$ctx = stream_context_create(array('http' => array('timeout' => 5)));
		$xml = file_get_contents($url, false, $ctx);
		$sx = simplexml_load_string($xml);

		foreach ($sx->show as $s)
		{
			$url = '';
			$item = array(
				'id' => $s->showid,
				'title' => $s->name,
				'date' => $s->started,
				'ref' => $s->link
			);
			$items[] = $item;
		}

		return $items;
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

	# Callbacks

	function cb_tv_check_series($series, &$msgs)
	{
	}
}

Scrape::Reg('tv', 'TVRage');
Scrape::Reg('tv-series', 'TVRage');
Scrape::Reg('tv-episode', 'TVRage');

?>
