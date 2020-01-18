<?php

namespace Blindern\Dugnaden;

use PDO;

class Db
{
    private static $instance;
    public static function get()
    {
        if (!static::$instance) {
            require_once "config.php";

            global $config_database;
            static::$instance = new Db(
                $config_database["host"],
                $config_database["username"],
                $config_database["password"],
                $config_database["dbname"]
            );
        }

        return static::$instance;
    }

    public $pdo;

    function __construct($host, $username, $password, $dbname)
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
    }
}
