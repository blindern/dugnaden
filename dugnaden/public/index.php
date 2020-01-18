<?php

header("Content-type: text/html; charset=utf-8");

include "../lib/default.php";

$page = new \Blindern\Dugnaden\Page();

switch (empty($_REQUEST["do"]) ? "" : $_REQUEST["do"]) {
    case "admin":
        (new \Blindern\Dugnaden\Pages\Admin($page))->show();
        break;

    case "Bytte dugnad":
        (new \Blindern\Dugnaden\Pages\SwitchDugnad($page))->show();
        break;

    case "Bytte passord":
        (new \Blindern\Dugnaden\Pages\SwitchPassword($page))->show();
        break;

    case "Se dugnadslisten uten passord":
        (new \Blindern\Dugnaden\Pages\Dugnadlist($page))->show();
        break;

    default:
        (new \Blindern\Dugnaden\Pages\Main($page))->show();
        break;
}

$page->render();
