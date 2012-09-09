<?php

require_once(dirname(__FILE__).'/../scrape/scrape.php');

class TSDB extends Module implements Scraper
{
	public $Name = 'TSDB';
	public $Link = 'http://thesubdb.com';
	public $Icon = 'modules/det_tsdb/icon.png';

	# Scraper Implementation

	function CanAuto() { return true; }
	function Find(&$md, $title = null)
	{
		# Prepare the MD5 hash of 64k front and end of file combined.
		$rsize = 65536;
		$fp = fopen($md->Path, 'rb');
		$dat = fread($fp, 65536);
		fseek($fp, -65536, SEEK_END);
		$dat .= fread($fp, 65536);
		$hash = md5($dat);

		$opts = array('http' => array(
			'user_agent' => 'SubDB/1.0 (Mediabrary/0.1; http://code.google.com/p/mediabrary)'
		));
		$ctx = stream_context_create($opts);
		$data = file_get_contents('http://api.thesubdb.com/?action=search&hash='.$hash, false, $ctx);

		$results = array();
		foreach (explode(',', $data) as $lang)
		{
			$id = $hash.':'.$lang;
			$results[$id] = array(
				'id' => $id,
				'title' => $lang,
				'date' => ''
			);
		}

		return $results;
	}
	function GetCovers($md) { return array(); }
	function Details($id) { }
	function Scrape(&$me, $id = null)
	{
		list($hash, $lang) = explode(':', $id);

		$opts = array('http' => array(
			'user_agent' => 'SubDB/1.0 (Mediabrary/0.1; http://code.google.com/p/mediabrary)'
		));
		$ctx = stream_context_create($opts);
		$data = file_get_contents("http://api.thesubdb.com/?action=download&hash=$hash&language=$lang", false, $ctx);

		$pinfo = pathinfo($me->Path);
		file_put_contents($pinfo['dirname'].'/'.$pinfo['filename'].'.srt', $data);
	}
	function GetDetails($t, $g, $a) { }
}

Module::Register('TSDB');
Scrape::Reg('movie', 'TSDB');
