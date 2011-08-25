<?php

require "base.php";

class pageobj extends pagehandle
{
	public function __construct()
	{
		parent::__construct();
		
		// koble opp database
		$this->db = new db_wrap();
		require_once "/etc/mysqlserver.php";
		$this->db->connect($mysqlserver, "blindern", "dugnaden", "blindern");
		
		$this->load_template("page_hovedsiden");
	}
}

new pageobj();
