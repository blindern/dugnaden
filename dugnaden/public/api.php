<?php

// begrens tilgang
$allow = array(
    gethostbyname("web-1.zt.foreningenbs.no"),
);
if (!in_array($_SERVER['REMOTE_ADDR'], $allow)) die("Not authorized.");

require_once __DIR__ . "/../../vendor/autoload.php";

use \Blindern\Dugnaden\Db;

$request = isset($_GET['method']) ? $_GET['method'] : null;

if ($request == "list") {
    $stmt = Db::get()->pdo->query(
        "SELECT dugnad_id, dugnad_dato, dugnad_slettet, dugnad_checked, dugnad_type,
               deltager_id, deltager_gjort, deltager_type,
               beboer_for, beboer_etter,
               CONCAT(rom_nr, rom_type) rom
        FROM
          bs_dugnad
          LEFT JOIN bs_deltager ON dugnad_id = deltager_dugnad
          LEFT JOIN bs_beboer   ON deltager_beboer = beboer_id
          LEFT JOIN bs_rom      ON beboer_rom = rom_id
        ORDER BY dugnad_dato, beboer_for, beboer_etter");

    $dugnader = array();
    foreach ($stmt as $row) {
        if (!isset($dugnader[$row['dugnad_id']])) {
            $dugnader[$row['dugnad_id']] = array(
                'id' => $row['dugnad_id'],
                'date' => $row['dugnad_dato'],
                'deleted' => $row['dugnad_slettet'],
                'checked' => $row['dugnad_checked'],
                'type' => $row['dugnad_type'],
                'people' => array()
            );
        }

        if ($row['deltager_id'] && $row['beboer_for']) {
            $dugnader[$row['dugnad_id']]['people'][] = array(
                'name' => $row['beboer_for'] . ' ' . $row['beboer_etter'],
                'room' => $row['rom'],
                'done' => $row['deltager_gjort'],
                'type' => $row['deltager_type']
            );
        }
    }

    header("Content-Type: text/json");
    echo json_encode(array_values($dugnader));
    die;
}

die("Unknown action");
