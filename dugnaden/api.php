<?php

// begrens tilgang
$allow = array(
	"83.143.83.35",   // blindern-studenterhjem.no
	"217.170.200.58", // blindern-studenterhjem.no
	#"37.191.203.140", // webdev.hsw.no (midlertidig adresse)
	#"37.191.201.59",  // darask-1301 (midlertidig adresse)
	gethostbyname("foreningenbs.no"),
);
if (!in_array($_SERVER['REMOTE_ADDR'], $allow)) die("Not authorized.");

require "script/default.php";
$request = isset($_GET['method']) ? $_GET['method'] : null;

if ($request == "list")
{
	$result = run_query("
		SELECT dugnad_id, dugnad_dato, dugnad_slettet, dugnad_checked,
		       deltager_id, deltager_gjort, deltager_type,
		       beboer_for, beboer_etter,
		       CONCAT(rom_nr, rom_type) rom
		FROM
		  bs_dugnad
		  LEFT JOIN bs_deltager ON dugnad_id = deltager_dugnad
		  LEFT JOIN bs_beboer   ON deltager_beboer = beboer_id
		  LEFT JOIN bs_rom      ON beboer_rom = rom_id
		ORDER BY dugnad_dato, beboer_for, beboer_etter");

	/*$t = array();
	while ($row = mysql_fetch_assoc($result))
	{
		$t[] = $row;
	};
	var_dump($t);*/

	$dugnader = array();
	while ($row = mysql_fetch_assoc($result))
	{
		if (!isset($dugnader[$row['dugnad_id']]))
		{
			$dugnader[$row['dugnad_id']] = array(
				'id' => $row['dugnad_id'],
				'date' => $row['dugnad_dato'],
				'deleted' => $row['dugnad_slettet'],
				'checked' => $row['dugnad_checked'],
				'people' => array()
			);
		}

		if ($row['deltager_id'] && $row['beboer_for'])
		{
			$dugnader[$row['dugnad_id']]['people'][] = array(
				'name' => $row['beboer_for'].' '.$row['beboer_etter'],
				'room' => $row['rom'],
				'done' => $row['deltager_gjort'],
				'type' => $row['deltager_type']
			);
		}
	}

	echo json_encode(array_values($dugnader));
	die;
}

die("Unknown action");
