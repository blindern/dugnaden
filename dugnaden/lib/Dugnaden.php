<?php

namespace Blindern\Dugnaden;

use Blindern\Dugnaden\Services\BeboerService;
use Blindern\Dugnaden\Services\DugnadService;

class Dugnaden
{
    private static $instance;

    /** @return Dugnaden */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new \Blindern\Dugnaden\Dugnaden();
        }

        return static::$instance;
    }

    /** @var \PDO */
    public $pdo;

    /** @var BeboerService */
    public $beboer;

    /** @var DugnadService */
    public $dugnad;

    function __construct()
    {
        $this->pdo = Db::get()->pdo;
        $this->beboer = new BeboerService($this);
        $this->dugnad = new DugnadService($this);
    }
}
