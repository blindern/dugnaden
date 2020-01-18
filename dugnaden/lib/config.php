<?php

global $config_database;
$config_database = [
    "host" => $_ENV["DATABASE_HOST"],
    "username" => $_ENV["DATABASE_USER"],
    "password" => trim(file_get_contents("/run/secrets/database-password")),
    "dbname" => $_ENV["DATABASE_NAME"],
];

// Set this to true to be allowed to delete the DB at any time.
// If set to false, the database can not be deleted if
// the first week with dugnads has passed.
define("DEVELOPER_MODE", false);

define("DUGNADURL", "https://foreningenbs.no/dugnaden/");

/**
 * Set the maximum count for a dugnad before it is closed. A closed dugnad can not
 * be selected by the kids when they are changing their dugnad date.
 */
define("MAX_KIDS", 20);

/**
 * Set the minimum count for a dugnad before a kid no longer can leave that dugnad by
 * selectig another date. This is to prevent a dugnad to go empty of kids.
 */
define("MIN_KIDS", 10);

/** The size of a bot in NOK. */
define("ONE_BOT", 500);
