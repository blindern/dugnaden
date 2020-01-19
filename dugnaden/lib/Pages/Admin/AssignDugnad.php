<?php

namespace Blindern\Dugnaden\Pages\Admin;

/**
 * Gives all beboere 2 new dugnads.
 */
class AssignDugnad extends BaseAdmin
{
    function show()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addNavigation("Semesterstart", "index.php?do=admin&admin=Semesterstart");
        $this->template->addNavigation("Tildele dugnad");

        $page = get_layout_parts("admin_tildeledugnad");

        $query = "SELECT dugnad_id FROM bs_dugnad WHERE dugnad_slettet = '0' ORDER BY dugnad_dato";
        $result = @run_query($query);
        $dugnad_count = @mysql_num_rows($result);


        $query = "SELECT DISTINCT beboer_id AS id, beboer_spesial AS spesial
                    FROM bs_beboer
                    WHERE beboer_spesial = '0' OR beboer_spesial = '8'";
        $result = @run_query($query);
        $beboer_count = @mysql_num_rows($result);

        /* Calculates how many deltagere do we need on each dugnad. */
        $per_dugnad = (int) (($beboer_count * 2) / $dugnad_count) + ((($beboer_count * 2) % $dugnad_count) > 0);

        $this->template->addContentHtml("<div class='failure'>" . $beboer_count . " dugnadspliktige beboere fordelt p&aring; " . $dugnad_count . " l&oslash;rdager gir " . $per_dugnad . " barn per dugnad.<br /></div>");

        if (truncateAllowed() == false) {
            $this->template->addContentHtml("<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>");
        } elseif (isset($_POST['performit'])) {
            $beboerGiven = 0;
            $dugnadGiven = 0;
            $forceCount = 0;

            while (list($beboer_id, $special) = @mysql_fetch_row($result)) {
                if ($special == 8) {
                    $forceCount = 1;
                } else {
                    $forceCount = 2;
                }

                $dugnadGiven += forceNewDugnads($beboer_id, $forceCount, $per_dugnad);
                $beboerGiven += 1;
            }

            $this->template->addContentHtml("<div class='success'>Totalt ble " . $beboerGiven . " dugnadspliktige beboere tildelt " . sprintf("%.5f", ($dugnadGiven / $beboerGiven)) . " dugnader i snitt.<br /></div>");
        } else {
            $page["pw_line"] = "<p>
                                    <input type='hidden' name='performit' value='1' />
                                    <input type='submit' name='admin' value='Tildele dugnad'>
                                    <input type='submit' name='admin' value='Semesterstart'>
                                </p>" . $page["pw_line"];
        }

        $this->template->addContentHtml(implode($page));
    }
}
