<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Model\Dugnad;
use Blindern\Dugnaden\Util\DateUtil;

class SwitchDugnad extends UserPage
{
    function show()
    {
        if (!$this->beboer) {
            $this->showLogin();
        } else {
            $this->showSwitchPage();
        }
    }

    function showLogin()
    {
        $this->template->addNavigation("Bytte dugnad", "index.php?do=Bytte dugnad");
        $this->showLoginFailure();
    }

    function showSwitchPage()
    {
        global $dugnad_is_empty, $dugnad_is_full;
        list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

        $this->template->addContentHtml(update_dugnads($this->formdata));

        $room = isset($this->formdata["room"])
            ? $this->dugnaden->room->getById($this->formdata["room"])
            : null;

        if ($room) {
            $this->dugnaden->beboer->updateRoom($this->beboer, $room);
        }

        $this->template->addNavigation("Bytte dugnad");

        $file = $this->template->getLayoutParts("menu_beboerctrl");
        $file["gutta"] = get_dugnadsledere() . $file["gutta"];
        $this->template->addContentHtml(implode($file));

        $this->template->addContentHtml('
            <div class="box-beboer">
                <form action="index.php" method="post">
                    <input type="hidden" name="do" value="Bytte dugnad" />
                    <input type="hidden" name="beboer" value="' . $this->formdata["beboer"] . '" />
                    <input type="hidden" name="pw" value="' . $this->formdata["pw"] . '" />
                    ' . $this->showBeboerCtrlPanel() . '
                </form>
            </div>');
    }

    function showBeboerCtrlPanel()
    {
        $admin_show_password = check_is_admin()
            ? "<b>Passord</b>: " . htmlspecialchars($this->beboer->password) . "&nbsp;"
            : "";

        return "<div class='name_ctrl'>" . htmlspecialchars($this->beboer->getName()) . "</div>
                <div class='room_ctrl'>Rom: " . $this->getRoomSelect($this->beboer) . "</div>
                <div class='when_ctrl'>" . $this->beboerGetDugnads() . "</div>
                <div class='note'>" . $admin_show_password . "<input type='submit' value='Lagre endringer'></div>
                <div class='spacer_small'>&nbsp;</div>\n\n";
    }

    function getRoomSelect(Beboer $beboer)
    {
        $rooms = $this->dugnaden->room->getAll();

        $options = '';
        foreach ($rooms as $room) {
            $selected = $room->id === $beboer->roomId
                ? ' selected="selected"'
                : '';

            $options .= '
                <option value="' . $room->id . '"' . $selected . '>
                    ' . $room->getPretty() . '
                </option>';
        }

        return '
            <select name="room">
                <option value="-1">Velg</option>
                ' . $options . '
            </select>';
    }

    function beboerGetDugnads()
    {
        $query = "SELECT    deltager_dugnad                AS id,

                        dugnad_dato                    AS dugnad_dato,
                        UNIX_TIMESTAMP(dugnad_dato)    AS is_happening,
                        dugnad_checked                AS checked,

                        deltager_gjort    AS status,
                        deltager_type    AS kind,
                        deltager_notat    AS note

                FROM bs_deltager

                    LEFT JOIN bs_dugnad
                        ON dugnad_id = deltager_dugnad
                            AND dugnad_slettet = '0'

                WHERE deltager_beboer = '" . $this->beboer->id . "'";

        $result = @run_query($query);
        $c = 0;

        while ($row = @mysql_fetch_array($result)) {
            if (!strcmp($row["status"], "0")) {

                if ((int) $row["id"] > 0 && (int) $row["is_happening"] < (time() + (60 * 60 * 24 * 6))) {
                    /* If the dugnad is allready completed - add it as pure text
                ---------------------------------------------------------------------------------------- */

                    if (!strcmp($row["checked"], "1")) {
                        $use_class = "done_dugnad";
                    } else {
                        $use_class = "valid_dugnad";
                    }
                    $static_content .= "<span class='" . $use_class . "'>" . DateUtil::formatDateShort($row["dugnad_dato"]) . "</span>\n";
                } else {
                    /* Add the dugnad as a selection box
                ---------------------------------------------------------------------------------------- */

                    $content .= $this->beboerMakeSelect($c++, $row["id"]);
                }
            } else {
                if (!empty($row["note"])) {
                    $more_info = " <img src='./images/info.gif' alt='[i]' title='" . $row["note"] . "'>";
                }

                $static_content .= "<span class='damn_dugnad'>" . DateUtil::formatDateShort($row["dugnad_dato"]) . $more_info . "</span>\n";
            }
        }

        return $static_content . $content;
    }

    function beboerMakeSelect($select_count, $date_id)
    {
        /* Used ONLY to let the user be able to change dugnad
    ------------------------------------------------------------------------------------ */

        global $dugnad_is_empty, $dugnad_is_full;

        $dugnad = $this->dugnaden->dugnad->getById($date_id);
        $deltager = $this->dugnaden->deltager->getByBeboerAndDugnad($this->beboer, $dugnad);

        $prefix = $dugnad ? Dugnad::getTypePrefix($dugnad->type) : "";

        if (isset($dugnad_is_empty[$date_id])) {

            /* Too few beboere at this particular day - disabling drop-down menu...
        ------------------------------------------------------------------------------------------ */

            $content .= "\n<select size='1' name='" . $this->beboer->id . "_" . $deltager->id . "' disabled class='no_block' >\n";


            $query = "SELECT    dugnad_dato        AS da_date,
                            dugnad_id        AS id

                    FROM bs_dugnad
                    WHERE dugnad_id = '" . $date_id . "'
                    LIMIT 1";

            $result = @run_query($query);

            if (@mysql_num_rows($result) == 1) {
                $row = @mysql_fetch_array($result);
                $content .= "<option value='" . $row["id"] . "' selected='selected' >" . $prefix . DateUtil::formatDateShort($row["da_date"]) . "</option>\n";
            } else {
                $content .= "<option value='-1' selected='selected' >:-)</option>\n";
            }

            $content .= "</select>\n";
        } else {
            /* Not too few...
        ------------------------------------------------------------------------------------------ */

            $content .= "\n<select size='1' name='" . $this->beboer->id . "_" . $deltager->id . "_" . $select_count . "' class='no_block' >\n";

            if ((int) $date_id < 0) {
                if ((int) $date_id == -2) {
                    $content .=    "<option value='-2' selected='selected' >Dagdugnad</option>\n";
                } elseif ((int) $date_id == -3) {
                    $content .=    "<option value='-3' selected='selected' >Utf&oslash;rt</option>\n";
                } elseif ((int) $date_id == -10) {
                    $content .=    "<option value='-10' selected='selected' >Hyttedugnad</option>\n";
                } elseif ((int) $date_id == -11) {
                    $content .=    "<option value='-11' selected='selected' >Ryddevakt</option>\n";
                } elseif ((int) $date_id == -12) {
                    $content .=    "<option value='-12' selected='selected' >Billavakt</option>\n";
                }
            } elseif ($dugnad && $dugnad['dugnad_type'] == 'anretning') {
                $content .=    "<option value='" . $dugnad['dugnad_id'] . "' selected='selected'>Anretningsdugnad: " . DateUtil::formatDateShort($dugnad['dugnad_dato']) . "</option>\n";
            } else {
                $content .=    "<option value='-10' >Hyttedugnad</option>\n";

                $query = "SELECT

                        dugnad_dato        AS da_date,
                        dugnad_id        AS id

                    FROM bs_dugnad
                    WHERE dugnad_dato > DATE_ADD(CURDATE(),INTERVAL 6 DAY)
                        AND dugnad_slettet ='0'
                    ORDER BY dugnad_dato";

                $result = @run_query($query);

                while ($row = @mysql_fetch_array($result)) {
                    if (!strcmp($row["id"], $date_id)) {
                        $content .= "<option value='" . $row["id"] . "' selected='selected' >" . $prefix . DateUtil::formatDateShort($row["da_date"]) . "</option>\n";
                    } else {
                        $checked = null;

                        if (empty($dugnad_is_full[$row["id"]])) {
                            $content .= "<option value='" . $row["id"] . "' " . $checked . ">" . $prefix . DateUtil::formatDateShort($row["da_date"]) . "</option>\n";
                        }
                    }
                }
            }
        }

        $content .= "</select>\n";

        return $content;
    }
}
