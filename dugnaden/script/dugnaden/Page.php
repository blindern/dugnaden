<?php

namespace Blindern\Dugnaden;

/**
 * An object used to collect data that will end up in the template and
 * be rendered to the user.
 */
class Page {

    private $cssName = "default";
    private $navigation = "";
    private $title = "";
    private $content = "";

    function setPrintView() {
        $this->cssName = "default_paper";
    }

    function setDugnadlisteView() {
        $this->cssName = "default_dugnadsliste";
    }

    function setTitleHtml($value) {
        $this->title = $value;
    }

    function addContentHtml($value) {
        $this->content .= $value;
    }

    function setNavigationHtml($value) {
        $this->navigation = $value;
    }

    function render() {
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
            <div class="navBar_menu">' . $this->navigation . '</div>
            <div class="navBar_heading">' . $this->title . ' - ' . get_usage_count() . '</div>
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
