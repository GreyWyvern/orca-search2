<?php /* ***** Orca Search - Spidering Engine ******************** */


$_SDATA['lang'] = true;
include "config.php";


/* ******************************************************************
******** Functions *********************************************** */
function OS_add2Queue($uri, $referer = "", $parentdepth = 0, $verifyonly = false) {
  global $_XDATA, $_VDATA, $_DDATA;

  $uri = str_replace("/./", "/", trim($uri));
  $uri = preg_replace("/(?<!:)\/{2,}/", "/", $uri);

  $turi = @parse_url($uri);
  if (!isset($turi['host']) || !isset($turi['scheme']) || !in_array($turi['scheme'], array("http", "https"))) return "";
  if (isset($turi['port'])) $turi['host'] .= ":".$turi['port'];

  if (!isset($turi['path'])) $turi['path'] = "/";
  $turi['path'] = preg_replace("/^(\/\.\.)+/", "", $turi['path']);

  while ($ti = $turi['path']) {
    $turi['path'] = preg_replace("/[^\/]+?\/\.\.\//", "", $turi['path']);
    $turi['path'] = preg_replace("/^\/\.\.\//", "/", $turi['path']);

    if ($ti == $turi['path']) {
      if (strpos($turi['path'], "../") !== false) return "";
      break;
    }
  }

  if (isset($_XDATA['domainLimit'][$turi['host']])) {
    $di = false;
    foreach ($_XDATA['domainLimit'][$turi['host']] as $limiter) if (strpos($turi['path'], $limiter) === 0) $di = true;
    if (!$di) return "";
  }
  $uri = "{$turi['scheme']}://{$turi['host']}{$turi['path']}".((isset($turi['query'])) ? "?{$turi['query']}" : "");

  if ($uri == $_VDATA['sp.pathto']) return "";
  if (isset($_XDATA['queue'][$uri])) return "";
  if (isset($_XDATA['scanned'][$uri])) return "";

  if (!$_XDATA['reindex']) {
    if (!in_array($turi['host'], $_XDATA['allowedDomains'])) return "";

    if (OS_isBlocked($uri)) {
      $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `status`='Blocked', `body`='' WHERE `uri`='".addslashes($uri)."';");
      if ($update->rowCount()) $_XDATA['stats']['Blocked']++;
      return "";
    }
  }

  if (!$verifyonly && $parentdepth <= $_VDATA['sp.linkdepth']) $_XDATA['queue'][$uri] = array($parentdepth + 1, $referer);
  return $uri;
}

function OS_isBlocked($uri) {
  global $_XDATA;

  $foo = (count($_XDATA['onlySpider'])) ? true : false;
  foreach ($_XDATA['noSpider'] as $noSpider) if (preg_match("/{$noSpider}/i", $uri)) return true;
  foreach ($_XDATA['robotsCancel'] as $robotsCancel) if (strpos($uri, $robotsCancel) !== false) return true;
  foreach ($_XDATA['onlySpider'] as $onlySpider) {
    if (preg_match("/{$onlySpider}/i", $uri)) {
      $foo = false;
      break;
    }
  }
  return $foo;
}

function OS_spiderError($errno, $errstr, $errfile, $errline) {
  global $_LOG, $_LANG, $page, $_VDATA, $mData, $_XDATA, $_DDATA;

  if ($errno < 2048 && error_reporting() != 0) {
    OS_setData("sp.lock", "false");

    $merror = $_DDATA['link']->errorInfo();
    $merror = ((int)$merror[0]) ? "<br>\nMySQL error: {$merror[2]}" : "";

    $errtxt = <<<ERR
<br>
{$_LANG['0q6']} {$page->uri}<br>
{$_LANG['0q7']}<br>
{$_LANG['0q8']}: $errno<br>
{$_LANG['0q9']}: $errstr<br>
{$_LANG['0qa']}: $errline$merror<br>
ERR;

    if ($_SERVER['REQUEST_METHOD'] != "CRON") { ?>
      <style type="text/css">form#canceller input { display:none; }</style><?php
      echo $errtxt; ?>
      <hr>
      <a href="<?php echo htmlspecialchars($_XDATA['linkback']); ?>" id="goback"><?php echo $_LANG['0q1']; ?></a>
      </body></html><?php

      if ($_VDATA['sp.email'] && !$_XDATA['reindex']) {
        $address = explode(" ", preg_replace("/[\"<>]/", "", trim($_VDATA['sp.email'])));
        while (count($address) > 2) {
          $str = array_shift($address);
          $address[0] = $str + $address[0];
        }
        if (count($address) == 1) array_unshift($address, "");

        $mail->AddAddress($address[1], $address[0]);
        $mail->Subject = "{$_LANG['0q5']}: {$_VDATA['sp.pathto']}";
        $mail->Body = implode("\n", $_LOG).strip_tags($errtxt);

        $mail->Send();

        // @mail($_VDATA['sp.email'], "{$_LANG['0q5']}: {$_VDATA['sp.pathto']}", implode("\n", $_LOG).strip_tags($errtxt), $mData['headers']);
      }
    } else echo strip_tags($errtxt);

    if ($_XDATA['needtemp'] && !@unlink($_XDATA['tempfile'])) die(sprintf($_LANG['03o'], $_XDATA['tempfile']));
    exit();
  }
}

function OS_parseHTMLTag($tag, $debug = false) {
  $output = array("closing" => false);
  $loaf = $tag = trim($tag);

  if ($tag{0} == "<" && $tag{strlen($tag) - 1} == ">") {
    $tag = preg_replace(array("/^<\s+\//","/\s*=\s*/"), array("</", "="), $tag);
    if ($tag{1} == "/") $output['closing'] = true;
    str_replace(array("\x05", "\x06"), "", $tag);

    preg_match("/^<\/?([\w\-]+?)(\s+|>)/", $tag, $element);
    if (@$element[1]) {
      $output['element'] = $element[1];
      $loaf = preg_replace("/\s*\/?>$/", "", substr($loaf, strlen($element[0])));

      if (strlen($loaf) >= 1) {
        preg_match_all("/=\s*('[^']*')/", $loaf, $qsin);
        $loaf = preg_replace("/=\s*('[^']*')/", "=\x05", $loaf);
        $qsin = $qsin[1];
        array_unshift($qsin, "");
        reset($qsin);

        preg_match_all("/=\s*(\"[^\"]*\")/", $loaf, $qdub);
        $loaf = preg_replace("/=\s*(\"[^\"]*\")/", "=\x06", $loaf);
        $qdub = $qdub[1];
        array_unshift($qdub, "");
        reset($qdub);

        $loaf = preg_replace(array("/\s*=\s*/", "/\s\s+/"), array("=", " "), $loaf);
        $loaf = explode(" ", $loaf);
        foreach ($loaf as $slice) {
          $slice = explode("=", $slice, 2);
          if (isset($slice[0]) && preg_match("/^[\w\-]+$/", $slice[0])) {
            if (count($slice) == 2) {
              $slice[1] = preg_replace(array("/^\x05$/", "/\x05/"), array('trim(next($qsin), "\'");', 'next($qsin);'), $slice[1]);
              $slice[1] = preg_replace(array("/^\x06$/", "/\x06/"), array('trim(next($qdub), "\"");', 'next($qdub);'), $slice[1]);
              $output[strtolower($slice[0])] = $slice[1];
            } else if (count($slice) == 1) $output[$slice[0]] = true;
          } else if ($debug) trigger_error("OS_parseHTMLTag: Invalid attribute name ".htmlspecialchars($slice[0]));
        }
      }
      return $output;

    } else if ($debug) trigger_error("OS_parseHTMLTag: Invalid element name");
  } else if ($debug) trigger_error("OS_parseHTMLTag: Input does not begin or end with angle brackets");
  return false;
}

function OS_unichr($dec) {
  if ($dec < 128) {
    $utf = chr($dec);
  } else if ($dec < 2048) {
    $utf = chr(192 + (($dec - ($dec % 64)) / 64));
    $utf .= chr(128 + ($dec % 64));
  } else if ($dec < 65536) {
    $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
    $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
    $utf .= chr(128 + ($dec % 64));
  } else if ($dec < 2097152) {
    $utf = chr(240 + (($dec - ($dec % 262144)) / 262144));
    $utf .= chr(128 + ((($dec % 262144) - ($dec % 4096)) / 4096));
    $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
    $utf .= chr(128 + ($dec % 64));
  } else return "";
  return $utf;
}

