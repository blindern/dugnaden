<?php

namespace Blindern\Dugnaden\Model;

use Blindern\Dugnaden\Util\DateUtil;
use DateTime;

class Dugnad
{
    /**
     * Special values not having a record in the database:
     *   -2 Dagdugnad
     *   -3 Utført
     *   -10 Hyttedugnad
     *   -11 Ryddevakt
     *   -12 Billavakt
     *
     * @var int
     */
    public $id;

    /** @var string */
    public $date;

    /** @var int */
    public $deleted;

    /** @var int */
    public $checked;

    /** @var string */
    public $type; // 'lordag','dagdugnad','anretning','vakt','ryddevakt'

    /** @var int */
    public $minKids;

    /** @var int */
    public $maxKids;

    public static function fromRow($row)
    {
        $data = new Dugnad();
        $data->id = $row["dugnad_id"];
        $data->date = $row["dugnad_dato"];
        $data->deleted = $row["dugnad_slettet"];
        $data->checked = $row["dugnad_checked"];
        $data->type = $row["dugnad_type"];
        $data->minKids = $row["dugnad_min_kids"];
        $data->maxKids = $row["dugnad_max_kids"];
        return $data;
    }

    public static function getTypePrefix($type)
    {
        switch ($type) {
            case 'anretning':
                return 'Anretning: ';
        }

        return '';
    }

    public function isFuture()
    {
        return strtotime($this->date) > time();
    }

    public function isDone()
    {
        return $this->checked == 1;
    }

    public function getWeekNumber()
    {
        $frags = explode("-", substr($this->date, 0, 10));
        $unixTimestamp = mktime(0, 0, 0, $frags[1], $frags[2], $frags[0]);
        return date("W", $unixTimestamp);
    }

    /**
     * Get the date formatted as YYYY-MM-DD.
     */
    public function getDate()
    {
        return substr($this->date, 0, 10);
    }

    public function formatDate()
    {
        return DateUtil::formatDate($this->date);
    }

    public function getDateObj()
    {
        return new DateTime($this->date);
    }

    public function offsetDaysFromToday()
    {
        $today = new DateTime(date("Y-m-d"));
        return $today->diff($this->getDateObj())->days;
    }

    public function getDayHeader()
    {
        $result = strtolower($this->getDateObj()->format("j. F"));

        if ($this->type === 'lordag') {
            $result .= " &nbsp;&nbsp; 10:00-14:00";
        } elseif ($this->type === 'anretning') {
            $result .= ' (anretningsdugnad)';
        }

        return $result;
    }
}
