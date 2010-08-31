<?php

class timespan
{
	// konstanter
	const TIME_FULL = 1; // sekunder
	const TIME_PARTIAL = 2; // sek
	const TIME_SHORT = 4; // s
	const TIME_PAST = 8;
	const TIME_FUTURE = 16;
	const TIME_NOBOLD = 32;
	const TIME_ALL = 64;
	const TIME_ABS = 128;
	
	/**
	 * Kalkuler hvor lang tid noe tar/har tatt
	 * @param integer $secs antall sekunder (eller tidspunkt hvis TIME_ABS er satt)
	 * @param integer $modifiers (standard: TIME_PARTIAL, TIME_FUTURE
	 */
	public static function format($secs, $modifiers = 0, $max = 2)
	{
		global $_lang;
		
		// kalkulere tiden?
		if ($modifiers & self::TIME_ABS)
		{
			if ($secs == 0)
			{
				return 'ikke tilgjengelig';
			}
			
			$secs = abs(time() - $secs);
		}
		
		$secs = round($secs);
		
		// begrens til $max egenskaper
		$data = array();
		
		// antall minutter
		if ($secs > 59)
		{
			// antall timer
			if ($secs > 3599)
			{
				// antall dager
				if ($secs > 86399)
				{
					// antall uker
					if ($secs > 604799)
					{
						$ant = floor($secs / 604800);
						$data["weeks"] = $ant;
						$secs -= $ant * 604800;
					}
					
					// dager
					$ant = floor($secs / 86400);
					if ($ant > 0 || $modifiers & self::TIME_ALL) $data["days"] = $ant;
					$secs -= $ant * 86400;
				}
				
				// timer
				$ant = floor($secs / 3600);
				if ($ant > 0 || $modifiers & self::TIME_ALL) $data["hours"] = $ant;
				$secs -= $ant * 3600;
			}
			
			// minutter
			$ant = floor($secs / 60);
			if ($ant > 0 || $modifiers & self::TIME_ALL) $data["minutes"] = $ant;
			$secs -= $ant * 60;
		}
		
		// sekunder
		if ($secs > 0 || ($modifiers & self::TIME_ALL && count($data) > 0)) $data["seconds"] = $secs;
		
		$data = array_slice($data, 0, $max, true);
		$ret = array();
		$bold = !($modifiers & self::TIME_NOBOLD);
		$type = $modifiers & self::TIME_SHORT ? 'short' : ($modifiers & self::TIME_FULL ? 'full' : 'partial');
		$typesplit = $modifiers & self::TIME_SHORT ? '' : ' ';
		foreach ($data as $i => $v)
		{
			$ret[] = ($bold ? "<b>$v</b>" : $v).$typesplit.$_lang[$i][$type][$v == 1 ? 0 : 1];
		}
		
		$timetype = count($ret) > 0 && $modifiers & self::TIME_PAST ? ' siden' : '';
		if (count($ret) == 0) $ret = array("akkurat nå");
		$last = array_pop($ret); 
		$lastsplit = $type == "full" ? ' og ' : ' ';
		return (count($ret) > 0 ? implode(" ", $ret) . $lastsplit : '') . $last . $timetype;
	}
}