<?php

class ModRoulette extends Module
{
	function Link()
	{
		global $_d;

		$_d['cb.head']['roulette'] = array(&$this, 'cb_head');
		$_d['movie.cb.head']['roulette'] = array(&$this, 'cb_movie_head');
		$_d['movie.cb.buttons']['roulette'] = array(&$this, 'cb_movie_buttons');
		$_d['tv.cb.buttons']['roulette'] = array(&$this, 'cb_tv_buttons');
	}

	function cb_head()
	{
		$js = Module::P('roulette/js.js');
		return <<<EOF
<script type="text/javascript" src="$js"></script>
EOF;
	}

	function cb_movie_head()
	{
		$t = new Template();
		return $t->ParseFile(Module::L('roulette/t.xml'));
	}

	function cb_tv_buttons()
	{
		$icon = Module::P('roulette/img/icon.gif');
		return '<a href="#" id="a-roulette-tv"><img src="'
			.$icon.'" alt="Roulette" /></a>';
	}

	function cb_movie_buttons()
	{
		$img = Module::P('roulette/img/icon.gif');
		return '<a href="#" id="a-roulette"><img src="'.$img.'" alt="roulette" /></a>';
	}
}

Module::Register('ModRoulette');

?>
