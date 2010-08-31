<?php

session_start();

/** Exception type for database */
class SQLException extends Exception
{
	protected $sql_err;
	protected $sql_errnum;
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->sql_err = $err;
		$this->sql_errnum = $errnum;
	}
	public function getSQLError() { return $this->sql_err; }
	public function getSQLErrorNum() { return $this->sql_errnum; }
}

/** Exception: Databasetilkobling */
abstract class SQLConnectionException extends SQLException {}

/** Exception: Ingen databasetilkobling */
class SQLNoConnectionException extends SQLConnectionException {
	public function __construct()
	{
		parent::__construct("", 0);
		$this->message = "Det finnes ingen tilkobling til databasen.";
	}
}

/** Exception: Databasetilkobling: Selve tilkoblingen */
class SQLConnectException extends SQLConnectionException
{
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->message = "Kunne ikke opprette kobling med databasen: ($errnum) $err";
	}
}

/** Exception: Databasetilkobling: Velge database */
class SQLSelectDatabaseException extends SQLConnectionException
{
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->message = "Kunne ikke velge riktig database: ($errnum) $err";
	}
}

/** Exception: Databasespørring */
class SQLQueryException extends SQLException {
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->message = "Kunne ikke utføre spørring: ($errnum) $err";
	}
}

class db_wrap_debug extends db_wrap
{
	public $queries_text = array();
	public $lastquery = 0;
	public $lastq_s = false;
	public $lastq_r = false;
	
	/** Utfør spørring */
	public function query($query, $critical = true, $debug = false)
	{
		// hent data
		$result = parent::query($query, $critical, $debug);
		$info = mysql_info($this->link);
		
		// tid siden forrige spørring
		if ($this->lastquery)
		{
			$time = $this->time_last_begin - $this->lastquery;
		}
		else
		{
			$time = 0;
		}
		
		// lagre debug
		$this->queries_text[] = array(
			"script_time____" => (microtime(true)-SCRIPT_START)*1000,
			"time_last_query" => round($time, 6)*1000,
			"query_time_____" => round($this->time_last, 6)*1000,
			"query_info_____" => $info,
			"query__________" => $query
		);
		$this->lastquery = microtime(true);
		
		// send svaret tilbake
		return $result;
	}
}

class db_wrap
{
	public $link = false;
	public $queries = 0;
	public $time = 0;
	public $time_last_begin = 0;
	public $time_last_end = 0;
	public $time_last = 0;
	
	public function __construct()
	{
		// koble til
		//$this->connect();
	}
	
	/** Opprett kobling mot databasen */
	public function connect($host, $user, $pass, $dbname)
	{
		// koble til databasen
		$this->link = @mysql_connect($host, $user, $pass);
		
		if (!$this->link)
		{
			throw new SQLConnectException(mysql_error(), mysql_errno());
		}
		
		// velg riktig database
		if (!@mysql_select_db($dbname, $this->link))
		{
			throw new SQLSelectDatabaseException(mysql_error($this->link), mysql_errno($this->link));
		}
	}
	
	/** Lukk tilkoblingen til databasen */
	public function close()
	{
		if ($this->link)
		{
			@mysql_close($this->link);
			$this->link = false;
		}
	}
	
	/** Utfør spørring */
	public function query($query, $critical = true, $debug = false)
	{
		// ikke tilkoblet?
		if (!$this->link)
		{
			throw new SQLNoConnectionException();
		}
		
		// øk teller
		++$this->queries;
		
		// utfør spørring
		$this->time_last_begin = microtime(true);
		$result = @mysql_query($query, $this->link);
		$this->time_last_end = microtime(true);
		
		$this->time_last = $this->time_last_end - $this->time_last_begin;
		$this->time += $this->time_last;
		
		// feil ved spørring (ikke vis dersom $critical = false)
		if (!$result && $critical)
		{
			$err = mysql_error($this->link);
			$errnum = mysql_errno($this->link);
			throw new SQLQueryException($err, $errnum);
		}
		elseif (!$result)
		{
			// legg til feilmelding
			global $_base;
			if (isset($_base->page)) $_base->page->add_message("Ukritisk databasefeil: ".mysql_error($this->link), "error");
		}
		
		// debug?
		if ($debug)
		{
			$this->debug($result, $query);
		}
		
		// send svaret tilbake
		return $result;
	}
	