function OS_entities2utf8($_) {
  static $trans = array();

  if (!count($trans)) {
    $trans = array_flip(get_html_translation_table(HTML_ENTITIES));
    while (list($key, $value) = each($trans)) $trans[$key] = utf8_encode($value);

    $trans = array_merge($trans, array(
      '&apos;'     => "'",   '&OElig;'    => "Œ",  '&oelig;'    => "œ",  '&Scaron;'   => "Š",  '&scaron;'   => "š",
      '&Yuml;'     => "Ÿ",  '&fnof;'     => "ƒ",  '&circ;'     => "ˆ",  '&tilde;'    => "˜",  '&Alpha;'    => "Α",
      '&Beta;'     => "Β",  '&Gamma;'    => "Γ",  '&Delta;'    => "Δ",  '&Epsilon;'  => "Ε",  '&Zeta;'     => "Ζ",
      '&Eta;'      => "Η",  '&Theta;'    => "Θ",  '&Iota;'     => "Ι",  '&Kappa;'    => "Κ",  '&Lambda;'   => "Λ",
      '&Mu;'       => "Μ",  '&Nu;'       => "Ν",  '&Xi;'       => "Ξ",  '&Omicron;'  => "Ο",  '&Pi;'       => "Π",
      '&Rho;'      => "Ρ",  '&Sigma;'    => "Σ",  '&Tau;'      => "Τ",  '&Upsilon;'  => "Υ",  '&Phi;'      => "Φ",
      '&Chi;'      => "Χ",  '&Psi;'      => "Ψ",  '&Omega;'    => "Ω",  '&alpha;'    => "α",  '&beta;'     => "β",
      '&gamma;'    => "γ",  '&delta;'    => "δ",  '&epsilon;'  => "ε",  '&zeta;'     => "ζ",  '&eta;'      => "η",
      '&theta;'    => "θ",  '&iota;'     => "ι",  '&kappa;'    => "κ",  '&lambda;'   => "λ",  '&mu;'       => "μ",
      '&nu;'       => "ν",  '&xi;'       => "ξ",  '&omicron;'  => "ο",  '&pi;'       => "π",  '&rho;'      => "ρ",
      '&sigmaf;'   => "ς",  '&sigma;'    => "σ",  '&tau;'      => "τ",  '&upsilon;'  => "υ",  '&phi;'      => "φ",
      '&chi;'      => "χ",  '&psi;'      => "ψ",  '&omega;'    => "ω",  '&thetasym;' => "ϑ",  '&upsih;'    => "ϒ",
      '&piv;'      => "ϖ",  '&ensp;'     => " ", '&emsp;'     => " ", '&thinsp;'   => " ", '&zwnj;'     => "‌",
      '&zwj;'      => "‍", '&lrm;'      => "‎", '&rlm;'      => "‏", '&ndash;'    => "–", '&mdash;'    => "—",
      '&lsquo;'    => "‘", '&rsquo;'    => "’", '&sbquo;'    => "‚", '&ldquo;'    => "“", '&rdquo;'    => "”",
      '&bdquo;'    => "„", '&dagger;'   => "†", '&Dagger;'   => "‡", '&bull;'     => "•", '&hellip;'   => "…",
      '&permil;'   => "‰", '&prime;'    => "′", '&Prime;'    => "″", '&lsaquo;'   => "‹", '&rsaquo;'   => "›",
      '&oline;'    => "‾", '&frasl;'    => "⁄", '&euro;'     => "€", '&weierp;'   => "℘", '&image;'    => "ℑ",
      '&real;'     => "ℜ", '&trade;'    => "™", '&alefsym;'  => "ℵ", '&larr;'     => "←", '&uarr;'     => "↑",
      '&rarr;'     => "→", '&darr;'     => "↓", '&harr;'     => "↔", '&crarr;'    => "↵", '&lArr;'     => "⇐",
      '&uArr;'     => "⇑", '&rArr;'     => "⇒", '&dArr;'     => "⇓", '&hArr;'     => "⇔", '&forall;'   => "∀",
      '&part;'     => "∂", '&exist;'    => "∃", '&empty;'    => "∅", '&nabla;'    => "∇", '&isin;'     => "∈",
      '&notin;'    => "∉", '&ni;'       => "∋", '&prod;'     => "∏", '&sum;'      => "∑", '&minus;'    => "−",
      '&lowast;'   => "∗", '&radic;'    => "√", '&prop;'     => "∝", '&infin;'    => "∞", '&ang;'      => "∠",
      '&and;'      => "∧", '&or;'       => "∨", '&cap;'      => "∩", '&cup;'      => "∪", '&int;'      => "∫",
      '&there4;'   => "∴", '&sim;'      => "∼", '&cong;'     => "≅", '&asymp;'    => "≈", '&ne;'       => "≠",
      '&equiv;'    => "≡", '&le;'       => "≤", '&ge;'       => "≥", '&sub;'      => "⊂", '&sup;'      => "⊃",
      '&nsub;'     => "⊄", '&sube;'     => "⊆", '&supe;'     => "⊇", '&oplus;'    => "⊕", '&otimes;'   => "⊗",
      '&perp;'     => "⊥", '&sdot;'     => "⋅", '&lceil;'    => "⌈", '&rceil;'    => "⌉", '&lfloor;'   => "⌊",
      '&rfloor;'   => "⌋", '&lang;'     => "〈", '&rang;'     => "〉", '&loz;'      => "◊", '&spades;'   => "♠",
      '&clubs;'    => "♣", '&hearts;'   => "♥", '&diams;'    => "♦"
    ));

    uksort($trans, create_function('$k1, $k2', 'return ($k1 == "&amp;") ? 1 : -1;'));
  }

  $_ = preg_replace(array("/&#(\d{2,7});/", "/&#x([\da-f]{2,6});/i"), array("OS_unichr('$1');", "OS_unichr(hexdec('$1'));"), $_);
  return strtr($_, $trans);
}

function OS_entities2ascii($_) {
  static $trans = array();

  if (!count($trans)) {
    $trans = array(
      '&quot;' => "\"",
      '&lt;' => "<",
      '&gt;' => ">",
      '&amp;' => "&"
    );
  }

  $_ = preg_replace(array("/(&#(\d{2,3});)/", "/(&#([\da-f]{2});)/i"), array("(((int)$2 < 256) ? chr('$2') : '$1')", "chr(hexdec('$2'))"), $_);
  return strtr($_, $trans);
}

function OS_mapSelectors($_, $items, $remove = false) {
  global $_XDATA;

  $captured = "";

  foreach ($items as $item) {
    $match = "";
    if (strpos($item, "#") === 0) {  // **************** #id
      $item = substr($item, 1);
      preg_match("/<([\w]+) [^>]*?id=(\"|')$item\\2[^>]*?>/i", $_, $match);
      if (isset($match[0])) {
        $match2 = ""; $subtags = 1;
        do {
          $tags = $subtags;
          preg_match("/".preg_quote($match[0], "/")."(".str_repeat(".*?<\/{$match[1]}>", $tags).")/is", $_, $match2);
          if (isset($match2[1])) $subtags = count(preg_split("/<{$match[1]}[> ]/i", $match2[1]));
        } while ($subtags > $tags);
        if (isset($match2[0])) {
          if ($remove) {
             $_ = str_replace($match2[0], "", $_);
          } else $captured .= trim(preg_replace(array("/\s/", "/\s{2,}/"), " ", strip_tags($match2[0])))." ";
        }
      }
    } else if (strpos($item, "#") !== false) {  // ***** element#id
      $item = explode("#", $item, 2);
      preg_match("/<{$item[0]} [^>]*?id=(\"|'){$item[1]}\\1[^>]*?>/i", $_, $match);
      if (isset($match[0])) {
        $match2 = ""; $subtags = 1;
        do {
          $tags = $subtags;
          preg_match("/".preg_quote($match[0], "/")."(".str_repeat(".*?<\/{$item[0]}>", $tags).")/is", $_, $match2);
          if (isset($match2[1])) $subtags = count(preg_split("/<{$item[0]}[> ]/i", $match2[1]));
        } while ($subtags > $tags);
        if (isset($match2[0])) {
          if ($remove) {
             $_ = str_replace($match2[0], "", $_);
          } else $captured .= trim(preg_replace(array("/\s/", "/\s{2,}/"), " ", strip_tags($match2[0])))." ";
        }
      }
    } else if (strpos($item, ".") === 0) {  // ********* .class
      $item = substr($item, 1);
      preg_match_all("/<([\w]+) [^>]*?class=(\"|')([^>]*?)\\2[^>]*?>/i", $_, $match);
      if (isset($match[0])) {
        foreach ($match[0] as $key => $value) {
          $match2 = ""; $subtags = 1;
          if (preg_match("/(^| )$item( |$)/i", $match[3][$key])) {
            do {
              $tags = $subtags;
              preg_match("/".preg_quote($value, "/")."(".str_repeat(".*?<\/{$match[1][$key]}>", $tags).")/is", $_, $match2);
              if (isset($match2[1])) $subtags = count(preg_split("/<{$match[1][$key]}[> ]/i", $match2[1]));
            } while ($subtags > $tags);
            if (isset($match2[0])) {
              if ($remove) {
                 $_ = str_replace($match2[0], "", $_);
              } else $captured .= trim(preg_replace(array("/\s/", "/\s{2,}/"), " ", strip_tags($match2[0])))." ";
            }
          }
        }
      }
    } else if (strpos($item, ".") !== false) {  // ***** element.class
      $item = explode(".", $item, 2);
      preg_match_all("/<{$item[0]} [^>]*?class=(\"|')([^>]*?)\\1[^>]*?>/i", $_, $match);
      if (isset($match[0])) {
        foreach ($match[0] as $key => $value) {
          $match2 = ""; $subtags = 1;
          if (preg_match("/(^| ){$item[1]}( |$)/i", $match[2][$key])) {
            do {
              $tags = $subtags;
              preg_match("/".preg_quote($value, "/")."(".str_repeat(".*?<\/{$item[0]}>", $tags).")/is", $_, $match2);
              if (isset($match2[1])) $subtags = count(preg_split("/<{$item[0]}[> ]/i", $match2[1]));
            } while ($subtags > $tags);
            if (isset($match2[0])) {
              if ($remove) {
                 $_ = str_replace($match2[0], "", $_);
              } else $captured .= trim(preg_replace(array("/\s/", "/\s{2,}/"), " ", strip_tags($match2[0])))." ";
            }
          }
        }
      }
    } else {  // *************************************** element
      preg_match_all("/<$item( [^>]*?>|>)/i", $_, $match);
      if (isset($match[0])) {
        foreach ($match[0] as $key => $value) {
          $match2 = ""; $subtags = 1;
          do {
            $tags = $subtags;
            preg_match("/".preg_quote($value, "/")."(".str_repeat(".*?<\/$item>", $tags).")/is", $_, $match2);
            if (isset($match2[1])) $subtags = count(preg_split("/<{$item}[> ]/i", $match2[1]));
          } while ($subtags > $tags);
          if (isset($match2[0])) {
            if ($remove) {
               $_ = str_replace($match2[0], "", $_);
            } else $captured .= trim(preg_replace(array("/\s/", "/\s{2,}/"), " ", strip_tags($match2[0])))." ";
          }
        }
      }
    }
  }

  return ($remove) ? $_ : $captured;
}


