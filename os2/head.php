<?php /* ***** Orca Search - Searching Engine ***********************


*********************************************************************
***** Output Documentation ******************************************
* Search Query:
*  - Only set if an actual query exists, unset otherwise
* 1) $_QUERY array with hash:
*   -> "original" - String
*        => Original query as typed
*   -> "category" - String
*        => Selected category, empty string if none
*   -> "allterms" - Array
*        => Unfiltered terms in no order (includes ignored terms)
*   -> "terms" - Array
*        => Filtered terms in no order (terms actually searched for)
*   -> "sorted" - String
*        => Filtered terms sorted alphabetically (cache index column)
*   -> "and" - Array
*        => All terms which were marked with +
*   -> "not" - Array
*        => All terms which were marked with - or !
*   -> "or" - Array
*        => All terms which were not marked
*   -> "andor" - Array
*        => Combined "or" and "and" arrays stripped of + marks
*
* Search Info:
* 1) $_SDATA['now'] - Float
*   -> *NIX timestamp with microtime() when script started
*   -> Use array_sum(explode(" ", microtime())) - $_SDATA['now']
*       to find execution-time
* 2) $_SDATA['totalRows'] - Integer
*   -> Number of searchable rows indexed
* 3) $_SDATA['categories'] - Array
*   -> List of all categories available from searchable pages
*
* Results:
* 1) If no database connection:
*   -> $_DDATA['online'] = false
*     -> $_DDATA['error'] = MySQL error message (if exists)
*
* 2) If the database is locked, and there are no cached results:
*   -> $_DDATA['online'] = true;
*   -> $_RESULTS == NULL
*
* 3) If no results are found:
*   -> $_DDATA['online'] = true
*   -> Empty $_RESULTS array
*
* 4) If results were found:
*   -> $_DDATA['online'] = true
*   -> Relevance-ordered array $_RESULTS with each item having hash:
*     -> "title" - String
*          => Title of match (or URI if no title)
*     -> "description" - String
*          => Meta (or table-assigned) description
*     -> "category" - String
*          => Assigned category, pre-filtered by $_REQUEST['c']
*     -> "uri" - String
*          => Full URI of match
*     -> "matchURI" - String
*          => Full URI with matching terms highlighted
*     -> "filetype" - String
*          => Page filetype: html, txt, pdf, jpg, etc.
*     -> "matchText" - String
*          => Collection of matched text from entry
*     -> "relevance" - Float
*          => Relevance score
****************************************************************** */


/* ******************************************************************
******** Functions *********************************************** */
function OS_mysqlFormat($input, $neg) {
  $input = ltrim($input, "!+-");
  if (strpos($input, " ") !== false) {
    $type = "REGEXP";
    $input = str_replace(" ", '[,.?!]?[ \-][,.?!]?', preg_quote($input));
  } else {
    $type = "LIKE";
    $input = "%$input%";
  }
  return (($neg) ? "NOT " : "")."$type '".addslashes($input)."'";
}

