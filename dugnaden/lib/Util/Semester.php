<?php

namespace Blindern\Dugnaden\Util;

class Semester
{
    public $year;
    public $semester;

    /** @return Semester */
    public static function getNextSemester()
    {
        $year = (int)date("Y", time());
        $month = (int)date("m", time());

        if ($month > 7) {
            $year++;
        }

        $semester = new Semester();
        $semester->year = $year;
        $semester->semester = $month > 7 ? "V" : "H";
    }

    public function str()
    {
        return $this->semester . $this->year;
    }
}
