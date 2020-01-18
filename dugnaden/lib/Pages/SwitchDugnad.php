<?php

namespace Blindern\Dugnaden\Pages;

class SwitchDugnad extends UserPage
{
    function show()
    {
        if (!$this->isValidLogin()) {
            $this->showLogin();
        } else {
            $this->showSwitchPage();
        }
    }

    function showLogin()
    {
        $this->page->setTitleHtml("Bytte Dugnad");
        $this->page->setNavigationHtml("<a href='index.php?beboer=" . $this->formdata["beboer"] . "'>Hovedmeny</a> &gt; Dugnad");
        $this->showLoginFailure();
    }

    function showSwitchPage()
    {
        global $dugnad_is_empty, $dugnad_is_full;
        list($dugnad_is_empty, $dugnad_is_full) = $this->dugnaden->dugnad->getDugnadStatus();

        $this->page->addContentHtml(update_dugnads());
        update_beboer_room($this->formdata["beboer"], $this->formdata["room"]);

        $this->page->setTitleHtml("Bytte dugnadsdatoer");
        $this->page->setNavigationHtml("<a href='index.php'>Hovedmeny</a> &gt; Profilen til " . get_beboer_name($this->formdata["beboer"], true));

        $file = get_layout_parts("menu_beboerctrl");
        $file["gutta"] = get_dugnadsledere() . $file["gutta"];
        $this->page->addContentHtml(implode($file));

        $this->page->addContentHtml("    <div class='bl'><div class='br'><div class='tl'><div class='tr'>
                                <form action='index.php' method='post'>
                                <input type='hidden' name='do' value='Bytte dugnad' />
                                <input type='hidden' name='beboer' value='" . $this->formdata['beboer'] . "' />
                                <input type='hidden' name='pw' value='" . $this->formdata['pw'] . "' />");

        $this->page->addContentHtml(show_beboer_ctrlpanel($this->formdata["beboer"]) . "</form></div></div></div></div>");
    }
}
