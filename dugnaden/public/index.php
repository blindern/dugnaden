<?php

header("Content-type: text/html; charset=utf-8");

include "../lib/default.php";

$formdata = get_formdata();
// print_r($formdata);

$page = new \Blindern\Dugnaden\Page();

if (!empty($formdata["do"])) {

    /* User had selected some action
    ------------------------------------------------------ */

    switch ($formdata["do"]) {
        case "admin":

            /* User wants to enter admin mode
            ------------------------------------------------------------ */

            list($title, $navigation, $content) = do_admin($page);
            $page->setTitleHtml($title);
            $page->setNavigationHtml($navigation);
            $page->addContentHtml($content);

            /* Updating all soon to be elefants to elefants if
             * it is after 15. March or 15. of Oct.:
            ---------------------------------------------------------- */

            $blivende_updates = update_blivende_elephants();

            if ($blivende_updates) {
                $page->addContentHtml("<p>Gratulerer til " . $blivende_updates . " beboer" . ($blivende_updates > 1 ? "e" : "") . " som endelig er elefant" .
                    ($blivende_updates > 1 ? "er" : "") . "!<br />Eventuelt tilknyttede dugnader er slettet..</p>");
            }

            break;

        case "Bytte dugnad":

            /* User is updating info
            ------------------------------------------------------------ */

            $valid_login = valid_login();

            if ((int) $formdata["beboer"] == -1 || $valid_login < 1) {
                /* Showing default menu
                ------------------------------------------------------------ */

                $page->setTitleHtml("Bytte Dugnad");
                $page->setNavigationHtml("<a href='index.php?beboer=" . $formdata["beboer"] . "'>Hovedmeny</a> &gt; Dugnad");

                if ($valid_login == 0) {
                    $page->addContentHtml("<p class='failure'>Passordet er ikke korrekt, pr&oslash;v igjen.</a>");
                } elseif ($valid_login == -1) {
                    $page->addContentHtml("<p class='failure'>Du har ikke tastet inn ditt passord, vennligst pr&oslash;v igjen.</a>");
                } else {
                    $page->addContentHtml("<p class='failure'>Du har ikke valgt navnet ditt fra nedtrekksmenyen.</a>");
                }

                $page_array = get_layout_parts("menu_main");
                $page->addContentHtml(output_default_frontpage());
            } else {
                /* VALID LOGIN - showing screen to allow user to change dugnadsdates
                -------------------------------------------------------------------------------- */

                global $dugnad_is_empty, $dugnad_is_full;
                list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

                $page->addContentHtml(update_dugnads());
                update_beboer_room($formdata["beboer"], $formdata["room"]);

                $page->setTitleHtml("Bytte dugnadsdatoer");
                $page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; Profilen til " . get_beboer_name($formdata["beboer"], true));

                $file = get_layout_parts("menu_beboerctrl");
                $file["gutta"] = get_dugnadsledere() . $file["gutta"];
                $page->addContentHtml(implode($file));

                $page->addContentHtml("    <div class='bl'><div class='br'><div class='tl'><div class='tr'>
                                    <form action='index.php' method='post'>
                                    <input type='hidden' name='do' value='Bytte dugnad' />
                                    <input type='hidden' name='beboer' value='" . $formdata['beboer'] . "' />
                                    <input type='hidden' name='pw' value='" . $formdata['pw'] . "' />");

                $page->addContentHtml(show_beboer_ctrlpanel($formdata["beboer"]) . "</form></div></div></div></div>");
            }

            break;

        case "Bytte passord":

            /* User wants to change password
            ------------------------------------------------------------ */

            $valid_login = valid_login();

            if ((int) $formdata["beboer"] == -1 || $valid_login < 1) {
                /* Invalid login - showing default menu
                ------------------------------------------------------------ */

                $page->setTitleHtml("Bytte passord");
                $page->setNavigationHtml("<a href='index.php?beboer=" . $formdata["beboer"] . "'>Hovedmeny</a> &gt; Passord");

                if ($valid_login == 0) {
                    $page->addContentHtml("<p class='failure'>Passordet er ikke korrekt, pr&oslash;v igjen.</a>");
                } elseif ($valid_login == -1) {
                    $page->addContentHtml("<p class='failure'>Du har ikke tastet inn ditt passord, vennligst pr&oslash;v igjen.</a>");
                } else {
                    $page->addContentHtml("<p class='failure'>Du har ikke valgt navnet ditt fra nedtrekksmenyen.</a>");
                }

                $page_array = get_layout_parts("menu_main");
                $page->addContentHtml(output_default_frontpage());
            } else {

                if (!empty($formdata["pw_2"]) && !empty($formdata["pw_b"])) {
                    if (!strcmp($formdata["pw_2"], $formdata["pw_b"])) {
                        $query = "UPDATE bs_beboer SET beboer_passord = '" . $formdata["pw_2"] . "' WHERE beboer_id = '" . $formdata["beboer"] . "'";
                        @run_query($query);

                        if (mysql_errno() == 0) {
                            $feedback = rounded_feedback_box("green", "Ditt nye passord er lagret.");
                        } else {
                            $feedback = rounded_feedback_box("red", "Beklager, passordet ble ikke lagret. Ta kontakt med en dugnadsleder.");
                        }

                        $show_menu = true;
                    } else {
                        $feedback = rounded_feedback_box("red", "Passordene du valgte stemmer ikke overens, de m&aring; v&aelig;re like...");
                    }
                } else {
                    if ((empty($formdata["pw_2"]) && !empty($formdata["pw_b"])) || (!empty($formdata["pw_2"]) && empty($formdata["pw_b"]))) {
                        $feedback = rounded_feedback_box($color, "Du har ikke fylt inn begge feltene..");
                    }
                }

                if (empty($show_menu)) {
                    $beboer_navn = get_beboer_name($formdata["beboer"], true);
                    $page->setTitleHtml("Endre passord til " . $beboer_navn);
                    $page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; Passord");

                    $page_array = get_layout_parts("form_pw");

                    $page->addContentHtml($feedback . $page_array["head"] . "<input type='hidden' name='beboer' value='" . $formdata["beboer"] . "' /><input type='hidden' name='pw' value='" . $formdata["pw"] . "' />" .  $page_array["hidden"] . $beboer_navn . $page_array["beboer_navn"]);
                } else {
                    /* Password was either saved or it failed, either way - show the main menu
                    --------------------------------------------------------------------------------- */

                    $page->setTitleHtml("Bytte passord");
                    $page->setNavigationHtml("<a href='index.php?beboer=" . $formdata["beboer"] . "'>Hovedmeny</a> &gt; Passord");

                    $page_array = get_layout_parts("menu_main");
                    $page->addContentHtml($feedback . output_default_frontpage());
                }
            }

            break;

        case "Se dugnadslisten uten passord":

            /* Showing fill list
            ------------------------------------------------------------ */

            global $dugnad_is_empty, $dugnad_is_full;
            list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

            $page->setTitleHtml("Komplett dugnadsliste");
            $page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; Dugnadslisten");

            $admin_access = false;
            $page->addContentHtml(output_full_list($admin_access));
            break;

        default:

            /* Default action
            ------------------------------------------------------------ */
            $page->setTitleHtml("Dugnadsordningen p&aring; nett");
            $page->setNavigationHtml("Hovedmeny");
            $page->addContentHtml(output_default_frontpage());
            break;
    }
} else {
    $page->setTitleHtml("Dugnadsordningen p&aring; nett");
    $page->setNavigationHtml("Hovedmeny");
    $page->addContentHtml(output_default_frontpage());
}

$page->render();
