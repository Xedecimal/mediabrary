<?php

# http://www.last.fm/api/intro

class LastFM extends Module implements Scraper
{
	const API_KEY = '134ea76dc66836eeb90930f9d4071fd0';
	const API_SECRET = '1e92893183c68ef4d75829dc898de0a1';

	public function CanAuto() { return false; }

	public function Details($id) {

	}

	public function Find($path) {

	}

	public function GetDetails($details, $item) {

	}

	public function GetName() { return 'Last.fm'; }

	public function Scrape($item, $id = null) {
	}
}

Module::Register('LastFM');
Scrape::Reg('music-artist', 'LastFM');

?>
