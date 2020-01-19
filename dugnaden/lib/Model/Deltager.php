<?php

namespace Blindern\Dugnaden\Model;

use Blindern\Dugnaden\Dugnaden;

class Deltager
{
    const DONE_FUTURE = 0;
    const DONE_BOT_NY_DUGNAD = 1;
    const DONE_NY_DUGNAD = 2;
    const DONE_BOT = 3;

    /** @var int */
    public $id;

    /** @var int */
    public $beboerId;

    /**
     * Special values:
     *   -2 - dagdugnad
     *
     * @var int
     */
    public $dugnadId;

    /**
     * 0 = Normal dugnad, carried out by the beboer
     * 1 = Bot og ny dugnad
     * 2 = Kun ny dugnad
     * 3 = Kun bot
     * @var int
     */
    public $done;

    /** @var int */
    public $type;

    /** @var string */
    public $note;

    /** @var Dugnad */
    private $_dugnad;

    public static function fromRow($row)
    {
        $data = new Deltager();
        $data->id = $row["deltager_id"];
        $data->beboerId = $row["deltager_beboer"];
        $data->dugnadId = $row["deltager_dugnad"];
        $data->done = $row["deltager_gjort"];
        $data->type = $row["deltager_type"];
        $data->note = $row["deltager_notat"];
        return $data;
    }

    /** @return Dugnad */
    public function getDugnad()
    {
        if (!$this->_dugnad) {
            // TODO: Preloading.
            $this->_dugnad = Dugnaden::get()->dugnad->getById($this->dugnadId);
        }

        return $this->_dugnad;
    }

    public function preloadDugnad(Dugnad $dugnad)
    {
        $this->_dugnad = $dugnad;
    }

    /**
     * Create text used for note for a dugnad created due to another.
     */
    // TODO: Remove?
    public function createNewNotat($deltagerGjort)
    {
        return $this->id . "-" - $deltagerGjort;
    }
}