function OS_addRelevance(&$value, $terms, $multiplier) {
  global $_VDATA;

  $areas = array("title", "body", "keywords", "wtags", "uri");
  $order = array_flip($areas);
  $multi = -1;
  $foundlimit = 3;
  $lbody = strtolower($value['body']);

  foreach ($terms as $term) {
    $relevance = $value['relevance'];
    $phrase = false;
    $match = array();
    $found = array();

    if (strpos($term, " ")) {
      $term = str_replace(" ", '[,.?!]?[ \-][,.?!]?', preg_quote($term, "/"));
      $phrase = true;
    } else if ($_VDATA['s.latinacc'] == "true") {
      $term = OS_latinAccents(preg_quote($term, "/"), ($_VDATA['sp.utf8'] == "true") ? true : false);
    } else $term = preg_quote($term, "/");

    foreach ($areas as $area) {
      if ($_VDATA['s.weight'][$order[$area]] > 0) {
        if ($phrase || $_VDATA['s.latinacc'] == "true") {
          preg_match_all("/$term/i", $value[$area], $match[$area]);
          $found[$area] = count($match[$area][0]);
        } else $found[$area] = substr_count(strtolower($value[$area]), strtolower($term));
      } else $found[$area] = 0;
    }

    foreach($areas as $key => $val) {
      $value['relevance'] += $_VDATA['s.weight'][$key] * min($foundlimit, $found[$val]) * $multiplier;
    }
    if ($value['relevance'] > $relevance) $multi++;

    unset($matchtext);
    if ($phrase || $_VDATA['s.latinacc'] == "true") {
      if (isset($match['body'][0][0]) && ($firstpos = strpos($lbody, strtolower($match['body'][0][0]))) !== false)
        $matchtext = substr("  ".$value['body']."  ", max(0, $firstpos - 50), 140 + strlen($match['body'][0][0]));
    } else if ($found['body']) {
      $firstpos = strpos($lbody, strtolower($term));
      $matchtext = substr("  ".$value['body']."  ", max(0, $firstpos - 50), 140 + strlen($term));
    }

    if (isset($matchtext) && ((strlen($value['matchText']) + strlen($matchtext)) < $_VDATA['s.matchingtext']) && !preg_match("/$term/i", $value['matchText'])) {
      if ($_VDATA['sp.utf8'] == "true") {
        $matchtext = preg_replace(array("/^.*?(?=[\xC2-\xDF\xE0-\xF4\s])/s", "/(\xF4[\x80-\x8F]?[\x80-\xBF]?|[\xF1-\xF3][\x80-\xBF]{,2}|\xF0[\x90-\xBF]?[\x80-\xBF]?|\xED[\x80-\x9F]?|[\xE1-\xEC\xEE\xEF][\x80-\xBF]?|\xE0[\xA0-\xBF]?|[\xC2-\xDF]|\w+)$/"), "", $matchtext);
      } else $matchtext = preg_replace(array("/^[^\s]*\s/", "/\s[^\s]*$/"), "", $matchtext);
      $value['matchText'] .= $matchtext." ... ";
    }
  }

  $value['relevance'] *= pow($_VDATA['s.weight'][5], $multi);
}

