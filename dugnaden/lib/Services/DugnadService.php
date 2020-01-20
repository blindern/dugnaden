<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Dugnad;

class DugnadService
{
    /** @var Dugnaden */
    private $dugnaden;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Dugnad */
    public function getById($id)
    {
        // TODO: Can we model this in a better way?
        if ($id == -2 || $id == -3 || $id == -10 || $id == -11 || $id == -12) {
            $dugnad = new Dugnad();
            $dugnad->id = (int)$id;
            return $dugnad;
        }

        $sql =
            "SELECT *
            FROM bs_dugnad
            WHERE dugnad_id = ?";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return Dugnad::fromRow($row);
    }

    /**
     * Get list of all dugnad.
     *
     * @return Dugnad[]
     */
    public function getAll($includeDeleted = false)
    {
        $where = $includeDeleted ? "" : "WHERE dugnad_deleted = 0";

        $sql =
            "SELECT *
            FROM bs_dugnad
            $where
            ORDER BY dugnad_dato";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute();

        $result = [];
        foreach ($stmt as $row) {
            $result[] = Dugnad::fromRow($row);
        }

        return $result;
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

    /**
     * Get a list of dugnad and its deltager count.
     *
     *   [dugnad_id => count]
     */
    public function getDugnadDeltagerCountAll()
    {
        $sql =
            "SELECT
                dugnad_id,
                count(*) AS count
            FROM bs_dugnad
            LEFT JOIN bs_deltager ON
                dugnad_id = deltager_dugnad AND
                dugnad_slettet = 0
            GROUP BY dugnad_id";

        $stmt = $this->dugnaden->pdo->query($sql);

        $list = [];
        foreach ($stmt as $row) {
            $list[$row["dugnad_id"]] = $row["count"];
        }

        return $list;
    }

    /**
     * Get number of deltager for a specific dugnad.
     */
    public function getDugnadDeltagerCount(Dugnad $dugnad)
    {
        $sql =
            "SELECT count(*)
            FROM bs_dugnad
            JOIN bs_deltager ON
              dugnad_id = deltager_dugnad AND
              dugnad_slettet = 0
            WHERE dugnad_id = ?";

        $stmt = $this->dugnaden->pdo->prepare($sql);
        $stmt->execute([$dugnad->id]);
        $count = $stmt->fetchColumn();
        if ($count === false) {
            return 0;
        } else {
            return $count;
        }
    }

    /**
     * Get future lørdag dugnad sorted by time.
     *
     * @return Dugnad[]
     */
    public function getFutureLoerdagDugnadList()
    {
        $sql =
            "SELECT *
            FROM bs_dugnad
            WHERE
                dugnad_dato > curdate() AND
                dugnad_slettet = '0' AND
                dugnad_type = 'lordag'
            ORDER BY dugnad_dato";

        $stmt = $this->dugnaden->pdo->query($sql);

        $result = [];
        foreach ($stmt as $row) {
            $result[] = Dugnad::fromRow($row);
        }

        return $result;
    }

    /**
     * Mark dugnad as completed.
     */
    public function markCompleted(Dugnad $dugnad)
    {
        $sql = "UPDATE bs_dugnad SET dugnad_checked = 1 WHERE dugnad_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$dugnad->id]);
    }

    /**
     * Undo mark dugnad as completed.
     */
    public function markCompletedUndo(Dugnad $dugnad)
    {
        $sql = "UPDATE bs_dugnad SET dugnad_checked = 0 WHERE dugnad_id = ?";
        $this->dugnaden->pdo->prepare($sql)->execute([$dugnad->id]);
    }
}