/* ******************************************************************
******** Classes ************************************************* */
class OS_Resource {
  var $uri         = "";
  var $referer     = "";
  var $depth       = 0;
  var $gzip        = false;
  var $curl        = false;
  var $md5         = "";
  var $accepted    = false;
  var $indexed     = false;
  var $ctype       = "";
  var $title       = "";
  var $body        = "";
  var $links       = array();
  var $keywords    = "";
  var $description = "";
  var $wtags       = "";
  var $changefreq  = "always";
  var $charset     = "-";
  var $mimetype    = "";
  var $metatags    = array();
  var $isnew       = "false";
  var $lastmod     = 0;
  var $status      = "";
  var $nofollow    = false;
  var $noindex     = false;
  var $reftime     = 0;
  var $refresh     = "";
  var $parsed      = array();


  function __construct($uri, $value) {
    $this->uri = $uri;
    $this->depth = $value[0];
    $this->referer = $value[1];
    $this->setBase($this->uri);
    $this->lastmod = time();
  }

  function setStatus($status) {
    global $_DDATA, $_XDATA, $page;

    $bodyblow = (in_array($status, array("Blocked", "Not Found"))) ? ", `body`=''" : "";
    $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `status`='$status'$bodyblow WHERE `uri`='".addslashes($this->uri)."';");
    if ($update->rowCount()) {
      $page->status = $status;
      $_XDATA['stats'][$status]++;
    }
  }

  function getMetatags($html) {
    global $_SDATA;

    preg_match("/<head.*?\/head>/is", $this->body, $headtag);
    if (isset($headtag[0])) {
      preg_match_all("/<meta\s[^>]+>/i", $headtag[0], $this->metatags);
      if (isset($this->metatags[0])) {
        $this->metatags = $this->metatags[0];

        while (list($key, $value) = each($this->metatags)) {
          $value = $this->metatags[$key] = OS_parseHTMLTag($value);

          if (isset($value['content'])) {
            if (isset($value['http-equiv'])) {
              switch (strtolower($value['http-equiv'])) {
                case "refresh":
                  preg_match("/^([\d\s]+);/", $value['content'], $reftime);
                  if (isset($reftime[1])) {
                    $this->reftime = (int)trim($reftime[1]);
                    preg_match("/".$_SDATA['protocol'].":\/\/.+;?/i", $value['content'], $refresh);
                    if (isset($refresh[0])) $this->refresh = $refresh[0];
                  }
                  break;
                case "content-type":
                  preg_match("/charset=([\w\-]+)/i", $value['content'], $charset);
                  if (isset($charset[1])) $this->charset = strtoupper($charset[1]);
                  break;
              }
            } else if (isset($value['name'])) {
              $value['name'] = strtolower($value['name']);
              $value['content'] = strtolower($value['content']);
              if ($value['name'] == "robots" || $value['name'] == "orcaspider") {
                if (strpos($value['content'], "nofollow") !== false) {
                  $this->nofollow = true;
                } else if (strpos($value['content'], "follow") !== false) $this->nofollow = false;
                if (strpos($value['content'], "noindex") !== false) {
                  $this->noindex = true;
                } else if (strpos($value['content'], "index") !== false) $this->noindex = false;
              }
            }
          }
        }
      }
    }
  }

  function setBase($_) {
    global $_DDATA;

    $_ = @parse_url($_);
    if (isset($_['scheme'])) {
      $this->parsed = $_;
      if (!isset($this->parsed['path'])) {
        $this->parsed['path'] = "/";
        if ($this->uri{strlen($this->uri) - 1} != "/") {
          $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `uri`=CONCAT(`uri`,'/') WHERE `uri`='".addslashes($this->uri)."';");
          $this->uri .= "/";
        }
      }
      $this->parsed['dir'] = ($this->parsed['path']{strlen($this->parsed['path']) - 1} == "/") ? $this->parsed['path'] : dirname($this->parsed['path']);
        if ($this->parsed['dir']{strlen($this->parsed['dir']) - 1} != "/") $this->parsed['dir'] .= "/";
      $this->parsed['base'] = basename($this->parsed['path']);
      $this->parsed['full'] = $this->parsed['path'].((isset($this->parsed['query'])) ? "?{$this->parsed['query']}" : "");
      $this->parsed['hostport'] = $this->parsed['host'].((isset($this->parsed['port'])) ? ":".$this->parsed['port'] : "");
      if (!isset($this->parsed['port'])) $this->parsed['port'] = "80";
    }
  }

  function mysqlPrep() {
    $this->title = addslashes(stripslashes(trim($this->title)));
    $this->body = addslashes(stripslashes(trim($this->body)));
    $this->keywords = addslashes(stripslashes(trim($this->keywords)));
    $this->description = addslashes(stripslashes(trim($this->description)));
    $this->wtags = addslashes(stripslashes(trim($this->wtags)));
    $this->links = (count($this->links)) ? trim(addslashes(stripslashes(implode("\n", array_filter($this->links))))) : "";
  }
}


/* ******************************************************************
******** Setup *************************************************** */
header("Orcascript: Search_Spider");
$_XDATA['reindex'] = false;
$_XDATA['linkback'] = "";
$_XDATA['errors'] = array();

if (isset($_SERVER['REQUEST_METHOD'])) {
  switch ($_SERVER['REQUEST_METHOD']) {
    case "POST":
      if (isset($_POST['fullscan']) && $_POST['fullscan'] == "true") OS_setData("sp.fullscan", "true");
      if (isset($_POST['linkback'])) $_XDATA['linkback'] = $_POST['linkback'];
      if (!isset($_POST['key']) || $_POST['key'] != $_VDATA['c.spkey']) {
        $_XDATA['errors'][] = $_LANG['0p0'];
      } else OS_setData("c.spkey", "");
      break;
    case "GET":
      if ($_VDATA['sp.cron'] == "true") $_XDATA['errors'][] = $_LANG['0qe'];
      if (isset($_GET['linkback'])) $_XDATA['linkback'] = $_GET['linkback'];
      if (!isset($_GET['key']) || $_GET['key'] != $_VDATA['s.spkey']) {
        $_XDATA['errors'][] = $_LANG['0p2'];
      } else {
        OS_setData("s.spkey", md5(time()));
        $_XDATA['reindex'] = (isset($_GET['reindex'])) ? true : false;
      }
      break;
    case "HEAD":
    default:
      exit();
  }

} else if ($_VDATA['sp.cron'] == "false") {
  echo "Script: {$_SDATA['userAgent']} - Spider\n";
  echo $_LANG['0p3'];
  exit();

} else if ($_VDATA['sp.cron'] == "true") {
  $_SERVER['REQUEST_METHOD'] = "CRON";
  chdir(dirname($_SERVER['argv'][0]));
  ob_start();
}


