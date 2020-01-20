<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Deltager;
use Blindern\Dugnaden\Model\Dugnad;
use Exception;

class DeltagerService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Deltager|null */
    public function getById($id)
    {
        $sql =
            "SELECT *
            FROM bs_deltager
            WHERE deltager_id = ?";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Deltager::fromRow($row);
    }

    /** @return Deltager|null */
    public function getByBeboerAndDugnad(Beboer $beboer, Dugnad $dugnad)
    {
        $sql =
            "SELECT *
            FROM bs_deltager
            WHERE deltager_beboer = ? AND deltager_dugnad = ?
            LIMIT 1";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$beboer->id, $dugnad->id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Deltager::fromRow($row);
    }

    /** @return Deltager[] */
    public function getListByBeboer(Beboer $beboer)
    {
        $sql =
            "SELECT *
            FROM bs_deltager
            WHERE deltager_beboer = ?
            LIMIT 1";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$beboer->id]);

        $result = [];
        foreach ($stmt as $row) {
            $result[] = Deltager::fromRow($row);
        }

        return $result;
    }

    public function updateDone(Deltager $deltager, $done)
    {
        $sql = "UPDATE bs_deltager SET deltager_gjort = ? WHERE deltager_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$done, $deltager->id]);

        $deltager->done = (int)$done;
    }

    public function delete(Deltager $deltager)
    {
        $this->deleteById($deltager->id);
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM bs_deltager WHERE deltager_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$id]);
    }

    public function deleteAllForBeboer(Beboer $beboer)
    {
        $sql = "DELETE FROM bs_deltager WHERE deltager_beboer = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$beboer->id]);
    }

    public function updateSpecialDugnad(Deltager $deltager, $dugnadId)
    {
        if (!($dugnadId < -1)) {
            throw new Exception("Invalid dugnad ID");
        }

        $sql = "UPDATE bs_deltager SET deltager_dugnad = ? WHERE deltager_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$dugnadId, $deltager->id]);
    }

    /** @return boolean */
    public function updateDugnad(Deltager $deltager, Dugnad $dugnad)
    {
        // Do not allow two on the same day.
        $sql =
            "SELECT 1
            FROM bs_deltager
            WHERE deltager_dugnad = ? AND deltager_beboer = ? AND deltager_id != ?";
        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$dugnad->id, $deltager->beboerId, $deltager->id]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        $sql = "UPDATE bs_deltager SET deltager_dugnad = ? WHERE deltager_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$dugnad->id, $deltager->id]);

        $deltager->dugnadId = $dugnad->id;
        $deltager->preloadDugnad($dugnad);

        return true;
    }

    /** @return Deltager */
    public function createVedlikeholdDugnad(Beboer $beboer)
    {
        $sql =
            "INSERT INTO bs_deltager (
                deltager_beboer,
                deltager_dugnad,
                deltager_gjort,
                deltager_type,
                deltager_notat
            ) VALUES (?, ?, ?, ?, ?)";

        $this->dugnaden->pdo->prepare($sql)->execute([
            $beboer->id,
            -2,
            0,
            0,
            "Opprettet av Vedlikehold",
        ]);

        return $this->getById($this->dugnaden->pdo->lastInsertId());
    }

    /** @return Deltager */
    public function createDugnad(Beboer $beboer, Dugnad $dugnad)
    {
        return $this->createDugnadCustom($beboer, $dugnad, 1, "Opprettet dugnad.");
    }

    /** @return Deltager */
    public function createDugnadCustom(Beboer $beboer, Dugnad $dugnad, $type, $notat)
    {
        $sql =
            "INSERT INTO bs_deltager (
                deltager_beboer,
                deltager_dugnad,
                deltager_type,
                deltager_notat
            ) VALUES (?, ?, ?, ?)";

        $this->dugnaden->pdo->prepare($sql)->execute([
            $beboer->id,
            $dugnad->id,
            $type,
            $notat,
        ]);

        return $this->getById($this->dugnaden->pdo->lastInsertId());
    }

    /** @return boolean */
    // TODO: Remove?
    public function hasCreatedNew(Deltager $deltager, $deltagerGjort)
    {
        $sql = "SELECT 1 FROM bs_deltager WHERE deltager_notat = ? LIMIT 1";
        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$deltager->createNewNotat($deltagerGjort)]);
        return !!$stmt->fetchColumn();
    }

    /**
     * Get deltager created as a "straff" for a given dugnad.
     */
    public function getDeltagerStraffFor(Beboer $beboer, Dugnad $dugnad)
    {
        $note = "Straff fra uke {$dugnad->getWeekNumber()}.";

        $sql =
            "SELECT *
            FROM bs_deltager
            WHERE deltager_beboer = ? AND deltager_notat = ?
            LIMIT 1";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$beboer->id, $note]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Deltager::fromRow($row);
    }
}
