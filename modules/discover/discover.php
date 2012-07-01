<?php

class Discover extends Module
{
	public $Name = 'discover';

	function __construct()
	{
		$this->CheckActive($this->Name);
	}

	function Link()
	{
		global $_d;

		$_d['nav.links']['Tools/Discover'] = '{{app_abs}}/'.$this->Name;
	}

	function Prepare()
	{
		global $_d;

		if (@$_d['q'][1] == 'scan')
		{
			//session_write_close();
			echo str_repeat(' ', 1024)."\r\n";
			flush();

			preg_match_all('/^\s+(\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3})/m', `arp -a`, $m);
			foreach ($m[1] as $ip)
			{
				ModCheck::Out("Found $ip...");
				if ($this->Ping($ip)) return;
			}
			#die();
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		return $t->ParseFile('modules/discover/discover.xml');
	}

	function Ping($ip)
	{
		//set_time_limit(0);
		if (preg_match('/^Reply from (\S+): bytes=\d+ time/m',
			`ping -n 1 -w 1000 $ip`, $m))
		{
			ModCheck::Out("Alive!");
			$this->FindShares($ip);
			return 1;
		}
	}

	function FindShares($ip)
	{
		$lines = explode("\n", `net view \\\\$ip`);
		foreach ($lines as $line)
		{
			if (preg_match('/(.*?)\s+(Disk)/', $line, $m))
			{
				ModCheck::Out("Found a disk: {$m[1]}\r\n");
				$this->CheckPath("//$ip/$m[1]");
				return;
			}
		}
	}

	function CheckPath($path)
	{
		foreach (scandir($path) as $f)
		{
			if ($f[0] == '.') continue;

			$cp = $path.'/'.$f;
			if (is_dir($cp)) $this->CheckPath($cp);

			if (count(glob($cp.'/*.avi')) > 0)
				ModCheck::Out("Interesting: {$cp}");
		}
		ModCheck::Out("Scanned $path");
		return;
	}
}

Module::Register('Discover');
