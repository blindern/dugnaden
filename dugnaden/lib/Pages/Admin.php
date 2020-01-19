<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Pages\Admin\AddBeboer;
use Blindern\Dugnaden\Pages\Admin\AssignDugnad;
use Blindern\Dugnaden\Pages\Admin\ChangeDugnadsleder;
use Blindern\Dugnaden\Pages\Admin\DayDugnad;
use Blindern\Dugnaden\Pages\Admin\DugnadCalendar;
use Blindern\Dugnaden\Pages\Admin\DugnadList;
use Blindern\Dugnaden\Pages\Admin\FeeList;
use Blindern\Dugnaden\Pages\Admin\FixBeboerName;
use Blindern\Dugnaden\Pages\Admin\Handout;
use Blindern\Dugnaden\Pages\Admin\ImportBeboer;
use Blindern\Dugnaden\Pages\Admin\ImportBeboerUpload;
use Blindern\Dugnaden\Pages\Admin\Main;
use Blindern\Dugnaden\Pages\Admin\NextDugnadList;
use Blindern\Dugnaden\Pages\Admin\RevokeFee;
use Blindern\Dugnaden\Pages\Admin\Semesterstart;
use Blindern\Dugnaden\Pages\Admin\Settings;
use Blindern\Dugnaden\Pages\Admin\UpdateLastDugnad;

class Admin extends BasePage
{
    function show()
    {
        require_admin();
        $this->page->addNavigation("Admin", "index.php?do=admin");

        switch (!empty($this->formdata["admin"]) ? $this->formdata["admin"] : "") {
            case "Annulere bot":
                (new RevokeFee($this))->show();
                break;

            case "Rette beboernavn":
                (new FixBeboerName($this))->show();
                break;

            case "Innstillinger":
                (new Settings($this))->show();
                break;

            case "Tildele dugnad":
                (new AssignDugnad($this))->show();
                break;

            case "Semesterstart":
                (new Semesterstart($this))->show();
                break;

            case "Dugnadslederstyring":
                (new ChangeDugnadsleder($this))->show();
                break;

            case "Infoliste":
                (new Handout($this))->show();
                break;

            case "Botliste":
                (new FeeList($this))->show();
                break;

            case "Neste dugnadsliste":
                (new NextDugnadList($this))->show();
                break;

            case "Oppdatere siste":
                (new UpdateLastDugnad($this))->show();
                break;

            case "Justere status":
                // fall-through
            case "Se over forrige semester":
                // fall-through
            case "Dugnadsliste":
                (new DugnadList($this))->show();
                break;

            case "Dagdugnad":
                (new DayDugnad($this))->show();
                break;

            case "Dugnadskalender":
                (new DugnadCalendar($this))->show();
                break;

            case "Innkalling av nye":
                (new AddBeboer($this))->show();
                break;

            case "Nye beboere":
                // fall-through
            case "Importer beboere":
                (new ImportBeboer($this))->show();
                break;

            case "upload":
                (new ImportBeboer($this))->showUpload();
                break;

            default:
                (new Main($this))->show();
                break;
        }

        $blivende_updates = $this->updateBlivendeElephants();

        if ($blivende_updates) {
            $this->page->addContentHtml("<p>Gratulerer til " . $blivende_updates . " beboer" . ($blivende_updates > 1 ? "e" : "") . " som endelig er elefant" .
                ($blivende_updates > 1 ? "er" : "") . "!<br />Eventuelt tilknyttede dugnader er slettet..</p>");
        }
    }

    function updateBlivendeElephants()
    {
        $blivende = 0;

        $month = date("m", time());
        $day = date("d", time());

        if ($month > 7) {
            /* We now know we are in the autumn semester
        -------------------------------------------------------- */

            if ($month == 10 && $day > 15 && $day < 22) {
                $query = "SELECT beboer_id FROM bs_beboer, bs_deltager WHERE beboer_spesial = '8' AND deltager_beboer = beboer_id";
                $result = @run_query($query);

                while (list($beboer_id) = @mysql_fetch_row($result)) {
                    // Remove dugnads from this person
                    $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $beboer_id . "'";
                    @run_query($query);
                }

                $query = "UPDATE bs_beboer SET beboer_spesial = '2' WHERE beboer_spesial = '8'";
                @run_query($query);
                $blivende++;
            }
        } else {
            /* .. Spring
        -------------------------------------------------------- */

            if ($month == 3 && $day > 15 && $day < 22) {
                $query = "SELECT beboer_id FROM bs_beboer, bs_deltager WHERE beboer_spesial = '8' AND deltager_beboer = beboer_id";
                $result = @run_query($query);

                while (list($beboer_id) = @mysql_fetch_row($result)) {
                    // Remove dugnads from this person
                    $query = "DELETE FROM bs_deltager WHERE deltager_beboer = '" . $beboer_id . "'";
                    @run_query($query);
                }

                $query = "UPDATE bs_beboer SET beboer_spesial = '2' WHERE beboer_spesial = '8'";
                @run_query($query);
                $blivende++;
            }
        }

        return $blivende;
    }
}