function OS_latinAccents($_, $utf8 = true) {
  static $accrep = array();

  if ($utf8) {
    $_ = str_replace(array("AE", "Ae", "aE", "ae"), "(ae|√[Ü¶])", $_);
    $_ = str_replace(array("A", "a"), "(a|√[ÄÅÇÉÑÖ†°¢£§•])", $_);
    $_ = str_replace(array("C", "c"), "(c|√[áß])", $_);
    $_ = str_replace(array("E", "e"), "(e|√[êàâäã∞®©™´])", $_);
    $_ = str_replace(array("I", "i"), "(i|√[åçéè¨≠ÆØ])", $_);
    $_ = str_replace(array("N", "n"), "(n|√[ë±])", $_);
    $_ = str_replace(array("O", "o"), "(o|√[íìîïñò≤≥¥µ∂∏])", $_);
    $_ = str_replace(array("S", "s"), "(s|√ü)", $_);
    $_ = str_replace(array("T", "t"), "(t|√[ûæ])", $_);
    $_ = str_replace(array("U", "u"), "(u|√[ôöõú∫ªº])", $_);
    $_ = str_replace(array("Y", "y"), "(y|√[ù∏Ωø])", $_);

  } else {
    if (!count($accrep)) {
      $accrep = array(
        "∆" => '([∆Ê]|&(\101\105|\141\145)\154\151\147;)', "Ê" => '([∆Ê]|&(\101\105|\141\145)\154\151\147;)',
        "¿" => '([¿‡]|&[\101\141]\147\162\141\166\145;)',  "¡" => '([¡·]|&[\101\141]\141\143\165\164\145;)',
        "¬" => '([¬‚]|&[\101\141]\143\151\162\143;)',      "√" => '([√„]|&[\101\141]\164\151\154\144\145;)',
        "ƒ" => '([ƒ‰]|&[\101\141]\165\155\154;)',          "≈" => '([≈Â]|&[\101\141]\162\151\156\147;)',
        "‡" => '([¿‡]|&[\101\141]\147\162\141\166\145;)',  "·" => '([¡·]|&[\101\141]\141\143\165\164\145;)',
        "‚" => '([¬‚]|&[\101\141]\143\151\162\143;)',      "„" => '([√„]|&[\101\141]\164\151\154\144\145;)',
        "‰" => '([ƒ‰]|&[\101\141]\165\155\154;)',          "Â" => '([≈Â]|&[\101\141]\162\151\156\147;)',
        "«" => '([«Á]|&[\103\143]\143\145\144\151\154;)',  "Á" => '([«Á]|&[\103\143]\143\145\144\151\154;)',
        "–" => '([–]|&(\105\124\110|\145\164\150);)',     "»" => '([»Ë]|&[\105\145]\147\162\141\166\145;)',
        "…" => '([…È]|&[\105\145]\141\143\165\164\145;)',  " " => '([ Í]|&[\105\145]\143\151\162\143;)',
        "À" => '([ÀÎ]|&[\105\145]\165\155\154;)',          "" => '([–]|&(\105\124\110|\145\164\150);)',
        "Ë" => '([»Ë]|&[\105\145]\147\162\141\166\145;)',  "È" => '([…È]|&[\105\145]\141\143\165\164\145;)',
        "Í" => '([ Í]|&[\105\145]\143\151\162\143;)',      "Î" => '([ÀÎ]|&[\105\145]\165\155\154;)',
        "Ã" => '([ÃÏ]|&[\111\151]\147\162\141\166\145;)',  "Õ" => '([ÕÌ]|&[\111\151]\141\143\165\164\145;)',
        "Œ" => '([ŒÓ]|&[\111\151]\143\151\162\143;)',      "œ" => '([œÔ]|&[\111\151]\165\155\154;)',
        "Ï" => '([ÃÏ]|&[\111\151]\147\162\141\166\145;)',  "Ì" => '([ÕÌ]|&[\111\151]\141\143\165\164\145;)',
        "Ó" => '([ŒÓ]|&[\111\151]\143\151\162\143;)',      "Ô" => '([œÔ]|&[\111\151]\165\155\154;)',
        "—" => '([—Ò]|&[\116\156]\164\151\154\144\145;)',  "Ò" => '([—Ò]|&[\116\156]\164\151\154\144\145;)',
        "“" => '([“Ú]|&[\117\157]\147\162\141\166\145;)',  "”" => '([”Û]|&[\117\157]\141\143\165\164\145;)',
        "‘" => '([‘Ù]|&[\117\157]\143\151\162\143;)',      "’" => '([’ı]|&[\117\157]\164\151\154\144\145;)',
        "÷" => '([÷ˆ]|&[\117\157]\165\155\154;)',          "ÿ" => '([ÿ¯]|&[\117\157]\163\154\141\163\150;)',
        "Ú" => '([“Ú]|&[\117\157]\147\162\141\166\145;)',  "Û" => '([”Û]|&[\117\157]\141\143\165\164\145;)',
        "Ù" => '([‘Ù]|&[\117\157]\143\151\162\143;)',      "ı" => '([’ı]|&[\117\157]\164\151\154\144\145;)',
        "ˆ" => '([÷ˆ]|&[\117\157]\165\155\154;)',          "¯" => '([ÿ¯]|&[\117\157]\163\154\141\163\150;)',
        "ﬂ" => '(ﬂ|&\163\172\154\151\147;)',
        "ﬁ" => '([ﬁ˛]|&(\124\110\117\122\116|\164\150\157\162\156);)',
        "˛" => '([ﬁ˛]|&(\124\110\117\122\116|\164\150\157\162\156);)',
        "Ÿ" => '([Ÿ˘]|&[\125\165]\147\162\141\166\145;)',  "⁄" => '([⁄˙]|&[\125\165]\141\143\165\164\145;)',
        "€" => '([€˚]|&[\125\165]\143\151\162\143;)',      "‹" => '([‹¸]|&[\125\165]\165\155\154;)',
        "˘" => '([Ÿ˘]|&[\125\165]\147\162\141\166\145;)',  "˙" => '([⁄˙]|&[\125\165]\141\143\165\164\145;)',
        "˚" => '([€˚]|&[\125\165]\143\151\162\143;)',      "¸" => '([‹¸]|&[\125\165]\165\155\154;)',
        "›" => '([›˝]|&[\131\171]\141\143\165\164\145;)',  "ü" => '([üˇ]|&[\131\171]\165\155\154;)',
        "˝" => '([›˝]|&[\131\171]\141\143\165\164\145;)',  "ˇ" => '([üˇ]|&[\131\171]\165\155\154;)'
      );
    }
    $_ = strtr($_, $accrep);
    $_ = str_replace(array("AE", "Ae", "aE", "ae"), "(ae|∆|Ê)", $_);
    $_ = str_replace(array("A", "a"), "([a¿¡¬√ƒ≈‡·‚„‰Â]|&a(\147\162\141\166\145|\141\143\165\164\145|\143\151\162\143|\164\151\154\144\145|\165\155\154|\162\151\156\147);)", $_);
    $_ = str_replace(array("C", "c"), "([c«Á]|&c\143\145\144\151\154;)", $_);
    $_ = str_replace(array("E", "e"), "([e–»… ÀËÈÍÎ]|&e((?<=\105)\124\110|(?<=\145)\164\150|\147\162\141\166\145|\141\143\165\164\145|\143\151\162\143|\165\155\154;)", $_);
    $_ = str_replace(array("I", "i"), "([iÃÕŒœÏÌÓÔ]|&i(\147\162\141\166\145|\141\143\165\164\145|\143\151\162\143|\165\155\154);)", $_);
    $_ = str_replace(array("N", "n"), "([n—Ò]|&n\164\151\154\144\145;)", $_);
    $_ = str_replace(array("O", "o"), "([o“”‘’÷ÿÚÛÙıˆ¯]|&o(\147\162\141\166\145|\141\143\165\164\145|\143\151\162\143|\164\151\154\144\145|\165\155\154|\163\154\141\163\150);)", $_);
    $_ = str_replace(array("S", "s"), "([sﬂ]|&\163\172\154\151\147;)", $_);
    $_ = str_replace(array("T", "t"), "([tﬁ˛]|&(\124\110\117\122\116|\164\150\157\162\156);)", $_);
    $_ = str_replace(array("U", "u"), "([uŸ⁄€‹˘˙˚¸]|&u(\147\162\141\166\145|\141\143\165\164\145|\143\151\162\143|\165\155\154);)", $_);
    $_ = str_replace(array("Y", "y"), "([y›ü˝ˇ]|&y(\141\143\165\164\145|\165\155\154);)", $_);
  }
  return $_;
}

