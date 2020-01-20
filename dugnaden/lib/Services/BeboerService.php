<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Room;

class BeboerService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Beboer|null */
    public function getById($id)
    {
        $sql =
            "SELECT *
            FROM bs_beboer
            WHERE beboer_id = ?";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Beboer::fromRow($row);
    }

    /** @return Beboer|null */
    public function getByName($firstName, $lastName)
    {
        $sql =
            "SELECT *
            FROM bs_beboer
            WHERE beboer_for = ? AND beboer_etter = ?
            LIMIT 1";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$firstName, $lastName]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Beboer::fromRow($row);
    }

    /** @return Beboer[] */
    public function getAll()
    {
        $sql =
            "SELECT *
            FROM bs_beboer
            ORDER BY beboer_etter, beboer_for";

        $stmt = $this->dugnaden->pdo->query($sql);

        $result = [];
        foreach ($stmt as $row) {
            $result[] = Beboer::fromRow($row);
        }

        return $result;
    }

    /** @return Beboer[] */
    public function getAllSortByRoom()
    {
        $sql =
            "SELECT *
            FROM bs_beboer
            LEFT JOIN bs_rom ON
                rom_id = beboer_rom
            ORDER BY rom_nr + 0, rom_nr, beboer_etter, beboer_for";

        $stmt = $this->dugnaden->pdo->query($sql);

        $result = [];
        foreach ($stmt as $row) {
            $result[] = Beboer::fromRow($row);
        }

        return $result;
    }

    public function updatePassword($id, $password)
    {
        $sql = "UPDATE bs_beboer SET beboer_passord = ? WHERE beboer_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$password, $id]);
    }

    public function updateName($beboer, $firstName, $lastName)
    {
        $sql = "UPDATE bs_beboer SET beboer_for = ?, beboer_etter = ? WHERE beboer_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$firstName, $lastName, $beboer->id]);

        $beboer->firstName = $firstName;
        $beboer->lastName = $lastName;
    }

    public function updateRoom(Beboer $beboer, Room $room)
    {
        $sql = "UPDATE bs_beboer SET beboer_rom = ? WHERE beboer_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$room->id, $beboer->id]);

        $beboer->roomId = $room->id;
    }

    public function delete(Beboer $beboer)
    {
        $sql = "DELETE FROM bs_bot WHERE bot_beboer = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$beboer->id]);

        $this->dugnaden->deltager->deleteAllForBeboer($beboer);

        $sql = "DELETE FROM bs_notat WHERE notat_beboer = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$beboer->id]);

        $sql = "DELETE FROM bs_beboer WHERE beboer_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$beboer->id]);
    }

    /** @return boolean */
    public function setAsElephant(Beboer $beboer)
    {
        if (!$this->setAsSpecialId($beboer, Beboer::SPECIAL_ELEPHANT)) {
            return false;
        }

        $this->dugnaden->deltager->deleteAllForBeboer($beboer);
        return true;
    }

    /** @return boolean */
    public function setAsBlivendeElephant(Beboer $beboer)
    {
        if (!$this->setAsSpecialId($beboer, Beboer::SPECIAL_BLIVENDE_ELEPHANT)) {
            return false;
        }

        $deltagerList = $this->dugnaden->deltager->getListByBeboer($beboer);

        // Beboer will become an elefant and should only have one dugnad this semester.
        $toDelete = sizeof($deltagerList) - 1;
        foreach ($deltagerList as $deltager) {
            if ($toDelete <= 0) break;

            // Only delete what happens in the future.
            if ($deltager->getDugnad()->isFuture()) {
                $this->dugnaden->deltager->delete($deltager);
                $toDelete--;
            }
        }

        return true;
    }

    /** @return boolean */
    public function setAsFestforening(Beboer $beboer)
    {
        if (!$this->setAsSpecialId($beboer, Beboer::SPECIAL_FF)) {
            return false;
        }

        $this->dugnaden->deltager->deleteAllForBeboer($beboer);
        return true;
    }

    /** @return boolean */
    public function setAsDugnadsfri(Beboer $beboer)
    {
        if (!$this->setAsSpecialId($beboer, Beboer::SPECIAL_DUGNADSFRI)) {
            return false;
        }

        $this->dugnaden->deltager->deleteAllForBeboer($beboer);
        return true;
    }

    /** @return boolean */
    public function setAsNormal(Beboer $beboer)
    {
        return $this->setAsSpecialId($beboer, Beboer::SPECIAL_NORMAL);
    }

    /** @return boolean */
    private function setAsSpecialId(Beboer $beboer, $specialId)
    {
        if ($beboer->specialId == $specialId) {
            return false;
        }

        $sql = "UPDATE bs_beboer SET beboer_spesial = ? WHERE beboer_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$specialId, $beboer->id]);

        $beboer->specialId = $specialId;

        return true;
    }

    /**
     * Get the deltager imports list.
     *
     * @return string[]
     */
    public function getImportsList()
    {
        $sql =
            "SELECT DISTINCT deltager_notat AS notat
            FROM bs_deltager
            WHERE deltager_notat LIKE 'IMP%'
            ORDER BY deltager_notat DESC";

        $stmt = $this->dugnaden->pdo->query($sql);

        $result = [];
        foreach ($stmt as $row) {
            $result[] = $row["notat"];
        }

        return $result;
    }

    /**
     * Get list of beboer for a specific import.
     *
     * @return Beboer[]
     */
    public function getImportBeboerList($importNote)
    {
        $sql =
            "SELECT bs_beboer.*
            FROM bs_beboer
            JOIN (
                SELECT DISTINCT deltager_beboer
                FROM bs_deltager
                WHERE deltager_notat = ?
            ) ref ON
                beboer_id = deltager_beboer
            LEFT JOIN bs_rom ON
                rom_id = beboer_rom
            ORDER BY rom_nr + 0, rom_nr, beboer_etter, beboer_for";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$importNote]);

        $result = [];
        foreach ($stmt as $row) {
            $result[] = Beboer::fromRow($row);
        }

        return $result;
    }

    /**
     * Get name of next import which is used for the note.
     */
    public function getNextImportName()
    {
        $notes = $this->getImportsList();
        $numbers = [];
        foreach ($notes as $note) {
            $numbers[] = (int)substr($note, 3);
        }

        sort($numbers);

        $next = sizeof($numbers) == 0
            ? 1
            : $numbers[sizeof($numbers) - 1] + 1;

        return "IMP" . $next;
    }
}
