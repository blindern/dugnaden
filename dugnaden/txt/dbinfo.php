<?php

/* SQL Database init
------------------------------------------------------------------------------------------ */
$srv = $_ENV["DATABASE_HOST"];
$usr = $_ENV["DATABASE_USER"];
$pas = trim(file_get_contents("/run/secrets/database-password"));
$db  = $_ENV["DATABASE_NAME"];

// Set this to true to be allowed to delete the DB at any time.
// If set to false, the database can not be deleted if
// the first week with dugnads has passed.

define('DEVELOPER_MODE', false);

?>
