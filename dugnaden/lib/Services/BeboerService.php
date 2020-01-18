<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;

class BeboerService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    function updatePassword($id, $password)
    {
        $sql = "UPDATE bs_beboer SET beboer_passord = ? WHERE beboer_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$password, $id]);
    }
}
