<?php

require "base/essentials.php";

$result = ess::$b->db->query("SELECT u_id, u_pass, u_pass_plain, u_fornavn, u_etternavn FROM users WHERE u_pass IS NULL");
while ($row = mysql_fetch_assoc($result))
{
	ess::$b->db->query("UPDATE users SET u_pass = ".ess::$b->db->quote(password::hash($row['u_pass_plain'], $row['u_id'], "user"))." WHERE u_id = {$row['u_id']}");
	echo "Fikset passord for {$row['u_fornavn']} {$row['u_etternavn']} ({$row['u_id']})<br />\n";
}