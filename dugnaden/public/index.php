<?php

header("Content-type: text/html; charset=utf-8");

include "../lib/default.php";

$template = new \Blindern\Dugnaden\Template();
$template->addNavigation("Hovedmeny", "index.php");

switch (empty($_REQUEST["do"]) ? "" : $_REQUEST["do"]) {
    case "admin":
        (new \Blindern\Dugnaden\Pages\Admin($template))->show();
        break;

    case "Bytte dugnad":
        (new \Blindern\Dugnaden\Pages\SwitchDugnad($template))->show();
        break;

    case "Bytte passord":
        (new \Blindern\Dugnaden\Pages\SwitchPassword($template))->show();
        break;

    case "Se dugnadslisten uten passord":
        (new \Blindern\Dugnaden\Pages\Dugnadlist($template))->show();
        break;

    default:
        (new \Blindern\Dugnaden\Pages\Main($template))->show();
        break;
}

$template->render();
