<?php

class TVDB extends Module implements Scraper
{
	public $Name = 'TVDB';

	const _tvdb_key = '138419DAB0A9141D';
	const _tvdb_find = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=';

	//http://www.thetvdb.com/api/138419DAB0A9141D/series/75897/all/en.zip

	# Module extension

	function Link()
	{
		global $_d;

		$_d['tv.cb.check.series'][$this->Name] = array(&$this, 'cb_tv_check_series');
	}

	# Scraper implementation

	public function CanAuto() {

	}

	public function Details($id) {

	}

	public function GetDetails($details, $item) {

	}

	public function GetName() {

	}

	public function Scrape($item, $id = null) {

	}

	function Find($path, $full = false)
	{
		global $_d;

		$sid = TVDB::GetSID($path);
		$key = TVDB::_tvdb_key;

		$ret = '';
		$dst = $path.'/.info.zip';
		$src = "http://www.thetvdb.com/api/{$key}/series/{$sid}/all/en.zip";
		if (!@file_put_contents($dst, file_get_contents($src)))
			return 'Unable to write tvdb metadata.';

		$za = new ZipArchive;
		$za->open($dst);
		$xml = $za->getFromName('banners.xml');
		$za->close();

		$sx = simplexml_load_string($xml);
		list($ban) = $sx->xpath("//Banners/Banner[BannerType='series']/BannerPath");
		$pi = pathinfo($ban);
		$series = basename($path);
		File::MakeFullDir($_d['config']['paths']['tv']['meta']);
		file_put_contents($_d['config']['paths']['tv']['meta']."/thm_$series",
			file_get_contents("http://www.thetvdb.com/banners/{$ban}"));

		die($ret."Grabbed");
	}

	# Callbacks

	function cb_tv_check_series($series, &$msgs)
	{
		global $_d;

		$se = new TVSeriesEntry($series);

		$creps = $_d['entry.ds']->find(array(
			'type' => 'tv-episode',
			'parent' => $se->Title
		));

		foreach ($creps as $ep)
			$dbeps[$ep['season']][$ep['episode']] = $ep;

		$tvdbeps = TVDB::GetInfo($series);

		foreach ($tvdbeps['eps'] as $s => $eps)
		{
			foreach ($eps as $e => $ep)
			{
				# TVDB is already appended to this.
				if (isset($dbeps[$s][$e]['details'][$this->Name])) continue;

				$tve = new TVEpisodeEntry(null);

				$msgs['TVDB/Metadata'][] = "Adding missing database entry for $series of $s $e.";

				if (!empty($dbeps[$s][$e]))
					$tve->Data = $dbeps[$s][$e];

				$tve->Data['details'][$this->Name] = $ep;

				if (!empty($ep['aired']))
				{
					$tve->Data['released'] = new MongoDate($ep['aired']);
				}

				if (!empty($ep['title'])) $tve->Data['title'] = $ep['title'];
				if (empty($tve->Data['path'])) $tve->Data['path'] = '';
				$tve->Data['type'] = 'tv-episode';
				$tve->Data['series'] = $se->Title;
				$tve->Data['season'] = $s;
				$tve->Data['episode'] = $e;
				$tve->Data['parent'] = $se->Title;
				$tve->Data['index'] = "S{$s}E{$e}";
				$tve->Title = @$ep['title'];
				$tve->Parent = $se->Title;

				$tve->save_to_db();

				# Check out the filename.

				/*$epname = '';
				if (!empty($eps['eps'][$s][$e]['title']))
					$epname = $eps['eps'][$s][$e]['title'];
				if (!empty($epname))
					$epname = MediaLibrary::CleanTitleForFile($epname, false);
				if (!empty($eps['series']))
					$eps['series'] = MediaLibrary::CleanTitleForFile($eps['series'], false);

				# <series> / <series> - S<season>E<episode> - <title>.avi
				if (!preg_match("@([^/]+)/({$se->Title}) - S([0-9]{2})E([0-9]{2}) - ".preg_quote($epname).'\.([^.]+)$@', $episode))
				{
					$dir = dirname($episode);
					$info['med_season'] = sprintf('%02d', $info['med_season']);
					$info['med_episode'] = sprintf('%02d', $info['med_episode']);
					$fname = "{$sname} - S{$info['med_season']}E{$info['med_episode']} - {$epname}";
					$url = Module::L('tv/rename?src='.urlencode($episode).'&amp;dst='.urlencode("$dir/$fname.$ext"));
					$msgs['TV/Filename Compliance'][] = "<a href=\"$url\" class=\"a-fix\">Fix</a> File $episode has invalid name, should be \"$dir/$fname.$ext\"";
					$errors++;
				}*/
			}
		}
	}

	# Statics

	static function GetSID($path)
	{
		global $_d;

		$sc = "$path/.info.dat";
		if (file_exists($sc))
		{
			$info = unserialize(file_get_contents($sc));
			if (isset($info['sid'])) return $info['sid'];
		}
		else $info = array();
		$file_title = "$path/.title.txt";
		if (file_exists($file_title)) $realname = file_get_contents($file_title);
		else $realname = basename($path);
		$url = TVDB::_tvdb_find.rawurlencode($realname);
		$sx = simplexml_load_string(file_get_contents($url));
		$sids = $sx->xpath('//Data/Series/seriesid');
		if (empty($sids)) return -1;
		$sid = (int)$sids[0];
		if (empty($sid)) return -1;
		$info['sid'] = $sid;
		file_put_contents($path.'/.info.dat', serialize($info));
		return $sid;
	}

	static function GetXML($series, $download = false)
	{
		global $_d;

		$sid = TVDB::GetSID($series);
		if ($sid == -1) {
			echo "Could not locate this series $series";
			return null;
		}

		$infoloc = "$series/.info.zip";
		if ($download || !file_exists($infoloc))
		{
			$url = 'http://www.thetvdb.com/api/138419DAB0A9141D/series/'
				.$sid.'/all/en.zip';
			file_put_contents($infoloc, file_get_contents($url));
		}
		$za = new ZipArchive;
		$za->open($infoloc);
		$xml = $za->getFromName('en.xml');
		$za->close();
		return simplexml_load_string($xml);
	}

	static function GetInfo($series)
	{
		$sx = TVDB::GetXML($series);
		if (empty($sx)) return array();
		foreach ($sx->Episode as $ep)
		{
			$sn = (int)$ep->SeasonNumber;
			$en = (int)$ep->EpisodeNumber;
			if (!empty($ep->FirstAired))
				$ret['eps'][$sn][$en]['aired'] = Database::MyDateTimestamp($ep->FirstAired);
			if (!empty($ep->EpisodeName))
				$ret['eps'][$sn][$en]['title'] = MediaLibrary::CleanString((string)$ep->EpisodeName);

			# Build TVDB url
			$eid = (string)$ep->id;
			$snid = (string)$ep->seasonid;
			$srid = (string)$ep->seriesid;
			$ret['eps'][$sn][$en]['links']['TVDB'] = "http://thetvdb.com/index.php?tab=episode&amp;seriesid=$srid&amp;seasonid=$snid&amp;id=$eid";
		}
		return $ret;
	}
}

Module::Register('TVDB');
Scrape::Reg('tv', 'TVDB');

?>
