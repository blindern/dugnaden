<?php

/**
 * @var pagehandle
 */
$ess::$b->page = $ess::$b->page;

header("Content-Type: text/html; charset=utf-8");

// sett opp head
$ess::$b->page->head = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Henrik Steen; http://henrist.net" />
<meta name="keywords" content="'.$ess::$b->page->generate_keywords().'" />
<meta name="description" content="'.$ess::$b->page->description.'" />
<!--<link rel="shortcut icon" href="/favicon.ico" />-->
<link href="'.$ess::$b->page->pagedata->doc_path.'/stil.css?'.@filemtime(dirname(__FILE__)."/stil.css").'" rel="stylesheet" type="text/css" />
<!--[if lte IE 8]>
<script src="'.$ess::$b->page->pagedata->doc_path.'/html5ie.js" type="text/javascript"></script>
<![endif]-->'.$ess::$b->page->head;



/*if (!ess::$b->page->js_disable)
{
	$head .= '
<script type="text/javascript">var js_start = (new Date).getTime();</script>';
	
	
	// mootools
	if (MAIN_SERVER)
	{
		$head .= '
<script src="'.LIB_HTTP.'/mootools/mootools-1.2.x-yc.js" type="text/javascript"></script>';
	}
	else
	{
		$head .= '
<script src="'.LIB_HTTP.'/mootools/mootools-1.2.x-core-nc.js" type="text/javascript"></script>
<script src="'.LIB_HTTP.'/mootools/mootools-1.2.x-more-nc.js" type="text/javascript"></script>';
	}
	
	$head .= '
<script type="text/javascript">var js_mootools_loaded = (new Date).getTime();</script>
<script src="'.ess::$s['relative_path'].'/js/default.js?update='.@filemtime(dirname(dirname(dirname("js/default.js")))).'" type="text/javascript"></script>';
	
	ess::$b->page->add_js('var serverTime='.(round(microtime(true)+ess::$b->date->timezone->getOffset(ess::$b->date->get()), 3)*1000).',relative_path='.js_encode(ess::$s['relative_path']).',static_link='.js_encode(STATIC_LINK).',imgs_http='.js_encode(IMGS_HTTP).',pcookie='.js_encode(ess::$s['cookie_prefix']).';');
	if (login::$logged_in) ess::$b->page->add_js('var pm_new='.login::$user->data['u_inbox_new'].',log_new='.(login::$user->player->data['up_log_new']+login::$user->player->data['up_log_ff_new']).',http_path='.js_encode(ess::$s['http_path']).',https_path='.js_encode(ess::$s['https_path'] ? ess::$s['https_path'] : ess::$s['http_path']).',use_https='.(HTTPS && login::$logged_in && login::$info['ses_secure'] ? "true" : "false").';');
	if (defined("LOCK") && LOCK) ess::$b->page->add_js('var theme_lock=true;');
}*/

return '
<!DOCTYPE html>
<html lang="no">
<head>
<title>'.$ess::$b->page->generate_title().'</title>'.$ess::$b->page->generate_head().'</head>
<body>'.$ess::$b->page->generate_body().'</body>
</html>';