<?php

new base();
class base
{
	public function __construct()
	{
		define("BASE_LOADED", true);
		
		// starter utdata buffer
		ob_start();
		
		// utf-8
		header("Content-Type: text/html; charset=utf-8");
		
		// hent essenntials
		require "essentials.php";
		ess::$b->base = $this;
		
		// kjør scheduler
		#if (!MAIN_SERVER) $this->scheduler();
		
		ess::$b->dt("load_es-gu_pre");
		
		// hent inn brukerinformasjon
		login::init();
		ess::$b->dt("post");
		
		// sjekk ssl
		$this->check_ssl();
		
		// brukerstæsj
		if (login::$logged_in)
		{
			$this->load_user_stuff();
		}
		
		define("SCRIPT_TIME_HALF", microtime(true)-SCRIPT_START);
		define("QUERIES_TIME_HALF", ess::$b->db->time);
		define("QUERIES_NUM_HALF", ess::$b->db->queries);
		
		ess::$b->dt("base_loaded");
	}
	
	/** Kjøre scheduler */
	protected function scheduler()
	{
		// kjør scheduler
		require_once ROOT . "/base/scripts/scheduler.php";
	}
	
	/** Kontroller SSL status */
	protected function check_ssl()
	{
		// kontroller https status
		if (defined("FORCE_HTTPS"))
		{
			force_https();
		}
		else
		{
			// ikke benytt https hvis ikke brukeren krever det
			if (!login::$logged_in || !login::$info['ses_secure'])
			{
				force_https(false);
			}
		}
	}
	
	/** Hent diverse bruker funksjoner */
	protected function load_user_stuff()
	{
		// queries info
		if (access::has("admin") && isset($_COOKIE['show_queries_info']))
		{
			define("SHOW_QUERIES_INFO", true);
		}
	}
}