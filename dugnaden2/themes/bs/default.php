<?php

if (!defined("SCRIPT_START")) {
	die("Mangler hovedscriptet! Kan ikke fortsette!");
}

class theme_bs_default
{
	protected static $date_now;
	protected static $class_browser;
	
	/**
	 * Behandle template
	 */
	public static function main()
	{
		self::$date_now = ess::$b->date->get();
		
		global $class_browser;
		require "include_top.php";
		
		self::$class_browser = $class_browser;
		self::generate_page();
	}
	
	protected static function generate_page()
	{
		echo '<!DOCTYPE html>
<html lang="no">
<head>
<title>'.ess::$b->page->generate_title().'</title>'.ess::$b->page->generate_head().'</head>
<body class="'.self::$class_browser.'">'.ess::$b->page->body_start.'
	<section id="default_content">'.ess::$b->page->content.'</section>
	
	
	<!--
	'.self::$date_now->format(date::FORMAT_SEC).'
	Script: '.round(microtime(true)-SCRIPT_START-ess::$b->db->time, 4).' sek
	Database: '.round(ess::$b->db->time, 4).' sek ('.ess::$b->db->queries.' spørring'.(ess::$b->db->queries == 1 ? '' : 'er').')
	-->
'.ess::$b->page->body_end;
		
		// debug time
		$time = SCRIPT_START;
		ess::$b->dt("end");
		$dt = 'start';
		foreach (ess::$b->time_debug as $row)
		{
			$dt .= ' -> '.round(($row[1]-$time)*1000, 2).' -> '.$row[0];
			$time = $row[1];
		}
		
		echo '
	<!-- '.$dt.' -->
</body>
</html>';
	}
}

theme_bs_default::main();