<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Deltager;
use Blindern\Dugnaden\Model\Dugnad;
use Blindern\Dugnaden\Model\Fee;

class FeeService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Fee|null */
    public function getById($id)
    {
        $sql =
            "SELECT *
            FROM bs_bot
            WHERE bot_id = ?";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Fee::fromRow($row);
    }

    /** @return Fee|null */
    public function getByBeboerAndDugnad(Beboer $beboer, Dugnad $dugnad)
    {

        $sql =
            "SELECT bs_bot.*
            FROM bs_bot
            JOIN bs_deltager ON deltager_id = bot_deltager
            WHERE deltager_beboer = ? AND deltager_dugnad = ?
            LIMIT 1";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$beboer->id, $dugnad->id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Fee::fromRow($row);
    }

    public function delete(Fee $fee)
    {
        $this->deleteById($fee->id);
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM bs_bot WHERE bot_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$id]);
    }

    /** @return Fee */
    public function create(Deltager $deltager)
    {
        // TODO: What about bot_beboer field?

        $sql = "INSERT INTO bs_bot (bot_deltager) VALUES (?)";
        $this->dugnaden->pdo->prepare($sql)->execute([$deltager->id]);

        $id = $this->dugnaden->pdo->lastInsertId();
        return $this->getById($id);
    }

    /** @return Fee */
    public function createRevoke(Beboer $beboer)
    {
        $sql = "INSERT INTO bs_bot (bot_beboer, bot_annulert) VALUES (?, ?)";
        $this->dugnaden->pdo->prepare($sql)->execute([$beboer->id, -1]);

        $id = $this->dugnaden->pdo->lastInsertId();
        return $this->getById($id);
    }
}
