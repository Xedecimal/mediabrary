<?php

class ModDetail extends Module
{
	function __construct()
	{
		global $_d;

		$_d['movie_detail.ds'] = new DataSet($_d['db'], 'movie_detail');
		$_d['movie_date.ds'] = new DataSet($_d['db'], 'movie_date');
	}

	function Link()
	{
		global $_d;

		$_d['tmdb.cb.postscrape']['detail'] = array(&$this, 'cb_tmdb_postscrape');
		$_d['tmdb.cb.scrape']['detail'] = array(&$this, 'cb_tmdb_scrape');
		$_d['movie.cb.detail']['detail'] = array(&$this, 'cb_movie_detail');
	}

	function cb_tmdb_scrape($item, $xml)
	{
		$sx = simplexml_load_string($xml);

		$this->details['overview'] = xpath_value($sx, '//movies/movie/overview');
		$this->details['rating'] = xpath_value($sx, '//movies/movie/rating');
		$this->details['certification'] = xpath_value($sx, '//movies/movie/certification');
		$this->details['trailer'] = xpath_value($sx, '//movies/movie/trailer');
		$this->details['url'] = xpath_value($sx, '//movies/movie/homepage');
		$this->dates['obtained'] = TimestampToMySql(filemtime(stripslashes($item['fs_path'])));
	}

	function cb_tmdb_postscrape($item)
	{
		global $_d;

		foreach ($this->details as $det => $v)
			$_d['movie_detail.ds']->Add(array(
				'md_movie' => $item['med_id'],
				'md_name' => $det,
				'md_value' => $v
			), true);

		foreach ($this->dates as $det => $v)
		{
			$_d['movie_date.ds']->Add(array(
				'md_movie' => $item['med_id'],
				'md_name' => $det,
				'md_date' => $v
			), true);
		}

		return $item;
	}

	function cb_movie_detail($item)
	{
		global $_d;

		if (empty($item['med_id'])) return $item;

		$details = $_d['movie_detail.ds']->Get(array(
			'match' => array(
				'md_movie' => $item['med_id']
			)
		));

		foreach ($details as $det)
			if (!empty($det['md_value']))
				$item['details'][$det['md_name']] = $det['md_value'];
		$item['details']['Size'] = GetSizeString(filesize($item['fs_path']));

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
}

Module::Register('ModDetail');

?>
