<?php

require "base.php";

class pageobj extends pagehandle
{
	public function __construct()
	{
		parent::__construct();
		
		// koble opp database
		$this->db = new db_wrap();
		$this->db->connect("127.0.0.1", "blindern", "dugnaden", "blindern");
		
		$this->load_template("page_hovedsiden");
	}
}

new pageobj();