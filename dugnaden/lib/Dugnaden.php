<?php

namespace Blindern\Dugnaden;

use Blindern\Dugnaden\Services\BeboerService;
use Blindern\Dugnaden\Services\DeltagerService;
use Blindern\Dugnaden\Services\DugnadService;
use Blindern\Dugnaden\Services\DugnadslederService;
use Blindern\Dugnaden\Services\FeeService;
use Blindern\Dugnaden\Services\NoteService;
use Blindern\Dugnaden\Services\RoomService;
use PDO;

class Dugnaden
{
    private static $instance;

    /** @return Dugnaden */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new Dugnaden();
        }

        return static::$instance;
    }

    /** @var PDO */
    public $pdo;

    /** @var BeboerService */
    public $beboer;

    /** @var DeltagerService */
    public $deltager;

    /** @var DugnadService */
    public $dugnad;

    /** @var DugnadslederService */
    public $dugnadsleder;

    /** @var FeeService */
    public $fee;

    /** @var NoteService */
    public $note;

    /** @var RoomService */
    public $room;

    function __construct()
    {
        $this->pdo = Db::get()->pdo;
        $this->beboer = new BeboerService($this);
        $this->deltager = new DeltagerService($this);
        $this->dugnad = new DugnadService($this);
        $this->dugnadsleder = new DugnadslederService($this);
        $this->fee = new FeeService($this);
        $this->note = new NoteService($this);
        $this->room = new RoomService($this);
    }
}
