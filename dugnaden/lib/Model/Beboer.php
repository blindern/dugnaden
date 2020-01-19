<?php

namespace Blindern\Dugnaden\Model;

use Blindern\Dugnaden\Dugnaden;

class Beboer
{
    const SPECIAL_NORMAL = 0;
    const SPECIAL_ELEPHANT = 2;
    const SPECIAL_FF = 4;
    const SPECIAL_DUGNADSFRI = 6;
    const SPECIAL_BLIVENDE_ELEPHANT = 8;

    /** @var int */
    public $id;

    /** @var string */
    public $firstName;

    /** @var string */
    public $lastName;

    /** @var int */
    public $roomId;

    /** @var string */
    // NOTE: This is not encrypted. Would be nice if we can switch all users to SAML later.
    public $password;

    /** @var int */
    public $specialId;

    public static function fromRow($row)
    {
        $data = new Beboer();
        $data->id = $row["beboer_id"];
        $data->firstName = $row["beboer_for"];
        $data->lastName = $row["beboer_etter"];
        $data->roomId = $row["beboer_rom"];
        $data->password = $row["beboer_passord"];
        $data->specialId = $row["beboer_spesial"];
        return $data;
    }

    public function getName()
    {
        return $this->firstName . " " . $this->lastName;
    }

    public function getNameTruncated()
    {
        // Don't show surnames that we cannot truncate.
        if (strlen($this->lastName) <= 4) {
            return $this->firstName;
        }

        return $this->firstName . " " . utf8_substr($this->lastName, 0, 4) . "...";
    }

    /** @return Room */
    public function getRoom()
    {
        return Dugnaden::get()->room->getById($this->roomId);
    }

    public function getDugnadslederPhone()
    {
        if ($this->firstName == "Karl-Martin" && $this->lastName == "Svastuen") {
            return "971 59 266";
        }

        if ($this->firstName == "Mathias Løland" && $this->lastName == "Velle") {
            return "412 14 541";
        }

        return null;
    }
}
