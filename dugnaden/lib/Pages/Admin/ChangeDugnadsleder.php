<?php

namespace Blindern\Dugnaden\Pages\Admin;

use Blindern\Dugnaden\Fragments\BeboerSelectFragment;
use Blindern\Dugnaden\Pages\Page;

class ChangeDugnadsleder extends Page
{
    function show()
    {
        $this->template->addNavigation("Innstillinger", "index.php?do=admin&admin=Innstillinger");
        $this->template->addNavigation("Dugnadslederstyring");

        $page = $this->template->getLayoutParts("admin_dugnadsledere");

        $content = "";

        $beboer = isset($this->formdata["beboer"])
            ? $this->dugnaden->beboer->getById($this->formdata["beboer"])
            : null;

        if ($beboer) {
            $this->dugnaden->dugnadsleder->assign($beboer);
            $beboer = null;
        }

        if (isset($this->formdata["del_dl"])) {
            foreach ($this->formdata["del_dl"] as $value) {
                $beboer = $this->dugnaden->beboer->getById($value);
                $this->dugnaden->dugnadsleder->delete($beboer);
            }
        }

        $page["hidden"] = " <input type='hidden' name='admin'    value='Dugnadslederstyring'>
                            <input type='hidden' name='do'        value='admin'>" . $page["hidden"];

        $dugnadslederList = $this->dugnaden->dugnadsleder->getList();

        foreach ($dugnadslederList as $beboer) {
            $all_dl .= "<input type='checkbox' name='del_dl[]' value='" . $beboer->id . "'>" . htmlspecialchars($beboer->getName()) . "<br />";
        }

        $page["dugnadslederne"] = $all_dl . $page["dugnadslederne"];

        $f = new BeboerSelectFragment($this->context);
        $f->truncateName = false;
        $f->selectText = "Velg ny dugnadslder...";

        $page["andre_beboere"] = $f->build() . $page["andre_beboere"];

        $content .= implode($page);
        $this->template->addContentHtml($content);
    }
}
