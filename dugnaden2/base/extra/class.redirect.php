<?php

class redirect
{
	/** Standard plassering */
	public static $location = false;
	
	/** Standard plassering (utgangspunktet) */
	public static $from = false;
	
	/** I aktiv mappe */
	const CURRENT = 0;
	
	/** Fra sideroot */
	const ROOT = 1;
	
	/** Fra serverroot */
	const SERVER = 2;
	
	/** Absolutt adresse */
	const ABSOLUTE = 3;
	
	/** Sette standard */
	public static function store($location, $from = self::CURRENT)
	{
		self::$location = $location;
		self::$from = $from;
	}
	
	/** Redirecte */
	public static function handle($location  = false, $from = NULL, $https = NULL)
	{
		global $__server, $_base;
		if ($from === NULL) $from = self::CURRENT;
		
		if ($location === false)
		{
			// refresh
			if (self::$location)
			{
				$location = self::$location;
				$from = self::$from;
			}
			
			else
			{
				$location = PHP_SELF;
				$from = self::SERVER;
			}
		}
		
		// prefix
		$prefix = ((HTTPS && $https !== false) || $https) && $__server['https_support'] ? $__server['https_path'] : $__server['http_path'];
		
		// fra sideroot
		if ($from == self::ROOT)
		{
			if (substr($location, 0, 1) != "/") $location = "/" . $location;
			$location = $prefix . $__server['relative_path'] . $location;
		}
		
		// fra serverroot
		elseif ($from == self::SERVER)
		{
			if (substr($location, 0, 1) != "/") $location = "/" . $location;
			$location = $prefix.$location;
		}
		
		// aktiv mappe
		elseif ($from != self::ABSOLUTE)
		{
			$p = str_replace("\\", "/", dirname(PHP_SELF));
			if ($p == "/") $p = "";
			if (substr($location, 0, 1) != "/") $location = "/" . $location;
			$location = $prefix . $p . $location;
		}
		
		// send til siden
		@header("Location: $location");
		@ob_clean();
		die('<HTML><HEAD><TITLE>302 Found</TITLE></HEAD><BODY><H1>Found</H1>You have been redirected <A HREF="'.$location.'">here</A>.<P></BODY></HTML>');
	}
}