	/** Hent siste ID som ble satt inn */
	public function insert_id()
	{
		return mysql_insert_id($this->link);
	}
	
	/** Quote verdi */
	public function quote($text, $null = true)
	{
		if (empty($text) && $null) return 'NULL';
		return "'".mysql_real_escape_string($text)."'";
	}
	
	/** Antall rader berørt */
	public function affected_rows()
	{
		return mysql_affected_rows($this->link);
	}
	
	/** Start transaksjon */
	public function begin()
	{
		$this->query("BEGIN");
	}
	
	/** Avslutt (fullfør) transaksjon */
	public function commit()
	{
		$this->query("COMMIT");
	}
	
	/** Avbryt transaksjon */
	public function rollback()
	{
		$this->query("ROLLBACK");
	}
	
	/** Debug spørring */
	public function debug($result, $query = "")
	{
		// fjern det som allerede har blitt sendt til buffer
		@ob_clean();
		
		// skriv xhtml
		echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; HenriSt.net" />
<title>Query Debug</title>
<style type="text/css">
<!--
.q_debug td {
	white-space: nowrap;
}
-->
</style>
</head>
<body>
<h1>Query Debug</h1>
<p>
	Debug of MySQL query:<br />
	<pre>'.htmlspecialchars($query).'</pre>
</p>
<table cellpadding="2" cellspacing="0" border="1" frame="hidden" rules="all" class="q_debug">
	<thead>
		<tr>';
		
		// list opp feltene
		while ($field = mysql_fetch_field($result)) {
			echo '
			<th bgcolor="#EEEEEE">'.htmlspecialchars($field->name).'</th>';
		}
		
		echo '
		</tr>
	</thead>
	<tbody>';
		
		if (mysql_num_rows($result) == 0) {
			// ingen rader?
			echo '
		<tr>
			<td colspan="'.mysql_num_fields($result).'">No row exists.</td>
		</tr>';
		} else {
			// gå til første rad
			mysql_data_seek($result, 0);
			
			// vis hver rad
			while ($row = mysql_fetch_row($result)) {
				echo '
		<tr>';
				
				// gå gjennom hvert felt
				foreach ($row as $value) {
					echo '
			<td>'.($value == NULL ? '<i style="color: #CCC">NULL</i>' : ($value === "" ? '<i style="color: #CCC">TOMT</i>' : nl2br(htmlspecialchars($value)))).'</td>';
				}
				
				echo '
		</tr>';
			}
		}
		
		echo '
	</tbody>
</table>';
		
		echo '
<p>
	<a href="http://hsw.no/">hsw.no</a>
</p>
</body>
</html>';
		
		die;
	}
}

function page_reload()
{
	$url = isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['SCRIPT_NAME'];
	$location = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? 'https' : 'http').'://'.$_SERVER["HTTP_HOST"].$url;
	
	// send til siden
	@header("Location: $location");
	@ob_clean();
	die('<HTML><HEAD><TITLE>302 Found</TITLE></HEAD><BODY><H1>Found</H1>You have been redirected <A HREF="'.$location.'">here</A>.<P></BODY></HTML>');
}

/**
 * Formatter data så det kan brukes i JavaScript variabler osv
 * Ikke UTF-8 (slik som json_encode)
 * 
 * @param string $value
 */
function js_encode($value)
{
	if (is_null($value)) return 'null';
	if ($value === false) return 'false';
	if ($value === true) return 'true';
	if (is_scalar($value))
	{
		if (is_string($value))
		{
			static $json_replace_from = array(
				"\\",
				'"',
				"/",
				"\x8",
				"\xC",
				"\n",
				"\r",
				"\t"
			);
			static $json_replace_to = array(
				"\\\\",
				'\\"',
				"\\/",
				"\\b",
				"\\f",
				"\\n",
				"\\r",
				"\\t"
			);
			
			return '"'.str_replace($json_replace_from, $json_replace_to, $value).'"';
		}
		
		return $value;
	}
	
	if (!is_array($value) && !is_object($value)) return false;
	
	$object = false;
	for ($i = 0, reset($value), $len = count($value); $i < $len; $i++, next($value))
	{
		if (key($value) !== $i)
		{
			$object = true;
			break;
		}
	}
	
	$result = array();
	if ($object)
	{
		foreach ($value as $k => $v) $result[] = js_encode($k).':'.js_encode($v);
		return '{'.implode(",", $result).'}';
	}
	
	foreach ($value as $v) $result[] = js_encode($v);
	return '['.implode(",", $result).']';
}


