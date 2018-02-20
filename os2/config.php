<?php /* ***** Orca Search - Global Configuration **************** */


/* ******************************************************************
* Orca Search v2.4
*  A robust auto-spidering search engine for single/multiple sites
* Copyright (C) 2016 GreyWyvern
*
* This program may be distributed under the terms of the GPL
*   - http://www.gnu.org/licenses/gpl.txt
*
* See the readme.txt file for installation and usage instructions.
****************************************************************** */


/* ******************************************************************
******** Functions *********************************************** */

/* ******************************************************************
 * Set a search variable in the MySQL table
 *
 */
function OS_setData($field, $input) {
  global $_DDATA, $_VDATA;
  $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablevars']}` SET `$field`='".addslashes($_VDATA[$field] = $input)."';");
  return $update->rowCount();
}

/* ******************************************************************
 * Change asterisk-prefixed listings into PCREs
 *
 */
function OS_pquote($input) {
  if ($input = trim($input)) $input = ($input{0} != "*") ? preg_quote($input, "/") : str_replace('/', '\/', substr($input, 1));
  return $input;
}

/* ******************************************************************
 * Clear the search cache
 *
 */
function OS_clearCache() {
  global $_DDATA;

  $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablestat']}` SET `cache`='';");
  $optimize = $_DDATA['link']->query("OPTIMIZE TABLE `{$_DDATA['tablestat']}`;");
}



/* ******************************************************************
******** Classes ************************************************* */

/* ******************************************************************
 * Control the list of Content Types
 *
 */
class OS_TypeList {
  var $ctype = array();

  function __construct() {}

  function get_ctype($cmtype) {
    foreach ($this->ctype as $key => $value) {
      if (strpos($cmtype, "/")) {
        if (in_array($cmtype, $value->mtypes)) return $key;
      } else if (in_array($cmtype, $value->ctypes)) return $key;
    }
    return false;
  }

  function verify() {
    global $_SDATA;

    clearstatcache();
    reset($this->ctype);
    while (list($key, $value) = each($this->ctype)) {
      $this->ctype[$key]->index = false;
      $this->ctype[$key]->ready = false;
      if (in_array($key, $_SDATA['sp.type.index'])) $this->ctype[$key]->index = true;
      if ($this->ctype[$key]->handler) {
        $this->ctype[$key]->ready = (is_file($this->ctype[$key]->handler)) ? true : false;
      } else $this->ctype[$key]->ready = true;
    }
  }

  function get_mtypes() {
    $build = array();
    foreach ($this->ctype as $value) $build = array_merge($build, $value->mtypes);
    return array_unique($build);
  }

  function needtemp() {
    foreach ($this->ctype as $value) if ($value->tofile) return true;
    return false;
  }
}

/* ******************************************************************
 * Create a Content Type
 *
 */
class OS_ContentType {
  var $mtypes  = array();
  var $ready   = false;
  var $tofile  = false;

  var $handler = "";
  var $index   = false;
  var $indexer = "";
  var $ctypes  = array();

  function __construct($mtypes = array(), $tofile = false) {
    $this->mtypes = $mtypes;
    $this->tofile = $tofile;
  }
}

/* ******************************************************************
 * Fetch a Resource
 *
 */
class OS_Fetcher {
  var $uri       = "";
  var $curl      = false;
  var $request   = "GET";
  var $ctype     = "none";
  var $into      = "";
  var $intostat  = false;
  var $errstr    = "";
  var $lastmod   = 0;
  var $parsed    = array();
  var $referer   = "";
  var $gzip      = false;
  var $headers   = array();
  var $httpcode  = "";
  var $body      = "";
  var $mimetype  = "";
  var $accept    = array("text/html");
  var $accepted  = true;
  var $charset   = "-";
  var $redirect  = "";
  var $status    = 0;  // 0 = OK, 1 = Unmodified, 2 = Timed out, 3 = Blocked, 4 = Not Found, 5 = Invalid URI, 6 = No Socket
  var $cookies   = array();
  var $dataleng  = 0;

  function __construct($uri = "") {
    global $_VDATA;

    $this->uri = $uri;
    if (function_exists("curl_init")) $this->curl = true;
  }

  function fetch() {
    $this->parsed = @parse_url($this->uri);
    if (isset($this->parsed['scheme'])) {
      if (!isset($this->parsed['path'])) {
        $this->parsed['path'] = "/";
        if ($this->uri{strlen($this->uri) - 1} != "/") $this->uri .= "/";
      }
      $this->parsed['full'] = $this->parsed['path'].((isset($this->parsed['query'])) ? "?{$this->parsed['query']}" : "");
      if (!isset($this->parsed['port']) || !$this->parsed['port'])
        $this->parsed['port'] = ($this->parsed['scheme'] == 'https') ? '443' : '80';
      $this->parsed['hostport'] = $this->parsed['host'].((isset($this->parsed['port'])) ? ":".$this->parsed['port'] : "");

      // if ($this->curl) {
      //   $this->fetchCURL();
      // } else
        $this->fetchSocket();

    } else $this->status = 5;
  }


