<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Page;

class UserPage extends BasePage
{
    function __construct(Page $page)
    {
        parent::__construct($page);
        $this->loginStatus = $this->getLoginStatus();
    }

    public function showLoginFailure() {
        if ($this->loginStatus == 0) {
            $this->page->addContentHtml("<p class='failure'>Passordet er ikke korrekt, pr&oslash;v igjen.</a>");
        } elseif ($this->loginStatus == -1) {
            $this->page->addContentHtml("<p class='failure'>Du har ikke tastet inn ditt passord, vennligst pr&oslash;v igjen.</a>");
        } else {
            $this->page->addContentHtml("<p class='failure'>Du har ikke valgt navnet ditt fra nedtrekksmenyen.</a>");
        }

        $this->page->addContentHtml(output_default_frontpage());
    }

    public function isValidLogin() {
        return $this->loginStatus == 1;
    }

    private function getLoginStatus() {
        if (check_is_admin()) {
            return 1;
        }

        if (!strcmp($this->formdata["beboer"], "-1")) {
            // User has not selected a valid beboer from the drop down list
            return -2;
        }

        if (empty($this->formdata["pw"])) {
            // Password is missing
            return -1;
        }

        // Password has been entered and a use selected from the drop down box
        $query = "SELECT beboer_id, beboer_passord
                    FROM bs_beboer
                    WHERE beboer_id = '" . $this->formdata["beboer"] . "'
                    LIMIT 1";
        $result = @run_query($query);

        $row = @mysql_fetch_array($result);

        if (isset($this->formdata["pw"]) && !strcmp($row["beboer_passord"], $this->formdata["pw"])) {
            // VALID LOGIN
            increase_normal_login();
            return 1;
        } else {
            // INVALID LOGIN
            return 0;
        }
    }
}
