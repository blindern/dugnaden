<?php

use Blindern\Dugnaden\PageContext;
use Blindern\Dugnaden\Pages\Admin;
use Blindern\Dugnaden\Pages\Dugnadlist;
use Blindern\Dugnaden\Pages\Main;
use Blindern\Dugnaden\Pages\SwitchDugnad;
use Blindern\Dugnaden\Pages\SwitchPassword;
use Blindern\Dugnaden\Template;

header("Content-type: text/html; charset=utf-8");

include "../lib/default.php";

$template = new Template();
$template->addNavigation("Hovedmeny", "index.php");

$context = new PageContext($template);

switch (empty($_REQUEST["do"]) ? "" : $_REQUEST["do"]) {
    case "admin":
        (new Admin($context))->show();
        break;

    case "Bytte dugnad":
        (new SwitchDugnad($context))->show();
        break;

    case "Bytte passord":
        (new SwitchPassword($context))->show();
        break;

    case "Se dugnadslisten uten passord":
        (new Dugnadlist($context))->show();
        break;

    default:
        (new Main($context))->show();
        break;
}

$template->render();
