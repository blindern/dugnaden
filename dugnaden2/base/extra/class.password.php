<?php

class password
{
	const LEVEL_STRONG = 1; // min 8, non-cap, cap, numbers
	const LEVEL_LONG = 2; // min 8
	const LEVEL_WEAK = 3; // min 3
	const LEVEL_LOGIN = 4; // minst 6 tegn
	const ERROR_SHORT = 1; // kort passord
	const ERROR_NONCAP = 2; // mangler små bokstaver
	const ERROR_CAP = 4; // mangler store bokstaver
	const ERROR_NUM = 8; // mangler nummer
	const ERROR_EASY = 16; // for lett passord
	
	/**
	 * Kontroller passord sikkerhet
	 *
	 * @param string passodet $password
	 * @param int nivået $level
	 * @return errors
	 */
	public static function validate($password, $level = self::LEVEL_STRONG)
	{
		$error = 0;
		$password = trim($password);
		
		switch ($level)
		{
			case self::LEVEL_STRONG:
				if (!preg_match("/[a-zæøå]/", $password))
				{
					$error |= self::ERROR_NONCAP;
				}
				
				if (!preg_match("/[A-ZÆØÅ]/", $password))
				{
					$error |= self::ERROR_CAP;
				}
				
				if (!preg_match("/\\d/", $password))
				{
					$error |= self::ERROR_NUM;
				}
				
			case self::LEVEL_LONG:
				if (strlen($password) < 8)
				{
					$error |= self::ERROR_SHORT;
				}
			case self::LEVEL_LOGIN:
				if (strlen($password) < 6) $error |= self::ERROR_SHORT;
				if (strpos($password, "12345") !== false) $error |= self::ERROR_EASY;
			break;
				
			case self::LEVEL_WEAK:
				if (strlen($password) < 3) $error |= self::ERROR_SHORT;
			break;
			
			case self::LEVEL_LOGIN:
				if (strlen($password) < 3) $error |= self::ERROR_SHORT;
		}
		
		return $error;
	}
	
	/**
	 * Formatter tekst for bestemt passordfeil
	 * 
	 * @param int passordfeil $error
	 * @return string
	 */
	public static function format_errors($error)
	{
		$errors = array();
		
		if ($error & self::ERROR_SHORT) $errors[] = 'lengde';
		if ($error & self::ERROR_NONCAP) $errors[] = 'små bokstaver';
		if ($error & self::ERROR_CAP) $errors[] = 'store bokstaver';
		if ($error & self::ERROR_NUM) $errors[] = 'tall';
		
		return $errors;
	}
	
	/**
	 * Krypter (generer) passord hash/verdi
	 *
	 * @param string passordet $password
	 * @param string ekstra $more
	 * @param string type $type
	 * @return unknown
	 */
	// krypter/generer passord verdi
	public static function hash($password, $more = '', $type = "combine")
	{
		switch ($type)
		{
			case "combine":
			case "user":
				return md5(sha1($password . $more) . $more);
		}
		
		throw new HSException("Unknown password hash type.");
	}
}