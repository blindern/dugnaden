<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Beboer;

class DugnadslederService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Beboer[] */
    public function getList()
    {
        $sql =
            "SELECT bs_beboer.*
            FROM bs_innstillinger
            JOIN bs_beboer ON
                beboer_id = innstillinger_verdi
            WHERE innstillinger_felt = 'dugnadsleder'";

        $stmt = $this->dugnaden->pdo->query($sql);
        $result = [];
        foreach ($stmt as $row) {
            $result[] = Beboer::fromRow($row);
        }
        return $result;
    }

    public function isDugnadsleder(Beboer $beboer)
    {
        $sql =
            "SELECT 1
            FROM bs_innstillinger
            WHERE
                innstillinger_felt = 'dugnadsleder' AND
                innstillinger_verdi = ?";
        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$beboer->id]);
        return !!$stmt->fetchColumn();
    }

    public function delete(Beboer $beboer)
    {
        $this->deleteById($beboer->id);
    }

    public function deleteById($id)
    {
        $sql =
            "DELETE FROM bs_innstillinger
            WHERE
                innstillinger_felt = 'dugnadsleder' AND
                innstillinger_verdi = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$id]);
    }

    public function assign(Beboer $beboer)
    {
        if ($this->isDugnadsleder($beboer)) return;

        $sql =
            "INSERT INTO bs_innstillinger (innstillinger_felt, innstillinger_verdi)
            VALUES ('dugnadsleder', ?)";

        $this->dugnaden->pdo->prepare($sql)->execute([$beboer->id]);
    }
}
