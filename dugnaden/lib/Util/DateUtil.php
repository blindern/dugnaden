<?php

namespace Blindern\Dugnaden\Util;

use DateTime;

class DateUtil
{
    /**
     * Format the date.
     */
    public static function formatDate($date)
    {
        $d = new DateTime($date);
        return $d->format("d.m.Y");
    }

    /**
     * Format the date (short year).
     */
    public static function formatDateShort($date)
    {
        $d = new DateTime($date);
        return $d->format("d.m.y");
    }
}