function OS_outputFormat($string, $terms) {
  global $_VDATA;

  $string = str_replace(" ... ", " %%%%%%%%%...%%%%/%%%% ", $string);
  foreach ($terms as $term) {
    $term = str_replace(" ", '[,.?!]?[ \-][,.?!]?', preg_quote($term, "/"));
    if ($_VDATA['s.latinacc'] == "true") $term = OS_latinAccents($term, ($_VDATA['sp.utf8'] == "true") ? true : false);
    $string = preg_replace("/($term)/i", "%%%%%%%%%$1%%%%/%%%%", $string);
  }

  $string = strtr($string, array("<" => "&lt;", ">" => "&gt;"));
  $string = str_replace(array("%%%%/%%%%", "%%%%%%%%%", "</strong><strong>"), array("</strong>", "<strong>", ""), $string);
  return $string;
}


/* ******************************************************************
******** Setup *************************************************** */
header("Orcascript: Search_Engine");

if ($_DDATA['online']) {
  $_SDATA['noSearch'] = array_filter(array_map("trim", explode("\n", $_VDATA['s.ignore'])));

  $_SDATA['lq'] = ($_VDATA['s.orphans'] == "show") ? " AND (`status`='OK' OR `status`='Orphan')" : " AND `status`='OK'";

  $_SDATA['nq'] = "";
  foreach ($_SDATA['noSearch'] as $noSearch)
    $_SDATA['nq'] .= " AND `uri` NOT ".(($noSearch[0] == "*") ? "REGEXP '".substr($noSearch, 1)."'": " LIKE '%{$noSearch}%'");

  $trow = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `unlist`='false'{$_SDATA['lq']}{$_SDATA['nq']};")->fetchAll(PDO::FETCH_NUM);
  list($_SDATA['totalRows']) = array_shift($trow);
  $_RESULTS = array();

  $_SDATA['categories'] = array();
  $select = $_DDATA['link']->query("SELECT DISTINCT `category` FROM `{$_DDATA['tablename']}` WHERE `unlist`='false'{$_SDATA['lq']}{$_SDATA['nq']} ORDER BY `category`;")->fetchAll();
  foreach ($select as $row) $_SDATA['categories'][] = $row['category'];

  $_REQUEST['c'] = (isset($_REQUEST['c'])) ? $_REQUEST['c'] : "";
  if (!in_array($_REQUEST['c'], $_SDATA['categories'])) $_REQUEST['c'] = "";

  if ($_VDATA['s.cachetime'] < (time() - $_VDATA['s.cachereset'] * 86400)) {
    if ($address = trim($_VDATA['s.cacheemail'])) {
      $mail = new PHPMailer\PHPMailer\PHPMailer();
      $mail->From = $_SERVER['SERVER_ADMIN'];
      $mail->FromName = "Orca Search Spider";
      $mail->CharSet = $_VDATA['c.charset'];

      $address = explode(" ", preg_replace("/[\"<>]/", "", $address));
      while (count($address) > 2) {
        $str = array_shift($address);
        $address[0] = $str + $address[0];
      }
      if (count($address) == 1) array_unshift($address, "");

      $mail->AddAddress($address[1], $address[0]);
      $mail->Subject = "{$_LANG['0lo']}: {$_SERVER['HTTP_HOST']} Orca Search";
      $mail->Body = sprintf($_LANG['0m2'], date("Y-m-d", $_VDATA['s.cachetime']), date("Y-m-d"))."\n\n";
      $mail->Body .= "{$_LANG['0m3']}\n  {$_SDATA['scheme']}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}\n\n";
      $mail->Body .= " {$_LANG['0lr']}   {$_LANG['0lp']}\n";
      $mail->Body .= "______ _______________________________________________________________\n";

      $select = $_DDATA['link']->query("SELECT `hits`, `query` FROM `{$_DDATA['tablestat']}` ORDER BY `hits` DESC, `query`;")->fetchAll();
      foreach ($select as $row)
        $mail->Body .= str_pad($row['hits'], 5, " ", STR_PAD_LEFT)."   ".substr($row['query'], 0, 60)."\n";

      $mail->Body .= "\n\n";
      $mail->Body .= "______________________________________________________________________\n";
      $mail->Body .= "Orca Search {$_SDATA['version']}";

      $mail->Send();

    }
    $truncate = $_DDATA['link']->query("TRUNCATE TABLE `{$_DDATA['tablestat']}`;");
    OS_setData("s.cachetime", time());

  } else {
    $srow = $_DDATA['link']->query("SELECT SUM(LENGTH(`cache`)) FROM `{$_DDATA['tablestat']}`;")->fetchAll(PDO::FETCH_NUM);
    list($show) = array_shift($srow);
    while ($show > $_VDATA['s.cachelimit'] * 1024) {
      $row = array_shift($_DDATA['link']->query("SELECT `query` FROM `{$_DDATA['tablestat']}` WHERE `cache`!='' ORDER BY `lasthit` LIMIT 1;")->fetchAll());
      $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablestat']}` SET `cache`='' WHERE `query`='".addslashes($row['query'])."';");
      if ($update->rowCount() <= 0) break;
      list($show) = array_shift($_DDATA['link']->query("SELECT SUM(LENGTH(`cache`)) FROM `{$_DDATA['tablestat']}`;")->fetchAll(PDO::FETCH_NUM));
    }
    $optimize = $_DDATA['link']->query("OPTIMIZE TABLE `{$_DDATA['tablestat']}`;");
  }

  /* ****************************************************************
  ******** Search ************************************************ */
  if (isset($_REQUEST['q']) && $_REQUEST['q'] = trim($_REQUEST['q'])) {
    $_QUERY = array();

    $_QUERY['query'] = $_QUERY['original'] = $_REQUEST['q'];

    preg_match_all("/[!+\-]?\".*?\"/", $_QUERY['query'], $quotes);
    $_QUERY['terms'] = str_replace('"', "", $quotes[0]);
    $_QUERY['query'] = preg_replace(array("/[!+\-]?\".*?\"/", "/\"/", "/\s{2,}/"), array("", "", " "), $_QUERY['query']);
    $_QUERY['terms'] = array_merge($_QUERY['terms'], explode(" ", $_QUERY['query']));
    $_QUERY['allterms'] = $_QUERY['terms'];
    $_QUERY['terms'] = array_filter($_QUERY['terms'], function($value) {
      global $_VDATA;
      return (strlen($value) >= $_VDATA['s.termlength']) ? true : false;
    });
    $_QUERY['terms'] = array_slice($_QUERY['terms'], 0, $_VDATA['s.termlimit']);

    $_QUERY['sorted'] = $_QUERY['terms'];
    sort($_QUERY['sorted']);
    $_QUERY['sorted'] = addslashes(stripslashes(implode(" ", $_QUERY['sorted'])));
    $frow = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablestat']}` WHERE `query`='{$_QUERY['sorted']}';")->fetchAll(PDO::FETCH_NUM);
    list($_SDATA['found']) = array_shift($frow);
    $_SDATA['count'] = $_DDATA['link']->query("SELECT `cache` FROM `{$_DDATA['tablestat']}` WHERE `query`='{$_QUERY['sorted']}' AND LENGTH(`cache`)>5;")->fetchAll();

    if (count($_QUERY['terms'])) {
      if (!count($_SDATA['count'])) {
        if ($_VDATA['sp.lock'] == "false" || $_VDATA['sp.seamless'] == "true") {
          $_QUERY['and'] = preg_grep("/^\+/", $_QUERY['terms']);
          $_QUERY['not'] = preg_grep("/^[!\-]/", $_QUERY['terms']);
          $_QUERY['or'] = array_diff($_QUERY['terms'], $_QUERY['and'], $_QUERY['not']);
          array_walk($_QUERY['and'], function(&$v, $k) { $v = substr($v, 1); });
          array_walk($_QUERY['not'], function(&$v, $k) { $v = substr($v, 1); });
          $_QUERY['andor'] = array_merge($_QUERY['and'], $_QUERY['or']);

          $_QUERY['typey'] = preg_grep("/^filetype:\w+/", $_QUERY['andor']);
          $_QUERY['andor'] = array_diff($_QUERY['andor'], $_QUERY['typey']);
          $_QUERY['and'] = array_diff($_QUERY['and'], $_QUERY['typey']);
          $_QUERY['or'] = array_diff($_QUERY['or'], $_QUERY['typey']);
          $_QUERY['typen'] = preg_grep("/^filetype:\w+/", $_QUERY['not']);
          $_QUERY['not'] = array_diff($_QUERY['not'], $_QUERY['typen']);

          $mq = "";
          foreach ($_QUERY['not'] as $not) {
            $shot = OS_mysqlFormat($not, true);
            $mq .= " AND `body` $shot AND `title` $shot AND `keywords` $shot";
          }
          foreach ($_QUERY['and'] as $and) {
            $shot = OS_mysqlFormat($and, false);
            $mq .= " AND (`body` $shot OR `title` $shot OR `keywords` $shot)";
          }
          foreach ($_QUERY['typey'] as $types) {
            $types = trim(preg_replace("/^filetype:/", "", $types));
            if (preg_match("/\w+/", $types)) $mq .= " AND `ctype`='".(($types = $_MIME->get_ctype($types)) ? $types : "none")."'";
          }
          foreach ($_QUERY['typen'] as $types) {
            $types = trim(preg_replace("/^filetype:/", "", $types));
            if (preg_match("/\w+/", $types)) $mq .= " AND `ctype`!='".(($types = $_MIME->get_ctype($types)) ? $types : "none")."'";
          }

          $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
          $select = $_DDATA['link']->query("SELECT * FROM `{$_DDATA['tablename']}` WHERE `unlist`='false'{$_SDATA['lq']}{$_SDATA['nq']}{$mq};")->fetchAll();

          foreach ($select as $uri) {
            $uri['relevance'] = 0;
            $uri['bonus'] = 1;
            $uri['matchText'] = "";
            OS_addRelevance($uri, $_QUERY['and'], $_VDATA['s.weight'][6]);
            OS_addRelevance($uri, $_QUERY['or'], 1);

            if ($uri['matchText']) {
              $uri['matchText'] = trim(substr($uri['matchText'], 0, strlen($uri['matchText']) - 5));
              if (preg_match("/^[^A-Z]/", $uri['matchText'][0])) $uri['matchText'] = " ... ".$uri['matchText'];
              if (preg_match("/[^.?!]/", $uri['matchText'][strlen($uri['matchText']) - 1])) $uri['matchText'] .= " ... ";
              $uri['matchText'] = str_replace(array("\n", "\r"), "", $uri['matchText']);
            } else if (trim($uri['description'])) {
              $uri['matchText'] = $uri['description'];
            } else if (trim($uri['body'])) {
              $uri['matchText'] = substr($uri['body'], mt_rand(0, max(0, strlen($uri['body']) - (int)($_VDATA['s.matchingtext'] / 3))), (int)($_VDATA['s.matchingtext'] / 3));
              if ($_VDATA['sp.utf8'] == "true") {
                $uri['matchText'] = preg_replace(array("/^.*?(?=[\xC2-\xDF\xE0-\xF4\s])/s", "/(\xF4[\x80-\x8F]?[\x80-\xBF]?|[\xF1-\xF3][\x80-\xBF]{,2}|\xF0[\x90-\xBF]?[\x80-\xBF]?|\xED[\x80-\x9F]?|[\xE1-\xEC\xEE\xEF][\x80-\xBF]?|\xE0[\xA0-\xBF]?|[\xC2-\xDF]|\w+)$/"), "", $uri['matchText']);
              } else $uri['matchText'] = preg_replace(array("/^[^\s]*\s/", "/\s[^\s]*$/"), "", $uri['matchText']);
              $uri['matchText'] .= " ... ";
            }

            $uri['matchText'] = OS_outputFormat($uri['matchText'], $_QUERY['andor']);
            $uri['title'] = OS_outputFormat($uri['title'], $_QUERY['andor']);
            $uri['matchURI'] = ((float)$_VDATA['s.weight'][4] > 0) ? OS_outputFormat($uri['uri'], $_QUERY['andor']) : $uri['uri'];
            $uri['description'] = OS_outputFormat($uri['description'], array());

            if ($uri['relevance'])
              $_RESULTS[] = array(
                "title"       => $uri['title'],
                "description" => $uri['description'],
                "category"    => $uri['category'],
                "uri"         => $uri['uri'],
                "matchURI"    => $uri['matchURI'],
                "filetype"    => $uri['ctype'],
                "matchText"   => $uri['matchText'],
                "relevance"   => $uri['relevance']
              );
          }

          $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
          if (count($_RESULTS)) {
            usort($_RESULTS, function($a, $b) {
              return ($a["relevance"] == $b["relevance"]) ? 0 : (($a["relevance"] > $b["relevance"]) ? -1 : 1);
            });
            $_RESULTS = array_slice($_RESULTS, 0, ($_VDATA['s.resultlimit']) ? $_VDATA['s.resultlimit'] : max(5, min(100, ceil($_SDATA['totalRows'] / 6))));
          }

          if ($_VDATA['s.cachelimit']) {
            $rStore = serialize($_RESULTS);
            $rStore = ($_VDATA['s.cachegzip'] == "on") ? gzcompress($rStore) : addslashes(stripslashes($rStore));
          } else $rStore = "";

          if (!$_SDATA['found']) {
            $insert = $_DDATA['link']->query("INSERT INTO `{$_DDATA['tablestat']}` SET
              `query`='{$_QUERY['sorted']}',
              `results`=".count($_RESULTS).",
              `astyped`='".addslashes($_QUERY['original'])."',
              `lasthit`=UNIX_TIMESTAMP(),
              `cache`='{$rStore}'
            ;");
          } else {
            $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablestat']}` SET
              `results`=".count($_RESULTS).",
              `hits`=`hits`+1,
              `lasthit`=UNIX_TIMESTAMP(),
              `cache`='{$rStore}',
              `astyped`='".addslashes($_QUERY['original'])."'
            WHERE `query`='{$_QUERY['sorted']}';");
          }

        } else {
          $_RESULTS = NULL;
          if ($_VDATA['sp.progress'] < time() - 60) OS_setData("sp.lock", "false");
        }

      } else {
        $_RESULTS = $_SDATA['count'][0]['cache'];
        $_RESULTS = unserialize(($_VDATA['s.cachegzip'] == "on") ? gzuncompress($_RESULTS) : stripslashes($_RESULTS));

        if (!isset($_REQUEST['start'])) {
          $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablestat']}` SET
            `hits`=`hits`+1,
            `lasthit`=UNIX_TIMESTAMP(),
            `astyped`='".addslashes($_QUERY['original'])."'
          WHERE `query`='{$_QUERY['sorted']}';");
        }
      }

      $_QUERY['category'] = $_REQUEST['c'];
      if ($_QUERY['category'] != "") {
        $_RESULTS = array_filter($_RESULTS, function($v) {
          global $_QUERY;
          return ($v['category'] != $_QUERY['category']) ? false : true;
        });
        $_RESULTS = array_values($_RESULTS);
      }
    }
  }

  /* ****************************************************************
  ******** Trigger Another Spider ******************************** */
  if ((!isset($_SERVER['HTTP_USER_AGENT']) || $_SERVER['HTTP_USER_AGENT'] != $_SDATA['userAgent']) &&
      $_VDATA['sp.lock'] == "false" &&
      $_VDATA['sp.cron'] == "false" &&
      $_VDATA['sp.interval'] &&
      $_VDATA['sp.time'] < (time() - $_VDATA['sp.interval'] * 3600)) {
    $rpage = new OS_Fetcher($_VDATA['sp.pathto']);
    $rpage->request = "HEAD";
    $rpage->accept = array("text/html", "application/xhtml+xml", "text/xml");
    $rpage->fetch();

    if (count(preg_grep("/^Orcascript: Search_Spider/", $rpage->headers))) {
      OS_setData("s.spkey", md5(time()));

      $stream_context = stream_context_create([
        'ssl' => [
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed' => true,
          'verify_depth' => 0
        ]
      ]);
      $timeout = ini_get("default_socket_timeout");
      $protocol = ($rpage->parsed['scheme'] == 'https') ? 'ssl' : 'tcp';

      // $conn2 = pfsockopen($rpage->parsed['realhost'], $rpage->parsed['port'], $erstr, $errno, 5);
      $conn2 = stream_socket_client("{$protocol}://{$rpage->parsed['host']}:{$rpage->parsed['port']}", $erstr, $errno, $timeout, STREAM_CLIENT_CONNECT, $stream_context);
      stream_set_blocking($conn2, false);

      @fwrite($conn2, "GET {$rpage->parsed['path']}?key={$_VDATA['s.spkey']} HTTP/1.0\r\nHost: {$rpage->parsed['hostport']}\r\nUser-Agent: {$_SDATA['userAgent']}\r\nReferer: {$_SDATA['scheme']}://{$_SERVER['HTTP_HOST']}{$_SERVER["REQUEST_URI"]}\r\n\r\n");
    }
  }
}