  /* *****
   * This cURL code is still experimental.  It is not used yet in the
   * released script.
   *
   */
  function fetchCURL() {
    global $_VDATA, $_SDATA, $_MIME;

    $cn = curl_init();
    curl_setopt($cn, CURLOPT_FAILONERROR, true);
    // curl_setopt($cn, CURLOPT_HEADER, true);
    curl_setopt($cn, CURLOPT_WRITEFUNCTION, array(&$this, 'fetchCURLBody'));
    curl_setopt($cn, CURLOPT_HEADERFUNCTION, array(&$this, 'fetchCURLHeader'));
    curl_setopt($cn, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cn, CURLOPT_CONNECTTIMEOUT, 5);
    // curl_setopt($cn, CURLOPT_COOKIE, "");
    curl_setopt($cn, CURLOPT_CUSTOMREQUEST, $this->request);
    curl_setopt($cn, CURLOPT_ENCODING, "gzip");
    if ($this->referer) curl_setopt($cn, CURLOPT_REFERER, $this->referer);
    curl_setopt($cn, CURLOPT_URL, $this->uri);
    curl_setopt($cn, CURLOPT_USERAGENT, $_SDATA['userAgent']);

    $headers = array();
    $headers[] = "Accept: ".implode(", ", $this->accept).", */*;q=0.1";
    if ($this->lastmod) $headers[] = "If-Modified-Since: ".date("r", $this->lastmod);

    $cookiereq = "";
    if ($_VDATA['sp.cookies'] == "true")
      foreach ($this->cookies as $cooky)
        if (is_object($cooky))
          if ($cook = trim($cooky->makeCookie($this->parsed['host'], $this->parsed['path']), "\r\n"))
            print($headers[] = $cook);

    curl_setopt($cn, CURLOPT_HTTPHEADER, $headers);

    // if ($this->into && $pout = @fopen($this->into, "w")) {
    //  curl_setopt($cn, CURLOPT_FILE, $pout);
    //  $this->intostat = true;
    //  curl_exec($cn);
    //  fclose($pout);
    //  $this->dataleng += @filesize($this->into);
    //} else {
      $this->body = curl_exec($cn);
      $this->dataleng += strlen($this->body);
    //}
    curl_close($cn);
  }

  function fetchCURLHeader(&$cn, $header) {
    global $_VDATA;

    $headerLength = strlen($header);
    $this->dataleng += $headerLength;

    if (preg_match("/^Orcascript: Search/", $header)) $this->status = 3;
    if (preg_match("/^HTTP\/1\.\d ([1-5]\d\d)/i", $header, $code)) {
      $this->httpcode = (string)$code[1];
      if ($this->httpcode{0} != "2" && $this->httpcode{0} != "3") $this->status = 4;
      if ($this->lastmod && $this->httpcode == "304") $this->status = 1;
    }

    if ($_VDATA['sp.cookies'] == "true") {
      if (preg_match("/^Set-Cookie:\s*([^\r\n]*?)[\r\n]/i", $header, $cooky)) {
        $cooky = new OS_Cookie($cooky[1], $this->parsed['host'], $this->parsed['path']);
        if ($cooky->valid) {
          reset($this->cookies);
          while (list($key, $value) = each($this->cookies))
            if ($cooky->name == $value->name && $cooky->domain == $value->domain && $cooky->path == $value->path) unset($this->cookies[$key]);
          if (!$cooky->expired) $this->cookies[] = $cooky;
        }
      }
    }

    if (preg_match("/^Location:\s*([^\r\n]*?)[\r\n]/i", $header, $location)) {
      $this->status = 3;
      if (isset($location[1])) $this->redirect = $location[1];
    }

    // if (preg_match("/^Content-Encoding:\s?gzip/", $header)) $this->gzip = true;

    if (preg_match("/^Content-Type:\s*([^;\r\n]+?)[\s;\r\n]/i", $header, $mime)) {
      $this->mimetype = $mime[1];
      if (in_array($this->mimetype, $this->accept)) {
        if (preg_match("/charset=\s*([^;\r\n]+?)[\s;\r\n]/i", $header, $charset)) $this->charset = strtoupper($charset[1]);
      } else {
        $this->accepted = false;
        $this->status = 3;
      }
    }

    $this->headers[] = $header;
    return (!$this->status) ? $headerLength : 0;
  }

  function fetchCURLBody(&$cn, $body) {
    $bodyLength = strlen($body);

    // echo $body;
    // exit();

    return $bodyLength;
  }
  /* *****
   * END cURL CODE
   * ******************************************************************
   */


  function fetchSocket() {
    global $_VDATA, $_SDATA, $_MIME;

    $stream_context = stream_context_create([
      'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
        'verify_depth' => 0
      ]
    ]);

    $timeout = ini_get("default_socket_timeout");
    $protocol = ($this->parsed['scheme'] == 'https') ? 'ssl' : 'tcp';

    $conn = stream_socket_client("{$protocol}://{$this->parsed['host']}:{$this->parsed['port']}", $erstr, $errno, $timeout, STREAM_CLIENT_CONNECT, $stream_context);

    if ($conn) {
      $this->parsed['realhost'] = $this->parsed['host'];

    } else if ($_SERVER['HTTP_HOST'] == $this->parsed['host']) {
      $conn = stream_socket_client("{$protocol}://{$_SERVER['SERVER_ADDR']}:{$this->parsed['port']}", $erstr, $errno, $timeout, STREAM_CLIENT_CONNECT, $stream_context);

      if ($conn) {
        $this->parsed['realhost'] = $_SERVER['SERVER_ADDR'];

      } else if (($ip = @gethostbyname($this->parsed['host'])) != $this->parsed['host']) {
        $conn = stream_socket_client("{$protocol}://{$ip}:{$this->parsed['port']}", $erstr, $errno, $timeout, STREAM_CLIENT_CONNECT, $stream_context);

        if ($conn)
          $this->parsed['realhost'] = $ip;
      }
    }

