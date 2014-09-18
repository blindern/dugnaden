<?php

/* SQL Database init
------------------------------------------------------------------------------------------ */
$srv = "localhost";
$usr = "dugnaden";
$pas = require "pass.php";
$db  = "dugnaden";

// Set this to true to be allowed to delete the DB at any time.
// If set to false, the database can not be deleted if
// the first week with dugnads has passed.

define(DEVELOPER_MODE, false);

?>
