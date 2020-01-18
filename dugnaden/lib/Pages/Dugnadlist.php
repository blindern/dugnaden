<?php

namespace Blindern\Dugnaden\Pages;

class Dugnadlist extends BasePage
{
    function show()
    {
        global $dugnad_is_empty, $dugnad_is_full;
        list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

        $this->page->setTitleHtml("Komplett dugnadsliste");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; Dugnadslisten");

        $admin_access = false;
        $this->page->addContentHtml(output_full_list($admin_access));
    }
}