    if ($conn) {
      $status = socket_get_status($conn);
      if (!$status['blocked']) socket_set_blocking($conn, true);
      // socket_set_timeout($conn, 5);

      $cookiereq = "";
      if ($_VDATA['sp.cookies'] == "true")
        foreach ($this->cookies as $cooky)
          if (is_object($cooky))
            $cookiereq .= $cooky->makeCookie($this->parsed['host'], $this->parsed['path']);

      $acceptreq = implode(", ", $this->accept);
      $lastmodreq = ($this->lastmod) ? "If-Modified-Since: ".date("r", $this->lastmod)."\r\n" : "";

      $this->parsed['full'] = str_replace(array("&amp;", " "), array("&", "%20"), $this->parsed['full']);

      fwrite($conn, "{$this->request} {$this->parsed['full']} HTTP/1.0\r\nHost: {$this->parsed['hostport']}\r\nUser-Agent: {$_SDATA['userAgent']}\r\n{$lastmodreq}{$cookiereq}Accept: {$acceptreq}, */*;q=0.1\r\nAccept-Encoding: gzip\r\n".(($this->referer) ? "Referer: {$this->referer}\r\n": "")."\r\n");
      while (!feof($conn) && !$this->status) {
        $data = fgets($conn, 1024);
        $this->dataleng += strlen($data);
        $this->headers[] = $data;

        $status = socket_get_status($conn);
        if ($status['timed_out']) $this->status = 2;
        if (preg_match("/^Orcascript: Search/", $data)) $this->status = 3;
        if (preg_match("/^HTTP\/1\.\d ([1-5]\d\d)/i", $data, $code)) {
          $this->httpcode = (string)$code[1];
          if ($this->httpcode{0} != "2" && $this->httpcode{0} != "3") $this->status = 4;
          if ($this->lastmod && $this->httpcode == "304") $this->status = 1;
        }

        if ($_VDATA['sp.cookies'] == "true") {
          if (preg_match("/^Set-Cookie:\s*([^\r\n]*?)[\r\n]/i", $data, $cooky)) {
            $cooky = new OS_Cookie($cooky[1], $this->parsed['host'], $this->parsed['path']);
            if ($cooky->valid) {
              reset($this->cookies);
              while (list($key, $value) = each($this->cookies))
                if ($cooky->name == $value->name && $cooky->domain == $value->domain && $cooky->path == $value->path) unset($this->cookies[$key]);
              if (!$cooky->expired) $this->cookies[] = $cooky;
            }
          }
        }

        if (preg_match("/^Location:\s*([^\r\n]*?)[\r\n]/i", $data, $location)) {
          $this->status = 3;
          if (isset($location[1])) $this->redirect = $location[1];
        }

        if (preg_match("/^Content-Encoding:\s?gzip/", $data)) $this->gzip = true;

        if (preg_match("/^Content-Type:\s*([^;\r\n]+?)[\s;\r\n]/i", $data, $mime)) {
          $this->mimetype = $mime[1];
          if (in_array($this->mimetype, $this->accept)) {
            if (preg_match("/charset=\s*([^;\r\n]+?)[\s;\r\n]/i", $data, $charset)) $this->charset = strtoupper($charset[1]);
          } else {
            $this->accepted = false;
            $this->status = 3;
          }
        }

        if (preg_match("/^Content-Length:\s*([^;\r\n]+?)[\s;\r\n]/i", $data, $length)) {
          if ($_VDATA['sp.filesizelimit'] < (int)$length[1]) {
            $this->accepted = false;
            $this->status = 3;
          }
        }

        if (preg_match("/^\r?\n$/", $data)) {
          if ($this->mimetype && !$this->status) {
            if (($this->ctype = $_MIME->get_ctype($this->mimetype)) && $_MIME->ctype[$this->ctype]->tofile) {
              if ($this->into && $_MIME->ctype[$this->ctype]->ready) {
                if ($pout = @fopen($this->into, "w")) {
                  while (!feof($conn)) {
                    if (($this->dataleng += strlen($data = fgets($conn, 1024))) > $_VDATA['sp.filesizelimit']) {
                      $this->accepted = false;
                      $this->status = 3;
                      break;
                    } else fwrite($pout, $data);
                  }
                  $this->intostat = true;
                  fclose($pout);
                }
              }
            } else {
              while (!feof($conn)) {
                if (($this->dataleng += strlen($data = fgets($conn, 1024))) > $_VDATA['sp.filesizelimit']) {
                  $this->accepted = false;
                  $this->status = 3;
                  break;
                } else $this->body .= $data;
              }
              $this->body = trim($this->body);
            }
          }
          break;
        }
      }
      fclose($conn);

    } else {
      $this->status = 6;
      $this->errstr = "$errno ~ $erstr";
    }

    $currenttime = timerVal();
    $totaltime = $currenttime - $starttime;
    if ($debug) {
      echo '<p>Time to fetch remote socket ('.$remote_socket.'): '.$totaltime.'</p>'."\n";
      exit;
    }
  }
}

/* ******************************************************************
 * Manage cookies while spidering
 *
 */
class OS_Cookie {
  var $name    = "";
  var $value   = "";
  var $comment = "";
  var $domain  = "";
  var $maxAge  = -1;
  var $path    = "";
  var $secure  = false;
  var $version = 1;

  var $acceptTime;
  var $valid   = true;
  var $expired = false;

  function __construct($cookytext, $host, $path) {
    $host = strtolower($host);

    $ahost = explode(".", $host);
    if (count($ahost) > 2) unset($ahost[0]);
    $this->domain = ".".join(".", $ahost);

    $this->path = (($slh = strrpos($path, "/")) > 0) ? substr($path, 0, $slh - 1) : $path;
    $this->acceptTime = time();

    $cooky = explode(";", $cookytext);
    for ($x = 0; $x < count($cooky); $x++) {
      $cook = explode("=", $cooky[$x], 2);
      if (isset($cook[1])) $cook[1] = trim(trim($cook[1]), "\"");
      if ($x == 0) {
        $this->name = $cook[0];
        $this->value = $cook[1];
      } else {
        switch (strtolower($cook[0])) {
          case "comment": $this->comment = $cook[1]; break;
          case "domain":
            $cook[1] = strtolower($cook[1]);
            if ($cook[1]{0} != "." ||
                $cook[1]{strlen($cook[1]) - 1} == "." ||
                !preg_match("/[^.]\.[^.]/", $cook[1]) ||
                strpos($host, $cook[1]) !== false) {
              $this->domain = $cook[1];
            } else $this->valid = false;
            break;
          case "max-age":
            if ($stamp = strtotime($cook[1])) $this->maxAge = $stamp;
            if ($this->maxAge < $this->acceptTime) $this->expired = true;
            break;
          case "path":
            if (strpos(strtolower($path), strtolower($cook[1])) === 0) {
              $this->path = $cook[1];
            } else $this->valid = false;
            break;
          case "secure": $this->secure = true; break;
          case "version": $this->version = (int)$cook[1]; break;
        }
      }
    }
  }