/* ***** Seamless Spider ***************************************** */
if ($_VDATA['sp.seamless'] == "true") {
  $drop = $_DDATA['link']->query("DROP TABLE `{$_DDATA['tabletemp']}`;");
  $create = $_DDATA['link']->query("CREATE TABLE `{$_DDATA['tabletemp']}` SELECT * FROM `{$_DDATA['tablename']}`");
} else $_DDATA['tabletemp'] = $_DDATA['tablename'];


/* ***** Spider Data ********************************************* */
$_XDATA['allowedDomains']   = array_filter(array_map("trim", explode("\n", $_VDATA['sp.domains'])));
$_XDATA['allDomains']       = ($_XDATA['reindex']) ? array() : $_XDATA['allowedDomains'];
$_XDATA['removeTags']       = preg_grep("/^[\w#._:-]+$/i", array_filter(array_map("trim", explode(" ", $_VDATA['sp.remtags']))));
$_XDATA['weightedTags']     = preg_grep("/^[\w#._:-]+$/i", array_filter(array_map("trim", explode(" ", $_VDATA['s.weightedtags']))));
$_XDATA['onlySpider']       = array_filter(array_map("OS_pquote", explode("\n", $_VDATA['sp.require'])));
$_XDATA['noSpider']         = array_filter(array_map("OS_pquote", explode("\n", $_VDATA['sp.ignore'])));
$_XDATA['titleStrip']       = array_filter(array_map("OS_pquote", explode("\n", $_VDATA['sp.remtitle'])));
$_XDATA['autoCat']          = array_filter(array_map("trim", explode("\n", $_VDATA['sp.autocat'])));
$_XDATA['ignoreExtensions'] = array_map("trim", explode(" ", $_VDATA['sp.extensions']));
foreach($_XDATA['ignoreExtensions'] as $ignoreExtensions)
  $_XDATA['noSpider'][]     = "\.".preg_quote($ignoreExtensions)."(\?|$)";

$_XDATA['checkRobots']      = true;
$_XDATA['robotsCancel']     = array();
$_XDATA['domainLimit']      = array();
$_XDATA['starters']         = array();
$_XDATA['reindexmd5s']      = array();
$_XDATA['queue']            = array();
$_XDATA['scanned']          = array();
$_XDATA['cookies']          = array();
$_XDATA['stats']            = array("New" => 0, "Updated" => 0, "Not Found" => 0, "Orphan" => 0, "Blocked" => 0);
$_XDATA['cleanup']          = false;
$_XDATA['dataleng']         = 0;
$_XDATA['linkdepth']        = 0;
$_XDATA['acceptHeader']     = implode(", ", $_MIME->get_mtypes());

if ($_XDATA['reindex']) {
  $_XDATA['reindexmd5s'] = explode(" ", $_VDATA['sp.reindex']);
  foreach ($_XDATA['reindexmd5s'] as $md5) {
    $select = $_DDATA['link']->query("SELECT `uri` FROM `{$_DDATA['tabletemp']}` WHERE `md5`='$md5';")->fetchAll();
    if (count($select)) {
      $_XDATA['starters'][] = $getInit = $select[0]['uri'];
      $getInit = @parse_url($getInit);
      if (isset($getInit['host'])) {
        if (isset($getInit['port'])) $getInit['host'] .= ":".$getInit['port'];
        if (!in_array($getInit['host'], $_XDATA['allDomains'])) $_XDATA['allDomains'][] = $getInit['host'];
      }
    }
  }
} else {
  $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
  $select = $_DDATA['link']->query("SELECT `uri` FROM `{$_DDATA['tabletemp']}`;")->fetchAll();
  foreach ($select as $row) {
    $getInit = @parse_url($row['uri']);
    if (isset($getInit['host'])) {
      if (isset($getInit['port'])) $getInit['host'] .= ":".$getInit['port'];
      if (!in_array($getInit['host'], $_XDATA['allDomains'])) $_XDATA['allDomains'][] = $getInit['host'];
    }
  }
  $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
  $_XDATA['starters'] = array_map("trim", array_values(explode("\n", $_VDATA['sp.start'])));
  foreach ($_XDATA['starters'] as $starter) {
    $getInit = @parse_url($starter);
    if (isset($getInit['host'])) {
      if (!isset($_XDATA['domainLimit'][$getInit['host']])) $_XDATA['domainLimit'][$getInit['host']] = array();
      $getInit['dir'] = (isset($getInit['path'])) ? substr($getInit['path'], 0, strrpos($getInit['path'], DIRECTORY_SEPARATOR) + 1) : DIRECTORY_SEPARATOR;
      $_XDATA['domainLimit'][$getInit['host']] = array_values(array_diff($_XDATA['domainLimit'][$getInit['host']], preg_grep("/^".preg_quote($getInit['dir'], "/")."/i", $_XDATA['domainLimit'][$getInit['host']])));
      $_XDATA['domainLimit'][$getInit['host']][] = $getInit['dir'];
      if (isset($getInit['port'])) $getInit['host'] .= ":".$getInit['port'];
      if (!in_array($getInit['host'], $_XDATA['allDomains'])) $_XDATA['allDomains'][] = $getInit['host'];
    }
  }
}

$_XDATA['linkRegexp'] = array(
  "/<a[^>]*?\shr(e)f=([^\"'][^\s>]+)/i",
  "/<a[^>]*?\shref=([\"'])(.*?)\\1/i",
  "/<link[^>]*?\shr(e)f=([^\"'][^\s>]+)/i",
  "/<link[^>]*?\shref=([\"'])(.*?)\\1/i",
  "/<area[^>]*?\shr(e)f=([^\"'][^\s>]+)/i",
  "/<area[^>]*?\shref=([\"'])(.*?)\\1/i",
  "/<i?frame[^>]*?\ss(r)c=([^\"'][^\s>]+)/i",
  "/<i?frame[^>]*?\ssrc=([\"'])(.*?)\\1/i"
);
if ($_XDATA['needtemp'] = $_MIME->needtemp()) {
  if ($undir = @opendir(".".DIRECTORY_SEPARATOR."temp")) {
    if (is_writable(".".DIRECTORY_SEPARATOR."temp".DIRECTORY_SEPARATOR) && file_exists(".".DIRECTORY_SEPARATOR."temp".DIRECTORY_SEPARATOR.".")) {
      while (($file = readdir($undir)) !== false) if (strpos($file, "orcatempfile") === 0) @unlink(".".DIRECTORY_SEPARATOR."temp".DIRECTORY_SEPARATOR."$file");
      $_XDATA['tempfile'] = @tempnam(".".DIRECTORY_SEPARATOR."temp", "orcatempfile");
    } else $_XDATA['errors'][] = $_LANG['0qf'];
  } else $_XDATA['errors'][] = $_LANG['0pa'];
}

$_XDATA['whitespace'] = array(
  "&nbsp;",   "&#160;",  "&#xa0;",
  "&ensp;",   "&#8194;", "&#x2002;",
  "&emsp;",   "&#8195;", "&#x2003;",
  "&thinsp;", "&#8201;", "&#x2009;",
  "&zwnj;",   "&#8204;", "&#x200c;"
);
$_XDATA['nonspace'] = array(
  "&shy;",    "&#173;",  "&#xad;",
  "&zwj;",    "&#8205;", "&#x200d;"
);
if ($_VDATA['sp.utf8'] == "true") {
  $_XDATA['whitespace'] = array_merge($_XDATA['whitespace'], array(" ", " ", " ", " ", "‌"));
  $_XDATA['nonspace'] = array_merge($_XDATA['nonspace'], array("­", "‍"));
}


/* ***** Mail Data *********************************************** */
if (!class_exists('phpmailer')) {
  require "phpmailer.php";
}
$mail = new PHPMailer();
$mail->From = $_SERVER['SERVER_ADMIN'];
$mail->FromName = "Orca Search Spider";
$mail->CharSet = $_VDATA['c.charset'];



/* ***** Execution Timer ***************************************** */
$_TIMER = array(
  "__log"           => $_SDATA['now'],  // Time script began
  "Initial"  => 0,  // Script setup and initiation
  "Robots"   => 0,  // Robots.txt loading and parsing
  "MySQL"    => 0,  // MySQL transactions
  "HTTP"     => 0,  // HTTP transactions
  "Links"    => 0,  // Link grabbing from HTML
  "Elements" => 0,  // Weighted/remove element functions
  "Content"  => 0,  // Parse content for archiving
  "Sleep"    => 0   // Sleeping
);

