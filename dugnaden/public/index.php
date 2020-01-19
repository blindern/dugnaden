<?php

use Blindern\Dugnaden\PageContext;

header("Content-type: text/html; charset=utf-8");

include "../lib/default.php";

$template = new \Blindern\Dugnaden\Template();
$template->addNavigation("Hovedmeny", "index.php");

$context = new PageContext($template);

switch (empty($_REQUEST["do"]) ? "" : $_REQUEST["do"]) {
    case "admin":
        (new \Blindern\Dugnaden\Pages\Admin($context))->show();
        break;

    case "Bytte dugnad":
        (new \Blindern\Dugnaden\Pages\SwitchDugnad($context))->show();
        break;

    case "Bytte passord":
        (new \Blindern\Dugnaden\Pages\SwitchPassword($context))->show();
        break;

    case "Se dugnadslisten uten passord":
        (new \Blindern\Dugnaden\Pages\Dugnadlist($context))->show();
        break;

    default:
        (new \Blindern\Dugnaden\Pages\Main($context))->show();
        break;
}

$template->render();
