<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;

class DugnadService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    public function getDugnadStatus()
    {
        // TODO: This will not return any dugnad if there are no deltaker - this is wrong?
        $sql =
            "SELECT
                COUNT(deltager_id) AS antall,
                deltager_dugnad AS id,
                dugnad_min_kids,
                dugnad_max_kids
            FROM bs_dugnad
            JOIN bs_deltager ON dugnad_id = deltager_dugnad
            WHERE
                dugnad_slettet = '0' AND
                dugnad_dato > NOW() + 7 AND
                dugnad_type = 'lordag'
            GROUP BY deltager_dugnad";

        $empty_dugnads = [];
        $full_dugnads = [];

        $stmt = $this->dugnaden->pdo->query($sql);
        foreach ($stmt as $row) {
            if ($row["antall"] <= $row['dugnad_min_kids']) {
                $empty_dugnads[$row["id"]] = "1";
            }

            if ($row["antall"] >= MAX_KIDS) {
                $full_dugnads[$row["id"]] = $row['dugnad_max_kids'];
            }
        }

        return [$empty_dugnads, $full_dugnads];
    }
}
