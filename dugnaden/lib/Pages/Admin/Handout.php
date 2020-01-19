<?php

namespace Blindern\Dugnaden\Pages\Admin;

class Handout extends BaseAdmin
{
    function show()
    {
        $this->page->setPrintView();

        $this->formdata["view"] = "Infoliste";

        $element_count = 0;

        $flyer = get_layout_parts("flyer_passord");

        $query = "SELECT
                beboer_id,
                beboer_passord,
                beboer_for,
                beboer_etter,
                (rom_nr + 0) AS rom_int,
                rom_nr AS rom_alpha,
                rom_type

            FROM bs_beboer

                LEFT JOIN bs_rom
                    ON rom_id = beboer_rom

            ORDER BY rom_int, rom_alpha, beboer_etter, beboer_for";

        $result = @run_query($query);

        $dugnadsledere = get_dugnadsledere();
        while ($row = @mysql_fetch_array($result)) {
            $undone_dugnads = get_undone_dugnads($row["beboer_id"]);

            if (!empty($undone_dugnads)) {
                $new_flyer = $flyer;

                $new_flyer["rom_info"] = get_public_lastname($row["beboer_etter"], $row["beboer_for"], false, true) . "<br />" .
                    (!strcmp($row["rom_int"], $row["rom_alpha"]) ? $row["rom_int"] : $row["rom_alpha"]) . $row["rom_type"] . $new_flyer["rom_info"];

                if ($element_count++ % 2) {
                    $new_flyer["format_print"] = "_break" . $new_flyer["format_print"];
                }

                $new_flyer["gutta"] = $dugnadsledere . $new_flyer["gutta"];
                $new_flyer["dugnad_url"] = DUGNADURL . $new_flyer["dugnad_url"];
                $new_flyer["dugnad_dugnad"] = $undone_dugnads . $new_flyer["dugnad_dugnad"];
                $new_flyer["passord"] = $row["beboer_passord"] . $new_flyer["passord"];

                $this->page->addContentHtml(implode($new_flyer));
            }
        }
    }
}
