<?php

namespace Blindern\Dugnaden\Pages\Admin;

class DugnadCalendar extends BaseAdmin
{
    function show()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addNavigation("Semesterstart", "index.php?do=admin&admin=Semesterstart");
        $this->template->addNavigation("Dugnadskalender");

        $content = "";

        switch ($this->formdata["saturdays"]) {
            case "add":

                if (truncateAllowed()) {
                    $query = "TRUNCATE TABLE bs_dugnad";
                    @run_query($query);

                    $content .= "<p class='success'>Tilf&oslash;yde " . $this->getSaturdays() . " l&oslash;rdager for dette semesteret.</p>";
                } else {
                    $content .= "<p class='failure'>Denne operasjonenen er ikke tillatt etter at dugnadsperioden har startet.</p>";
                }
                break;

            case "remove":

                $query = "TRUNCATE TABLE bs_deltager";
                @run_query($query);

                if (@mysql_errno() == 0) {
                    $query = "TRUNCATE TABLE bs_dugnad";
                    @run_query($query);

                    $content .= "<p class='success'>Alle l&oslash;rdager er slettet.</p>";
                } else {
                    $content .= "<p class='failure'>Beklager, det oppstod en feil under sletting av l&oslash;rdagene.</p>";
                }

                break;

            case "idle":
                $content .= $this->updateSaturdaysStatus();
                break;

            default:
                /* Not deleting, adding og updating...
                    --------------------------------------------------------------- */

                break;
        }

        $content .= "<form action='index.php' method='post'>" . $this->showAllSaturdays();
        $content .= get_layout_content("form_update") . "</form>" . $msg;

        if (truncateAllowed($future_check) == false) {

            $warning = "<p>&nbsp;</p>\n\t\t\t\t\t\t\t<div class='bl_red'>
                            <div class='br_red'>
                                <div class='tl_red'>
                                    <div class='tr_red'>
                                        <b>MERK</b>: Sletter du alle l&oslash;rdager, slettes ogs&aring; alle tilknyttede dugnader. Nye dugnader m&aring; derfor alltid tildeles etter sletting.
                                    </div>
                                </div>
                            </div>
                        </div>\n";

            $content .= $warning;
        }

