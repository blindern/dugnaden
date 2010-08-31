<?php

// hentes fra cache hvis mulig
settings::init();

class settings
{
	/** Innstillinger */
	public static $data;
	
	/**
	 * Init: Last inn innstillinger fra cache hvis mulig
	 */
	public static function init()
	{
		// hent innstillinger
		self::$data = cache::fetch("settings");
		if (!self::$data)
		{
			// hent ferske innstillinger
			self::reload_db();
		}
	}
	
	/**
	 * Last inn innstillinger fra databasen
	 */
	public static function reload_db()
	{
		$result = ess::$b->db->query("SELECT set_id, set_name, set_value FROM settings");
		self::$data = array();
		while ($row = mysql_fetch_assoc($result))
		{
			self::$data[$row['set_name']] = $row['set_value'];
		}
		
		// lagre til cache
		self::cache_store();
	}
	
	/**
	 * Hent innstilling
	 */
	public static function item_get($name, $default = null)
	{
		if (!isset(self::$data[$name])) return $default;
		return self::$data[$name];
	}
	
	/**
	 * Lagre innstilling
	 */
	public static function item_set($name, $value)
	{
		self::$data[$name] = $value;
		
		// oppdater databasen
		ess::$b->db->query("
			INSERT INTO settings SET set_value = ".ess::$b->db->quote($value)."
			WHERE set_name = ".ess::$b->db->quote($name)."
			ON DUPLICATE KEY UPDATE set_value = VALUES(set_value)");
		
		// oppdater cache
		self::cache_store();
	}
	
	/**
	 * Lagre i cache
	 */
	protected static function cache_store()
	{
		// behold for 10 minutter
		cache::store("settings", self::$data, 600);
	}
}