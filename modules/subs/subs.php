<?php

class Subs extends Module
{
	public $Name = 'subs';

	function Link()
	{
		global $_d;

		$_d['movie.cb.buttons']['subs'] = array(&$this, 'cb_movie_buttons');
	}

	function Prepare()
	{
		$this->CheckActive($this->Name);
		if (!$this->Active) return;

		$path = $_GET['path'];
		$title = $_GET['title'];
		$pinfo = pathinfo($path);
		$target = $pinfo['dirname'].'/subtitles/'.$pinfo['filename'].'.srt';

		###
		### thesubdb.com
		###

		# Prepare the MD5 hash of 64k front and end of file combined.

		/*$rsize = 65536;
		$fp = fopen($path, 'rb');
		$dat = fread($fp, 65536);
		fseek($fp, -65536, SEEK_END);
		$dat .= fread($fp, 65536);
		$hash = md5($dat);*/

		# Prepare the stream context.

		#@TODO: Change this back to api.thesubdb.com
		/*$ch = curl_init('http://sandbox.thesubdb.com/?action=search&hash='.$hash);
		curl_setopt($ch, CURLOPT_USERAGENT, 'SubDB/1.0 (Mediabrary/0.1; http://code.google.com/p/mediabrary)');
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_exec($ch);
		$res = curl_getinfo($ch);
		curl_close($ch);

		var_dump($res);*/

		###
		### allsubs.org
		###

		$xml = file_get_contents('http://api.allsubs.org/index.php?search='
			.rawurlencode(str_replace(' - ', ' ', $title)).'&language=en&limit=1');

		$sx = simplexml_load_string($xml);
		foreach ($sx->xpath('//items/item') as $i)
		{
			$url = str_replace('subs-download', 'subs-download2', (string)$i->link);
			#@TODO: php devs really need stream based zip support!
			file_put_contents('tmp.zip', file_get_contents($url));
			$za = new ZipArchive;
			$za->open('tmp.zip');
			$files = explode('|', $i->files_in_archive);
			file_put_contents($target, $za->getFromName($files[0]));
			$za->close();
			unlink('tmp.zip');
		}

		//http://www.allsubs.org//subs-download2/X-Men+First+Class/3782502//
		//http://www.allsubs.org/subs-download/X-Men+Origins+-+Wolverine/1835746/
	}

	function cb_movie_buttons()
	{
		return '<a href="{{Path}}" id="a-get-subs">Get Subs</a>';
	}

	function Get()
	{
		$ret['head'] = '<script type="text/javascript"
			src="'.Module::P('subs/subs.js').'"></script>';

		return $ret;
	}
}

Module::Register('Subs');

?>
