<?php

namespace Blindern\Dugnaden\Pages;

class Admin extends BasePage
{
    function show()
    {
        list($title, $navigation, $content) = do_admin($this->page);
        $this->page->setTitleHtml($title);
        $this->page->setNavigationHtml($navigation);
        $this->page->addContentHtml($content);

        /* Updating all soon to be elefants to elefants if
            * it is after 15. March or 15. of Oct.:
        ---------------------------------------------------------- */

        $blivende_updates = update_blivende_elephants();

        if ($blivende_updates) {
            $this->page->addContentHtml("<p>Gratulerer til " . $blivende_updates . " beboer" . ($blivende_updates > 1 ? "e" : "") . " som endelig er elefant" .
                ($blivende_updates > 1 ? "er" : "") . "!<br />Eventuelt tilknyttede dugnader er slettet..</p>");
        }
    }
}