  function makeCookie($pagehost, $pagepath) {
    if (preg_match("/".preg_quote($this->domain, "/")."$/", $pagehost))
      if (strpos($pagepath, $this->path) === 0)
        if ($this->maxAge > time() || $this->maxAge == -1)
          return "Cookie: \$Version=\"{$this->version}\"; {$this->name}=\"{$this->value}\"; \$Path=\"{$this->path}\"\r\n";

    return "";
  }
}


function timerVal() {
  $mtime = microtime();
  $mtime = explode(' ', $mtime);
  $mtime = $mtime[1] + $mtime[0];
  return $mtime;
}

/* ******************************************************************
******** Begin Program ******************************************* */
error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));
$_SDATA['now'] = array_sum(explode(" ", microtime()));


/* ***** Include User Variables ********************************** */
$_MIME = new OS_TypeList();


/* ***** URL Scheme ********************************************** */
$_SDATA['scheme'] = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http");

// Detect CDN-provided SSL such as CloudFlare Flexible SSL
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
  $_SDATA['scheme'] = $_SERVER['HTTP_X_FORWARDED_PROTO'];


require "config.ini.php";


/* ***** Import Language File ************************************ */
if ($langfile = @fopen("{$_SERVER['DOCUMENT_ROOT']}/{$_SDATA['directory']}/lang.txt", "r") or
    $langfile = @fopen("./{$_SDATA['directory']}/lang.txt", "r") or
    $langfile = @fopen("{$_SDATA['directory']}/lang.txt", "r") or
    $langfile = @fopen("./lang.txt", "r") or
    $langfile = @fopen("lang.txt", "r")) {

  while (!feof($langfile)) {
    $line = fgets($langfile);
    if (strpos($line, "=") && $line{0} != "#") {
      $line = explode("=", $line, 2);
      if (trim($line[1]) == "{") {
        $_LANG[$line[0]] = "";
        while (trim($multiline = fgets($langfile)) != "}" && !feof($langfile))
          $_LANG[$line[0]] .= trim($multiline)."\n";
        $_LANG[$line[0]] = trim($_LANG[$line[0]]);
      } else $_LANG[$line[0]] = rtrim($line[1]);
    }
  }
  fclose($langfile);
} else die("Unable to load language file");


