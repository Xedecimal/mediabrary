<?php

class ViewList extends Module
{
	public $Name = 'view_list';

	function __construct() { $this->CheckActive($this->Name); }

	function Link()
	{
		global $_d;

		$_d['nav.links']['Everything/List'] = 'view_list';
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		$t->Rewrite('item', array(&$this, 'TagItem'));
		return $t->ParseFile(Module::L('view_list/view_list.xml'));
	}

	function TagItem($t, $g)
	{
		global $_d;

		$cr = $_d['entry.ds']->find();

		return VarParser::Concat($g, $cr);
	}
}

Module::Register('ViewList');

?>
