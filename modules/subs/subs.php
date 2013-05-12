<?php

class Subs extends Module
{
	public $Name = 'subs';

	function Link()
	{
		global $_d;

		$_d['cb.detail.buttons']['subs'] = array(&$this, 'cb_movie_buttons');
	}

	function Prepare()
	{
		$this->CheckActive($this->Name);
		if (!$this->Active) return;

		if (@$_d['q'][1] == 'find')
		{

		}

		$path = $_GET['path'];
		$title = $_GET['title'];
		$date = $_GET['date'];
		$pinfo = pathinfo($path);
		$target = $pinfo['dirname'].'/'.$pinfo['filename'].'.srt';

		$ret = $this->SearchTheSubDB($path);
		die(json_encode($ret));
		//http://www.allsubs.org/subs-download2/X-Men+First+Class/3782502
		//http://www.allsubs.org/subs-download/X-Men+Origins+-+Wolverine/1835746
	}

	function cb_movie_buttons()
	{
		return '<a href="{{Path}}" id="a-get-subs"><img src="'
			.Module::P('subs/img/cc.jpg')
			.'" alt="Download Subtitles" title="Download Subtitles"/></a>';
	}

	function Get()
	{
		$ret['head'] = '<script type="text/javascript"
			src="'.Module::P('subs/subs.js').'"></script>';

		return $ret;
	}

	# TheSubDB

	function SearchTheSubDB($path)
	{
		# Prepare the MD5 hash of 64k front and end of file combined.
		$rsize = 65536;
		$fp = fopen($path, 'rb');
		$dat = fread($fp, 65536);
		fseek($fp, -65536, SEEK_END);
		$dat .= fread($fp, 65536);
		$hash = md5($dat);

		# Prepare the stream context.
		$ch = curl_init('http://api.thesubdb.com/?action=search&hash='.$hash);
		curl_setopt($ch, CURLOPT_USERAGENT, 'SubDB/1.0 (Mediabrary/0.1; http://code.google.com/p/mediabrary)');
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		list($heads, $data) = explode("\r\n\r\n", curl_exec($ch));
		curl_close($ch);

		$results = array();
		foreach (explode(',', $data) as $lang)
		{
			$results[$lang] = "http://api.thesubdb.com/?action=download&hash=$hash&language=$lang";
		}

		return $results;
	}

	# AllSubs

	function SearchAllSubs()
	{
		/*$xml = file_get_contents('http://api.allsubs.org/index.php?search='
			.rawurlencode(str_replace(' - ', ' ', $title).' '.$date).'&language=en&limit=1');

		var_dump($xml);
		$sx = simplexml_load_string($xml);
		$items = $sx->xpath('//items/item');
		if (!empty($items))
		{
			foreach ($items as $i)
			{
				$url = str_replace('subs-download', 'subs-download2', (string)$i->link);
				# @TODO: php devs really need stream based zip support!
				file_put_contents('tmp.zip', file_get_contents($url));
				$za = new ZipArchive;
				$za->open('tmp.zip');
				$files = explode('|', $i->files_in_archive);
				file_put_contents($target, $za->getFromName($files[0]));
				$za->close();
				unlink('tmp.zip');
			}
			$ret['result'] = 'success';
		}
		else $ret['reslut'] = 'failure';*/
	}

	# OpenSubtitles

	function SearchOpenSubtitles($file)
	{
		$hash = $this->OpenSubtitlesHash($file);
	}

	function OpenSubtitlesHash($file)
	{
	    $handle = fopen($file, "rb");
	    $fsize = filesize($file);

	    $hash = array(3 => 0,
	                  2 => 0,
	                  1 => ($fsize >> 16) & 0xFFFF,
	                  0 => $fsize & 0xFFFF);

	    for ($i = 0; $i < 8192; $i++)
	    {
	        $tmp = ReadUINT64($handle);
	        $hash = AddUINT64($hash, $tmp);
	    }

	    $offset = $fsize - 65536;
	    fseek($handle, $offset > 0 ? $offset : 0, SEEK_SET);

	    for ($i = 0; $i < 8192; $i++)
	    {
	        $tmp = ReadUINT64($handle);
	        $hash = AddUINT64($hash, $tmp);
	    }

	    fclose($handle);
	        return UINT64FormatHex($hash);
	}

	function ReadUINT64($handle)
	{
	    $u = unpack("va/vb/vc/vd", fread($handle, 8));
	    return array(0 => $u["a"], 1 => $u["b"], 2 => $u["c"], 3 => $u["d"]);
	}

	function AddUINT64($a, $b)
	{
	    $o = array(0 => 0, 1 => 0, 2 => 0, 3 => 0);

	    $carry = 0;
	    for ($i = 0; $i < 4; $i++)
	    {
	        if (($a[$i] + $b[$i] + $carry) > 0xffff )
	        {
	            $o[$i] += ($a[$i] + $b[$i] + $carry) & 0xffff;
	            $carry = 1;
	        }
	        else
	        {
	            $o[$i] += ($a[$i] + $b[$i] + $carry);
	            $carry = 0;
	        }
	    }

	    return $o;
	}

	function UINT64FormatHex($n)
	{
	    return sprintf("%04x%04x%04x%04x", $n[3], $n[2], $n[1], $n[0]);
	}
}

Module::Register('Subs');