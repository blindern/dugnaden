<?php

namespace Blindern\Dugnaden\Pages\Admin;

class ChangeDugnadsleder extends BaseAdmin
{
    function show()
    {
        $this->page->setTitleHtml($this->formdata["admin"]);
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; <a href='index.php?do=admin'>Admin</a> &gt; <a href='index.php?do=admin&admin=Innstillinger'>Innstillinger</a> &gt; Dugnadslederstyring");

        $page = get_layout_parts("admin_dugnadsledere");

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

        $dugnadsleder = true;
        $page["andre_beboere"] = get_beboer_select($dugnadsleder) . $page["andre_beboere"];

        $content .= implode($page);
        $this->page->addContentHtml($content);
    }
}
