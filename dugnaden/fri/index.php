<?php

header("Content-type: text/html; charset=utf-8");

include_once "../script/default.php";

$formdata = get_formdata();

// This is used so that all images are given the correct path
// as they now are referenced from a sub folder.
$isSubFolder = ".";

/* User want to change entries
    ------------------------------------------------------------ */

$title = "Dugnadfri til Undergrupper";

$valid_login = valid_admin_login();

if ($valid_login == 3 || $valid_login == 1) {
    $feedback .= update_dugnads();
    $navigation = "<a href='index.php'>Logginn</a> &gt; Dugnadsfri";

    if (!empty($formdata["deln"])) {
        delete_note($formdata["deln"]);
    } elseif (!empty($formdata["notat"]) && !empty($formdata["beboer"]) && get_beboer_name($formdata["beboer"])) {
        if (insert_note($formdata["beboer"], $formdata["notat"], $formdata["mottaker"])) {
            $feedback .= "<p class='success'>Nytt notat ble lagret.</p>";
        } else {
            $feedback .= "<p class='failure'>Det oppstod en feil, nytt notat ble ikke lagret.</p>";
        }
    }

    if (!empty($formdata["delete_person"]) && $valid_admin == 1) {
        if ($valid_admin == 1) {
            /* Some user is to be deleted .. */
            $feedback .= delete_beboer_array($formdata["delete_person"]);
        } else {
            $feedback .= "<p class='failure'>Beklager, du har ikke rettigheter til &aring; slette beboere.</p>";
        }
    }

    if (!empty($formdata["delete"])) {
        foreach ($formdata["delete"] as $beboer_dugnad) {
            $beboer_split = explode("_", $beboer_dugnad);

            if (!delete_beboer($beboer_id) && $success) {
                $success = false;
                $failed++;
            } else {
                $deleted++;
            }
        }

        if ($success) {
            $feedback .= "<p class='success'>Slettet " . $deleted . " beboere fra dugnadsordningen.</p>";
        } else {
            $feedback .= "<p class='failure'>Av totalt " . $deleted + $failed . " var det " . $failed . " som ikke ble slettet.</p>";
        }
    }

    global $dugnad_is_empty, $dugnad_is_full;
    list($dugnad_is_empty, $dugnad_is_full) = get_dugnad_status();

    $correctPath = true;
    $content = $feedback . output_full_list($valid_login, $correctPath); // true for admin
} else {
    $navigation = "Logginn";

    if (!empty($formdata["pw"]) && $valid_login === 0) {
        $feedback .= "<p class='failure'>Beklager, men det er n&aring; for sent &aring; endre dugnadsstatus med dette passordet...</p>";
    } elseif (!empty($formdata["pw"])) {
        $feedback .= "<p class='failure'>Galt passord, pr&oslash;v igjen...</p>";
    }

    $admin_login = file_to_array("../layout/admin_login_undergruppe.html");


    if (get_innstilling("open_season", "1")) {
        $admin_login["box_style"] = '
    <div class="bl_green">
        <div class="br_green">
            <div class="tl_green">
                <div class="tr_green">                    Passord: <input type="password" name="pw" value="" size="15" maxlength="15"> <input type="submit" value="Logginn">
                </div>
            </div>
        </div>
    </div>' . $admin_login["box_style"];
    } else {
        $admin_login["box_style"] = '
    <div class="bl_red">
        <div class="br_red">
            <div class="tl_red">
                <div class="tr_red">Beklager, men Undergrupper har for &oslash;yeblikket ikke tilgang til denne tjenesten. Be en dugnadsleder aktivisere passordet.
                </div>
            </div>
        </div>
    </div>' . $admin_login["box_style"];
    }


    $admin_login["hidden"] = "<input type='hidden' name='admin' value='Dugnadsliste'>" . $admin_login["hidden"];
    $admin_login["dugnadslederne"] = get_dugnadsledere() . $admin_login["dugnadslederne"];

    $content = $feedback . implode($admin_login);
}

?><html>

<head>

    <title></title>

    <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <META HTTP-EQUIV="Expires" CONTENT="-1">
    <META name="revisit-after" content="30 days">

    <META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    <META name-equiv="content-language" content="en-us">

    <META name="robots" content="all">
    <META name="rating" content="general">
    <META name="distribution" content="global">

    <META name="keywords" content="web page, design, pandemic, gatada">
    <META name="description" content="A web page made by Johan H. W. Basberg">

    <!--link rel="icon" href="/common/favicon.ico" type="image/x-icon" /-->
    <!--link rel="shortcut icon" href="/common/favicon.ico" type="image/x-icon" /-->

    <!-- link rel="stylesheet" type="text/css" href="/css/default.css" title="default" /-->
    <!-- script type="text/javascript" src="/script/core.js"></script -->

    <link href="../css/default.css" rel="stylesheet" type="text/css">

</head>

<body <?php if (DEVELOPER_MODE) print "id='red'"; ?>>

    <?php

    if (empty($formdata["view"])) {
        print "            <div class=\"main\">
        <div class=\"navBar\">
            <div class=\"navBar_menu\">" . $navigation . "</div>
            <div class=\"navBar_heading\">" . $title . "</div>
        </div>
        <div class=\"content\">
            " . $content . "
        </div>
    </div>\n\n";

        if (!strcmp($formdata["do"], "admin")) {
            print "<div class='footer_info'>Ta kontakt med <a target='top' href='http://www.gatada.com/people/johan/contact.php'>Johan H. W. Basberg</a> hvis du har sp&oslash;rsm&aring;l om denne tjenesten.</div>";
        }
    } else {
        print $content;
    }

    ?>

</body>

</html>
