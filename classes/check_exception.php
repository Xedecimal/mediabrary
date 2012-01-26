<?php

define('ACTION_SKIP', 0);
define('ACTION_RETRY', 1);

class CheckException extends Exception
{
	public $msg;
	public $source;
	public $action;

	public function __construct($msg, $action = null, $source = null)
	{
		$this->msg = $msg;
		$this->source = $source;
		$this->action = $action;
	}
}

?>
