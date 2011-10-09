<?php

class ViewList extends Module
{
	public $Name = 'view_list';

	private $_limit = 500;
	private $_page = 0;
	private $_sort = array();
	private $_q = array();

	function __construct() { $this->CheckActive($this->Name); }

	function Link()
	{
		global $_d;

		$_d['nav.links']['Everything/List'] = 'view_list';
		$_d['cb.list.item']['list'] = array(&$this, 'cb_list_item');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'items')
		{
			$this->_q = Server::GetVar('q');
			$this->_page = Server::GetVar('page', 0);
			$this->_sort = Server::GetVar('sort', array());
			die($this->cb_list_item());
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		return $t->ParseFile(Module::L('view_list/view_list.xml'));
	}

	function cb_list_item($t = null)
	{
		global $_d;

		$g = file_get_contents(Module::L('view_list/view_list_item.xml'));

		foreach ($this->_sort as &$v) $v = (int)$v;

		$m = array();
		if (!empty($this->_q))
		foreach ($this->_q as $col => $q)
			if (!empty($q))
				$m[$col] = new MongoRegex('/'.preg_quote($q).'/i');

		$cr = $_d['entry.ds']->find($m)
			->sort($this->_sort)
			->skip($this->_page*$this->_limit)
			->limit($this->_limit);

		$res = array();
		foreach ($cr as $i)
		{
			# We do not have this item.
			if (empty($i['path']))
			{
				# Release date is available.
				if (!empty($i['released']))
					# Has not yet been released, we can't get it.
					if ($i['released']->sec > time())
						$i['class'] = 'unavail';
					# Has been released.
					else $i['class'] = 'missing';
				# Should be available.
				else $i['class'] = 'missing';
			}
			else $i['class'] = 'existing';

			if (!empty($i['released']))
				if (is_object($i['released']))
					$i['released'] = date('Y-m-d', $i['released']->sec);

			$res[] = $i;
		}

		return VarParser::Concat($g, $res);
	}
}

Module::Register('ViewList');

?>
