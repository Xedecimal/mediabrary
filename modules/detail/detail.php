<?php

class ModDetail extends Module
{
	function __construct()
	{
		global $_d;

		$_d['movie_detail.ds'] = new DataSet($_d['db'], 'movie_detail', 'md_id');
		$_d['movie_date.ds'] = new DataSet($_d['db'], 'movie_date', 'md_id');
		$_d['movie_float.ds'] = new DataSet($_d['db'], 'movie_float', 'md_id');
	}

	function Link()
	{
		global $_d;

		$cert = Server::GetVar('cert');
		if ($cert == 'None') unset($_SESSION['cert']);
		else if (!empty($cert))
		{
			# Movies without a certification set
			if ($cert == 'Uncertified')
			{
				$_d['movie.cb.query']['joins']['detail'] =
					new Join($_d['movie_detail.ds'], "md_movie = mov_id
						AND md_name = 'certification'", 'LEFT JOIN');
				$_d['movie.cb.query']['match']['md_value'] =
					Database::SqlUnquote(' IS NULL');
			}
			# Movies with a specific certification
			else
			{
				$_d['movie.cb.query']['joins']['detail'] =
					new Join($_d['movie_detail.ds'], 'md_movie = mov_id',
						'LEFT JOIN');
				$_d['movie.cb.query']['match']['md_name'] = 'certification';
				$_d['movie.cb.query']['match']['md_value'] = $cert;
			}
			
			$_d['movie.skipfs'] = true;
		}
		else $_d['movie.cb.query']['joins']['date'] =
			new Join($_d['movie_date.ds'], 'md_movie = mov_id', 'LEFT JOIN');

		$_d['movie.cb.check']['detail'] = array(&$this, 'cb_movie_check');
		$_d['movie.cb.detail']['detail'] = array(&$this, 'cb_movie_detail');
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
		global $_d;

		$q['columns']['cert'] = 'md_value';
		$q['columns']['movies'] = Database::SqlCount('mov_id');
		$q['joins']['movie'] = new Join($_d['movie.ds'], 'mov_id = md_movie',
			'LEFT JOIN');
		$q['match']['md_name'] = 'certification';
		$q['group'] = 'md_value';
		$certs = $_d['movie_detail.ds']->Get($q);

		foreach ($certs as $c) $cloud[$c['cert']] = $c['movies'];
		$cloud['None'] = 0;
		$cloud['Uncertified'] = 0;

		$cloud = Math::RespectiveSize($cloud);

		$out = '';
		foreach ($cloud as $c => $s)
			$out .= '<a href="{{app_abs}}/cert/'.$c.'" class="aCert category"
				style="font-size: '.$s.'px;">'.$c.'</a> ';

		$url = Module::P('detail/detail.js');
		$r['head'] = '<script type="text/javascript" src="'.$url.'"></script>';
		$r['filters'] = '<div class="filter">'.$out.'</div>';

		return $r;
	}

	function cb_movie_check($movie)
	{
		global $_d;

		$ret = array();
		$q['match']['md_movie'] = $movie['mov_id'];
		$q['match']['md_name'] = 'certification';
		$q['match']['md_value'] = Database::SqlUnquote(' IS NOT NULL');
		$rows = $_d['movie_detail.ds']->Get($q);
		if (empty($rows))
		{
			$uep = urlencode($movie['mov_path']);
			$url = "{{app_abs}}/movie/scrape?target={$uep}&fast=1";
			$ret['Details'][] = <<<EOD
<a class="a-fix" href="{$url}">Scrape</a> No certification for
{$movie['mov_title']}
- <a href="http://www.themoviedb.org/movie/{$movie['mov_tmdbid']}" target="_blank">Reference</a>
EOD;
		}
		return $ret;
	}

	function cb_movie_detail($item)
	{
		global $_d;

		if (empty($item['mov_id'])) return $item;

		$details = $_d['movie_detail.ds']->Get(array(
			'match' => array(
				'md_movie' => $item['mov_id']
			)
		));

		foreach ($details as $det)
			if (!empty($det['md_value']))
				$item['details'][$det['md_name']] = $det['md_value'];
		$item['details']['Size'] = File::SizeToString(filesize($item['fs_path']));

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
		$sx = simplexml_load_string($xml);
		foreach ($sx->movies->movie[0] as $n => $v)
		{
			$v = trim($v);
			if (empty($v)) continue;

			if (is_numeric($v)) $this->rows['float'][$n] = $v;
			else if (preg_match('/\d{4}-\d{2}-\d{2}/', $v))
				$this->rows['date'][$n] = $v;
			else $this->rows['detail'][$n] = $v;
		}

		$this->rows['date']['obtained'] =
			Database::TimestampToMySql(filemtime($item['fs_path']));
	}

	function cb_tmdb_postscrape($item)
	{
		global $_d;

		if (!empty($this->rows))
		foreach ($this->rows as $table => $values)
		{
			foreach ($values as $n => $v)
			{
				$a = array(
					'md_movie' => $item['mov_id'],
					'md_name' => $n,
					'md_value' => $v
				);
				$_d['movie_'.$table.'.ds']->Add($a, true);
			}
		}

		return $item;
	}
}

Module::Register('ModDetail');

?>