<?php

//http://musicbrainz.org/ws/2/release?artist=f1c8da15-b408-4149-b863-f1cbe9971f19&status=official

class MusicBrainz extends Module implements Scraper
{
	public function CanAuto() { return false; }

	public function Details($id) { }

	public function Find(&$me, $title) { }

	function GetCovers($item) {}

	public function GetDetails($t, $g, $a) { }

	public function GetName() { }

	public function Scrape(&$item, $id = null) { }
}

?>
