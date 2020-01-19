<?php

namespace Blindern\Dugnaden\Model;

class Fee
{
    /** @var int */
    public $id;

    /** @var int */
    public $registered;

    /** @var int */
    public $deltagerId;

    /** @var int */
    public $beboerId;

    /** @var int */
    public $revoked;

    public static function fromRow($row)
    {
        $data = new Fee();
        $data->id = $row["bot_id"];
        $data->registered = $row["bot_registrert"];
        $data->deltagerId = $row["bot_deltager"];
        $data->beboerId = $row["bot_beboer"];
        $data->revoked = $row["bot_annulert"];
        return $data;
    }
}