/**
 * Sending av e-post
 * 
 * Støtter html og tekst, samt vanlige vedlegg og vedlegg for html
 */
class email
{
	public $html = false;
	public $text = false;
	public $html_attachments = array();
	public $attachments = array();
	public $headers = array();
	public $params = '';
	public $data = false;
	
	/**
	 * Constructor
	 *
	 * @param string $sender avsender
	 */
	public function __construct($sender = "Espen og Henriette <henriette.steen@gmail.com>")
	{
		$this->headers["From"] = $sender;
		$this->headers["MIME-Version"] = "1.0";
		$this->headers["X-Mailer"] = "HenriSt Mailer (PHP ".phpversion().")";
		
		// avsender
		$this->params = "";
		$matches = false;
		preg_match("/([a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z])/i", $this->headers['From'], $matches);
		if (isset($matches[1]))
		{
			$this->params = "-f {$matches[1]}";
		}
	}
	
	/**
	 * Sett HTML
	 *
	 * @param string html $content
	 * @return object $this
	 */
	public function html($content)
	{
		$this->html = $content;
		return $this;
	}
	
	/**
	 * Sett tekst
	 * 
	 * @param string $content
	 * @return object $this
	 */
	public function text($content)
	{
		$this->text = $content;
		return $this;
	}
	
	/**
	 * Lag boundary ID
	 *
	 * @return string
	 */
	private function genid()
	{
		return "----HENRIST-".uniqid();
	}
	
	/**
	 * Kod om tekst (base64 eller quoted-printable)
	 * 
	 * @param string $data
	 * @param string $encoding
	 * @return array (headings, content)
	 */
	private function encode($data, $encoding = "base64")
	{
		switch ($encoding)
		{
			case "base64":
				$data = trim(chunk_split(base64_encode($data)));
			break;
			
			case "quoted-printable":
				$length = strlen($data);
				$result = '';
				$linelength = 0;
				
				for ($i = 0; $i < $length; $i++)
				{
					$c = ord($data[$i]);
					
					// linjeskift?
					if ($c == 10 || $c == 13)
					{
						$result .= $data[$i];
						$linelength = 0;
						continue;
					}
					
					// ny linje?
					if ($linelength == 75)
					{
						$result .= "=\r\n";
						$linelength = 0;
					}
					
					// tegn som må kodes om?
					// kan forbedres litt for å stemme fullstendig med RFC 2045, men denne gjør jobben
					if (($c == 61 || $c < 33 || $c > 126) && ($c != 32 || $linelength > 73) && ($c != 9 || $linelength > 73))
					{
						// må vi over på ny linje?
						if ($linelength+3 > 76)
						{
							$result .= "=\r\n";
							$linelength = 0;
						}
						elseif ($linelength+3 == 76)
						{
							$next = $data[$i+1];
							if ($next != "\r" && $next != "\n")
							{
								$result .= "=\r\n";
								$linelength = 0;
							}
						}
						
						$result .= "=".str_pad(strtoupper(dechex($c)), 2, '0', STR_PAD_LEFT);
						$linelength += 3;
					}
					
					else
					{
						$result .= $data[$i];
						$linelength++;
					}
				}
				
				$data = $result;
			break;
			
			default:
				throw new Exception("Encoding type $encoding not supported.");
		}
		
		return array('Content-Transfer-Encoding: '.$encoding, $data);
	}
	
	/**
	 * Legg til vanlig vedlegg
	 *
	 * @param string $headers
	 * @param string $data
	 * @param string $encoding
	 * @return object $this
	 */
	public function attach($headers, $data, $encoding = "base64")
	{
		$data = $this->encode($data, $encoding);
		$this->attachments[] = array((!empty($headers) ? $headers."\r\n" : '') . $data[0], $data[1]);
		return $this;
	}
	
