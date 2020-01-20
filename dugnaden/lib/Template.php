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

    private function getLayoutContent($name)
    {
        return file_get_contents(__DIR__ . "/layout/$name.html");
    }

    function getLayoutParts($name)
    {
        $buffer = $this->getLayoutContent($name);

        // Returns an array with content and the entire tag
        // in the order they were found in $filename.

        $out = preg_split('(\[([a-zA-Z_]+?)\])', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);

        $final = array();
        $final["head"] = $out[0];

        for ($c = 1; $c < sizeof($out); $c = $c + 2) {
            $final[strtolower($out[$c])] = $out[$c + 1];
        }

        return $final;
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
