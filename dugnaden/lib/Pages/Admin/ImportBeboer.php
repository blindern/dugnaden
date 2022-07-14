<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Pages\Page;

class ImportBeboer extends Page
{
    function show()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addNavigation("Semesterstart", "index.php?do=admin&admin=Semesterstart");
        $this->template->addNavigation("Importer beboere");

        $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        LIMIT 1";

        $result = @run_query($query);

        if (@mysql_num_rows($result) == 0) {
            $feedback .= "<div class='failure'>Det er ikke generert noen dugnadsdager, dette gj&oslash;res fra <a href='?do=admin&admin=Dugnadskalender'>Dugnadskalenderen</a>.</div>";
        }

        $page = $this->template->getLayoutParts("form_import");

        if (truncateAllowed() == false) {
            $page["disable_slett"] = "disabled='disabled'" . $page["disable_slett"];
            $page["disable_slett_start"] = "<span class='disabled'>" . $page["disable_slett_start"];
            $page["disable_slett_end"] = "</span>" . $page["disable_slett_end"];
        }

        $this->template->addContentHtml($feedback . implode($page));
    }

    function showUpload()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addNavigation("Semesterstart", "index.php?do=admin&admin=Semesterstart");
        $this->template->addNavigation("Importer beboere");

        $content = "";

        if (!empty($this->formdata["list"])) {

            // DELETING ALL FROM DATABASE - IF USER HAS CHECKED THE BOX

            // Setting it to true after a dugnad has been
            // arranged. It will only be false if the user
            // tries to delete beboere after a dugnadsperiode has started.

            $do_it = true;

            if ($this->formdata["delappend"] === "del") {

                if (truncateAllowed()) {
                    // Fetching all elephants and other special people:
                    $special_query = "SELECT beboer_for, beboer_etter, beboer_spesial FROM bs_beboer WHERE beboer_spesial = '2' OR beboer_spesial = '6'";
                    $special_result = @run_query($special_query);

                    // Fetching all elephants and other special people:
                    $dugnad_query = "SELECT beboer_id FROM bs_beboer, bs_innstillinger WHERE innstillinger_felt = 'dugnadsleder' AND innstillinger_verdi = beboer_id";
                    $dugnad_result = @run_query($dugnad_query);

                    $query = "TRUNCATE TABLE bs_notat";
                    @run_query($query);

                    $query = "TRUNCATE TABLE bs_bot";
                    @run_query($query);

                    $query = "TRUNCATE TABLE bs_deltager";
                    @run_query($query);

                    $query = "DELETE FROM bs_innstillinger WHERE innstillinger_felt = 'dugnadsleder'";
                    @run_query($query);

                    $query = "TRUNCATE TABLE bs_beboer";
                    @run_query($query);
                } else {
                    $content .= "<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>";
                    $do_it = false;
                }
            }

            // --------------------------------------------------------- ADDING NEW DATA!

            if ($do_it && $this->storeData($this->formdata["list"], "/")) {
                $content .= "<p class='success'>Oppdatering av databasen var vellykket.</p>";

                // ADDING SPECIAL STATUS TO ALL THAT HAD IT BEFORE DELETING ALL!
                if (isset($special_result) && @mysql_num_rows($special_result)) {
                    $first_miss = true;

                    while (list($fornavn, $etternavn, $status) = @mysql_fetch_row($special_result)) {
                        $update_special = "UPDATE bs_beboer SET beboer_spesial = '" . $status . "' WHERE beboer_for = '" . $fornavn . "' AND beboer_etter = '" . $etternavn . "'";
                        @run_query($update_special);

                        if (@mysql_affected_rows() == 0) {
                            if ($first_miss) {
                                $content .= "\n<p>Beboere med dugnadsfri:\n";
                                $first_miss = false;
                            }

                            $content .= "<li>" . $fornavn . " " . $etternavn . " " . ($status == 2 ? "(Elefant)" : "(Dugnadsfri)") . "</li>\n";
                        }
                    }
                    $content .= "</p>\n\n";

                    // ADDING DUGNADSLEDERS!!
                    $first_miss = true;
                    while (list($id) = @mysql_fetch_row($dugnad_result)) {
                        $update_special = "INSERT INTO bs_innstillinger (innstillinger_felt, innstillinger_verdi) VALUES ('dugnadsleder', '" . $id . "')";
                        @run_query($update_special);

                        if (@mysql_affected_rows() == 0) {
                            if ($first_miss) {
                                $content .= "\n<p>Dugnadsledere som ikke ble gjenkjent:\n";
                                $first_miss = false;
                            }

                            $content .= "<li>" . $fornavn . " " . $etternavn . " " . ($status == 2 ? "(Elefant)" : "(m&aring; eventuelt legges til manuelt)") . "</li>\n";
                        }
                    }
                    $content .= "</p>\n\n";
                }

                if ($this->formdata["delappend"] === "append") {
                    /* Adding dugnads to newly added beboere:
                        ------------------------------------------------- */

                    $txt_lines = explode("**", $this->cleanText($this->formdata["list"]));
                    $c = 0;

                    $dugnadGiven = 0;
                    $beboerGiven = 0;

                    $importName = $this->dugnaden->beboer->getNextImportName();

                    foreach ($txt_lines as $line) {
                        $c++;
                        $splits = explode("/", $line);

                        /* First name and last name is divided with a character different from each column: */
                        list($last, $first) = explode(",", $splits[0]);

                        $first = trim($first);
                        $last = trim($last);

                        $beboer = $this->dugnaden->beboer->getByName($first, $last);
                        $person_id = $beboer ? $beboer->id : -1;

                        $dugnadGiven += forceNewDugnads($person_id, 2, 25, $importName);
                        $beboerGiven += 1;
                    }

                    $content .= "<div class='success'>Totalt ble " . $beboerGiven . " <b>ny" . ($beboerGiven > 1 ? "e" : null) . "</b> dugnadspliktig" . ($beboerGiven > 1 ? "e" : null) . " beboer" . ($beboerGiven > 1 ? "e" : null) . " tildelt " . sprintf("%.5f", ($dugnadGiven / $beboerGiven)) . " dugnad" . ($dugnadGiven / $beboerGiven == 1 ? null : "er") . " i snitt.<br /></div>";

                    // Letting the user easily printout the latest import of beboere

                    $query = "SELECT
                                        DISTINCT deltager_notat AS notat

                                    FROM bs_deltager
                                    WHERE
                                        deltager_notat LIKE 'IMP%'

                                    ORDER BY deltager_notat DESC";

                    $result = @run_query($query);
                    list($impValue) = @mysql_fetch_row($result);

                    $content .= "<form method='post' action='index.php'>
                                        <input type='hidden' name='do' value='admin'>
                                        <input type='hidden' name='print' value='lastimport'>
                                        <input type='hidden' name='nyinnkalling' value='" . $impValue . "'>
                                        <h1>Innkalling til disse nye beboerne</h1>
                                        <p class='txt'>
                                            Klikk p&aring; knappen for &aring; skrive ut dugnadsinnkalling til
                                            disse beboerne. Har du ikke har tilgang til en skriver n&aring;    ,
                                            s&aring; kan lappene skrives ut p&aring; et senere tidspunkt
                                            fra <i>Admin</i> &gt; <i>Innstillinger</i>.
                                        </p>
                                        <input type='submit' name='admin' value='Innkalling av nye'>
                                    </form><p>&nbsp;</p>";


                    $content .= implode("", $this->template->getLayoutParts("admin_mainmenu"));

                    /* -------------------------------------------------
                            Done adding dugnads                           */
                } else {
                    $content .= implode("", $this->template->getLayoutParts("menu_semesterstart"));
                }
            } else {
                if ($do_it) {
                    $content .= "Det skjedde en feil under lagring av dugnadsliste, vennligst pr&oslash;v igjen...";
                    $content .= implode("", $this->template->getLayoutParts("form_import"));
                } else {
                    $content .= implode("", $this->template->getLayoutParts("menu_semesterstart"));
                }
            }
        } else {
            $content = implode("", $this->template->getLayoutParts("form_import"));
        }

        $this->template->addContentHtml($content);
    }

    function cleanText($str)
    {
        $str = preg_replace('/ +/', ' ', trim($str));
        //$str = ereg_replace (', +', ',', $str);
        $str = preg_replace("/[\r\n]+/", "\r\n", $str);
        $str = preg_replace("/\r\n/", "**", $str);
        return $str;
    }

    function storeData($txt_data, $split_char = "/", $split_name = ",")
    {
        $c = 0;
        $success = true;
        $txt_lines = explode("**", $this->cleanText($txt_data));

        foreach ($txt_lines as $line) {
            $c++;
            $splits = explode("/", $line);

            if (strcmp($split_char, $split_name)) {
                list($last, $first) = explode($split_name, $splits[0]);
            }

            $first = trim($first);
            $last = trim($last);

            $beboer = $this->dugnaden->beboer->getByName($first, $last);

            if (!$beboer) {
                /* Person does not exist, inserting it into the db:
            ---------------------------------------------------------------------- */

                /* Generate random password: */
                $jumble = md5(((time() / 10000) + ($c * 45)) . getmypid());
                $password = substr($jumble, 0, 5);

                $phone_query = "SELECT rom_id FROM bs_rom WHERE CONCAT(rom_nr, rom_type) = '" . trim($splits[1]) . "' LIMIT 1";

                $phone_result = @run_query($phone_query);
                if (@mysql_num_rows($phone_result)) {
                    list($rom_id) = @mysql_fetch_array($phone_result);
                } else {
                    $rom_id = 0;
                }

                /* Inserting person: */
                $query = "INSERT INTO bs_beboer (beboer_for, beboer_etter, beboer_rom, beboer_passord)
                        VALUES ('" . $first . "', '" . $last . "', '" . $rom_id . "', '" . $password . "')";

                @run_query($query);

                if (@mysql_errno() > 0) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}