	/**
	 * Legg til vedlegg for HTML
	 * 
	 * @param string $headers
	 * @param string $data
	 * @param string $encoding
	 * @return object $this
	 */
	public function html_attach($headers, $data, $encoding = "base64")
	{
		$data = $this->encode($data, $encoding);
		$this->html_attachments[] = array((!empty($headers) ? $headers."\r\n" : '') . $data[0], $data[1]);
		return $this;
	}
	
	/**
	 * Formater e-posten
	 *
	 * @return object $this
	 */
	public function format()
	{
		// for å sjekke om det blir opprettet noen grupper (multiparts)
		$id = false;
		
		// ingenting å sende?
		if ($this->text == false && $this->html == false && count($this->html_attachments) == 0 && count($this->attachments) == 0)
		{
			throw new Exception("No content to send by email."); 
		}
		
		// sett opp html
		$html = false;
		if ($this->html)
		{
			$html = $this->encode($this->html, "quoted-printable");
			$html[0] = "Content-Type: text/html; charset=ISO-8859-1\r\n".$html[0];
			
			// vedlegg?
			if (count($this->html_attachments) > 0)
			{
				$id = $this->genid();
				
				$html[1] = "--".$id."\r\n".$html[0]."\r\n\r\n".$html[1]."\r\n--".$id;
				$html[0] = 'Content-Type: multipart/related; boundary="'.$id.'"';
				
				// vedleggene
				foreach ($this->html_attachments as $item)
				{
					$html[1] .= "\r\n".$item[0]."\r\n\r\n".$item[1]."\r\n--".$id;
				}
				
				$html[1] .= "--";
			}
		}
		
		// sett opp tekst
		$text = false;
		if ($this->text)
		{
			$text = $this->encode($this->text, "quoted-printable");
			$text[0] = "Content-Type: text/plain; charset=ISO-8859-1\r\n".$text[0];
		}
		
		// slå sammen med html
		$data = !$text && $html ? $html : ($text && !$html ? $text : false);
		if ($text && $html)
		{
			$id = $this->genid();
			
			$data[1] = "--".$id."\r\n".$text[0]."\r\n\r\n".$text[1]."\r\n--".$id;
			$data[1] .= "\r\n".$html[0]."\r\n\r\n".$html[1]."\r\n--".$id."--";
			$data[0] = 'Content-Type: multipart/alternative; boundary="'.$id.'"';
		}
		
		// legg til vedlegg
		if (count($this->attachments) > 0)
		{
			$id = $this->genid();
			
			$data[1] = "--".$id."\r\n".$data[0]."\r\n\r\n".$data[1]."\r\n--".$id;
			$data[0] = 'Content-Type: multipart/mixed; boundary="'.$id.'"';
			
			// vedleggene
			foreach ($this->attachments as $item)
			{
				$data[1] .= "\r\n".$item[0]."\r\n\r\n".$item[1]."\r\n--".$id;
			}
			
			$data[1] .= "--";
		}
		
		// sett opp resten av e-posten
		if ($id)
		{
			$data[1] = "This is a multi-part message in MIME format.\r\n".$data[1];
		}
		
		// sett opp headers
		$headers = array();
		foreach ($this->headers as $name => $value)
		{
			$headers[] = "$name: $value";
		}
		$headers = implode("\r\n", $headers)."\r\n".$data[0];
		
		$this->data = array($headers, $data[1]);
		return $this;
	}
	
	/**
	 * Send e-posten
	 *
	 * @param string $receiver
	 * @param string $subject
	 * @return boolean success
	 */
	public function send($receiver, $subject = "<no subject>")
	{
		// ikke formatert e-posten?
		if (!$this->data)
		{
			$this->format();
		}
		
		$headers = $this->data[0];
		
		// sørg for gyldig mottakeradresse (kun e-post, ikke navn)
		$matches = false;
		preg_match("/([a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z])/i", $receiver, $matches);
		if (isset($matches[1]))
		{
			// kjør To: header
			$headers = "To: ".preg_replace("/[\\r\\n]/", "", $receiver).($headers !== "" ? "\r\n" : "").$headers;
			$receiver = $matches[1];
		}
		
		// send e-posten
		return @mail($receiver, $subject, $this->data[1], $headers, $this->params);
	}
}

