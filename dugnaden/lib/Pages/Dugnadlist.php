<?php

namespace Blindern\Dugnaden\Pages;

class Dugnadlist extends Page
{
    function show()
    {
        global $dugnad_is_empty, $dugnad_is_full;
        list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

        $this->template->addNavigation("Komplett dugnadsliste");

        $admin_access = false;
        $this->template->addContentHtml(output_full_list($admin_access));
    }
}
