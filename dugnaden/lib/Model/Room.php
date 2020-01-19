<?php

namespace Blindern\Dugnaden\Model;

class Room
{
    /** @var int */
    public $id;

    /** @var string */
    public $nr;

    /** @var string */
    public $type;

    public static function fromRow($row)
    {
        $data = new Room();
        $data->id = $row["rom_id"];
        $data->nr = $row["rom_nr"];
        $data->type = $row["rom_type"];
        return $data;
    }

    public function getPretty()
    {
        return $this->nr . $this->type;
    }
}