function postval($name, $default = "")
{
	if (!isset($_POST[$name])) return $default;
	return $_POST[$name];
}


/** Sjekk for gyldig e-postadresse */
function validemail($address)
{
	return preg_match("/^[a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z]$/Di", $address);
}


class pagedata
{
	public $path;
	public $path_parts;
	
	public $doc_path = "";
	
	public function __construct()
	{
		$this->load_page_path();
	}
	
	public function load_page_path()
	{
		if (!isset($_SERVER['REDIRECT_URL']))
		{
			$this->path = "";
			$this->path_parts = array();
			return;
		}
		$redirect = $_SERVER['REDIRECT_URL'];
		$script = $_SERVER['SCRIPT_NAME'];
		
		$this->doc_path = substr($script, 0, strrpos($script, "/"));
		$this->path = substr($redirect, strlen($this->doc_path)+1);
		$this->path_parts = explode("/", $this->path);
	}
}

class pagehandle
{
	const TEMPLATES_DIR = "./templates";
	const TEMPLATES_DEFAULT = "html5_main";
	
	/**
	 * @var pagedata
	 */
	public $pagedata;
	
	/**
	 * @var db_wrap
	 */
	public $db;
	
	public function __construct()
	{
		@ob_start();
		$this->pagedata = new pagedata();
	}
	
	/** Sett melding */
	/*public function msg_set($msg)
	{
		$_SESSION['msg'] = $msg;
	}*/
	
	/** Hent ut melding */
	/*public function msg_get()
	{
		if (!isset($_SESSION['msg'])) return NULL;
		
		$msg = $_SESSION['msg'];
		unset($_SESSION['msg']);
		return $msg;
	}*/
	
	/* ====================
	 * Template spesifikt
	 */
	
	public $title_split = " => ";
	public $title_direction = "right";
	
	public $title = array();
	public $head = '';
	public $css = '';
	public $js = '';
	public $js_domready = '';
	protected $js_files_loaded = array();
	public $body_start = '';
	public $body_end = '';
	public $keywords = array();
	public $description = '';
	
	/**
	 * Kjøre ut template
	 */
	public function load_template($template = null)
	{
		if (!$template) $template = self::TEMPLATES_DEFAULT;
		$file = self::TEMPLATES_DIR."/$template.php";
		
		if (!file_exists($file))
		{
			throw new Exception("Fant ikke template: $template");
		}
		
		$pagehandle = $this;
		echo require $file;
	}
	
	/** Hent innhold til <head> */
	public function generate_head()
	{
		$head = $this->head;
		
		// legg til css
		if (!empty($this->css))
		{
			$head .= "<style type=\"text/css\">\r\n<!--\r\n" . $this->css . "-->\r\n</style>\r\n";
		}
		
		// legg til javascript
		if (!empty($this->js) || !empty($this->js_domready))
		{
			$dr = !empty($this->js_domready) ? "window.addEvent(\"sm_domready\", function() {\r\n{$this->js_domready}});\r\n" : "";
			$head .= "<script type=\"text/javascript\">\r\n<!--\r\n" . $this->js . $dr . "// -->\r\n</script>\r\n";
		}
		
		// send resultatet
		return $head;
	}
	
	/** Generer tittel */
	public function generate_title()
	{
		// sett sammen tittelen og send resultatet
		return implode($this->title_split, ($this->title_direction == "right" ? $this->title : array_reverse($this->title)));
	}
	
	/** Generer nøkkelord */
	public function generate_keywords()
	{
		// sett sammen keywords og send resultatet
		return implode(", ", $this->keywords);
	}
	
	/** Generer innhold på høyre siden */
	public function generate_content_right()
	{
		$content = "";
		foreach ($this->content_right as $row)
		{
			$content .= $row["content"];
		}
		
		return $content;
	}
	
	/** Legg til innhold på høyre siden */
	public function add_content_right($content, $priority = NULL)
	{
		// bestem prioritering
		if ($priority !== NULL) $priority = (int) $priority;
		
		// innholdet
		$arr = array("priority" => $priority, "content" => $content);
		
		// finn ut hvor vi skal plassere den
		if ($priority === NULL) array_push($this->content_right, $arr);
		else
		{
			$i = 0;
			foreach ($this->content_right as $row)
			{
				if ($row['priority'] > $priority)
				{
					array_splice($this->content_right, $i, 0, array($arr));
					$i = -1;
					break;
				}
				$i++;
			}
			
			if ($i >= 0) array_push($this->content_right, $arr);
		}
	}
	