function addTime($wish) {
  global $_TIMER;

  $snapshot = array_sum(explode(" ", microtime()));
  $_TIMER[$wish] += $snapshot - $_TIMER['__log'];
  $_TIMER['__log'] = $snapshot;
}


/* ***** Log Construction **************************************** */
$_LOG = array();
$_LOG[] = "Script: {$_SDATA['userAgent']} - Spider";
$_LOG[] = sprintf($_LANG['0p4'], date("r"), $_SERVER['REQUEST_METHOD']);
if (@$_SERVER['HTTP_REFERER']) $_LOG[] = sprintf($_LANG['0p5'], $_SERVER['HTTP_REFERER']);


/* ***** Begin Output ******************************************** */
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <title><?php echo $_LANG['0p6']; ?></title>
  <meta http-equiv="Content-type" content="text/html; charset=<?php echo $_VDATA['c.charset']; ?>;">
  <style type="text/css">
body { background-color:#ffffff; font:normal 100% sans-serif; }
body .warning { color:#ff0000; background-color:transparent; }
body .green { color:#009900; }
body form#canceller { margin:0px; }
body form#canceller input { margin-left:30px; vertical-align:top; }
body h1 { margin:3px 0px; font:bold 130% sans-serif; }
body h2 { margin:2px 0px; font:normal 100% sans-serif; }
body h3 { margin:1px 240px 1px 5px; font:normal 85% monospace; }
body h3.notice { text-indent:-1.2em; padding-left:1em; }
body p { position:absolute; top:0px; left:40%; color:#000000; background-color:#ffffff; font:normal 250% sans-serif; }
body label textarea { margin-bottom:5px; }
body a#goback { display:block; text-align:center; font:bold 125% sans-serif; border:4px groove #dddddd; background-color:#eeeeee; }
body table { position:absolute; top:5px; right:5px; background-color:#ffffff; font-size:80%; border-collapse:collapse; }
body table thead tr th { padding: 2px 10px; }
body table thead tr th, body table tbody tr th, body table tbody tr td { border:1px solid #666666; }
body table tbody tr th, body table tbody tr td { padding:2px; text-align:right; }
body div#lower { position:relative; }
body div#lower h2 { border-bottom:2px groove #dddddd; }
body div#lower pre { font:normal 95% sans-serif; margin:8px 0px 1em 2em; }
body div#lower ul { margin-top:8px; }
  </style>
</head>
<body><?php
  OS_setData("sp.cancel", "false");
  if ($_XDATA['linkback']) { ?>
    <form action="<?php echo htmlspecialchars($_XDATA['linkback']); ?>" method="post" id="canceller">
      <h1><?php echo $_LANG['0p7']; ?> <input type="submit" name="spider_Cancel" value="<?php echo $_LANG['01s']; ?>"></h1>
    </form><?php

  } else { ?>
    <h1><?php echo $_LANG['0p7']; ?></h1><?php
  }

  if (count($_XDATA['errors'])) { ?>
    <ul class="warning"><?php
      foreach ($_XDATA['errors'] as $error) { ?>
        <li><?php echo $error; ?></li><?php
      } ?>
    </ul><?php

  } else { ?>
    <h2 class="green"><?php echo $_LANG['0p9']; ?></h2><?php
    flush();


    addTime("Initial");


    /* ***** Check allDomains robots.txt *************************** */
    if ($_XDATA['checkRobots']) {
      $domCount = 0;
      foreach ($_XDATA['allDomains'] as $allDomains) {
        $robot = new OS_Fetcher($_SDATA['protocol']."://$allDomains/robots.txt");
        $robot->accept[] = "text/plain";
        $robot->cookies = $_XDATA['cookies'];
        $robot->fetch();
        $_XDATA['dataleng'] += $robot->dataleng;
        $_XDATA['cookies'] = $robot->cookies;

        echo "<p class=\"green\">", ++$domCount, " / ", count($_XDATA['allDomains']), " &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </p>\n";
        flush();

        if (!$robot->status) {
          $robot->body = explode("\n", $robot->body);
          $tracker = false;
          foreach ($robot->body as $line) {
            if (preg_match("/^[^#]*?User-agent:(.*?)($|#)/i", $line, $match)) {
              $tracker = (($match[1] = trim($match[1])) && ($match[1] == "*" || strtolower($match[1]) == "orcaspider"));
              continue;
            }

            if ($tracker) {
              if (preg_match("/^[^#]*?Disallow:(.*?)($|#)/i", $line, $match)) {
                if ($match[1] = trim($match[1])) {
                  if ($match[1]{0} == "/") $_XDATA['robotsCancel'][] = $allDomains.$match[1];
                } else $_XDATA['robotsCancel'] = array_values(array_filter($_XDATA['robotsCancel'], create_function('$a', 'return (strpos($a, "'.$allDomains.'") === false);')));
              }
            }
          }
        }
        unset($robot);
        $_XDATA['robotsCancel'] = array_unique($_XDATA['robotsCancel']);
      }


      addTime("Robots");


    }

    if (isset($_POST['spider_Force'])) OS_setData("sp.lock", "false");

    if ($_VDATA['sp.lock'] == "true") { ?>
      <h2 class="warning"><?php echo $_LOG[] = $_LANG['0pb']; ?></h2>
      <h2><?php echo $_LANG['0pd']; ?></h2>
      <h2><?php echo $_LOG[] = $_LANG['0pc']; ?></h2><?php

    } else {
      ignore_user_abort(true);
      @set_time_limit(0);
      set_error_handler("OS_spiderError");
      OS_setData("sp.lock", "true"); ?>

      <h2><?php echo $_LANG['0pe']; ?></h2>
      <h2><?php echo $_LANG['0pf']; ?></h2><?php

      flush();

      /* **************************************************************
      ******** Begin Spider **************************************** */
      foreach ($_XDATA['starters'] as $starter) OS_add2Queue($starter);
      $result = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tabletemp']}`;")->fetchAll(PDO::FETCH_NUM);
      list($indexed) = array_shift($result);
      if (count($_XDATA['queue']) || $indexed) {
        if ($_XDATA['reindex']) {
          foreach ($_XDATA['reindexmd5s'] as $md5)
            $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `new`='false' WHERE `md5`='$md5';");
        } else $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `new`='false';");


        addTime("Initial");


        while (1) {
          while (count($_XDATA['queue'])) {

            $select = $_DDATA['link']->query("SELECT `sp.cancel` FROM `{$_DDATA['tablevars']}` LIMIT 1;")->fetchAll();
            if ($select[0]['sp.cancel'] == "true") {
              $_LOG[] = $_LANG['0pg'];
              break 2;
            }

            if (count($_XDATA['scanned']) >= $_VDATA['sp.pagelimit']) {
              echo "<h3 class=\"notice warning\">&bull; ", $_LOG[] = "{$_LANG['0p1']} ({$_VDATA['sp.pagelimit']})", "</h3>\n";
              break 2;
            }

            reset($_XDATA['queue']);
            list($uri, $value) = each($_XDATA['queue']);
            $page = new OS_Resource($uri, $value);
            array_shift($_XDATA['queue']);
            $_XDATA['scanned'][$page->uri] = $page->referer;

            $select = $_DDATA['link']->query("SELECT `md5`, `status`, `links`, `locked`, `sm.lastmod`, `sm.changefreq` FROM `{$_DDATA['tabletemp']}` WHERE `uri`='".addslashes($page->uri)."' LIMIT 1;")->fetchAll();
            if (count($select)) {
              $_EXISTING = $select[0];
            } else $_EXISTING = array();

            $fpage = new OS_Fetcher($page->uri);
            $fpage->referer = $page->referer;
            $fpage->cookies = $_XDATA['cookies'];
            $fpage->accept = $_MIME->get_mtypes();
            $fpage->lastmod = ($_VDATA['sp.fullscan'] == "false" && !$_XDATA['reindex'] && isset($_EXISTING['sm.lastmod'])) ? $_EXISTING['sm.lastmod'] : 0;
            if ($_XDATA['needtemp']) $fpage->into = $_XDATA['tempfile'];
            $fpage->fetch();

            $page->curl = $fpage->curl;

            $_XDATA['dataleng'] += $fpage->dataleng;

            switch ($fpage->status) {
              case 6: // No Socket
                $page->setStatus("Blocked");
                echo "<h3 class=\"notice warning\">&bull; ", $_LOG[] = $fpage->errstr, "</h3>\n";
                break;

              case 5: // Invalid URI
                $page->setStatus("Blocked");
                break;

              case 4: // Not Found
                $page->setStatus("Not Found");
                echo "<h3 class=\"notice warning\">&bull; ", $_LOG[] = sprintf($_LANG['0ph'], $fpage->httpcode, $page->uri, $page->referer), "</h3>\n";
                break;

              case 3: // Blocked
                $page->setStatus("Blocked");
                if ($fpage->redirect) {
                  echo "<h3 class=\"notice\">&bull; ", $_LOG[] = sprintf($_LANG['0pi'], $page->uri, $fpage->redirect, $page->referer), "</h3>\n";

                  $result = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tabletemp']}` WHERE `uri`='".addslashes($fpage->redirect)."';")->fetchAll(PDO::FETCH_NUM);
                  list($newbump) = array_shift($result);
                  if (!$newbump) {
                    if ($newuri = OS_add2Queue($fpage->redirect, $page->uri, $page->depth)) {
                      $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `uri`='".addslashes($newuri)."' WHERE `uri`='".addslashes($page->uri)."';");
                      if ($update->rowCount())
                        echo "<h3 class=\"notice\">--&gt; ", $_LOG[] = sprintf($_LANG['0pj'], $page->uri), "</h3>\n";
                    }
                  } else {
                    $delete = $_DDATA['link']->query("DELETE FROM `{$_DDATA['tabletemp']}` WHERE `uri`='".addslashes($page->uri)."';");
                    if ($delete->rowCount())
                      echo "<h3 class=\"notice\">--&gt; ", $_LOG[] = sprintf($_LANG['0pk'], $page->uri), "</h3>\n";
                  }
                }
                break;

              case 2: // Timed out
                $page->setStatus("Blocked");
                echo "<h3 class=\"notice warning\">&bull; ", $_LOG[] = sprintf($_LANG['0qb'], $page->uri), "</h3>\n";
                break;

              case 1: // Unmodified
                $_XDATA['cookies'] = $fpage->cookies;
                $page->accepted = true;
                break;

              case 0: // OK
                $_XDATA['cookies'] = $fpage->cookies;
                if ($page->mimetype = $fpage->mimetype) $page->accepted = true;
                $page->charset = $fpage->charset;
                $page->gzip = $fpage->gzip;
                // $page->parsed = $fpage->parsed;
                if ($page->body = $fpage->body) $page->indexed = true;
                if ($page->intostat = $fpage->intostat) $page->indexed = true;
            }


            addTime("HTTP");


            OS_setData("sp.progress", time());
            // echo "<!-- {$page->uri} --> ";
            echo "<p>", count($_XDATA['scanned']), " / ", count($_XDATA['scanned']) + count($_XDATA['queue']), " &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </p>\n";
            flush();

            if ($fpage->status > 1) continue;
            $page->md5 = ($fpage->status == 1) ? $_EXISTING['md5'] : (($page->body) ? md5($page->body) : md5($page->uri));

            $dbl = $_DDATA['link']->query("SELECT `uri` FROM `{$_DDATA['tabletemp']}` WHERE `uri`!='".addslashes($page->uri)."' AND `md5`='{$page->md5}';")->fetchAll();
            for ($x = 0, $dblskip = false; $x < count($dbl); $x++) {
              $dbluri = $dbl[$x]['uri'];
              if (isset($_XDATA['scanned'][$dbluri])) {
                $dblpsd = parse_url($dbluri);
                $pka = array_search($page->parsed['hostport'], $_XDATA['allowedDomains']);
                $pkb = array_search($dblpsd['host'], $_XDATA['allowedDomains']);
                if ($pka !== false && $pkb !== false) {
                  if ($pka > $pkb || ($pka == $pkb && strlen($page->uri) > strlen($dbluri))) {
                    list($pka, $pkb) = array($page->uri, $dbluri);
                  } else list($pka, $pkb) = array($dbluri, $page->uri);
                  $delete = $_DDATA['link']->query("DELETE FROM `{$_DDATA['tabletemp']}` WHERE `uri`='".addslashes($pka)."';");
                  echo "<h3 class=\"notice\">&bull; ", $_LOG[] = sprintf(($delete->rowCount()) ? $_LANG['0pl'] : $_LANG['0pm'], $pka, $pkb, $_XDATA['scanned'][$pka]." + ".$_XDATA['scanned'][$pkb]), "</h3>\n";
                  if ($pka == $page->uri) $dblskip = true;
                }
              }
            }
            if ($dblskip) continue;


            addTime("MySQL");


            if ($page->accepted) {
              // if ($page->gzip && !$page->curl) $page->body = gzinflate(substr($page->body, 10));
              if ($page->gzip) $page->body = gzinflate(substr($page->body, 10));

              if (count($_EXISTING)) {
                $page->changefreq = $_EXISTING['sm.changefreq'];
                $page->isnew = ($fpage->status == 1 || ($page->md5 == $_EXISTING['md5'] && ($_VDATA['sp.fullscan'] == "true" || $_XDATA['reindex']))) ? "false" : "true";
                $page->lastmod = ($page->isnew == "false") ? (int)$_EXISTING['sm.lastmod'] : time();
                if ($_VDATA['sm.changefreq'] == "true") {
                  $adjmod = time() - (int)$_EXISTING['sm.lastmod'];
                  if ($adjmod <= 2700) $page->changefreq = 'always';
                  if ($adjmod > 2700 && $adjmod <= 64800) $page->changefreq = 'hourly';
                  if ($adjmod > 64800 && $adjmod <= 432000) $page->changefreq = 'daily';
                  if ($adjmod > 432000 && $adjmod <= 2160000) $page->changefreq = 'weekly';
                  if ($adjmod > 2160000 && $adjmod <= 21600000) $page->changefreq = 'monthly';
                  if ($adjmod > 21600000) $page->changefreq = 'yearly';
                }
              }


              addTime("MySQL");


              if ($_VDATA['sp.fullscan'] == "false" &&
                  !$_XDATA['reindex'] &&
                  count($_EXISTING) && (
                    $fpage->status == 1 || (
                      $_EXISTING['md5'] == $page->md5 && (
                        $_EXISTING['status'] == "OK" || (
                          $_EXISTING['status'] == "Orphan" &&
                          $_XDATA['cleanup']))))) {
                $page->links = array_filter(explode("\n", $_EXISTING['links']));
                $lalt = false;
                while (list($key, $value) = each($page->links)) {
                  $valid = OS_add2Queue($value, $page->uri, $page->depth);
                  if (!$valid) {
                    $lalt = true;
                    unset ($page->links[$key]);
                  }
                }

                $minor = "";
                if ($_VDATA['sm.changefreq'] == "true") $minor .= ",`sm.changefreq`='{$page->changefreq}' ";
                if ($_EXISTING['status'] == "OK" && $_XDATA['cleanup']) $minor .= ",`status`='Orphan' ";
                if ($lalt) $minor .= ",`links`='".trim(implode("\n", array_filter($page->links)))."'";

                if ($minor) {
                  $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET ".substr($minor, 1)." WHERE `md5`='{$page->md5}' LIMIT 1;");
                  if ($update->rowCount() && $_EXISTING['status'] == "OK" && $_XDATA['cleanup']) $_XDATA['stats']['Orphan']++;
                }

              } else {
                if ($page->indexed) {
                  $page->ctype = $_MIME->get_ctype($page->mimetype);
                  if ($_MIME->ctype[$page->ctype]->index) {
                    switch ($page->ctype) {
                      case "html":
                        $page->body = preg_replace(array("/<!--.*?-->/s", "/<script.*?\/script>/is"), "", $page->body);
                        $page->body = str_replace($_XDATA['whitespace'], " ", $page->body);
                        $page->body = str_replace($_XDATA['nonspace'], "", $page->body);
                        $page->getMetatags($page->body);

                        if ($page->refresh && $page->reftime < 10) {
                          $page->setStatus("Blocked");
                          echo "<h3 class=\"notice\">&bull; ", $_LOG[] = sprintf($_LANG['0qc'], $page->uri, $page->refresh), "</h3>\n";

                          if (OS_add2Queue($page->refresh, $page->uri, $page->depth)) {
                            $delete = $_DDATA['link']->query("DELETE FROM `{$_DDATA['tabletemp']}` WHERE `uri`='".addslashes($page->uri)."';");
                            if ($delete->rowCount()) {
                              echo "<h3 class=\"notice\">--&gt; ", sprintf($_LANG['0qd'], $page->uri), "</h3>\n";
                              $_LOG[] = "--> ".sprintf($_LANG['0qd'], $page->uri);
                            }
                          }
                          continue;
                        }

                        if (!$page->nofollow) {
                          preg_match("/<base[^>]+?href=[\"']([^\"'>\s]*)?/i", $page->body, $base);
                          if (isset($base[1])) $page->setBase($base[1]);

                          foreach ($_XDATA['linkRegexp'] as $linkRegexp) {
                            preg_match_all($linkRegexp, $page->body, $links);
                            $links = array_unique($links[2]);
                            foreach ($links as $link) {
                              if ($link && $link{0} != "#" && $link != $_SDATA['protocol']."://") {
                                if (preg_match("/^\/\//", $link)) {
                                  $link = "{$page->parsed['scheme']}:$link";
                                } else if (!preg_match("/^\w+:/", $link)) {
                                  switch ($link{0}) {
                                    case "/": $link = "{$page->parsed['scheme']}://{$page->parsed['hostport']}$link"; break;
                                    case "?": $link = "{$page->parsed['scheme']}://{$page->parsed['hostport']}/{$page->parsed['path']}$link"; break;
                                    default: $link = "{$page->parsed['scheme']}://{$page->parsed['hostport']}{$page->parsed['dir']}$link";
                                  }
                                }
                                $page->links[] = OS_add2Queue($link, $page->uri, $page->depth, $_XDATA['reindex']);
                              }
                            }
                          }
                        }


                        addTime("Links");


                        if (!$page->noindex) {
                          if (isset($page->metatags)) {
                            foreach ($page->metatags as $metatags) {
                              if (isset($metatags['name']) && isset($metatags['content'])) {
                                if ($metatags['name'] == "description") $page->description = $metatags['content'];
                                if ($metatags['name'] == "keywords") $page->keywords = $metatags['content'];
                              }
                            }
                          }

                          $page->title = (preg_match("/<title[^>]*?>([^<]+?)<\/title>/i", $page->body, $match)) ? str_replace(array("\r", "\n"), " ", $match[1]) : "";
                          foreach ($_XDATA['titleStrip'] as $titleStrip) $page->title = preg_replace("/{$titleStrip}/", "", $page->title);

                          // Apply Remove Tags and then capture text from Weighted Tags
                          $page->body = OS_mapSelectors($page->body, $_XDATA['removeTags'], true);
                          $page->wtags = OS_mapSelectors($page->body, $_XDATA['weightedTags']);


                          addTime("Elements");


                          $page->body = preg_replace("/<img[^>]+alt=(['\"])(.*?)\\1[^>]*>/i", " $2 ", $page->body);
                          $page->body = strip_tags(str_replace(array("<", ">"), array(" <", "> "), $page->body));
                          $page->body = str_replace("&nbsp;", " ", $page->body);
                          $page->body = preg_replace("/(\s|&nbsp;){2,}/", " ", $page->body);

                          if ($_VDATA['sp.utf8'] == "true") {
                            $page->wtags = OS_entities2utf8($page->wtags);
                            $page->title = OS_entities2utf8($page->title);
                            $page->keywords = OS_entities2utf8($page->keywords);
                            $page->description = OS_entities2utf8($page->description);
                            $page->body = OS_entities2utf8($page->body);
                          } else {
                            $page->wtags = OS_entities2ascii($page->wtags);
                            $page->title = OS_entities2ascii($page->title);
                            $page->keywords = OS_entities2ascii($page->keywords);
                            $page->description = OS_entities2ascii($page->description);
                            $page->body = OS_entities2ascii($page->body);
                          }


                          addTime("Content");


                        } else {
                          $page->setStatus("Blocked");
                          continue;
                        }
                        break;


                      case "txt":
                        $page->body = preg_replace("/\s{2,}/", " ", $page->body);
                        break;


                      default: // Include plugin indexed file types
                        switch (call_user_func_array($_MIME->ctype[$page->ctype]->indexer, array(&$page))) {
                          case 1:  // Data retrieved
                          case 0:  // No data retrieved
                            break;
                          case -1: // Error
                            $page->setStatus("Blocked");
                            continue;
                        }
                    }

                  } else {
                    $page->setStatus("Blocked");
                    continue;
                  }

                } else {
                  // Accepted but not indexed types
                  // User must supply details via Entry List panel

                }

                $page->status = ($_XDATA['cleanup']) ? "Orphan" : "OK";
                $page->mysqlPrep();

                if (count($_EXISTING)) {
                  $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET
                    `md5`='{$page->md5}',
                    `ctype`='{$page->ctype}',
                    ".(($_EXISTING['locked'] == "false") ? "`title`='{$page->title}'," : "")."
                    ".(($_EXISTING['locked'] == "false" && $page->description) ? "`description`='{$page->description}'," : "")."
                    ".(($_EXISTING['locked'] == "false" && $page->keywords) ? "`keywords`='{$page->keywords}'," : "")."
                    `wtags`='{$page->wtags}',
                    `body`='{$page->body}',
                    `links`='{$page->links}',
                    `encoding`='{$page->charset}',
                    `status`='{$page->status}',
                    `new`='{$page->isnew}',
                    `sm.lastmod`={$page->lastmod},
                    `sm.changefreq`='{$page->changefreq}'
                  WHERE `uri`='".addslashes($page->uri)."';");
                  if ($update->rowCount()) {
                    if ($page->status != "Orphan" || $page->isnew == "true") $_XDATA['stats']['Updated']++;
                  } else {
                    $err = $update->errorInfo();
                    if ((int)$err[0]) var_dump($err);
                  }


                  addTime("MySQL");


                } else {
                  $category = $_VDATA['sp.defcat'];
                  reset($_XDATA['autoCat']);
                  foreach ($_XDATA['autoCat'] as $autoCat) {
                    $against = "";
                    if (strpos($autoCat, $sep = ":::")) {
                      $against = $page->uri;
                    } else if (strpos($autoCat, $sep = ";;;")) $against = $page->title;
                    if ($against) {
                      $autoCat = array_map("trim", explode($sep, $autoCat));
                      $autoCat[1] = ($autoCat[1]{0} != "*") ? preg_quote($autoCat[1], "/") : substr($autoCat[1], 1);
                      if (preg_match("/{$autoCat[1]}/", $against)) {
                        $category = $autoCat[0];
                        break;
                      }
                    }
                  }

                  $insert = $_DDATA['link']->query("INSERT INTO `{$_DDATA['tabletemp']}` SET
                    `uri`='".addslashes($page->uri)."',
                    `md5`='{$page->md5}',
                    `ctype`='{$page->ctype}',
                    `title`='{$page->title}',
                    `category`='$category',
                    `description`='{$page->description}',
                    `keywords`='{$page->keywords}',
                    `wtags`='{$page->wtags}',
                    `body`='{$page->body}',
                    `links`='{$page->links}',
                    `encoding`='{$page->charset}',
                    `sm.lastmod`=UNIX_TIMESTAMP()
                  ;");
                  if ($insert->rowCount()) $_XDATA['stats']['New']++;


                  addTime("MySQL");


                }
              }
            }

            // You may uncomment the usleep() line below to add a delay between each request.
            //   You should enable Seamless Spidering before doing so.
            //   NOTE: usleep works on PHP3+ for *nix but only PHP5+ for Windows.

            // usleep(500000);  // This is a one-half second delay (500,000 microseconds)


            addTime("Sleep");


          }

          /* ***** Add Orphans to the queue ************************ */
          if (!$_XDATA['cleanup'] && !$_XDATA['reindex']) {
            $_XDATA['cleanup'] = true;
            $select = $_DDATA['link']->query("SELECT `uri` FROM `{$_DDATA['tabletemp']}`;")->fetchAll();
            foreach ($select as $orp) {
              if (!isset($_XDATA['scanned'][$orp['uri']])) {
                if (OS_isBlocked($orp['uri'])) {
                  $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tabletemp']}` SET `status`='Blocked', `body`='' WHERE `uri`='".addslashes($orp['uri'])."';");
                  if ($update->rowCount()) $_XDATA['stats']['Blocked']++;
                } else $_XDATA['queue'][$orp['uri']] = array(0, "");
              }
            }


            addTime("MySQL");


          } else break;
        }

        if ($_XDATA['needtemp'] && !@unlink($_XDATA['tempfile']))
          echo "<h3 class=\"notice warning\">", $_LOG[] = sprintf($_LANG['03o'], $_XDATA['tempfile']), "</h3>\n";

        if ($_VDATA['sp.seamless'] == "true") {
          $truncate = $_DDATA['link']->query("TRUNCATE TABLE `{$_DDATA['tablename']}`;");
          $insert = $_DDATA['link']->query("INSERT INTO `{$_DDATA['tablename']}` SELECT * FROM `{$_DDATA['tabletemp']}`;");
          $drop = $_DDATA['link']->query("DROP TABLE `{$_DDATA['tabletemp']}`;");
        }
        $optimize = $_DDATA['link']->query("OPTIMIZE TABLE `{$_DDATA['tablename']}`;");
        $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablestat']}` SET `cache`='';");

        OS_setData("sp.lock", "false");
        $lasttime = array_sum(explode(" ", microtime())) - $_SDATA['now'];
        if (!$_XDATA['reindex']) {
          OS_setData("sp.time", time());
          OS_setData("sp.fullscan", "false");
          OS_setData("sp.lasttime", $lasttime);
          OS_setData("sp.alldata", $_XDATA['dataleng']);
        }


        addTime("MySQL");


        restore_error_handler();

        $_LOG[] = "*** ".sprintf($_LANG['0pn'], count($_XDATA['scanned']))." ***";
        $_LOG[] = "{$_LANG['0po']}: ".sprintf("%01.2f", $lasttime)."s";
        if (count($_XDATA['scanned']))
          $_LOG[] = "{$_LANG['0pp']}: ".sprintf("%01.3f", $lasttime / count($_XDATA['scanned']))."s";
        $_LOG[] = "{$_LANG['0pq']}: {$_XDATA['stats']['New']}";
        $_LOG[] = "{$_LANG['0pr']}: {$_XDATA['stats']['Updated']}";
        $_LOG[] = "{$_LANG['0ps']}: {$_XDATA['stats']['Not Found']}";
        $_LOG[] = "{$_LANG['0pt']}: {$_XDATA['stats']['Orphan']}";
        $_LOG[] = "{$_LANG['0pu']}: {$_XDATA['stats']['Blocked']}";

        /* ***** End Spider *******************************************
        ***************************************************************
        ******** Begin Sitemap ************************************* */

        if ($_VDATA['sm.enable'] == "true") { ?>
          <h2><?php echo $_LANG['0pv']; ?></h2><?php
          flush();

          $cData['smnf'] = true;
          $cData['smnw'] = true;
          if (file_exists($_VDATA['sm.pathto'])) {
            $cData['smnf'] = false;
            if (is_writable($_VDATA['sm.pathto'])) $cData['smnw'] = false;
          }
          if ($cData['smnf'] || $cData['smnw']) { ?>
            <h2 class="warning"><?php echo $_LOG[] = $_LANG['0pw']; ?></h2><?php

          } else {
            ob_start();

            if ($_VDATA['sm.unlisted'] != "true") {
              $lq = ($_VDATA['s.orphans'] == "show") ? " AND (`status`='OK' OR `status`='Orphan')" : " AND `status`='OK'";

              $nq = "";
              $sData['noSearch'] = array_filter(array_map("trim", explode("\n", $_VDATA['s.ignore'])));
              foreach ($sData['noSearch'] as $noSearch)
                $nq .= " AND `uri` NOT ".(($noSearch{0} == "*") ? "REGEXP '".substr(str_replace("'", "\\'", $noSearch), 1)."'": " LIKE '%".str_replace("'", "\\'", $noSearch)."%'");

              $qadd = " AND `unlist`!='true'{$lq}{$nq}";
            } else $qadd = "";

            $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $sitemap = $_DDATA['link']->query("SELECT `uri`, `sm.lastmod`, `sm.changefreq`, `sm.priority` FROM `{$_DDATA['tablename']}` WHERE `sm.list`='true' AND `uri` LIKE '%//".str_replace("'", "\\'", $_VDATA['sm.domain'])."/%' AND `body`!=''$qadd;")->fetchAll();

            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            ?><urlset xmlns="http://www.google.com/schemas/sitemap/0.84">
<?php foreach ($sitemap as $smrow) { ?>  <url>
    <loc><?php echo htmlspecialchars($smrow['uri']); ?></loc>
    <lastmod><?php echo date("Y-m-d", $smrow['sm.lastmod']); ?></lastmod>
    <changefreq><?php echo $smrow['sm.changefreq']; ?></changefreq><?php
    if ($smrow['sm.priority'] != 0.5) { ?>
    <priority><?php echo $smrow['sm.priority']; ?></priority><?php
    } ?>
  </url>
<?php } ?></urlset><?php

            $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $sitemap = ob_get_contents();
            ob_end_clean();

            if ($_SDATA['zlib'] && $_VDATA['sm.gzip'] == "true") {
              $shell = gzopen($_VDATA['sm.pathto'], "w");
              gzwrite($shell, $sitemap);
              gzclose($shell);
            } else {
              $shell = fopen($_VDATA['sm.pathto'], "w");
              fwrite($shell, $sitemap);
              fclose($shell);
            }
            $_LOG[] = "*** {$_LANG['0px']} ***";
          }
        }
        /* ***** End Sitemap ******************************************
        ********************************************************* */ ?>

        <h1><?php echo $_LANG['0py']; ?></h1>
        <style type="text/css">form#canceller input { display:none; }</style>

        <label><?php echo $_LANG['0pz']; ?>:<br>
          <textarea rows="15" cols="60" readonly="readonly" wrap="off"><?php
            foreach ($_XDATA['scanned'] as $key => $scanned) echo "\n", str_replace("{$_SDATA['protocol']}://{$_SERVER['HTTP_HOST']}/", "/", $key);
          ?></textarea>
        </label>

        <table cellspacing="0" border="1">
          <tbody>
            <tr>
              <th><?php echo $_LANG['0pq']; ?></th>
              <td><?php echo $_XDATA['stats']['New']; ?></td>
            </tr>
            <tr>
              <th><?php echo $_LANG['0pr']; ?></th>
              <td><?php echo $_XDATA['stats']['Updated']; ?></td>
            </tr>
            <tr>
              <th><?php echo $_LANG['0ps']; ?></th>
              <td><?php echo $_XDATA['stats']['Not Found']; ?></td>
            </tr>
            <tr>
              <th><?php echo $_LANG['0pt']; ?></th>
              <td><?php echo $_XDATA['stats']['Orphan']; ?></td>
            </tr>
            <tr>
              <th><?php echo $_LANG['0pu']; ?></th>
              <td><?php echo $_XDATA['stats']['Blocked']; ?></td>
            </tr>
            <tr>
              <th><?php echo $_LANG['0po']; ?></th>
              <td><?php printf("%01.2f", $lasttime); ?>s</td>
            </tr>
            <tr>
              <th><?php echo $_LANG['0pp']; ?></th>
              <td><?php printf("%01.3f", $lasttime / count($_XDATA['scanned'])); ?>s</td>
            </tr>
          </tbody>
        </table><?php
      } else {

        OS_setData("sp.lock", "false"); ?>
        <h2 class="warning"><?php echo $_LANG['0q0']; ?></h1><?php
      }
    }

    if ($_SERVER['REQUEST_METHOD'] != "CRON" && $_XDATA['linkback']) { ?>
      <a href="<?php echo htmlspecialchars($_XDATA['linkback']); ?>" id="goback"><?php echo $_LANG['0q1']; ?></a><?php
    } ?>

    <hr>

    <div id="lower">
      <h1><?php echo $_LANG['0q2']; ?></h1>

      <h2><?php echo $_LANG['0q3']; ?></h2><?php
      if (count($_XDATA['robotsCancel'])) { ?>
        <ul><?php
          foreach ($_XDATA['robotsCancel'] as $robotrule) { ?>
            <li><?php echo $robotrule; ?></li><?php
          } ?>
        </ul><?php
      } else { ?>
        <div>None</div><?php
      } ?>

      <h2><?php echo $_LANG['0p8']; ?></h2>
      <pre><?php echo ((sizeof($_XDATA['cookies']) > 0) ? print_r($_XDATA['cookies'], true) : ''); ?></pre>

      <table cellspacing="0" border="1">
        <thead>
          <tr>
            <th><?php echo $_LANG['0qg']; ?></th>
            <th><?php echo $_LANG['0q4']; ?></th>
          </tr>
        </thead>
        <tbody><?php
          while (list($key, $value) = each($_TIMER)) {
            if ($key != "__log") { ?>
              <tr>
                <th><?php echo $key; ?></th>
                <td><?php printf("%01.3f", $value); ?>s</td>
              </tr><?php
            }
          } ?>
        </tbody>
      </table>
    </div><?php
  } ?>
</body>
</html><?php

if ($_SERVER['REQUEST_METHOD'] == "CRON") {
  ob_end_clean();
  echo implode("\n", $_LOG);

/* ***** Email Results ******************************************* */
} else if ($_VDATA['sp.email'] && !$_XDATA['reindex']) {
  $address = explode(" ", preg_replace("/[\"<>]/", "", trim($_VDATA['sp.email'])));
  while (count($address) > 2) {
    $str = array_shift($address);
    $address[0] = $str + $address[0];
  }
  if (count($address) == 1) array_unshift($address, "");

  $mail->AddAddress($address[1], $address[0]);
  $mail->Subject = "{$_LANG['0q5']}: {$_VDATA['sp.pathto']}";
  $mail->Body = implode("\n", $_LOG)."\n\n".implode("\n", $_XDATA['errors']);

  $mail->Send();

  // @mail($_VDATA['sp.email'], "{$_LANG['0q5']}: {$_VDATA['sp.pathto']}", implode("\n", $_LOG)."\n\n".implode("\n", $_XDATA['errors']), $mData['headers']);
}
