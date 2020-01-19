<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Model\Beboer;
use Blindern\Dugnaden\Page;

class UserPage extends BasePage
{
    /** @var Beboer */
    protected $beboer;

    function __construct(Page $page)
    {
        parent::__construct($page);
        $this->loginStatus = $this->getLoginBeboer();
        if (!is_int($this->loginStatus)) {
            $this->beboer = $this->loginStatus;
        }
    }

    public function showLoginFailure()
    {
        if ($this->loginStatus == 0) {
            $this->page->addContentHtml("<p class='failure'>Passordet er ikke korrekt, pr&oslash;v igjen.</a>");
        } elseif ($this->loginStatus == -1) {
            $this->page->addContentHtml("<p class='failure'>Du har ikke tastet inn ditt passord, vennligst pr&oslash;v igjen.</a>");
        } else {
            $this->page->addContentHtml("<p class='failure'>Du har ikke valgt navnet ditt fra nedtrekksmenyen.</a>");
        }

        $this->page->addContentHtml(output_default_frontpage());
    }

    private function getLoginBeboer()
    {
        $beboer = isset($this->formdata["beboer"])
            ? $this->dugnaden->beboer->getById($this->formdata["beboer"])
            : null;

        if (!$beboer) {
            return -2;
        }

        if (check_is_admin()) {
            return $beboer;
        }

        if (empty($this->formdata["pw"])) {
            return -1;
        }

        if ($this->formdata["pw"] === $beboer->password) {
            $this->increaseNormalLogin();
            return $beboer;
        } else {
            return 0;
        }
    }

    function increaseNormalLogin()
    {
        $visitor_ip = getenv("REMOTE_ADDR");

        $query = "INSERT INTO bs_admin_access  (admin_access_ip, admin_access_date, admin_access_success)
                VALUES ('" . $visitor_ip . "', NOW(), '0')";

        @run_query($query);
    }
}