        $this->template->addContentHtml($content);
    }

    private function updateSaturdaysStatus()
    {
        global $formdata;


        if (check_is_admin()) {
            $count = 0;

            $year    = date("Y", time());
            $month    = date("m", time());

            foreach ($formdata["sat"] as $value) {
                /* making an array to make it easier to check further down */
                $disabled_sats[$value] = "1";
            }

            if ($month > 7) {
                /* We now know we are in the autumn semester
            -------------------------------------------------------- */
                $months = array("8", "9", "10", "11", "12");
            } else {
                /* .. Spring
            -------------------------------------------------------- */
                $months = array("1", "2", "3", "4", "5", "6");
            }

            foreach ($months as $month) {
                for ($i = 1; $i <= 31; $i++) {
                    if (checkdate($month, $i, $year)) {
                        if (date("w", mktime(0, 0, 0, $month, $i, $year)) == '6') {
                            $count++;

                            if ($this->dateExists($month, $i, $year)) {
                                $sat_id = $this->getDateId($month, $i, $year);

                                if (!empty($disabled_sats[$sat_id])) {
                                    $query = "UPDATE bs_dugnad SET dugnad_slettet = '1'
                                            WHERE dugnad_id = '" . $sat_id . "'";
                                } else {
                                    $query = "UPDATE bs_dugnad SET dugnad_slettet = '0'
                                            WHERE dugnad_id = '" . $sat_id . "'";
                                }

                                @run_query($query);
                            }
                        }
                    }
                }
            }

            return null;
        } else {
            return "<p class='failure'>Du har ikke tastet inn korrekt passord.</p>";
        }
    }

    private function showAllSaturdays()
    {
        $count = 0;

        $year    = date("Y", time());
        $month    = date("m", time());

        if ($month > 7) {
            /* We now know we are in the autumn semester
        -------------------------------------------------------- */
            $months = array("8", "9", "10", "11", "12");
        } else {
            /* .. Spring
        -------------------------------------------------------- */
            $months = array("1", "2", "3", "4", "5", "6");
        }

        $months = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

        $content = "<img src='./images/update.gif' class='hoyre' /><h1>Merk alle dugnadsfrie l&oslash;rdager:</h1>\n\n";

        foreach ($months as $month) {
            $content .= "<div class='saturday_container'>\n";

            for ($i = 1; $i <= 31; $i++) {
                if (checkdate($month, $i, $year)) {
                    if (date("w", mktime(0, 0, 0, $month, $i, $year)) == '6') {
                        $count++;

                        if ($this->dateExists($month, $i, $year)) {
                            if (!$this->dateIsDisabled($month, $i, $year)) {
                                $content .= "<div class='saturday'><input type='checkbox' name='sat[]' value='" . $this->getDateId($month, $i, $year) . "' /> " . $i . ". " . date("M", mktime(0, 0, 0, $month, $i, $year)) . " <span class='disabled'>(" . $this->dugnaden->dugnad->getDugnadDeltagerCount($this->dugnaden->dugnad->getById($this->getDateId($month, $i, $year))) . ")</span></div>\n";
                            } else {
                                $content .= "<div class='saturday_off'><input type='checkbox' name='sat[]' value='" . $this->getDateId($month, $i, $year) . "' checked='checked' /> " . $i . ". " . date("M", mktime(0, 0, 0, $month, $i, $year)) . "</div>\n";
                            }
                        }
                    }
                }
            }

            $content .= "</div><br clear='left' />\n\n";
        }

        return $content;
    }

    private function getSaturdays()
    {
        $count = 0;

        $year    = date("Y", time());
        $month    = date("m", time());

        if ($month > 7) {
            /* We now know we are in the autumn semester
        -------------------------------------------------------- */
            $months = array("8", "9", "10", "11", "12");
        } else {
            /* .. Spring
        -------------------------------------------------------- */
            $months = array("1", "2", "3", "4", "5", "6");
        }

        foreach ($months as $month) {
            for ($i = 1; $i <= 31; $i++) {
                if (checkdate($month, $i, $year)) {
                    if (date("w", mktime(0, 0, 0, $month, $i, $year)) == '6') {
                        if (!$this->dateExists($month, $i, $year)) {
                            $query = "INSERT INTO bs_dugnad (dugnad_dato)
                                    VALUES ('" . date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $i, $year)) . "')";

                            @run_query($query);

                            if (@mysql_affected_rows() == 0) {
                                $count++;
                            } else {
                                print @mysql_error();
                            }
                        }
                    }
                }
            }
        }

        return $count;
    }

    private function dateExists($month, $day, $year)
    {
        $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

        if (checkdate($month, $day, $year)) {
            if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
                $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        WHERE dugnad_dato = '" . $the_day . "'
                        LIMIT 1";

                $result = @run_query($query);

                if (@mysql_num_rows($result) == 1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function dateIsDisabled($month, $day, $year)
    {
        $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

        if (checkdate($month, $day, $year)) {
            if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
                $query = "SELECT dugnad_id
                            FROM bs_dugnad
                            WHERE dugnad_dato = '" . $the_day . "'
                                AND dugnad_slettet = '1'
                            LIMIT 1";

                $result = @run_query($query);

                if (@mysql_num_rows($result) == 1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function getDateId($month, $day, $year)
    {
        $the_day = date("Y-m-d H:i:s", mktime(0, 0, 0, $month, $day, $year));

        if (checkdate($month, $day, $year)) {
            if (date("w", mktime(0, 0, 0, $month, $day, $year)) == '6') {
                $query = "SELECT dugnad_id
                        FROM bs_dugnad
                        WHERE dugnad_dato = '" . $the_day . "'
                        LIMIT 1";

                $result = @run_query($query);

                if (@mysql_num_rows($result) == 1) {
                    $row = @mysql_fetch_array($result);
                    return $row["dugnad_id"];
                } else {
                    return -1;
                }
            } else {
                return -1;
            }
        } else {
            return -1;
        }
    }
}
