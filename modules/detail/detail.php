<?php

class ModDetail extends Module
{
	function __construct()
	{
		global $_d;
	}

	function Link()
	{
		global $_d;

		$cert = Server::GetVar('cert');
		if ($cert == 'Remove Filter') unset($_SESSION['cert']);
		else if (!empty($cert))
		{
			if ($cert == 'Uncertified') $cert = null;
			$_d['movie.cb.query']['match']['details.certification'] = $cert;
			$_d['movie.skipfs'] = true;
		}

		$_d['movie.cb.check']['detail'] = array(&$this, 'cb_movie_check');
		$_d['movie.cb.detail']['detail'] = array(&$this, 'cb_movie_detail');
		$_d['filter.cb.filters']['detail'] = array(&$this, 'cb_filter_filters');
		$_d['tmdb.cb.postscrape']['detail'] = array(&$this, 'cb_tmdb_postscrape');
		$_d['tmdb.cb.scrape']['detail'] = array(&$this, 'cb_tmdb_scrape');
	}

	function Prepare()
	{
		global $_d;

		if ($_d['q'][0] == 'cert') $_SESSION['cert'] = $_d['q'][1];
	}

	function Get()
	{
		$ret['head'] = '<script type="text/javascript"
			src="modules/detail/detail.js"></script>';

		return $ret;
	}

	function cb_movie_check($movie)
	{
		global $_d;

		$ret = array();
		if (empty($movie['details']['certification']))
		{
			$uep = urlencode($movie['fs_path']);
			$url = "{{app_abs}}/movie/scrape?target={$uep}&amp;fast=1";
			$tmdbid = @$movie['tmdbid'];
			$imdbid = @$movie['details']['imdb_id'];
			$ret['Details'][] = <<<EOD
<a href="{$url}">Scrape</a> No certification for
{$movie['title']}
- <a href="http://www.themoviedb.org/movie/{$tmdbid}" target="_blank">TMDB</a>
- <a href="http://www.imdb.com/title/{$imdbid}" target="_blank">IMDB</a>
EOD;
		}
		return $ret;
	}

	function cb_movie_detail($item)
	{
		global $_d;

		if (empty($item['_id'])) return $item;

		$item['details']['Size'] = File::SizeToString(filesize($item['fs_path']));

		foreach ($item['details'] as $n => $v)
			if (is_array($v)) $item['details'][$n] = implode(', ', $v);

		if (!empty($item['details']['trailer']))
		{
			preg_match('/\?v=([^&]+)/', $item['details']['trailer'], $m);
			$v = $m[1];
		$item['details']['trailer'] = <<<EOF
<object width="580" height="360"><param name="movie" value="http://www.youtube.com/v/$v&amp;hl=en_US&amp;fs=1?color1=0x3a3a3a&amp;color2=0x999999&amp;hd=1&amp;border=1"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/$v&amp;hl=en_US&amp;fs=1?color1=0x3a3a3a&amp;color2=0x999999&amp;hd=1&amp;border=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="580" height="360"></embed></object>
EOF;
		}

		return $item;
	}

	function cb_tmdb_scrape($item, $xml)
	{
		file_put_contents('scrape.txt', $xml);
		$sx = simplexml_load_string($xml);
		$this->save = array();
		foreach ($sx->movies->movie[0] as $n => $v)
		{
			$v = trim($v);
			if (empty($v)) continue;

			if (is_numeric($v)) $this->save[$n] = (float)$v;
			else if (preg_match('/\d{4}-\d{2}-\d{2}/', $v))
				$this->save[$n] = new MongoDate(strtotime($v));
			else $this->save[$n] = $v;
		}

		$this->save['categories'] = array();
		foreach ($sx->movies->movie->categories->category as $c)
			$this->save['categories'][] = (string)$c['name'];

		$this->save['keywords'] = array();
		foreach ($sx->movies->movie->keywords->keyword as $k)
			$this->save['keywords'][] = (string)$k['name'];

		$this->save['obtained'] =
			Database::TimestampToMySql(filemtime($item['fs_path']));
	}

	function cb_tmdb_postscrape($item)
	{
		global $_d;
		if (!empty($this->save)) $item['details'] = $this->save;
		return $item;
	}

	function cb_filter_filters()
	{
		global $_d;

		$curcert = Server::GetVar('cert');

		$m = new MongoCode('function() {
	emit(this.details.certification, { count: 1 });
};');

		$r = new MongoCode('function(key, values) {
	var total = 0
	for (var i = 0; i < values.length; i++)
		total += values[i].count;
	return { count: total };
};');

		$_d['db']->command(array('mapreduce' => 'entry',
			'map' => $m, 'reduce' => $r, 'out' => 'mr_out'));

		$res = $_d['db']->mr_out->find();
		while ($res->hasNext())
		{
			$r = $res->getNext();
			$n = empty($r['_id']) ? 'Uncertified' : $r['_id'];
			$cloud[$n]['cert_class'] = 'aCert';
			if ($n == $curcert) $cloud[$n]['cert_class'] .= ' current';
			$sizes[$n] = $r['value']['count'];
		}

		$cloud['Remove Filter'] = array('cert_class' => 'aCert', 'cert_size' => 0);
		$sizes['Remove Filter'] = 0;

		if (empty($sizes)) return;

		$sizes = Math::RespectiveSize($sizes);
		foreach ($sizes as $n => $size)
			$cloud[$n]['cert_size'] = $size;

		$out = '';
		foreach ($cloud as $n => $item)
			$out .= '<a href="{{app_abs}}/cert/'.$n.'" class="'.$item['cert_class'].'"
				style="font-size: '.$item['cert_size'].'px;">'.$n.'</a> ';

		return '<div class="filter">Certification: '.$out.'</div>';
	}

	static function GetCertCloud()
	{
	}
}

Module::Register('ModDetail');

?>
