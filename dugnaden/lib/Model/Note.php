<?php

namespace Blindern\Dugnaden\Model;

class Note
{
    /** @var int */
    public $id;

    /** @var string */
    public $text;

    /** @var int */
    public $beboerId;

    // /** @var int */
    // public $type;

    public static function fromRow($row)
    {
        $data = new Note();
        $data->id = $row["notat_id"];
        $data->text = $row["notat_txt"];
        $data->beboerId = $row["notat_beboer"];
        // $data->type = $row["notat_mottaker"];
        return $data;
    }
}
