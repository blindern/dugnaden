<?php

namespace Blindern\Dugnaden\Fragments;

use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Dugnad;
use Blindern\Dugnaden\Util\DateUtil;

class DugnadlistFragment extends Fragment
{
    public function build($admin = false)
    {
        if ($admin) {
            $hidden = "<input type='hidden' name='do' value='admin' />
                            <input type='hidden' name='admin' value='Dugnadsliste' />";

            $list_title = "Administrering av dugnadsliste";

            $admin_buttons = "<div class='row_explained'><div class='name'><input type='reset' class='check_space' value='Nullstille endringer' /></div><div class='when_narrow'><input type='submit' class='check_space' value='Oppdater dugnadslisten' /></div></div>";
        } else {
            $hidden = "<input type='hidden' name='do' value='Se dugnadslisten uten passord' />";
            $list_title = "Fullstendig dugnadsliste";
        }

        $content  = "<h1>" . $list_title . "</h1>";
        $content .= "<form method='post' action='index.php'>" . $hidden . "
        \n";

        $beboere = $this->dugnaden->beboer->getAll();

        $content .= "<div class='row_explained'><div class='name'>" . ($admin ? "Slett " : null) . "Beboer</div><div class='when_narrow'>Tildelte dugnader</div><div class='note'>Notater</div><div class='spacer'>&nbsp;</div></div>";

        $c = 0;
        foreach ($beboere as $beboer) {
            $content .= "\n\n" . $this->showPerson($beboer, $c++, $admin) . "\n";
        }

        return $content . $admin_buttons . "</form>";
    }

    function showPerson(Beboer $beboer, $line_count, $admin)
    {
        global $formdata;

        if ($admin) {
            $check_box = "<input type='checkbox' name='delete_person[]' value='" . $beboer->id . "'> ";
        }

        $full_name = $admin ? $beboer->getName() : $beboer->getNameTruncated();

        if (!$admin) {
            $dugnads = $this->getSpecialStatusImage($beboer, $line_count) . $this->getDugnads($beboer->id);
        } else {
            $dugnads = $this->getSpecialStatusImage($beboer, $line_count) . admin_get_dugnads($beboer->id) . admin_addremove_dugnad($beboer->id, $line_count);
        }

        $entries .= "<div class='row" . ($line_count % 2 ? "_odd" : null) . "'><div class='name'>" . $check_box . $full_name . (empty($rom) ? " (<b>rom ukjent</b>)" : null) . "</div>\n<div class='when'>" . $dugnads . "</div><div class='note'>" . get_notes($formdata, $beboer->id, $admin) . "&nbsp;</div><div class='spacer'>&nbsp;</div></div>\n\n";

        return $entries;
    }

    function getSpecialStatusImage(Beboer $beboer, $line_count)
    {
        $result = "";

        if ($beboer->specialId == Beboer::SPECIAL_ELEPHANT) {
            $result .= "<img src='./images/elephant" . ($line_count % 2 ? "_odd" : null) . ".gif' title='Elefanter har dugnadsfri' border='0' align='top'>";
        }

        if ($beboer->specialId == Beboer::SPECIAL_FF) {
            $result .= "<img src='./images/festforeningen" . ($line_count % 2 ? "_odd" : null) . ".gif' title='Festforeningen har dugnadsfri' border='0' align='top'>";
        }

        if ($beboer->specialId == Beboer::SPECIAL_DUGNADSFRI) {
            $result .= "<img src='./images/dugnadsfri" . ($line_count % 2 ? "_odd" : null) . ".gif' width='24px' height='24px' title='Denne beboeren har dugnadsfri' border='0' align='top'>";
        }

        if ($beboer->specialId == Beboer::SPECIAL_BLIVENDE_ELEPHANT) {
            $result .= "<img class='dugnads_status' src='./images/blivende" . ($line_count % 2 ? "_odd" : null) . ".gif' width='32px' height='20px' title='Denne beboeren skal kun ha en dugnad' border='0' align='top'>";
        }

        return $result;
    }

    function getDugnads($id, $hide_outdated_dugnads = false)
    {
        $query = "SELECT    deltager_dugnad    AS id,

                        dugnad_dato        AS dugnad_dato,
                        dugnad_checked    AS checked,
                        dugnad_type,

                        deltager_gjort    AS status,
                        deltager_type    AS kind,
                        deltager_notat    AS note

                FROM bs_deltager

                    LEFT JOIN bs_dugnad
                        ON dugnad_id = deltager_dugnad AND dugnad_slettet = '0'

                WHERE deltager_beboer = '" . $id . "'
                    " . ($hide_outdated_dugnads ? 'AND (deltager_gjort = \'0\' AND (dugnad_dato > now() OR dugnad_dato IS NULL)) ' : '') . "
                ORDER BY dugnad_dato";

        $result = @run_query($query);

        while ($row = @mysql_fetch_array($result)) {
            $type = Dugnad::getTypePrefix($row["dugnad_type"]);

            /* ADDING NOTES
        ------------------------------------------------------------ */

            if (!empty($row["note"])) {
                // Showing the note
                $more_info = " <img src='./images/info.gif' alt='[i]' title='" . $row["note"] . "'>";
            } else {
                // No note to show
                $more_info = null;
            }

            /* ADDING DUGNADS
        ------------------------------------------------------------ */

            if ((int) $row["id"] > 0 && !strcmp($row["status"], "0")) {

                // Those done and not done (yet)

                if (!strcmp($row["checked"], "1")) {
                    $use_class = "done_dugnad";
                } else {
                    $use_class = "valid_dugnad";
                }
                $content .= "<span class='" . $use_class . "'>" . $type .  DateUtil::formatDateShort($row["dugnad_dato"]) . $more_info . "</span>\n";
            } else {

                /* Dugnad special cases
            --------------------------- */

                if ((int) $row["id"] < 0) {

                    // OK dugnads;

                    if ((int) $row["id"] == -2) {
                        $content .=    "<span class='valid_dugnad'>Dagdugnad</span>\n";
                    } elseif ((int) $row["id"] == -3) {
                        $content .=    "<span class='done_dugnad'>Utf&oslash;rt</span>\n";
                    } elseif ((int) $row["id"] == -10) {
                        $content .=    "<span class='valid_dugnad'>Hyttedugnad</span>\n";
                    } elseif ((int) $row["id"] == -11) {
                        $content .=    "<span class='valid_dugnad'>Ryddevakt</span>\n";
                    } elseif ((int) $row["id"] == -12) {
                        $content .=    "<span class='valid_dugnad'>Billavakt</span>\n";
                    }
                } else {
                    // Damn dugnads; those they did not do..

                    $content .= "<span class='damn_dugnad'>" . $type . DateUtil::formatDateShort($row["dugnad_dato"]) . $more_info . "</span>\n";
                }
            }
        }

        return $content;
    }
}
