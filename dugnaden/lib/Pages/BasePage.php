<?php

namespace Blindern\Dugnaden\Pages;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Page;

class BasePage
{
    /** @var Page */
    public $page;

    /** @var Dugnaden */
    public $dugnaden;

    public $formdata;

    function __construct(Page $page)
    {
        $this->page = $page;
        $this->dugnaden = Dugnaden::get();
        $this->formdata = $this->getFormData();
    }

    private function getFormData()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            foreach ($_POST as $key => $value) {
                $formdata[$key] = $value;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
            foreach ($_GET as $key => $value) {
                $formdata[$key] = $value;
            }
        } else {
            return [];
        }

        return $formdata;
    }
}
