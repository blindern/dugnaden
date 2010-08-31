<?php

class pagedata
{
	public $path;
	public $path_parts;
	
	public $doc_path = "";
	public $redirect;
	
	public function __construct()
	{
		$this->load_page_path();
	}
	
	public function load_page_path()
	{
		if (!isset($_SERVER['REDIRECT_URL']) && !isset($_SERVER['PATH_INFO']))
		{
			$this->path = "";
			$this->path_parts = array();
			return;
		}
		$script = $_SERVER['SCRIPT_NAME'];
		
		$this->doc_path = substr($script, 0, strrpos($script, "/"));
		
		if (isset($_SERVER['REDIRECT_URL']))
		{
			$this->redirect = $_SERVER['REDIRECT_URL'];
			$this->path = substr($this->redirect, strlen($this->doc_path)+1);
		}
		else
		{
			$this->path = substr($_SERVER['PATH_INFO'], 1);
		}
		
		$this->path_parts = explode("/", $this->path);
	}
}