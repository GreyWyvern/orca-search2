<?php /* ***** Orca Search - Javascript Writer Extension ********* */


$_SDATA['lang'] = true;
include "config.php";


/* ******************************************************************
******** Javascript File Write *********************************** */
header("Orcascript: Search_JWriter");

if ($_SERVER['REQUEST_METHOD'] != "GET" || !isset($_GET['key']) || $_GET['key'] != $_VDATA['jw.key']) {
  $_JDATA['error'][] = $_LANG['0o2'];

} else if (!isset($_GET['linkback'])) {
  $_JDATA['error'][] = $_LANG['0o3'];

} else {
  @set_time_limit(0);
  OS_setData("jw.key", "");

  $_JDATA['stripBaseURIs'] = array_filter(array_map("trim", explode("\n", $_VDATA['jw.remuri'])));
  $_JDATA['md5'] = md5(microtime());
  $_JDATA['indextable'] = array_shift($_DDATA['link']->query("SHOW TABLE STATUS LIKE '{$_DDATA['tablename']}';")->fetchAll());
  $_JDATA['isize'] = (int)($_JDATA['indextable']['Data_length'] / 1024);

  $_JDATA['entities'] = array(
    "&" => "&amp;",
    ">" => "&gt;",
    "<" => "&lt;",
    "\"" => "&quot;",
  );

  $lq = ($_VDATA['s.orphans'] == "show") ? " AND (`status`='OK' OR `status`='Orphan')" : " AND `status`='OK'";

  $nq = "";
  $sData['noSearch'] = array_filter(array_map("trim", explode("\n", $_VDATA['s.ignore'])));
  foreach ($sData['noSearch'] as $noSearch)
    $nq .= " AND `uri` NOT ".(($noSearch{0} == "*") ? "REGEXP '".substr($noSearch, 1)."'": " LIKE '%{$noSearch}%'");

  $date = date("r");
  $template = str_replace("\n", '\n', addslashes($_VDATA['jw.template']));


  /* ***** Language Strings for HEREDOC Insertion **************** */
  $_JLANG = array();
  $_JLANG['000'] = sprintf(addslashes($_LANG['0o4']), '" + os_start + "', '" + os_end + "', '" + os_matches.length + "', '" + os_keyring + "', $_JDATA['md5']);
  $_JLANG['001'] = addslashes($_LANG['0o5']);
  $_JLANG['002'] = addslashes($_LANG['01w']);
  $_JLANG['003'] = addslashes($_LANG['01x']);
  $_JLANG['004'] = sprintf(addslashes($_LANG['0o6']), '" + os_keyring + "');
  $_JLANG['005'] = sprintf(addslashes($_LANG['0o8']), '" + window.location.pathname + "?q=" + os_keyring + "');
  $_JLANG['006'] = addslashes($_LANG['0o9']);
  $_JLANG['007'] = addslashes($_LANG['0oa']);
  $_JLANG['008'] = addslashes($_LANG['0ob']);
  $_JLANG['009'] = addslashes($_LANG['01v']);


  $_EGG = <<<ORCA
/* ******************************************************************
* {$_SDATA['userAgent']}
*    - Offline Javascript File
*
* Generated $date
****************************************************************** */

/* ***** Begin Timing ******************************************** */
var os_mark = new Date();
var os_then = os_mark.getTime();


/* ***** User Options ******************************************** */
var os_maxmatches = 3;


/* ***** Entry Object Constructor ******************************** */
function os_entry(category, title, ctype, uri, description, keywords, wtags, text) {
  this.category = category;
  this.uri = uri;
  this.title = (title) ? title : uri;
  this.ctype = (ctype && ctype != "html" && ctype != "text") ? "[" + ctype + "]" : "";
  this.keywords = keywords;
  this.wtags = wtags;
  this.description = (description) ? description : text.substr(0, 200);
  this.text = text;
  this.matchtext = " [[[strong]]]...[[[/strong]]] ";
  this.relevance = 0;
  this.words = -1;
}


/* ***** Make data safe for replace() **************************** */
function rescape(encode) {
  if (encode) return this.replace(/\\$/, "&#36;").replace(/\{/, "&#123;").replace(/\}/, "&#125;");
  return this.replace(/&#36;/, "$").replace(/&#123;/, "{").replace(/&#125;/, "}");
}
String.prototype.rescape = rescape;


/* ***** Heapsort ************************************************ */
function heapsort() {
  if (this.length <= 1) return;
  this.unshift("");
  var ir = this.length - 1;
  var l = (ir >> 1) + 1;
  while (1) {
    if (l <= 1) {
      var rra = this[ir];
      this[ir] = this[1];
      if (--ir == 1) {
        this[1] = rra;
        this.shift();
        return this;
      }
    } else var rra = this[--l];
    var i = l;
    var j = l << 1;
    while (j <= ir) {
      if ((j < ir) && (this[j].relevance < this[j + 1].relevance)) j++;
      if (rra.relevance < this[j].relevance) {
        this[i] = this[j];
        j += (i = j);
      } else j = ir + 1;
    }
    this[i] = rra;
  }
}
Array.prototype.heapsort = heapsort;


/* ***** Number Format ******************************************* */
function numFormat(num, decimalNum, bolLeadingZero, bolParens) {
  var tmpNum = num;
  tmpNum *= Math.pow(10, decimalNum);
  tmpNum = Math.round(tmpNum);
  tmpNum /= Math.pow(10, decimalNum);
  var tmpStr = new String(tmpNum);
  if (!bolLeadingZero && num < 1 && num > -1 && num !=0)
    tmpStr = (num > 0) ? tmpStr.substring(1, tmpStr.length) : "-" + tmpStr.substring(2, tmpStr.length);
  if (bolParens && num < 0) tmpStr = "(" + tmpStr.substring(1, tmpStr.length) + ")";
  return tmpStr;
}


/* ***** Website Entry Database ********************************** */
var os_entries = [
ORCA;
}


/* ***** Begin Output ******************************************** */
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <title>JWriter - <?php echo $_LANG['0o0']; ?></title>
  <meta http-equiv="Content-type" content="text/html; charset=<?php echo $_VDATA['c.charset']; ?>;" />
  <style type="text/css">
body { background-color:#ffffff; font:normal 100% sans-serif; }
body .warning { color:#ff0000; background-color:transparent; }
body form#canceller { margin:0px; }
body form#canceller h1 { display:inline; }
body form#canceller input { margin-left:30px; vertical-align:top; }
body h1 { margin:3px 0px; font:bold 130% sans-serif; }
body h2 { margin:2px 0px; font:normal 100% sans-serif; }
body p { position:absolute; top:0px; left:65%; color:#000000; background-color:#ffffff; font:normal 250% sans-serif; }
body a#goback { display:block; text-align:center; font:bold 125% sans-serif; border:4px groove #dddddd; background-color:#eeeeee; }
  </style>
</head>
<body>
  <?php if (isset($_GET['linkback'])) { ?> 
    <form action="<?php echo $_GET['linkback']; ?>" method="post" id="canceller">
      <h1><?php echo $_LANG['0o1']; ?></h1>
      <input type="submit" name="jwriter_Cancel" value="<?php echo $_LANG['01s']; ?>" />
    </form>
  <?php } else { ?> 
    <h1><?php echo $_LANG['0o1']; ?></h1>
  <?php } ?> 

  <?php if (isset($_JDATA['error'])) { ?>
    <ul class="warning">
      <?php foreach ($_JDATA['error'] as $error) { ?> 
        <li><?php echo $_LANG['030'], ": ", $error; ?></li>
      <?php } ?> 
    </ul>

  <?php } else { ?> 
    <h2><?php echo $_LANG['0o7']; ?></h2><?php

    function trimmer($_) {
      return trim($_, " .,;:?&[]{}()/\\-_=+*^%$#@!`~<>|\"'");
    }

    $select = $_DDATA['link']->query("SELECT `body` FROM `{$_DDATA['tablename']}` WHERE `unlist`!='true'{$lq}{$nq};")->fetchAll();
    $rowcount = count($select);
    $_ALL = array();
    $duncount = 0;
    $pcent = 0;
    foreach ($select as $row) {
      if ($row['body'] = trim($row['body'])) {
        $row['body'] = str_replace(array("\n", "\r", "\t", "  "), " ", $row['body']);
        $row['body'] = array_values(array_unique(array_filter(explode(" ", $row['body']))));
        $row['body'] = array_map("trimmer", $row['body']);
        foreach ($row['body'] as $word) {
          $word = strtolower($word);
          if (isset($_ALL[$word])) {
            $_ALL[$word]++;
          } else $_ALL[$word] = 0;
        }
      }
      if ((int)(($duncount++ / $rowcount) * 30) > $pcent) {
        $pcent = (int)(($duncount / $rowcount) * 30);
        echo "<p>", $pcent, "%</p>\n";
        flush();
      }
    }


    $wl = floor($rowcount / 3);
    foreach($_ALL as $key => $value) {
      if ($value <= $wl) unset($_ALL[$key]);
    }
    $_IGNORED = array_keys($_ALL);

    function caseCheck($_) {
      global $_IGNORED;
      return (in_array(strtolower($_), $_IGNORED)) ? 0 : 1;
    }

    $select = $_DDATA['link']->query("SELECT `uri`, `title`, `category`, `ctype`, `description`, `keywords`, `wtags`, `body` FROM `{$_DDATA['tablename']}` WHERE `unlist`!='true'{$lq}{$nq};")->fetchAll();
    $duncount = 0;
    foreach ($select as $row) {
      if ($row['body'] = trim($row['body'])) {
        $row['body'] = str_replace(array("\n", "\r", "\t", "  "), " ", $row['body']);
        $row['body'] = array_values(array_unique(array_filter(explode(" ", $row['body']))));
        $row['body'] = array_map("trimmer", $row['body']);
        $row['body'] = implode(" ", array_filter($row['body'], "caseCheck"));
      }

      foreach ($_JDATA['stripBaseURIs'] as $stripBaseURIs)
        $row['uri'] = preg_replace("/^".preg_quote($stripBaseURIs, "/")."/i", "", $row['uri']);
      if ($row['uri'] == "" || $row['uri']{strlen($row['uri']) - 1} == "/") $row['uri'] .= $_VDATA['jw.index'];
      if ($row['ctype'] && $row['ctype'] != "none") {
        $row['uri'] = preg_replace("/\.\w{1,5}($|\?)/", ".{$row['ctype']}$1", $row['uri']);
        $row['uri'] = preg_replace("/(^|\/)([^.]*?)($|\?)/i", "$1$2.{$row['ctype']}$3", $row['uri']);
      }
      $row['title'] = strtr($row['title'], $_JDATA['entities']);
      $row['description'] = strtr($row['description'], $_JDATA['entities']);

      $row = array_map("addslashes", $row);
      $_EGG .= (($duncount++ > 0) ? "," : "")."\n\tnew os_entry('{$row['category']}', '{$row['title']}', '{$row['ctype']}', '{$row['uri']}', '{$row['description']}', '{$row['keywords']}', '{$row['wtags']}', '{$row['body']}')";

      if ((int)(($duncount / $rowcount) * 70 + 30) > $pcent) {
        $pcent = (int)(($duncount / $rowcount) * 70 + 30);
        echo "<p>", $pcent, "%</p>\n";
        flush();
      }
    }

    $_EGG .= <<<ORCA

];


/* ***** Variable Migration from PHP ***************************** */
var os_resultlimit = {$_VDATA['s.resultlimit']};
var os_termlimit = {$_VDATA['s.termlimit']};
var os_termlength = {$_VDATA['s.termlength']};
var os_weighttitle = {$_VDATA['s.weight'][0]};
var os_weightbody = {$_VDATA['s.weight'][1]};
var os_weightkeywords = {$_VDATA['s.weight'][2]};
var os_weightwtags = {$_VDATA['s.weight'][3]};
var os_bonusmulti = {$_VDATA['s.weight'][5]};
var os_bonusimportant = {$_VDATA['s.weight'][6]};
var os_matchlimit = {$_VDATA['s.matchingtext']};
var os_template = "$template";
var os_resultspp = {$_VDATA['jw.pagination']};


/* ***** Compile Category List *********************************** */
for (var x = 0, os_categories = []; x < os_entries.length; x++) {
  for (var y = 0, found = false; y < os_categories.length; y++) if (os_entries[x].category == os_categories[y]) found = true;
  if (!found) os_categories[os_categories.length] = os_entries[x].category;
}


/* ***** Parse the Query String ********************************** */
var os_query = window.location.search.substr(1).replace(/\+/g, " ");
var os_qbits = os_query.split("&");

for (var x = 0, os_keyring = [], os_category = "", os_start = 1, os_end = 0; x < os_qbits.length; x++) {
  os_qbit = os_qbits[x].split("=");
  for (var y = 0; y < os_qbit.length; y++) os_qbit[y] = unescape(os_qbit[y]);
  if (os_qbit[0] == "q") os_keyring = os_qbit[1].replace(/(^\s+|\s+$)/g, "").replace(/\s{2,}/g, " ");
  if (os_qbit[0] == "c") os_category = os_qbit[1];
  if (os_qbit[0] == "start") os_start = Number(os_qbit[1]);
}
if (os_category == "" || os_categories.length < 2) os_category = "";


/* ***** Begin Output ******************************************* */
document.write("<div id=\"os_main\"></div>");
var os_xhtml = "";

if (os_keyring.length > 0) {

  /* ***** Search Entries *************************************** */
  var os_keys = os_keyring.toLowerCase().split(" ").slice(0, os_termlimit - 1);

  // Filter the entry list of negative and important matches
  for (var x = 0, os_keys2 = [], os_ignored = []; x < os_keys.length; x++) {
    if (os_keys[x].substr(0, 1) == "!" || os_keys[x].substr(0, 1) == "-") {
      os_keys[x] = os_keys[x].substr(1).replace(/["']/g, "");
      for (var y = 0, os_entries2 = []; y < os_entries.length; y++) {
        if (os_entries[y].title.toLowerCase().indexOf(os_keys[x]) == -1 &&
            os_entries[y].text.toLowerCase().indexOf(os_keys[x]) == -1 &&
            os_entries[y].keywords.toLowerCase().indexOf(os_keys[x]) == -1 &&
            os_entries[y].wtags.toLowerCase().indexOf(os_keys[x]) == -1)
          os_entries2[os_entries2.length] = os_entries[y];
      }
      os_entries = os_entries2;
    } else if (os_keys[x].substr(0, 1) == "+") {
      os_keys2[os_keys2.length] = os_keys[x].replace(/["']/g, "");
      for (var y = 0, os_entries2 = []; y < os_entries.length; y++) {
        if (os_entries[y].title.toLowerCase().indexOf(os_keys[x]) != -1 ||
            os_entries[y].text.toLowerCase().indexOf(os_keys[x]) != -1 ||
            os_entries[y].keywords.toLowerCase().indexOf(os_keys[x]) != -1 ||
            os_entries[y].wtags.toLowerCase().indexOf(os_keys[x]) != -1)
          os_entries2[os_entries2.length] = os_entries[y];
      }
      os_entries = os_entries2;
    } else if (os_keys[x].replace(/["']/g, "").length >= os_termlength) {
      os_keys2[os_keys2.length] = os_keys[x].replace(/["']/g, "");
    } else os_ignored[os_ignored.length] = os_keys[x].replace(/["']/g, "");
  }
  os_keys = os_keys2;

  // Filter the entry list of excluded categories
  if (os_category != "") {
    for (var y = 0, os_entries2 = []; y < os_entries.length; y++)
      if (os_entries[y].category == os_category) os_entries2[os_entries2.length] = os_entries[y];
    os_entries = os_entries2;
  }

  // Search the entries for items from the query string and apply relevance values
  for (var y = 0; y < os_entries.length; y++) {
    for (var x = 0; x < os_keys.length; x++) {
      var os_relevance = os_entries[y].relevance;
      if (os_keys[x].substr(0, 1) == "+") {
        os_importance = os_bonusimportant;
        os_keys[x] = os_keys[x].substr(1);
      } else os_importance = 1;
      var os_titlesplit = os_entries[y].title.toLowerCase().split(os_keys[x]);
      os_entries[y].relevance += os_weighttitle * Math.min(os_maxmatches, os_titlesplit.length - 1) * os_importance;
      if (os_titlesplit.length > 1) {
        for (var z = 0, os_tdist = 0, os_title = ""; z < os_titlesplit.length - 1; z++) {
          os_title += os_entries[y].title.substr(os_tdist, os_titlesplit[z].length);
          os_tdist += os_titlesplit[z].length;
          os_title += "<strong>" + os_entries[y].title.substr(os_tdist, os_keys[x].length) + "</strong>";
          os_tdist += os_keys[x].length;
        }
        os_title += os_entries[y].title.substr(os_tdist);
        os_entries[y].title = os_title;
      }

      os_entries[y].relevance += os_weightkeywords * Math.min(os_maxmatches, os_entries[y].keywords.toLowerCase().split(os_keys[x]).length - 1) * os_importance;
      os_entries[y].relevance += os_weightwtags * Math.min(os_maxmatches, os_entries[y].wtags.toLowerCase().split(os_keys[x]).length - 1) * os_importance;

      var os_bodysplit = os_entries[y].text.toLowerCase().split(os_keys[x]);
      os_entries[y].relevance += os_weightbody * Math.min(os_maxmatches, os_bodysplit.length - 1) * os_importance;
      if (os_bodysplit.length > 1 && os_entries[y].matchtext.length < os_matchlimit) {
        var os_term = os_entries[y].text.substr(os_bodysplit[0].length, os_keys[x].length);
        var os_matchtext = os_entries[y].text.substr(Math.max(0, os_bodysplit[0].length - 80), Math.min(os_bodysplit[0].length, 80));
        os_matchtext += "[[[strong]]]" + os_term + "[[[/strong]]]";
        os_matchtext += os_entries[y].text.substr(os_bodysplit[0].length + os_keys[x].length, os_keys[x].length + 80);
        os_entries[y].matchtext += os_matchtext + " [[[strong]]]...[[[/strong]]] ";
      }
      if (os_relevance != os_entries[y].relevance) os_entries[y].words++;
    }
    os_entries[y].relevance *= Math.pow(os_bonusmulti, os_entries[y].words);
    if (os_entries[y].matchtext != " [[[strong]]]...[[[/strong]]] ") {
      os_entries[y].matchtext = os_entries[y].matchtext.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\[\[\[strong\]\]\]/g, "<strong>").replace(/\[\[\[\/strong\]\]\]/g, "</strong>");
    } else os_entries[y].matchtext = os_entries[y].description;
  }

  // Sort the entries
  if (os_entries.length > 1)
    var os_entries = os_entries.heapsort().reverse();

  // Remove entries below the relevance threshold
  for (var x = 0, os_matches = []; x < os_entries.length; x++)
    if (os_entries[x].relevance > 0) os_matches[os_matches.length] = os_entries[x];
  if (os_matches.length > os_resultlimit) os_matches = os_matches.slice(0, os_resultlimit - 1);


  /* ***** Compile Output **************************************** */
  if (os_matches.length) {
    // Find start and end values for this range of matches
    os_start = (os_matches.length <= os_resultspp) ? 1 : os_start;
    os_end = Math.min(os_start + os_resultspp - 1, os_matches.length);


    // Draw the upper result information bar
    os_xhtml += "  <p id=\"os_resultbar\">";
    var os_mark = new Date();
    os_xhtml += "    {$_JLANG['000']}";
    os_xhtml += "  </p>";

    // Mention Ignored terms
    if (os_ignored.length) os_xhtml += "    <p class=\"os_msg\">{$_JLANG['001']}: <strong>" + os_ignored.join(" ") + "</strong>";

    // Write results
    os_xhtml += "  <ol id=\"os_results\" start=\"" + os_start + "\">";
    for (var x = os_start - 1; x < os_end; x++) {
      var os_templatex = os_template.replace(/\{R_NUMBER\}/g, x + 1).replace(/\{R_RELEVANCE\}/g, numFormat(os_matches[x].relevance, 1, true, false));
      os_templatex = os_templatex.replace(/\{R_FILETYPE\}/g, os_matches[x].ctype).replace(/\{R_URI\}/g, os_matches[x].uri.rescape(true));
      os_templatex = os_templatex.replace(/\{R_CATEGORY\}/g, os_matches[x].category.rescape(true)).replace(/\{R_TITLE\}/g, os_matches[x].title.rescape(true));
      os_templatex = os_templatex.replace(/\{R_DESCRIPTION\}/g, os_matches[x].description.rescape(true)).replace(/\{R_MATCH\}/g, os_matches[x].matchtext.rescape(true));
      os_xhtml += "  <li>";
      os_xhtml += "      " + os_templatex.rescape(false);
      os_xhtml += "    </li>";
    }
    os_xhtml += "  </ol>";

    // Pagination
    if (os_matches.length > os_resultspp) {
      var os_common = unescape(window.location.search).substr(1).replace(/&start=\d+/i, "");
      os_xhtml += "  <div id=\"os_pagination\">";
      os_xhtml += "    <div id=\"os_pagin1\">";
      if (os_start > 1) {
        var os_prev = Math.max(1, os_start - os_resultspp);
        os_xhtml += "      <a href=\"?" + os_common + "&start=" + os_prev + "\" title=\"{$_JLANG['002']}\">&lt;&lt; {$_JLANG['002']}</a>";
      } else os_xhtml += "      &nbsp;";
      os_xhtml += "    </div>";
      os_xhtml += "    <div id=\"os_pagin3\">";
      if (os_end < os_matches.length) {
        var os_next = os_end + 1;
        os_xhtml += "      <a href=\"?" + os_common + "&start=" + os_next + "\" title=\"{$_JLANG['003']}\">{$_JLANG['003']} &gt;&gt;</a>";
      } else os_xhtml += "      &nbsp;";
      os_xhtml += "    </div>";
      os_xhtml += "    <div id=\"os_pagin2\">";
      var pagemax = Math.ceil(os_matches.length / os_resultspp);
      for (var x = 1; x <= pagemax; x++) {
        var os_list = (x - 1) * os_resultspp + 1;
        if (os_list == os_start) {
          os_xhtml += "      <strong>" + x + "</strong>";
        } else {
          var os_title = os_list + " - " + Math.min(os_list + os_resultspp - 1, os_matches.length);
          os_xhtml += "      <a href=\"?" + os_common + "&start=" + os_list + "\" title=\"" + os_title + "\">" + x + "</a>";
        }
      }
      os_xhtml += "    </div>";
      os_xhtml += "  </div>";
    }

  } else {
    os_xhtml += "  <p id=\"os_resultbar\">&nbsp;</p>";
    os_xhtml += "  <p class=\"os_msg\">{$_JLANG['004']}";
    if (os_ignored.length) os_xhtml += "    <br />{$_JLANG['001']}: <strong>" + os_ignored.join(" ") + "</strong>";
    if (os_category != "") os_xhtml += "    <br /><br />{$_JLANG['005']}";
    os_xhtml += "    </p>";
  }

} else {
  os_xhtml += "  <p id=\"os_resultbar\">&nbsp;</p>";
  os_xhtml += "  <p class=\"os_msg\">{$_JLANG['006']}</p>";
}


/* ***** Search Form ********************************************* */
os_xhtml += "  <form action=\"" + window.location.pathname + "\" method=\"get\" id=\"os_search\">";
os_xhtml += "    <div>";
os_xhtml += "      <input type=\"text\" name=\"q\" value=\"" + os_keyring + "\" />";
if (os_categories.length > 1) {
  os_xhtml += "        <label>";
  os_xhtml += "          &nbsp; {$_JLANG['007']}";
  os_xhtml += "          <select name=\"c\" size=\"1\">";
  os_xhtml += "            <option value=\"\">{$_JLANG['008']}</option>";
  for (var x = 0; x < os_categories.length; x++)
    os_xhtml += "            <option value=\"" + os_categories[x] + "\"" + ((os_category == os_categories[x]) ? " selected=\"selected\"" : "") + ">" + os_categories[x] + "</option>";
  os_xhtml += "          </select>";
  os_xhtml += "        </label>";
}
os_xhtml += "      <input type=\"submit\" value=\"{$_JLANG['009']}\" />";
os_xhtml += "    </div>";
os_xhtml += "  </form>";


/* ***** Tag Line ************************************************ */
os_xhtml += "  <div style=\"text-align:center;font:italic 80% Arial,sans-serif;\">";
os_xhtml += "    <hr style=\"width:60%;margin:10px auto 2px auto;\" />";
os_xhtml += "    An <strong>Orca</strong> Script";
os_xhtml += "  </div>";

os_xhtml += "</div>";


/* ***** Write to Page ******************************************* */
var os_mark = new Date();
var os_marked = (os_mark.getTime() - os_then) / 1000;
os_xhtml = os_xhtml.replace(/{$_JDATA['md5']}/, numFormat(os_marked, 2, true, false));

document.getElementById('os_main').innerHTML = os_xhtml;

ORCA;

    $shell = fopen($_VDATA['jw.egg'], "w");
    fwrite($shell, $_EGG);
    fclose($shell);

    ?><style type="text/css">form#canceller input { display:none; }</style>
    <p>100%</p>
    <h1><?php echo $_LANG['0oc']; ?></h1>

    <ul>
      <li><?php printf($_LANG['0od'], sprintf("%01.2f", array_sum(explode(" ", microtime())) - $_SDATA['now'])); ?></li>
      <li><?php printf($_LANG['0oe'], $_JDATA['isize'], (100 - (int)((int)(strlen($_EGG) / 1024) / $_JDATA['isize'] * 100)), (int)(strlen($_EGG) / 1024)); ?></li>
    </ul>

    <a id="goback" href="<?php echo $_GET['linkback']; ?>"><?php echo $_LANG['0of']; ?></a>
  <?php } ?> 


</body>
</html>