<?php

require_once('h_main.php');

class ModFilter extends Module
{
	function __construct()
	{
		$this->Filters[] = new FilterReleased();
	}

	function Prepare()
	{
		global $_d;

		if (@$_d['q'][1] == 'filter')
		{
			SetVar('date.min', $_d['q'][2]);
			SetVar('date.max', $_d['q'][3]);
			$_d['movie.skipfs'] = true;
		}
	}

	function Link()
	{
		global $_d;
		$_d['movie.cb.head'][] = array(&$this, 'cb_movie_head');

		$type = Server::GetVar('filter.type');

		if ($type == 'cert')
		{
			$_d['movie.cb.query']['joins']['md'] =
				new Join($_d['movie_detail.ds'], "md_name = 'certification'
					AND md_movie = mov_id", 'LEFT JOIN');
			$checks = Server::GetVar('filter.checks');
			if (!empty($checks))
				$_d['movie.cb.query']['match']['md_value'] =
					Database::SqlIn($checks);
		}

		$min = Server::GetVar('filter.min');
		$max = Server::GetVar('filter.max');

		if (!empty($min) && !@$_d['movie.exclusive'])
		{
			if ($type == 'obtained')
			{
				$_d['movie.cb.query']['joins']['md'] =
					new Join($_d['movie_date.ds'], "md_name = 'obtained' AND md_movie = mov_id", 'LEFT JOIN');
				$_d['movie.cb.query']['match']['md_date'] =
					Database::SqlBetween(date('Y-m-d', $min), date('Y-m-d', $max+1));
				$_d['movie.cb.query']['order'] = array('md_date' => 'DESC');
			}
			else if ($type == 'rating')
			{
				$_d['movie.cb.query']['joins']['mf'] =
					new Join($_d['movie_float.ds'], "mf_name = 'rating' AND mf_movie = mov_id", 'LEFT JOIN');
				$_d['movie.cb.query']['match']['mf_value'] =
					Database::SqlBetween($min, $max);
				$_d['movie.cb.query']['order'] = array('mf_value' => 'DESC');
			}
			else $_d['movie.cb.query']['match'][$type] =
				Database::SqlBetween($min, $max);
		}
	}

	function cb_movie_head()
	{
		$t = new Template();
		return $t->ParseFile(Module::L('filter/t.xml'));
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'filter') return;

		if ($_d['q'][1] == 'content')
		{
			$t = new Template();
			die($t->ParseFile(Module::L('filter/content.xml')));
		}
		if ($_d['q'][1] == 'get')
		{
			$type = Server::GetVar('filter.type', 'mov_date');

			if ($type == 'YEAR(mov_date)')
			{
				$query = $_d['movie.cb.query'];
				$query['columns'] = array(
					'min' => Database::SqlUnquote('YEAR(min(mov_date))'),
					'max' => Database::SqlUnquote('YEAR(max(mov_date))')
				);
				$query['match'][$type] = Database::SqlMore('0000-00-00');

				$items = $_d['movie.ds']->Get($query);
			}
			else if ($type == 'obtained')
			{
				$q['columns'] = array(
					'min' => Database::SqlUnquote('UNIX_TIMESTAMP(min(md_date))'),
					'max' => Database::SqlUnquote('UNIX_TIMESTAMP(max(md_date))')
				);
				$q['match']['md_name'] = 'obtained';
				$q['match']['md_date'] = Database::SqlMore('0000-00-00');

				$items = $_d['movie_date.ds']->Get($q);
			}
			else if ($type == 'rating')
			{
				$q['columns'] = array(
					'min' => Database::SqlUnquote('min(CAST(md_value as DECIMAL))'),
					'max' => Database::SqlUnquote('max(CAST(md_value as DECIMAL))')
				);
				$q['match']['md_name'] = 'rating';

				$items = $_d['movie_detail.ds']->Get($q);
			}
			else if ($type == 'cert')
			{
				$items[0] = array(
					'source' => $type,
					'checks' => Server::GetVar('filter.checks', array())
				);
				die(json_encode($items[0]));
			}
			else
			{
				$items[0] = array('min' => 0, 'max' => 1);
			}
			$items[0]['cmin'] = Server::GetVar('filter.min', $items[0]['min']);
			$items[0]['cmax'] = Server::GetVar('filter.max', $items[0]['max']);
			$items[0]['source'] = $type;
			die(json_encode($items[0]));
		}
		if ($_d['q'][1] == 'set')
		{
			$type = $_SESSION['filter.type'] = $_d['q'][2];
			if ($type == 'cert')
				$_SESSION['filter.checks'] = Server::GetVar('checks');
			else // Date
			{
				$_SESSION['filter.min'] = $_d['q'][3];
				$_SESSION['filter.max'] = $_d['q'][4];
			}
			die(session_write_close());
		}
	}
}

class Filter
{
	static function Register($filter)
	{
		$_d['filter.filters'][] = $filter;
	}
}

class FilterReleased
{
	function __construct()
	{
		$this->_text = 'Released';
		$this->_col = 'YEAR(mov_date)';
		$this->_match = Database::SqlBetween(Server::GetVar('filter.min'),
			Server::GetVar('filter.max'));
	}
}

Filter::Register(new FilterReleased);

Module::Register('ModFilter');

?>
