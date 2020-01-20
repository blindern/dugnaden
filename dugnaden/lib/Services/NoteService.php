<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Note;

class NoteService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Note */
    public function getById($id)
    {
        $sql =
            "SELECT *
            FROM bs_notat
            WHERE notat_id = ?";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Note::fromRow($row);
    }

    public function delete(Note $note)
    {
        $this->deleteById($note->id);
    }

    public function deleteById($id)
    {
        $sql = "DELETE FROM bs_notat WHERE notat_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$id]);
    }

    /** @return Note */
    public function create(Beboer $beboer, string $text)
    {
        $sql = "INSERT INTO bs_notat (notat_txt, notat_beboer) VALUES (?, ?)";
        $this->dugnaden->pdo->prepare($sql)->execute([$text, $beboer->id]);

        $id = $this->dugnaden->pdo->lastInsertId();
        return $this->getById($id);
    }
}