	/** Legg til tittel */
	public function add_title()
	{
		foreach (func_get_args() as $value) {
			$this->title[] = htmlspecialchars($value);
		}
	}
	
	/** Legg til data i <head> */
	public function add_head($value)
	{
		$this->head .= $value."\r\n";
	}
	
	/** Legg til CSS */
	public function add_css($value)
	{
		$this->css .= $value."\r\n";
	}
	
	/** Legg til en hel CSS fil */
	public function add_css_file($path, $media = "all")
	{
		$this->add_head('<link rel="stylesheet" type="text/css" href="'.$path.'" media="'.$media.'" />');
	}
	
	/** Legg til javascript */
	public function add_js($value)
	{
		$this->js .= $value."\r\n";
	}
	
	/** Legg til javascript som kjøres i domready event */
	public function add_js_domready($value)
	{
		$this->js_domready .= $value."\r\n";
	}
	
	/** Legg til javascript fil */
	public function add_js_file($path)
	{
		// allerede lastet inn?
		if (in_array($path, $this->js_files_loaded)) return;
		$this->js_files_loaded[] = $path;
		$this->add_head('<script src="'.$path.'" type="text/javascript"></script>');
	}
	
	/** Legg til HTML rett etter <body> */
	public function add_body_pre($value)
	{
		$this->body_start .= $value."\r\n";
	}
	
	/** Legg til HTML rett før </body> */
	public function add_body_post($value)
	{
		$this->body_end .= $value."\r\n";
	}
	
	/** Legg til nøkkelord */
	public function add_keyword()
	{
		foreach (func_get_args() as $value) {
			$this->keywords[] = htmlspecialchars($value);
		}
	}
	
	/** Nullstill alle nøkkelordene (sletter dem) */
	public function reset_keywords()
	{	
		$this->keywords = array();
	}
	
	/** Endre beskrivelsen */
	public function set_description($value)
	{
		$this->description = htmlspecialchars($value);
	}
	
	/**
	 * Legg til informasjonsmelding (info, error, osv)
	 * 
	 * @param string $value
	 * @param string $type = NULL
	 * @param string $force = NULL
	 * @param string $name = NULL
	 */
	public function add_message($value, $type = NULL, $force = NULL, $name = NULL)
	{
		// standard type er info
		if ($type === NULL) $type = "info";
		
		// raden
		$row = array(
			"type" => $type,
			"message" => $value
		);
		
		// skal den plasseres et bestemt sted?
		if ($force !== NULL) $row['force'] = $force;
		
		// for å muliggjøre overskriving/sletting
		if ($name)
		{
			$this->messages[$name] = $row;
		}
		else
		{
			$this->messages[] = $row;
		}
	}
	
	/**
	 * Hent ut en bestemt informasjonsmelding
	 */
	public function message_get($name, $erase = true, $format = null)
	{
		// finnes ikke meldingen?
		if (!isset($this->messages[$name])) return null;
		$msg = &$this->messages[$name];
		
		// slette meldingen?
		if ($erase)
		{
			unset($this->messages[$name]);
		}
		
		if ($format) return $this->message_format($msg);
		return $msg;
	}
	
	/**
	 * Formater html for melding
	 */
	public function message_format($row)
	{
		// hva slags type melding?
		switch ($row['type'])
		{
			// feilmelding
			case "error":
				return '<div class="error_box">'.$row['message'].'</div>';
			break;
			
			// informasjon
			case "info":
				return '<div class="info_box">'.$row['message'].'</div>';
			break;
			
			// egendefinert
			case "custom":
				return $row['message'];
			break;
			
			// ukjent
			default:
				return '<div class="info_box">'.htmlspecialchars($row['type']).' (ukjent): '.$row['message'].'</div>';
		}
	}
	
	public function generate_body()
	{
		$content = @ob_get_contents();
		@ob_clean();
		
		return $content;
	}
}