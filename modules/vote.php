<?php

class ModVote extends Module
{
	function __construct()
	{
		global $_d;
		
		$_d['vote.ds'] = new DataSet($_d['db'], 'vote');
	}
	
	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'vote') return;

		if (@$_d['q'][1] == 'vote')
		{
			$vote = @$_d['q'][2];
			$path = '/'.implode('/', array_splice($_d['q'], 3));
			$ip = ip2long(GetVar('REMOTE_ADDR'));
			$_d['vote.ds']->Add(array(
				'vote_ip' => $ip,
				'vote_positive' => !empty($vote),
				'vote_path' => $path
			), true);
			die($path);
		}
	}
}

Module::RegisterModule('ModVote');

?>
