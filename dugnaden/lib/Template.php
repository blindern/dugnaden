<?php

namespace Blindern\Dugnaden;

/**
 * An object used to collect data that will end up in the template and
 * be rendered to the user.
 */
class Template
{
    private $cssName = "default";
    private $content = "";

    private $navigationItems = [];

    function setPrintView()
    {
        $this->cssName = "default_paper";
    }

    function setDugnadlisteView()
    {
        $this->cssName = "default_dugnadsliste";
    }

    function addContentHtml($value)
    {
        $this->content .= $value;
    }

    function addNavigation($title, $link = null)
    {
        $this->navigationItems[] = [$title, $link];
    }

    private function buildNavigation()
    {
        $result = [];

        $num = count($this->navigationItems);
        $i = 0;
        foreach ($this->navigationItems as $item) {
            $i++;

            $title = htmlspecialchars($item[0]);

            if ($i == $num || !$item[1]) {
                $result[] = $title;
            } else {
                $result[] = '<a href="' . htmlspecialchars($item[1]) . '">' . $title . '</a>';
            }
        }

        return implode(" &raquo; ", $result);
    }

    function render()
    {
        $show_queries = isset($GLOBALS['queries']) && DEVELOPER_MODE;

        echo '
<html>
<head>
    <meta http-equiv=Content-Type content="text/html; charset=UTF-8">

    <title>Dugnadsordningen - Blindern Studenterhjem</title>

    <meta name="keywords" content="Blindern Studenterhjems dugnadsordning p&aring; nettet." />
    <meta name="author" content="Dugnadslederne H. W. Basberg" />

    <meta name="robots" content="noindex, nofollow" />

    <link href="./css/' . $this->cssName . '.css" rel="stylesheet" type="text/css">

</head>

<body ' . (DEVELOPER_MODE ? "id='red'" : '') . '>
    <div class="main">
        <div class="navBar">
            <div class="navBar_menu">' . $this->buildNavigation() . '</div>
            <div class="navBar_heading">&nbsp;</div>
        </div>
        <div class="content">
            ' . $this->content . '
        </div>
    </div>' . ($show_queries ? '
    <pre id="queries_list">Spørringer:' . implode("


    ", $GLOBALS['queries']) . '
    </pre>
    ' : '') . '
</body>
</html>';
    }
}