/* ***** Setup *************************************************** */
if (@$_SERVER['REQUEST_URI']) $_SERVER['PHP_SELF'] = preg_replace("/\?.*$/", "", $_SERVER['REQUEST_URI']);
if (!isset($_SERVER['SERVER_ADMIN'])) {
  if (!isset($_SERVER['MAILTO'])) {
    $_SERVER['SERVER_ADMIN'] = "webmaster@".((@$_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : "nobody");
  } else $_SERVER['SERVER_ADMIN'] = $_SERVER['MAILTO'];
}

$_SDATA['version'] = "2.4";
$_SDATA['userAgent'] = "OrcaSearch/{$_SDATA['version']} (http://www.greywyvern.com/orca#search)";
$_SDATA['windows'] = (DIRECTORY_SEPARATOR == "\\") ? true : false;
$_SDATA['zlib'] = (function_exists("gzopen")) ? true : false;

$_MIME->ctype['none'] = new OS_ContentType();
$_MIME->ctype['txt']  = new OS_ContentType(array("text/plain"));
$_MIME->ctype['html'] = new OS_ContentType(array("text/html", "application/xhtml+xml", "application/xml", "text/xml"));


/* ***** MySQL *************************************************** */
$_DDATA['online'] = false;
$_DDATA['tablevars'] = $_DDATA['tablename']."_v";
$_DDATA['tablestat'] = $_DDATA['tablename']."_s";
$_DDATA['tabletemp'] = $_DDATA['tablename']."_t";

$_DDATA['link'] = new PDO("mysql:host={$_DDATA['hostname']};dbname={$_DDATA['database']}", $_DDATA['username'], $_DDATA['password']);
$_DDATA['error'] = $_DDATA['link']->errorInfo();
$_DDATA['link']->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


if (!$_DDATA['error'][0]) {
  $_DDATA['online'] = true;

  $_DDATA['tables'] = array();
  $show = $_DDATA['link']->query("SHOW TABLES FROM `{$_DDATA['database']}`;")->fetchAll(PDO::FETCH_NUM);
  foreach ($show as $row) $_DDATA['tables'][] = $row[0];

  if (!in_array($_DDATA['tablename'], $_DDATA['tables'])) {
    $create = $_DDATA['link']->query("CREATE TABLE IF NOT EXISTS `{$_DDATA['tablename']}` (
      `uri` text NOT NULL,
      `md5` tinytext NOT NULL,
      `ctype` tinytext NOT NULL,
      `title` text NOT NULL,
      `category` tinytext NOT NULL,
      `description` text NOT NULL,
      `keywords` text NOT NULL,
      `wtags` text NOT NULL,
      `body` longtext NOT NULL,
      `links` text NOT NULL,
      `encoding` tinytext NOT NULL,
      `status` enum('OK','Orphan','Added','Blocked','Not Found') NOT NULL default 'OK',
      `unlist` enum('true','false') NOT NULL default 'false',
      `new` enum('true','false') NOT NULL default 'true',
      `locked` enum('true','false') NOT NULL default 'false',
      `sm.list` enum('true','false') NOT NULL default 'true',
      `sm.lastmod` int(11) NOT NULL default '0',
      `sm.changefreq` enum('always','hourly','daily','weekly','monthly','yearly','never') NOT NULL default 'weekly',
      `sm.priority` float NOT NULL default '0.5'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    $err = $_DDATA['link']->errorInfo();
    if ((int)$err[0])
      die("Could not create table: {$_DDATA['tablename']}\n - ".print_r($err, true));
  }

  if (!in_array($_DDATA['tablevars'], $_DDATA['tables'])) {
    $create = $_DDATA['link']->query("CREATE TABLE IF NOT EXISTS `{$_DDATA['tablevars']}` (
      `db.version` tinyint(4) NOT NULL default '4',

      /* ***** Spider ******************************************** */
      `sp.start` text NOT NULL,
      `sp.reindex` tinytext NOT NULL,
      `sp.domains` text NOT NULL,
      `sp.extensions` text NOT NULL,
      `sp.require` text NOT NULL,
      `sp.ignore` text NOT NULL,
      `sp.type.index` tinytext NOT NULL,
      `sp.type.accept` text NOT NULL,
      `sp.remtags` text NOT NULL,
      `sp.remtitle` text NOT NULL,
      `sp.defcat` tinytext NOT NULL,
      `sp.autocat` text NOT NULL,
      `sp.cookies` enum('true','false') NOT NULL default 'false',
      `sp.utf8` enum('true','false') NOT NULL default 'false',
      `sp.time` int(11) NOT NULL default '-1',
      `sp.progress` int(11) NOT NULL default '-1',
      `sp.interval` smallint(6) NOT NULL default '24',
      `sp.seamless` enum('true','false') NOT NULL default 'true',
      `sp.pagelimit` smallint(6) NOT NULL default '1000',
      `sp.filesizelimit` int(11) NOT NULL default '204800',
      `sp.linkdepth` tinyint(4) NOT NULL default '10',
      `sp.lock` enum('true','false') NOT NULL default 'false',
      `sp.cancel` enum('true','false') NOT NULL default 'false',
      `sp.pathto` text NOT NULL,
      `sp.fullscan` enum('true','false') NOT NULL default 'true',
      `sp.lasttime` float NOT NULL default '-1',
      `sp.alldata` bigint(20) NOT NULL default '0',
      `sp.cron` enum('true','false') NOT NULL default 'false',
      `sp.email` tinytext NOT NULL,

      /* ***** Search ******************************************** */
      `s.termlimit` tinyint(4) NOT NULL default '7',
      `s.termlength` tinyint(4) NOT NULL default '3',
      `s.weight` tinytext NOT NULL,
      `s.latinacc` enum('true','false') NOT NULL default 'false',
      `s.weightedtags` text NOT NULL,
      `s.resultlimit` smallint(6) NOT NULL default '500',
      `s.pagination` tinyint(4) NOT NULL default '10',
      `s.matchingtext` smallint(6) NOT NULL default '300',
      `s.ignore` text NOT NULL,
      `s.orphans` enum('show','hide') NOT NULL default 'hide',
      `s.cachetime` int(11) NOT NULL default '0',
      `s.cachereset` tinyint(4) NOT NULL default '15',
      `s.cacheemail` tinytext NOT NULL,
      `s.cachelimit` smallint(6) NOT NULL default '250',
      `s.cachegzip` enum('disabled','off','on') NOT NULL default 'disabled',
      `s.spkey` tinytext NOT NULL,

      /* ***** Sitemap ******************************************* */
      `sm.enable` enum('true','false') NOT NULL default 'false',
      `sm.pathto` text NOT NULL,
      `sm.domain` tinytext NOT NULL,
      `sm.unlisted` enum('true','false') NOT NULL default 'false',
      `sm.changefreq` enum('true','false') NOT NULL default 'true',
      `sm.gzip` enum('true','false') NOT NULL default 'false',

      /* ***** Control Panel - Misc ****************************** */
      `c.location` enum('List','Search','Spider','Stats','Tools') NOT NULL default 'Spider',
      `c.column` enum('title','uri') NOT NULL default 'uri',
      `c.sortby` enum('col1','col2') NOT NULL default 'col1',
      `c.pagination` smallint(6) NOT NULL default '100',
      `c.charset` tinytext NOT NULL,
      `c.logkey` tinytext NOT NULL,
      `c.logtime` int(11) NOT NULL default '0',
      `c.spkey` tinytext NOT NULL,

      /* ***** Control Panel - Filters *************************** */
      `cf.textexclude` tinytext NOT NULL,
      `cf.textmatch` tinytext NOT NULL,
      `cf.category` tinytext NOT NULL,
      `cf.status` enum('All','OK','Orphan','Added','Blocked','Not Found','Unlisted','Unread','Indexed') NOT NULL default 'All',
      `cf.new` enum('true','false') NOT NULL default 'false',

      /* ***** JWriter ******************************************* */
      `jw.hide` enum('true','false') NOT NULL default 'true',
      `jw.key` tinytext NOT NULL,
      `jw.progress` tinyint(4) NOT NULL default '0',
      `jw.status` int(11) NOT NULL default '0',
      `jw.egg` text NOT NULL,
      `jw.writer` text NOT NULL,
      `jw.remuri` text NOT NULL,
      `jw.ext` enum('true','false') NOT NULL default 'true',
      `jw.index` tinytext NOT NULL,
      `jw.template` text NOT NULL,
      `jw.pagination` tinyint(4) NOT NULL default '10'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    $err = $_DDATA['link']->errorInfo();
    if ((int)$err[0])
      die("Could not create table: {$_DDATA['tablevars']}\n - ".print_r($err, true));
  }

  $vrow = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablevars']}`;")->fetchAll(PDO::FETCH_NUM);
  list($count) = array_shift($vrow);
  if (!$count) {
    $insert = $_DDATA['link']->query("INSERT INTO `{$_DDATA['tablevars']}` SET

      /* ***** Spider ******************************************** */
      `sp.start`='{$_SDATA['scheme']}://{$_SERVER['HTTP_HOST']}/',
      `sp.domains`='{$_SERVER["HTTP_HOST"]}',
      `sp.extensions`='7z au aiff avi bin bz bz2 cab cda cdr class com css csv doc dll dtd dwg dxf eps exe gif hqx ico image jar jav jfif jpeg jpg js kbd mid moov mov movie mp3 mpeg mpg ocx ogg pdf png pps ppt ps psd qt ra ram rar rm rpm rtf scr sea sit svg swf sys tar.gz tga tgz tif tiff ttf uu uue vob wav xls z zip',
      `sp.type.index`='txt html',
      `sp.remtags`='.noindex form head nav noscript select style textarea',
      `sp.defcat`='Main',
      `sp.pathto`='{$_SDATA['scheme']}://{$_SERVER['HTTP_HOST']}/{$_SDATA['directory']}/spider.php',

      /* ***** Search ******************************************** */
      `s.weight`='1.3%0.5%2.1%1.9%0.2%2.5%1.5',
      `s.weightedtags`='.important dt h1 h2 h3',
      `s.cachetime`=UNIX_TIMESTAMP(),

      /* ***** Sitemap ******************************************* */
      `sm.pathto`='..".DIRECTORY_SEPARATOR."sitemap.xml',
      `sm.domain`='{$_SERVER["HTTP_HOST"]}',

      /* ***** Control Panel - Misc ****************************** */
      `c.charset`='ISO-8859-1',
      `c.logtime`=(UNIX_TIMESTAMP()-180),

      /* ***** Control Panel - Filters *************************** */
      `cf.category`='-',

      /* ***** JWriter ******************************************* */
      `jw.egg`='.".DIRECTORY_SEPARATOR."egg.js',
      `jw.writer`='{$_SDATA['scheme']}://{$_SERVER['HTTP_HOST']}/{$_SDATA['directory']}/jwriter.php',
      `jw.remuri`='{$_SDATA['scheme']}://{$_SERVER['HTTP_HOST']}/',
      `jw.index`='index.html',
      `jw.template`='<h3>\n  <span class=\"filetype\">{R_FILETYPE}</span>\n  <a href=\"{R_URI}\" title=\"{R_DESCRIPTION}\">{R_TITLE}</a>\n  - <small>{R_CATEGORY}</small>\n</h3>\n<blockquote>\n  <p>\n    {R_MATCH}<br />\n    <cite>{R_URI}</cite> <small>({R_RELEVANCE})</small>\n  </p>\n</blockquote>'
    ;");
    $err = $_DDATA['link']->errorInfo();
    if ((int)$err[0])
      die("Could not insert default data: {$_DDATA['tablevars']}\n - ".print_r($err, true));
  }

  if (!in_array($_DDATA['tablestat'], $_DDATA['tables'])) {
    $create = $_DDATA['link']->query("CREATE TABLE IF NOT EXISTS `{$_DDATA['tablestat']}` (
      `query` text NOT NULL,
      `results` tinyint(4) NOT NULL default '0',
      `hits` mediumint(9) NOT NULL default '1',
      `astyped` text NOT NULL,
      `lasthit` int(11) NOT NULL default '0',
      `cache` longblob NOT NULL,
      KEY `qk` (`query`(127))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    $err = $_DDATA['link']->errorInfo();
    if ((int)$err[0])
      die("Could not create table: {$_DDATA['tablestat']}\n - ".print_r($err, true));
  }

  $trow = $_DDATA['link']->query("SELECT * FROM `{$_DDATA['tablevars']}`;")->fetchAll();
  $_VDATA = array_shift($trow);
  $_VDATA['s.weight'] = explode("%", $_VDATA['s.weight']);


  /* ****************************************************************
  ***** This section can be safely removed after upgrade ************
  ******** Upgrade to 2.0a *************************************** */
  if (!isset($_VDATA['sp.require'])) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablevars']}`
      ADD `sp.require` TEXT AFTER `sp.extensions`
    ;");
    $_VDATA['sp.require'] = "";
  }

  /* ***** Upgrade to 2.0b *************************************** */
  if (count($_VDATA['s.weight']) == 6) {
    array_splice($_VDATA['s.weight'], 4, 0, "0.0");
    $weight = implode("%", $_VDATA['s.weight']);
    if (OS_setData("s.weight", $weight)) $_VDATA['s.weight'] = explode("%", $_VDATA['s.weight']);
    OS_clearCache();
  }

  /* ***** Upgrade to 2.1 **************************************** */
  if (!isset($_VDATA['sp.cookies'])) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablevars']}`
      ADD `sp.cookies` ENUM('true','false') DEFAULT 'false' AFTER `sp.autocat`,
      CHANGE `sp.mimetypes` `sp.type.index` TEXT NULL DEFAULT NULL,
      ADD `sp.type.accept` TEXT NULL DEFAULT NULL AFTER `sp.type.index`,
      CHANGE `cf.status` `cf.status` ENUM('All','OK','Orphan','Added','Blocked','Not Found','Unlisted','Unread','Indexed') NULL DEFAULT 'All',
      ADD `sp.linkdepth` TINYINT(4) AFTER `sp.pagelimit`,
      ADD `sp.fullscan` ENUM('true','false') NULL DEFAULT 'true' AFTER `sp.pathto`,
      ADD `sp.reindex` TEXT NULL DEFAULT NULL AFTER `sp.start`,
      CHANGE `jw.memory` `jw.status` INT(11)
    ;");
    $_VDATA['sp.cookies'] = "false";
    OS_setData("sp.type.index", "text html");
    $_VDATA['sp.type.accept'] = "";
    unset($_VDATA['sp.mimetypes']);
    OS_setData("cf.status", "All");
    OS_setData("sp.linkdepth", 10);
    OS_setData("sp.fullscan", "true");
    OS_setData("sp.reindex", "");
    OS_setData("jw.status", "0");

    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablename']}`
      CHANGE `status` `status` ENUM('OK','Orphan','Added','Blocked','Not Found') NULL DEFAULT 'OK',
      ADD `ctype` TINYTEXT NULL DEFAULT NULL AFTER `md5`,
      ADD `locked` ENUM('true','false') DEFAULT 'false' AFTER `new`
    ;");
    $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `status`='Blocked' WHERE `status`='Unread' OR `status`='';");
    $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `ctype`='html', `locked`='false';");
  }

  /* ***** Upgrade to 2.1a *************************************** */
  if (isset($_VDATA['jw.extto'])) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablevars']}`
      DROP `jw.extfrom`,
      CHANGE `jw.extto` `jw.ext` ENUM('true','false') NULL DEFAULT 'true'
    ;");
    unset($_VDATA['jw.extfrom']);
    unset($_VDATA['jw.extto']);
    OS_setData("jw.ext", "true");
    OS_setData("sp.type.index", str_replace("text", "txt", $_VDATA['sp.type.index']));

    $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `ctype`='txt' WHERE `ctype`='text';");
    $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `ctype`='jpg' WHERE `ctype`='jpeg';");
  }

  /* ***** Upgrade to 2.1b *************************************** */
  if (!isset($_VDATA['db.version'])) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablevars']}`
      ADD `db.version` TINYINT DEFAULT '0' NOT NULL FIRST,
      CHANGE `sp.start` `sp.start` TEXT NOT NULL,
      CHANGE `sp.reindex` `sp.reindex` TINYTEXT NOT NULL,
      CHANGE `sp.domains` `sp.domains` TEXT NOT NULL,
      CHANGE `sp.extensions` `sp.extensions` TEXT NOT NULL,
      CHANGE `sp.require` `sp.require` TEXT NOT NULL,
      CHANGE `sp.ignore` `sp.ignore` TEXT NOT NULL,
      CHANGE `sp.type.index` `sp.type.index` TINYTEXT NOT NULL,
      CHANGE `sp.type.accept` `sp.type.accept` TEXT NOT NULL,
      CHANGE `sp.remtags` `sp.remtags` TEXT NOT NULL,
      CHANGE `sp.remtitle` `sp.remtitle` TEXT NOT NULL,
      CHANGE `sp.defcat` `sp.defcat` TINYTEXT NOT NULL,
      CHANGE `sp.autocat` `sp.autocat` TEXT NOT NULL,
      CHANGE `sp.cookies` `sp.cookies` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sp.utf8` `sp.utf8` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sp.time` `sp.time` INT(11) NOT NULL DEFAULT '-1',
      CHANGE `sp.progress` `sp.progress` INT(11) NOT NULL DEFAULT '-1',
      CHANGE `sp.interval` `sp.interval` SMALLINT(6) NOT NULL DEFAULT '24',
      CHANGE `sp.pagelimit` `sp.pagelimit` SMALLINT(6) NOT NULL DEFAULT '1000',
      CHANGE `sp.linkdepth` `sp.linkdepth` TINYINT(4) NOT NULL DEFAULT '10',
      CHANGE `sp.lock` `sp.lock` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sp.cancel` `sp.cancel` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sp.pathto` `sp.pathto` TEXT NOT NULL,
      CHANGE `sp.fullscan` `sp.fullscan` ENUM('true','false') NOT NULL DEFAULT 'true',
      CHANGE `sp.lasttime` `sp.lasttime` FLOAT NOT NULL DEFAULT '-1',
      CHANGE `sp.alldata` `sp.alldata` BIGINT(20) NOT NULL DEFAULT '0',
      CHANGE `sp.cron` `sp.cron` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sp.email` `sp.email` TINYTEXT NOT NULL,
      CHANGE `s.termlimit` `s.termlimit` TINYINT(4) NOT NULL DEFAULT '7',
      CHANGE `s.termlength` `s.termlength` TINYINT(4) NOT NULL DEFAULT '3',
      CHANGE `s.weight` `s.weight` TINYTEXT NOT NULL,
      CHANGE `s.latinacc` `s.latinacc` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `s.weightedtags` `s.weightedtags` TEXT NOT NULL,
      CHANGE `s.resultlimit` `s.resultlimit` TINYINT(4) NOT NULL DEFAULT '0',
      CHANGE `s.pagination` `s.pagination` TINYINT(4) NOT NULL DEFAULT '10',
      CHANGE `s.matchingtext` `s.matchingtext` SMALLINT(6) NOT NULL DEFAULT '300',
      CHANGE `s.ignore` `s.ignore` TEXT NOT NULL,
      CHANGE `s.orphans` `s.orphans` ENUM('show','hide') NOT NULL DEFAULT 'hide',
      CHANGE `s.cachetime` `s.cachetime` INT(11) NOT NULL,
      CHANGE `s.cachereset` `s.cachereset` TINYINT(4) NOT NULL DEFAULT '15',
      CHANGE `s.cachelimit` `s.cachelimit` SMALLINT(6) NOT NULL DEFAULT '250',
      CHANGE `s.cachegzip` `s.cachegzip` ENUM('disabled','off','on') NOT NULL DEFAULT 'disabled',
      CHANGE `s.spkey` `s.spkey` TINYTEXT NOT NULL,
      CHANGE `sm.enable` `sm.enable` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sm.pathto` `sm.pathto` TEXT NOT NULL,
      CHANGE `sm.domain` `sm.domain` TINYTEXT NOT NULL,
      CHANGE `sm.unlisted` `sm.unlisted` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sm.changefreq` `sm.changefreq` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sm.gzip` `sm.gzip` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `c.location` `c.location` ENUM('List','Search','Spider','Stats','Tools') NOT NULL DEFAULT 'Spider',
      CHANGE `c.column` `c.column` ENUM('title','uri') NOT NULL DEFAULT 'uri',
      CHANGE `c.sortby` `c.sortby` ENUM('col1','col2') NOT NULL DEFAULT 'col1',
      CHANGE `c.pagination` `c.pagination` SMALLINT(6) NOT NULL DEFAULT '100',
      CHANGE `c.charset` `c.charset` TINYTEXT NOT NULL,
      CHANGE `c.logkey` `c.logkey` TINYTEXT NOT NULL,
      CHANGE `c.logtime` `c.logtime` INT(11) NOT NULL,
      CHANGE `c.spkey` `c.spkey` TINYTEXT NOT NULL,
      CHANGE `cf.textexclude` `cf.textexclude` TINYTEXT NOT NULL,
      CHANGE `cf.textmatch` `cf.textmatch` TINYTEXT NOT NULL,
      CHANGE `cf.category` `cf.category` TINYTEXT NOT NULL,
      CHANGE `cf.status` `cf.status` ENUM('All','OK','Orphan','Added','Blocked','Not Found','Unlisted','Unread','Indexed') NOT NULL DEFAULT 'All',
      CHANGE `cf.new` `cf.new` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `jw.hide` `jw.hide` ENUM('true','false') NOT NULL DEFAULT 'true',
      CHANGE `jw.key` `jw.key` TINYTEXT NOT NULL,
      CHANGE `jw.progress` `jw.progress` TINYINT(4) NOT NULL DEFAULT '0',
      CHANGE `jw.status` `jw.status` INT(11) NOT NULL DEFAULT '0',
      CHANGE `jw.egg` `jw.egg` TEXT NOT NULL,
      CHANGE `jw.writer` `jw.writer` TEXT NOT NULL,
      CHANGE `jw.remuri` `jw.remuri` TEXT NOT NULL,
      CHANGE `jw.ext` `jw.ext` ENUM('true','false') NOT NULL DEFAULT 'true',
      CHANGE `jw.index` `jw.index` TINYTEXT NOT NULL,
      CHANGE `jw.template` `jw.template` TEXT NOT NULL,
      CHANGE `jw.pagination` `jw.pagination` TINYINT(4) NOT NULL DEFAULT '10'
    ;");
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablename']}`
      CHANGE `uri` `uri` TEXT NOT NULL,
      CHANGE `md5` `md5` TINYTEXT NOT NULL,
      CHANGE `ctype` `ctype` TINYTEXT NOT NULL,
      CHANGE `title` `title` TEXT NOT NULL,
      CHANGE `category` `category` TINYTEXT NOT NULL,
      CHANGE `description` `description` TEXT NOT NULL,
      CHANGE `keywords` `keywords` TEXT NOT NULL,
      CHANGE `wtags` `wtags` TEXT NOT NULL,
      CHANGE `body` `body` TEXT NOT NULL,
      CHANGE `links` `links` TEXT NOT NULL,
      CHANGE `encoding` `encoding` TINYTEXT NOT NULL,
      CHANGE `status` `status` ENUM('OK','Orphan','Added','Blocked','Not Found') NOT NULL DEFAULT 'OK',
      CHANGE `unlist` `unlist` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `new` `new` ENUM('true','false') NOT NULL DEFAULT 'true',
      CHANGE `locked` `locked` ENUM('true','false') NOT NULL DEFAULT 'false',
      CHANGE `sm.list` `sm.list` ENUM('true','false') NOT NULL DEFAULT 'true',
      CHANGE `sm.lastmod` `sm.lastmod` INT(11) NOT NULL DEFAULT '0',
      CHANGE `sm.changefreq` `sm.changefreq` ENUM('always','hourly','daily','weekly','monthly','yearly','never') NOT NULL DEFAULT 'weekly',
      CHANGE `sm.priority` `sm.priority` FLOAT NOT NULL DEFAULT '0.5'
    ;");
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablestat']}`
      CHANGE `query` `query` TEXT NOT NULL ,
      CHANGE `hits` `hits` MEDIUMINT(9) NOT NULL DEFAULT '1',
      CHANGE `astyped` `astyped` TEXT NOT NULL ,
      CHANGE `lasthit` `lasthit` INT(11) NOT NULL DEFAULT '0',
      CHANGE `cache` `cache` LONGBLOB NOT NULL
    ;");
    $_VDATA['db.version'] = 0;
  }

  /* ***** Upgrade to 2.1c *************************************** */
  if ($_VDATA['db.version'] == 0) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablestat']}`
      ADD `results` TINYINT(4) DEFAULT '0' NOT NULL AFTER `query`
    ;");
    OS_setData("db.version", 1);
  }

  /* ***** Upgrade to 2.2 **************************************** */
  if ($_VDATA['db.version'] == 1) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablevars']}`
      ADD `sp.seamless` ENUM('true','false') NOT NULL DEFAULT 'true' AFTER `sp.interval`
    ;");
    $_VDATA['sp.seamless'] = "false";
    OS_setData("db.version", 2);
  }

  /* ***** Upgrade to 2.3 **************************************** */
  if ($_VDATA['db.version'] == 2) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablename']}`
      CHANGE `body` `body` LONGTEXT NOT NULL
    ;");
    OS_setData("db.version", 3);
  }

   /* ***** Upgrade to 2.3a *************************************** */
  if ($_VDATA['db.version'] == 3) {
    $alter = $_DDATA['link']->query("ALTER TABLE `{$_DDATA['tablevars']}`
      ADD `sp.filesizelimit` INT NOT NULL DEFAULT '204800' AFTER `sp.pagelimit`,
      ADD `s.cacheemail` TINYTEXT NOT NULL AFTER `s.cachereset`,
      CHANGE `s.resultlimit` `s.resultlimit` SMALLINT(6) NOT NULL DEFAULT '0'
    ;");
    $_VDATA['sp.filesizelimit'] = 204800;
    OS_setData("db.version", 4);
  }

  /* ***** End of Upgrades ******************************************
  **************************************************************** */


  if ($_VDATA['s.cachegzip'] == "disabled" && $_SDATA['zlib']) {
    OS_setData("s.cachegzip", "off");
    OS_clearCache();
  } else if ($_VDATA['s.cachegzip'] != "disabled" && !$_SDATA['zlib']) {
    OS_setData("s.cachegzip", "disabled");
    OS_clearCache();
  }

  $_SDATA['sp.type.index'] = array_filter(array_unique(explode(" ", $_VDATA['sp.type.index'])));
  foreach ($_SDATA['sp.type.index'] as $key => $mimeindex) if (!isset($_MIME->ctype[$mimeindex])) unset($_SDATA['sp.type.index'][$key]);
  if (OS_setData("sp.type.index", trim(implode(" ", array_values($_SDATA['sp.type.index']))))) {
    $_SDATA['sp.type.index'] = array_filter(array_unique(explode(" ", $_VDATA['sp.type.index'])));
    OS_setData("sp.fullscan", "true");
  }

  $_MIME->verify();

  $mimeAccept = array_filter(array_map("trim", array_unique(explode("\n", $_VDATA['sp.type.accept']))));
  foreach ($mimeAccept as $accepted) $_MIME->ctype['none']->mtypes[] = $accepted;
  $_MIME->ctype['none']->mtypes = array_filter(array_unique($_MIME->ctype['none']->mtypes));

}


/* ******************************************************************
******** Do not cache this page ********************************** */
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
