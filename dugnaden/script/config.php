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

define("DEVELOPER_MODE", false);

/* Version number
------------------------------------------------------------------------------------------ */
define("VERSION", "v2.91-henrist");

/* Setting the url for the dugnad
------------------------------------------------------------------------------------------ */
define("DUGNADURL", "http://blindern-studenterhjem.no/dugnaden/");

/* This is only used when the database does not include any valid passwords
------------------------------------------------------------------------------------------ */
define("SUPERUSER", "DLWHBS");

/* Set the maximum count for a dugnad before it is closed. A closed dugnad can not
be selected by the kids when they are changing their dugnad date.
------------------------------------------------------------------------------------------ */
define("MAX_KIDS", 20);

/* Set the minimum count for a dugnad before a kid no longer can leave that dugnad by
selectig another date. This is to prevent a dugnad to go empty of kids.
------------------------------------------------------------------------------------------ */
define("MIN_KIDS", 10);

/* The size of a bot.
------------------------------------------------------------------------------------------ */
define("ONE_BOT", 500);

/* Show buatelefon.
------------------------------------------------------------------------------------------ */
define("SHOW_BUATELEFON", true);
