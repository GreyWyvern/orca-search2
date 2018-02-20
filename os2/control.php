<?php /* ***** Orca Search - Control Panel *********************** */


require "config.php";


/* ******************************************************************
******** Functions *********************************************** */
function OS_countUp($time) {
  global $_LANG;
  static $ctr = 0;
  $ctr++;

  $since = time() - $time;
  $days = floor($since / 86400); $since %= 86400;
  $hours = floor($since / 3600); $since %= 3600;
  $minutes = floor($since / 60);
  $seconds = $since % 60; ?> 
  <span id="days<?php echo $ctr; ?>"><?php echo $days; ?></span> <?php echo $_LANG['014']; ?>,
  <span id="hours<?php echo $ctr; ?>"><?php printf("%02s", $hours); ?></span> <?php echo $_LANG['015']; ?>,
  <span id="minutes<?php echo $ctr; ?>"><?php printf("%02s", $minutes); ?></span> <?php echo $_LANG['016']; ?>,
  <span id="seconds<?php echo $ctr; ?>"><?php printf("%02s", $seconds); ?></span> <?php echo $_LANG['017'], " ", $_LANG['018']; ?> 
  <script type="text/javascript"><?php
    if ($ctr == 1) { ?> 
      function incrTime(y) {
        if (++atime[y][3] > 59) {
          atime[y][3] = 0;
          atime[y][2]++;
        }
        if (atime[y][2] > 59) {
          atime[y][2] = 0;
          atime[y][1]++;
        }
        if (atime[y][1] > 23) {
          atime[y][1] = 0;
          atime[y][0]++;
        }
        for (var x = 0; x < atype.length; x++)
          document.getElementById(atype[x] + y).firstChild.nodeValue = ((atime[y][x] < 10 && x != 0) ? "0" : "") + atime[y][x];
      }
      var atime = [];
      var atype = ["days", "hours", "minutes", "seconds"];<?php
    } ?> 
    atime[<?php echo $ctr; ?>] = ["<?php echo $days; ?>", "<?php echo $hours; ?>", "<?php echo $minutes; ?>", "<?php echo $seconds; ?>"];
    setInterval("incrTime(<?php echo $ctr; ?>);", 1000);
  </script><?php
}


/* ******************************************************************
******** Setup *************************************************** */
header("Orcascript: Search_ControlPanel");

$_CDATA['loggedIn'] = false;
$_CDATA['command'] = "";
$_CDATA['cookietime'] = 5400; // 1.5hrs


/* ******************************************************************
******** Handle Language Bits ************************************ */
$_LANG['langcf'] = array(
  "always"  => $_LANG['019'],
  "hourly"  => $_LANG['01a'],
  "daily"   => $_LANG['01b'],
  "weekly"  => $_LANG['01c'],
  "monthly" => $_LANG['01d'],
  "yearly"  => $_LANG['01e'],
  "never"   => $_LANG['01f']
);
$_LANG['langst'] = array(
  "Not Found" => $_LANG['01g'],
  "Blocked"   => $_LANG['01h'],
  "Unlisted"  => $_LANG['01i'],
  "OK"        => $_LANG['01j'],
  "Orphan"    => $_LANG['01k'],
  "Added"     => $_LANG['01n']
);


/* ******************************************************************
******** Login & Verification ************************************ */
if ($_DDATA['online']) {
  if (isset($_COOKIE['osc_cp'])) {
    $login = explode("::", base64_decode($_COOKIE['osc_cp']));
    if ($login[0] == $_SDATA['adminName']) {
      if ($login[1] == $_VDATA['c.logkey']) {
        setcookie("osc_cp", $_COOKIE['osc_cp'], time() + $_CDATA['cookietime']);
        OS_setData("c.logtime", time());
        $_CDATA['loggedIn'] = true;
      } else {
        setcookie("osc_cp", "", time() - 86400);
        $_ERROR['error'][] = $_LANG['031'];
      }
    } else {
      setcookie("osc_cp", "", time() - 86400);
      $_ERROR['error'][] = $_LANG['032'];
    }
  } else if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (isset($_POST['loginName']) && isset($_POST['loginPass'])) {
      if ($_POST['loginName'] == $_SDATA['adminName'] && $_POST['loginPass'] == $_SDATA['adminPass']) {
        if ($_VDATA['c.logtime'] < time() - 180) {
          OS_setData("c.logkey", $key = md5(time()));
          setcookie("osc_cp", base64_encode(implode("::", array($_SDATA['adminName'], $key))), time() + $_CDATA['cookietime']);
          OS_setData("c.logtime", time());
          $_CDATA['loggedIn'] = true;
        } else $_ERROR['error'][] = sprintf($_LANG['033'], time() - $_VDATA['c.logtime']);
      }
    }
  }
}
if ($_CDATA['loggedIn']) {
  if ($_SERVER['REQUEST_METHOD'] == "GET" && isset($_SERVER['QUERY_STRING'])) {
    if (strpos($_SERVER['QUERY_STRING'], "=") === false) {
      $_CDATA['command'] = "location";
      $_CDATA['location'] = $_SERVER['QUERY_STRING'];
    } else {
    }
  } else if ($_SERVER['REQUEST_METHOD'] == "POST") {
    reset($_POST);
    while (list($key, $value) = each($_POST)) {
      if (strpos($key, "_")) {
        $command = explode("_", $key);
        $_CDATA['command'] = $command[0];
        $_CDATA[$command[0]] = $command[1];
        break;
      }
    }
  }
}


/* ******************************************************************
******** Command Execution *************************************** */
if ($_DDATA['online'] && $_CDATA['loggedIn']) {
  $_SDATA['noSearchSQL'] = array_filter(array_map("trim", explode("\n", $_VDATA['s.ignore'])));
  $_SDATA['noSearch'] = array_filter(array_map("OS_pquote", explode("\n", $_VDATA['s.ignore'])));

  switch ($_CDATA['command']) {
    /* *********************************************************** */
    case "location":
      switch ($_CDATA['location']) {
        case "Timeout": /* *************************************** */
          $_ERROR['error'][] = $_LANG['03n'];

        case "Logout":
          setcookie("osc_cp", "", time() - 86400);
          OS_setData("c.logtime", time() - 86400);
          $_CDATA['loggedIn'] = false;
          break;

        case "Search": /* **************************************** */
        case "List":
        case "Spider":
        case "Stats":
        case "Tools":
          OS_setData("c.location", $_CDATA['location']);
          break;

        case "Dump": /* ****************************************** */
          $_VDATA['c.location'] = "Dump";
          break;
      }
      break;


    /* *********************************************************** */
    case "filter":
      OS_setData("c.location", "List");
      $_GET['start'] = 0;
      switch ($_CDATA['filter']) {
        case "Clear": /* ***************************************** */
          OS_setData("cf.textexclude", "");
          OS_setData("cf.textmatch", "");
          OS_setData("cf.category", "-");
          OS_setData("cf.status", "All");
          OS_setData("cf.new", "false");
          break;

        case "Set": /* ******************************************* */
          OS_setData("cf.textexclude", $_POST['textexclude']);
          OS_setData("cf.textmatch", $_POST['textmatch']);
          OS_setData("cf.category", (isset($_POST['category'])) ? $_POST['category'] : "-");
          OS_setData("cf.status", $_POST['status']);
          OS_setData("cf.new", (isset($_POST['new'])) ? "true" : "false");
          break;
      }
      break;


    /* *********************************************************** */
    case "action":
      OS_setData("c.location", "List");
      $_CDATA['input'] = $_POST[$_CDATA['action']];
      if (!isset($_POST['action'])) $_POST['action'] = array();
      if (isset($_POST['actionIDs'])) $_POST['action'] = explode("::", $_POST['actionIDs']);

      if (count($_POST['action'])) {
        switch ($_CDATA['input']) {
          case "delete": /* **************************************** */
            foreach ($_POST['action'] as $action)
              $delete = $_DDATA['link']->query("DELETE FROM `{$_DDATA['tablename']}` WHERE `md5`='{$action}';");
            OS_clearCache();
            $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "unlist": /* **************************************** */
            foreach ($_POST['action'] as $action)
              $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `unlist`=(CASE `unlist` WHEN 'true' THEN 'false' WHEN 'false' THEN 'true' END) WHERE `md5`='{$action}';");
            OS_clearCache();
            $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "category": /* ************************************** */
            break;

          case "lock": /* ****************************************** */
            foreach ($_POST['action'] as $action)
              $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `locked`=(CASE `locked` WHEN 'true' THEN 'false' WHEN 'false' THEN 'true' END) WHERE `md5`='{$action}';");
            $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "respider": /* ************************************** */
            $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablevars']}` SET `sp.reindex`='".implode(" ", $_POST['action'])."';");
            OS_setData("s.spkey", md5(time()));
            header("Location: {$_VDATA['sp.pathto']}?key={$_VDATA['s.spkey']}&reindex=yes&linkback=".rawurlencode($_SERVER['PHP_SELF']));
            exit();
            break;

          case "sm.unlist": /* ************************************* */
            if ($_VDATA['sm.enable'] == "true") {
              foreach ($_POST['action'] as $action)
                $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `sm.list`=(CASE `sm.list` WHEN 'true' THEN 'false' WHEN 'false' THEN 'true' END) WHERE `md5`='{$action}';");
              OS_clearCache();
            } else $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "sm.changefreq": /* ********************************* */
            if ($_VDATA['sm.enable'] != "true" || $_VDATA['sm.changefreq'] == "true") $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "sm.priority": /* *********************************** */
            if ($_VDATA['sm.enable'] != "true") $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "smcConfirm": /* ************************************ */
            if ($_VDATA['sm.enable'] == "true" && $_VDATA['sm.changefreq'] != "true" && array_key_exists($_POST['changefreq'], $_LANG['langcf']))
              foreach ($_POST['action'] as $action)
                $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `sm.changefreq`='{$_POST['changefreq']}' WHERE `md5`='$action';");
            $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "smpConfirm": /* ************************************ */
            if ($_VDATA['sm.enable'] == "true") {
              $_POST['priority'] = (float)$_POST['priority'];
              foreach ($_POST['action'] as $action)
                $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `sm.priority`='{$_POST['priority']}' WHERE `md5`='$action';");
            }
            $_CDATA['command'] = $_CDATA['input'] = "";
            break;

          case "catConfirm": /* ************************************ */
            $_CDATA['row'] = array();
            $_CDATA['categories'] = array();
            $select = $_DDATA['link']->query("SELECT DISTINCT `category` FROM `{$_DDATA['tablename']}`;")->fetchAll();
            foreach ($select as $row) $_CDATA['categories'][] = $row['category'];
            if ($_VDATA['cf.category'] != "-" && !in_array($_VDATA['cf.category'], $_CDATA['categories'])) $_CDATA['categories'][] = $_VDATA['cf.category'];

            if ($_POST['categoryExist'] == "-") {
              if (!trim($_POST['categoryNew'])) {
                $_CDATA['row']['category'] = "-";
                $_ERROR['error'][] = $_LANG['035'];
              } else $_CDATA['row']['category'] = trim($_POST['categoryNew']);
            } else if (in_array(trim($_POST['categoryExist']), $_CDATA['categories'])) {
              $_CDATA['row']['category'] = trim($_POST['categoryExist']);
            } else $_ERROR['error'] = $_LANG['036'];

            if (!isset($_ERROR)) {
              foreach ($_POST['action'] as $action)
                $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET `category`='{$_CDATA['row']['category']}' WHERE `md5`='$action';");
              OS_clearCache();
              $_CDATA['command'] = $_CDATA['input'] = "";
            } else $_CDATA['input'] = "category";
            break;

          default: /* ********************************************** */
            $_CDATA['command'] = $_CDATA['input'] = "";

        }
      } else $_CDATA['command'] = $_CDATA['input'] = "";
      break;


    /* *********************************************************** */
    case "show":
      OS_setData("c.location", "List");
      if (OS_setData("c.pagination", max(10, min(999, (int)trim($_POST['show'.$_CDATA['show']]))))) $_GET['start'] = 0;
      break;


    /* *********************************************************** */
    case "add":
      OS_setData("c.location", "List");
      $_CDATA['row'] = array();
      if ($_CDATA['add'] == "Confirm") {
        $_CDATA['row']['uri'] = trim($_POST['uri']);
        $_CDATA['row']['title'] = trim($_POST['title']);

        $_CDATA['categories'] = array();
        $select = $_DDATA['link']->query("SELECT DISTINCT `category` FROM `{$_DDATA['tablename']}`;")->fetchAll();
        foreach ($select as $row) $_CDATA['categories'][] = $row['category'];
        if ($_VDATA['cf.category'] != "-" && !in_array($_VDATA['cf.category'], $_CDATA['categories'])) $_CDATA['categories'][] = $_VDATA['cf.category'];

        if ($_POST['categoryExist'] == "-") {
          $_CDATA['row']['category'] = trim($_POST['categoryNew']);
          if (!$_CDATA['row']['category']) $_ERROR['error'][] = $_LANG['035'];
        } else if (in_array(trim($_POST['categoryExist']), $_CDATA['categories'])) {
          $_CDATA['row']['category'] = trim($_POST['categoryExist']);
        } else $_ERROR['error'] = $_LANG['036'];

        $_CDATA['row']['description'] = str_replace(array("\n", "\r"), " ", trim($_POST['description']));
        $_CDATA['row']['keywords'] = str_replace(array("\n", "\r"), " ", trim($_POST['keywords']));
        $_CDATA['row']['unlist'] = (isset($_POST['unlist']) && $_POST['unlist'] == "true") ? "true" : "false";
        $_CDATA['row']['locked'] = (isset($_POST['locked']) && $_POST['locked'] == "true") ? "true" : "false";

        $_CDATA['row']['sm.list'] = (isset($_POST['smlist']) && $_POST['smlist'] == "true") ? "true" : "false";
        $_CDATA['row']['sm.changefreq'] = (isset($_POST['changefreq']) && array_key_exists($_POST['changefreq'], $_LANG['langcf'])) ? $_POST['changefreq'] : "weekly";
        $_CDATA['row']['sm.priority'] = (isset($_POST['priority'])) ? max(0, min(1, (float)$_POST['priority'])) : "0.5";

        $result = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `uri`='".addslashes($_POST['uri'])."';")->fetchAll(PDO::FETCH_NUM);
        list($count) = array_shift($result);

        if ($count) {
          $_ERROR['error'][] = $_LANG['03b'];

        } else {
          $apage = new OS_Fetcher($_CDATA['row']['uri']);
          $apage->request = "HEAD";
          $apage->accept = $_MIME->get_mtypes();
          $apage->fetch();

          $_CDATA['row']['mimetype'] = $apage->mimetype;
          $_CDATA['row']['charset'] = $apage->charset;

          switch ($apage->status) {
            case 6: // ***** No Socket
              $_ERROR['error'][] = sprintf($_LANG['039'], $apage->parsed['host']);
              break;

            case 5: // ***** Invalid URI
              $_ERROR['error'][] = $_LANG['03a'];
              break;

            case 4: // ***** Not Found
              $_ERROR['error'][] = sprintf($_LANG['037'], $apage->httpcode, $apage->uri);
              break;

            case 3: // ***** Blocked
              if ($apage->redirect) {
                $_ERROR['error'][] = sprintf($_LANG['038'], $apage->uri, $apage->redirect);
                $_CDATA['row']['uri'] = $apage->redirect;
              } else if (!$apage->accepted) {
                $_ERROR['error'][] = sprintf($_LANG['0g8'], $apage->mimetype);
              } else $_ERROR['error'][] = $_LANG['0g9'];
              break;

            case 2: // ***** Timed Out
              $_ERROR['error'][] = sprintf($_LANG['0qb'], $apage->uri);
              break;

            case 1: // ***** Unmodified
              break;

            case 0: // ***** OK
              $_CDATA['row'] = array_map("stripslashes", $_CDATA['row']);
              $_CDATA['row'] = array_map("addslashes", $_CDATA['row']);

              $insert = $_DDATA['link']->query("INSERT INTO `{$_DDATA['tablename']}` VALUES (
                '{$_CDATA['row']['uri']}',
                '".md5($_CDATA['row']['uri'])."',
                '{$_CDATA['row']['mimetype']}',
                '{$_CDATA['row']['title']}',
                '{$_CDATA['row']['category']}',
                '{$_CDATA['row']['description']}',
                '{$_CDATA['row']['keywords']}',
                '',
                '',
                '',
                '{$_CDATA['row']['charset']}',
                'Added',
                '{$_CDATA['row']['unlist']}',
                'true',
                '{$_CDATA['row']['locked']}',
                '{$_CDATA['row']['sm.list']}',
                UNIX_TIMESTAMP(),
                '{$_CDATA['row']['sm.changefreq']}',
                '{$_CDATA['row']['sm.priority']}'
              );");
              if ($insert->rowCount()) {
                OS_clearCache();
                $_ERROR['success'][] = sprintf($_LANG['060'], stripslashes($_CDATA['row']['uri']));
                $_CDATA['command'] = "";
              } else $_ERROR['error'][] = sprintf($_LANG['03c'], stripslashes($_CDATA['row']['uri']));

              unset($_CDATA['add']);
          }
        }
        if (isset($_ERROR['error'])) $_CDATA['add'] = "Again";

      } else {
        $_CDATA['row']['uri'] = $_SDATA['scheme']."://";
        $_CDATA['row']['title'] = "";
        $_CDATA['row']['category'] = $_VDATA['sp.defcat'];
        $_CDATA['row']['description'] = "";
        $_CDATA['row']['keywords'] = "";
        $_CDATA['row']['unlist'] = "false";
        $_CDATA['row']['locked'] = "false";
        $_CDATA['row']['sm.list'] = "true";
        $_CDATA['row']['sm.changefreq'] = "weekly";
        $_CDATA['row']['sm.priority'] = "0.5";
      }
      break;


    /* *********************************************************** */
    case "edit":
      OS_setData("c.location", "List");
      $_CDATA['row'] = array();
      if ($_CDATA['edit'] == "Confirm") {
        $_CDATA['categories'] = array();
        $select = $_DDATA['link']->query("SELECT DISTINCT `category` FROM `{$_DDATA['tablename']}`;")->fetchAll();
        foreach ($select as $row) $_CDATA['categories'][] = $row['category'];
        if ($_VDATA['cf.category'] != "-" && !in_array($_VDATA['cf.category'], $_CDATA['categories'])) $_CDATA['categories'][] = $_VDATA['cf.category'];

        $select = $_DDATA['link']->query("SELECT `sm.changefreq`, `sm.priority` FROM `{$_DDATA['tablename']}` WHERE `md5`='{$_POST['md5']}';")->fetchAll();

        if (count($select)) {
          $row = array_shift($select);
          $_CDATA['row']['uri'] = trim($_POST['uri']);
          $_CDATA['row']['title'] = trim($_POST['title']);
          if ($_POST['categoryExist'] == "-") {
            if (trim($_POST['categoryNew'])) {
              $_CDATA['row']['category'] = trim($_POST['categoryNew']);
            } else {
              $_CDATA['row']['category'] = trim($_POST['categoryNow']);
              $_ERROR['error'][] = $_LANG['035'];
            }
          } else if (in_array(trim($_POST['categoryExist']), $_CDATA['categories'])) {
            $_CDATA['row']['category'] = trim($_POST['categoryExist']);
          } else $_ERROR['error'] = $_LANG['036'];
          $_CDATA['row']['description'] = str_replace(array("\n", "\r"), " ", trim($_POST['description']));
          $_CDATA['row']['keywords'] = str_replace(array("\n", "\r"), " ", trim($_POST['keywords']));
          $_CDATA['row']['unlist'] = (isset($_POST['unlist']) && $_POST['unlist'] == "true") ? "true" : "false";
          $_CDATA['row']['locked'] = (isset($_POST['locked']) && $_POST['locked'] == "true") ? "true" : "false";
          $_CDATA['row']['md5'] = $_POST['md5'];

          $_CDATA['row']['sm.list'] = (isset($_POST['smlist']) && $_POST['smlist'] == "true") ? "true" : "false";
          $_CDATA['row']['sm.changefreq'] = (isset($_POST['changefreq']) && array_key_exists($_POST['changefreq'], $_LANG['langcf'])) ? $_POST['changefreq'] : $row['sm.changefreq'];
          $_CDATA['row']['sm.priority'] = (isset($_POST['changefreq'])) ? max(0, min(1, (float)$_POST['priority'])) : $row['sm.priority'];

          if (!isset($_ERROR)) {
            $_CDATA['row'] = array_map("stripslashes", $_CDATA['row']);
            $_CDATA['row'] = array_map("addslashes", $_CDATA['row']);

            $update = $_DDATA['link']->query("UPDATE `{$_DDATA['tablename']}` SET
              `title`='{$_CDATA['row']['title']}',
              `category`='{$_CDATA['row']['category']}',
              `description`='{$_CDATA['row']['description']}',
              `keywords`='{$_CDATA['row']['keywords']}',
              `unlist`='{$_CDATA['row']['unlist']}',
              `locked`='{$_CDATA['row']['locked']}',
              `sm.list`='{$_CDATA['row']['sm.list']}',
              `sm.changefreq`='{$_CDATA['row']['sm.changefreq']}',
              `sm.priority`='{$_CDATA['row']['sm.priority']}'
              WHERE `md5`='{$_CDATA['row']['md5']}';");
            OS_clearCache();

            $_CDATA['command'] = "";
          } else $_CDATA['edit'] = $_CDATA['row']['md5'];
        } else $_ERROR['error'][] = $_LANG['03d'];

      } else {
        $select = $_DDATA['link']->query("SELECT `uri`, `title`, `encoding`, `category`, `description`, `keywords`, `unlist`, `md5`, `locked`, `sm.list`, `sm.changefreq`, `sm.priority` FROM `{$_DDATA['tablename']}` WHERE `md5`='{$_CDATA['edit']}';")->fetchAll();
        if (!count($select)) {
          unset($_CDATA['edit']);
          $_ERROR['error'][] = $_LANG['03d'];
        } else $_CDATA['row'] = array_shift($select);
      }
      break;


    /* *********************************************************** */
    case "spider":
      OS_setData("c.location", "Spider");
      switch ($_CDATA['spider']) {
        case "Edit": /* ****************************************** */
          $_POST = array_map(create_function('$v', 'return str_replace("\r", "", $v);'), $_POST);

          if ($_POST['pathto'] = trim($_POST['pathto'])) {
            if (!preg_match("/^{$_SDATA['scheme']}:\/\//", $_POST['pathto'])) $_POST['pathto'] = "{$_SDATA['scheme']}://{$_POST['pathto']}";
            OS_setData("sp.pathto", $_POST['pathto']);
          }

          if ($_POST['start'] = trim($_POST['start'])) {
            $_POST['start'] = preg_grep("/^{$_SDATA['scheme']}:\/\/\w/", array_map("trim", explode("\n", $_POST['start'])));
            while (list($key, $value) = each($_POST['start'])) {
              $uri = parse_url($value);
              if (isset($uri['host']) && !isset($uri['path'])) $_POST['start'][$key] .= "/";
            }
            OS_setData("sp.start", implode("\n", $_POST['start']));
          }

          OS_setData("sp.cron", (isset($_POST['cron'])) ? "true" : "false");

          OS_setData("sp.pagelimit", max(1, abs((int)trim($_POST['pagelimit']))));

          OS_setData("sp.filesizelimit", max(1, abs((int)trim($_POST['pagelimit']))) * 1024);

          if (isset($_POST['interval']))
            OS_setData("sp.interval", min(1536, abs((int)trim($_POST['interval']))));

          OS_setData("sp.seamless", (isset($_POST['seamless'])) ? "true" : "false");

          $_POST['defcat'] = trim($_POST['defcat']);
          OS_setData("sp.defcat", $_POST['defcat']);

          $_POST['autocat'] = preg_replace("/\n{2,}/", "\n", trim($_POST['autocat']));
          OS_setData("sp.autocat", $_POST['autocat']);

          if (isset($_POST['email']))
            OS_setData("sp.email", trim($_POST['email']));

          $doFullScan = false;

          if (OS_setData("sp.cookies", (isset($_POST['cookies'])) ? "true" : "false")) $doFullScan = true;

          if (OS_setData("sp.utf8", (isset($_POST['utf8'])) ? "true" : "false")) $doFullScan = true;

          if (isset($_POST['typeIndex']) && count(array_filter($_POST['typeIndex']))) {
            $_POST['typeIndex'] = trim(implode(" ", $_POST['typeIndex']));
            if (preg_match("/[^\w+\/ ]/", $_POST['typeIndex'])) {
              $_ERROR['error'][] = $_LANG['03e'];
            } else if (OS_setData("sp.type.index", $_POST['typeIndex'])) {
              $_VDATA['sp.type.index'] = array_filter(array_unique(explode(" ", $_VDATA['sp.type.index'])));
              $doFullScan = true;
              $_MIME->verify();
            }
          }

          $_POST['mimeAccept'] = preg_replace("/\n{2,}/", "\n", trim($_POST['mimeAccept']));
          if (OS_setData("sp.type.accept", $_POST['mimeAccept'])) $doFullScan = true;

          if (OS_setData("sp.linkdepth", abs((int)trim($_POST['linkdepth'])))) $doFullScan = true;

          $_POST['domains'] = preg_replace("/\n{2,}/", "\n", trim($_POST['domains']));
          if (OS_setData("sp.domains", $_POST['domains'])) $doFullScan = true;

          $_POST['require'] = preg_replace("/\n{2,}/", "\n", trim($_POST['require']));
          if (OS_setData("sp.require", $_POST['require'])) $doFullScan = true;

          $_POST['ignore'] = preg_replace("/\n{2,}/", "\n", trim($_POST['ignore']));
          if (OS_setData("sp.ignore", $_POST['ignore'])) $doFullScan = true;

          $_POST['extensions'] = trim($_POST['extensions']);
          if (!preg_match("/[^\w\d\s.]/", $_POST['extensions'])) {
            $_POST['extensions'] = preg_replace(array("/\s/", "/\s{2,}/", "/(^|\s)\.+/", "/\.(\s|$)/"), array(" ", " ", "$1", "$1"), $_POST['extensions']);
            $_POST['extensions'] = explode(" ", $_POST['extensions']);
            sort($_POST['extensions']);
            $_POST['extensions'] = implode(" ", $_POST['extensions']);
            if (OS_setData("sp.extensions", $_POST['extensions'])) $doFullScan = true;
          } else $_ERROR['error'][] = $_LANG['03f'];

          $_POST['remtags'] = trim($_POST['remtags']);
          if (!preg_match("/[^\w\s_:#.-]/", $_POST['remtags'])) {
            $_POST['remtags'] = preg_replace(array("/\s/", "/\s{2,}/"), " ", $_POST['remtags']);
            $_POST['remtags'] = explode(" ", $_POST['remtags']);
            sort($_POST['remtags']);
            $_POST['remtags'] = implode(" ", $_POST['remtags']);
            if (OS_setData("sp.remtags", $_POST['remtags'])) $doFullScan = true;
          } else $_ERROR['error'][] = $_LANG['03g'];

          if (OS_setData("sp.remtitle", $_POST['remtitle'])) $doFullScan = true;

          if ($doFullScan) OS_setData("sp.fullscan", "true");

          break;

        case "Go": /* ******************************************** */
          break;

        case "Cancel": /* **************************************** */
          OS_setData("sp.cancel", "true");
          OS_setData("sp.lock", "false");
          $_ERROR['success'][] = $_LANG['061'];
          break;

      }
      break;


    /* *********************************************************** */
    case "search":
      OS_setData("c.location", "Search");
      switch ($_CDATA['search']) {
        case "Cache": /* ***************************************** */
          if ($_POST['cachelimit'] = abs((int)trim($_POST['cachelimit'])))
            OS_setData("s.cachelimit", $_POST['cachelimit']);

          if ($_VDATA['s.cachegzip'] != "disabled") {
            $_POST['cachegzip'] = (isset($_POST['cachegzip'])) ? "on" : "off";
            if ($_POST['cachegzip'] != $_VDATA['s.cachegzip']) {
              OS_setData("s.cachegzip", $_POST['cachegzip']);
              OS_clearCache();
            }
          }
          break;

        case "Purge": /* ***************************************** */
          OS_clearCache();
          break;

        case "Edit": /* ****************************************** */
          $_POST = array_map(create_function('$v', 'return str_replace("\r", "", $v);'), $_POST);

          $_POST['ignore'] = preg_replace("/\n{2,}/", "\n", trim($_POST['ignore']));
          OS_setData("s.ignore", $_POST['ignore']);

          if ($_POST['termlimit'] = abs((int)trim($_POST['termlimit'])))
            OS_setData("s.termlimit", $_POST['termlimit']);

          if ($_POST['termlength'] = abs((int)trim($_POST['termlength'])))
            OS_setData("s.termlength", $_POST['termlength']);

          for ($x = 0, $weight = array(); $x < 7; $x++)
            $weight[] = (string)abs((float)trim($_POST['weight'.$x]));

          OS_setData("s.weight", implode("%", $weight));
          $_VDATA['s.weight'] = $weight;

          OS_setData("s.latinacc", (isset($_POST['latinacc'])) ? "true" : "false");

          $_POST['weightedtags'] = trim($_POST['weightedtags']);
          if (!preg_match("/[^\w\s_:#.-]/", $_POST['weightedtags'])) {
            $_POST['weightedtags'] = preg_replace(array("/\s/", "/\s{2,}/"), " ", $_POST['weightedtags']);
            $_POST['weightedtags'] = explode(" ", $_POST['weightedtags']);
            sort($_POST['weightedtags']);
            $_POST['weightedtags'] = implode(" ", $_POST['weightedtags']);
            if (OS_setData("s.weightedtags", $_POST['weightedtags'])) OS_setData("sp.fullscan", "true");
          } else $_ERROR['error'][] = $_LANG['03h'];

          OS_setData("s.resultlimit", abs((int)trim($_POST['resultlimit'])));

          if ($_POST['matchingtext'] = abs((int)trim($_POST['matchingtext'])))
            OS_setData("s.matchingtext", $_POST['matchingtext']);

          OS_setData("s.orphans", (isset($_POST['orphans'])) ? "show" : "hide");

          OS_clearCache();

      }
      break;


    /* *********************************************************** */
    case "stats":
      OS_setData("c.location", "Stats");
      switch ($_CDATA['stats']) {
        case "Autoreset": /* ************************************* */
          if ($_POST['cachereset'] = abs((int)trim($_POST['cachereset'])))
            OS_setData("s.cachereset", $_POST['cachereset']);

          if (isset($_POST['cacheemail']))
            OS_setData("s.cacheemail", trim($_POST['cacheemail']));
          break;

        case "Reset": /* ***************************************** */
          $truncate = $_DDATA['link']->query("TRUNCATE TABLE `{$_DDATA['tablestat']}`;");
          OS_setData("s.cachetime", time());

      }
      break;


    /* *********************************************************** */
    case "control":
      OS_setData("c.location", "Tools");
      if ($_CDATA['control'] == "Control") {
        if (preg_match("/[^\w\d\-]/", trim($_POST['charset']))) {
          $_ERROR['error'][] = $_LANG['03i'];
        } else OS_setData("c.charset", trim($_POST['charset']));
      }
      break;


    /* *********************************************************** */
    case "sitemap":
      OS_setData("c.location", "Tools");
      switch ($_CDATA['sitemap']) {
        case "Control": /* *************************************** */
          $_CDATA['domains'] = array();
          $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
          $domains = $_DDATA['link']->query("SELECT `uri` FROM `{$_DDATA['tablename']}` WHERE `sm.list`='true';")->fetchAll();
          foreach ($domains as $domrow) {
            $parsed = parse_url($domrow['uri']);
            if (!array_key_exists($parsed['host'], $_CDATA['domains'])) {
              $_CDATA['domains'][$parsed['host']] = 1;
            } else $_CDATA['domains'][$parsed['host']]++;
          }
          $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
          arsort($_CDATA['domains']);
          reset($_CDATA['domains']);

          if (!array_key_exists($_VDATA['sm.domain'], $_CDATA['domains']))
            OS_setData("sm.domain", key($_CDATA['domains']));

          OS_setData("sm.enable", (isset($_POST['enable'])) ? "true" : "false");

          if (isset($_POST['pathto'])) {
            if ($_POST['pathto'] = trim($_POST['pathto']))
              OS_setData("sm.pathto", $_POST['pathto']);

            OS_setData("sm.gzip", (isset($_POST['gzip'])) ? "true" : "false");
            if ($_VDATA['sm.gzip'] == "true") {
              if (substr($_VDATA['sm.pathto'], -3, 3) != ".gz") OS_setData("sm.pathto", $_VDATA['sm.pathto'].".gz");
            } else if (substr($_VDATA['sm.pathto'], -3, 3) == ".gz") OS_setData("sm.pathto", substr($_VDATA['sm.pathto'], 0, strlen($_VDATA['sm.pathto']) - 3));

            if (isset($_POST['domain']) && $_POST['domain'] = trim($_POST['domain'])) {
              if (!array_key_exists($_POST['domain'], $_CDATA['domains'])) {
                $_ERROR['error'][] = $_LANG['03j'];
              } else OS_setData("sm.domain", $_POST['domain']);
            }

            OS_setData("sm.unlisted", (isset($_POST['unlisted'])) ? "true" : "false");

            OS_setData("sm.changefreq", (isset($_POST['changefreq'])) ? "true" : "false");
          }
          break;

        case "Commit": /* **************************************** */
          $_CDATA['smnf'] = true;
          $_CDATA['smnw'] = true;
          if (file_exists($_VDATA['sm.pathto'])) {
            $_CDATA['smnf'] = false;
            if (is_writable($_VDATA['sm.pathto'])) $_CDATA['smnw'] = false;
          }

          if (!$_CDATA['smnf'] && !$_CDATA['smnw']) {
            if ($_VDATA['sm.unlisted'] != "true") {
              $lq = ($_VDATA['s.orphans'] == "show") ? " AND (`status`='OK' OR `status`='Orphan')" : " AND `status`='OK'";

              $nq = "";
              $_SDATA['noSearch'] = array_filter(array_map("trim", explode("\n", $_VDATA['s.ignore'])));
              foreach ($_SDATA['noSearch'] as $noSearch)
                $nq .= " AND `uri` NOT ".(($noSearch{0} == "*") ? "REGEXP '".substr($noSearch, 1)."'": " LIKE '%{$noSearch}%'");

              $qadd = " AND `unlist`!='true'{$lq}{$nq}";
            } else $qadd = "";

            $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $sitemap = $_DDATA['link']->query("SELECT `uri`, `sm.lastmod`, `sm.changefreq`, `sm.priority` FROM `{$_DDATA['tablename']}` WHERE `sm.list`='true' AND `uri` LIKE '%//{$_VDATA['sm.domain']}/%'$qadd;")->fetchAll();

            ob_start();
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
            $_ERROR['success'][] = $_LANG['062'];
          } else $_ERROR['error'][] = $_LANG['03k'];
          break;

      }
      break;


    /* *********************************************************** */
    case "jwriter";
      OS_setData("c.location", "Tools");
      $_POST = array_map(create_function('$v', 'return str_replace("\r", "", $v);'), $_POST);
      switch ($_CDATA['jwriter']) {
        case "Options": /* *************************************** */
          OS_setData("jw.hide", (isset($_POST['hide'])) ? "false" : "true");

          if (isset($_POST['writer'])) {
            if ($_POST['writer'] = trim($_POST['writer']))
              OS_setData("jw.writer", $_POST['writer']);

            if ($_POST['egg'] = trim($_POST['egg']))
              OS_setData("jw.egg", $_POST['egg']);

            $_POST['remuri'] = preg_replace("/\n{2,}/", "\n", trim($_POST['remuri']));
            OS_setData("jw.remuri", $_POST['remuri']);

            $_POST['index'] = trim($_POST['index']);
            OS_setData("jw.index", $_POST['index']);

            OS_setData("jw.ext", (isset($_POST['ext'])) ? "true" : "false");

            OS_setData("jw.pagination", max(1, abs((int)trim($_POST['pagination']))));

            if ($_POST['template'] = trim($_POST['template'])) {
              OS_setData("jw.template", $_POST['template']);
            } else OS_setData("jw.template", "<h3><a href=\"{R_URI}\" title=\"{R_DESCRIPTION}\">{R_TITLE}</a> - <small>{R_CATEGORY}</small></h3>\n<blockquote>\n  <p>\n    {R_MATCH}<br />\n    <cite>{R_URI}</cite> <small>({R_RELEVANCE})</small>\n  </p>\n</blockquote>");
          }
          break;

        case "Commit": /* **************************************** */
          OS_setData("jw.key", md5(time()));
          header("Location: {$_VDATA['jw.writer']}?key={$_VDATA['jw.key']}&linkback=".rawurlencode($_SERVER['PHP_SELF']));
          exit();
          break;

        case "Cancel": /* **************************************** */

      }
      break;

    default:
  }
}


/* ******************************************************************
******** Display Setup ******************************************* */
if ($_DDATA['online'] && $_CDATA['loggedIn']) {
  $_CDATA['lq'] = ($_VDATA['s.orphans'] == "show") ? " AND (`status`='OK' OR `status`='Orphan')" : " AND `status`='OK'";

  $_CDATA['nq'] = "";
  $igl = array_filter(array_map("trim", explode("\n", $_VDATA['s.ignore'])));
  foreach ($igl as $ig) $_CDATA['nq'] .= " AND `uri` NOT ".(($ig{0} == "*") ? "REGEXP '".substr($ig, 1)."'": " LIKE '%{$ig}%'");

  switch ($_VDATA['c.location']) {
    case "Tools": /* ********************************************* */
      if ($_VDATA['sm.enable'] == "true") {
        $_CDATA['smnf'] = true;
        $_CDATA['smnw'] = true;
        if (file_exists($_VDATA['sm.pathto'])) {
          $_CDATA['smnf'] = false;
          if (is_writable($_VDATA['sm.pathto'])) $_CDATA['smnw'] = false;
        }

        if (!isset($_CDATA['domains'])) {
          $_CDATA['domains'] = array();
          $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
          $domains = $_DDATA['link']->query("SELECT `uri` FROM `{$_DDATA['tablename']}` WHERE `sm.list`='true';")->fetchAll();
          foreach ($domains as $domrow) {
            $parsed = parse_url($domrow['uri']);
            if (!array_key_exists($parsed['host'], $_CDATA['domains'])) {
              $_CDATA['domains'][$parsed['host']] = 1;
            } else $_CDATA['domains'][$parsed['host']]++;
          }
          $_DDATA['link']->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
          arsort($_CDATA['domains']);
        }
      }

      if ($_VDATA['jw.hide'] == "false") {
        $result = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `unlist`!='true'{$_CDATA['lq']}{$_CDATA['nq']};")->fetchAll(PDO::FETCH_NUM);
        list($count) = array_shift($result);

        $_CDATA['jnf'] = true;
        $_CDATA['jnw'] = true;
        if (file_exists($_VDATA['jw.egg'])) {
          $_CDATA['jnf'] = false;
          if (is_writable($_VDATA['jw.egg'])) $_CDATA['jnw'] = false;
        }

        $_CDATA['wnf'] = true;
        $_CDATA['wer'] = true;
        if ($_VDATA['jw.writer'] != $_SDATA['scheme']."://") {
          $tpage = new OS_Fetcher($_VDATA['jw.writer']);
          $tpage->request = "HEAD";
          $tpage->accept = array("text/html", "application/xhtml+xml", "text/xml");
          $tpage->fetch();

          if (!$tpage->errstr) {
            if ($tpage->httpcode{0} == "2") $_CDATA['wnf'] = false;
            if (count(preg_grep("/^Orcascript: Search_JWriter/", $tpage->headers))) $_CDATA['wer'] = false;
          }
        }

        $indextable = $_DDATA['link']->query("SHOW TABLE STATUS LIKE '{$_DDATA['tablename']}';")->fetchAll();
        $_CDATA['indextable'] = array_shift($indextable);
        $_CDATA['indexmem'] = $_CDATA['indextable']['Data_length'];

        $_CDATA['phpmem'] = ini_get("memory_limit") or $_CDATA['phpmem'] = $_LANG['01o'];
      }
      break;

    case "Search": /* ******************************************** */
      $optimize = $_DDATA['link']->query("OPTIMIZE TABLE `{$_DDATA['tablestat']}`;");
      $cachedno = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablestat']}` WHERE LENGTH(`cache`)>5;")->fetchAll(PDO::FETCH_NUM);
      list($_CDATA['cachedno']) = array_shift($cachedno);

      $cachekb = $_DDATA['link']->query("SELECT SUM(LENGTH(`cache`)) FROM `{$_DDATA['tablestat']}`;")->fetchAll(PDO::FETCH_NUM);
      list($_CDATA['cachekb']) = array_shift($cachekb);
      $_CDATA['cachekb'] /= 1024;
      break;

    case "Stats": /* ********************************************* */
      if ($_VDATA['sp.lasttime'] != -1) {
        $indextable = $_DDATA['link']->query("SHOW TABLE STATUS LIKE '{$_DDATA['tablename']}';")->fetchAll();
        $_CDATA['indextable'] = array_shift($indextable);
        $_CDATA['indexmem'] = $_CDATA['indextable']['Data_length'];

        $allpages = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}`;")->fetchAll(PDO::FETCH_NUM);
        list($_CDATA['allpages']) = array_shift($allpages);
        $indexpages = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `body`!='';")->fetchAll(PDO::FETCH_NUM);
        list($_CDATA['indexpages']) = array_shift($indexpages);
        $indexsrchd = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `unlist`!='true' AND `body`!=''{$_CDATA['lq']}{$_CDATA['nq']};")->fetchAll(PDO::FETCH_NUM);
        list($_CDATA['indexsrchd']) = array_shift($indexsrchd);
        $indexcats = $_DDATA['link']->query("SELECT DISTINCT `category` FROM `{$_DDATA['tablename']}`;")->fetchAll();
        $_CDATA['indexcats'] = count($indexcats);

        $_CDATA['encodings'] = array();
        $encodings = $_DDATA['link']->query("SELECT `encoding`, COUNT(*) as `num` FROM `{$_DDATA['tablename']}` WHERE `body`!='' GROUP BY `encoding` ORDER BY `num` DESC;")->fetchAll();
        foreach ($encodings as $row) $_CDATA['encodings'][] = $row;
      }

      if ($_VDATA['s.cachetime']) {
        if (time() > $_VDATA['s.cachetime']) {
          $scount = $_DDATA['link']->query("SELECT SUM(`hits`) FROM `{$_DDATA['tablestat']}`;")->fetchAll(PDO::FETCH_NUM);
          list($_CDATA['scount']) = array_shift($scount);
          $_CDATA['sperhour'] = $_CDATA['scount'] * 3600 / (time() - $_VDATA['s.cachetime']);
        } else $_CDATA['sperhour'] = 0;

        $sravg = $_DDATA['link']->query("SELECT AVG(`results`) FROM `{$_DDATA['tablestat']}`;")->fetchAll(PDO::FETCH_NUM);
        list($_CDATA['sravg']) = array_shift($sravg);
      }
      break;

    case "Spider": /* ******************************************** */
      OS_setData("c.spkey", md5(time()));

      $_CDATA['snf'] = true;
      $_CDATA['ser'] = true;
      if ($_VDATA['sp.pathto'] != $_SDATA['scheme']."://") {
        $spage = new OS_Fetcher($_VDATA['sp.pathto']);
        $spage->request = "HEAD";
        $spage->accept = array("text/html", "application/xhtml+xml", "text/xml");
        $spage->fetch();

        if (!$spage->errstr) {
          if ($spage->httpcode[0] == "2") $_CDATA['snf'] = false;
          if (count(preg_grep("/^Orcascript: Search_Spider/", $spage->headers))) $_CDATA['ser'] = false;
          // $_ERROR['error'][] = '<pre>'.print_r($spage, true).'</pre>';
        }
      }

      $_CDATA['cronsp'] = parse_url($_VDATA['sp.pathto']);

      $indexpages = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `body`!='';")->fetchAll(PDO::FETCH_NUM);
      list($_CDATA['indexpages']) = array_shift($indexpages);
      $utf8pages = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `encoding`='UTF-8' AND `body`!='';")->fetchAll(PDO::FETCH_NUM);
      list($_CDATA['utf8pages']) = array_shift($utf8pages);
      break;

    default: /* ************************************************** */
      $_GET['start'] = (isset($_GET['start'])) ? $_GET['start'] : ((isset($_POST['start'])) ? $_POST['start'] : 0);

      $_CDATA['categories'] = array();
      $select = $_DDATA['link']->query("SELECT DISTINCT `category` FROM `{$_DDATA['tablename']}`;")->fetchAll();
      foreach ($select as $row) $_CDATA['categories'][] = $row['category'];
      if ($_VDATA['cf.category'] != "-" && !in_array($_VDATA['cf.category'], $_CDATA['categories'])) $_CDATA['categories'][] = $_VDATA['cf.category'];

      if ($_CDATA['command'] != "add" &&
          $_CDATA['command'] != "edit" &&
          ($_CDATA['command'] != "action" ||
            ($_CDATA['input'] != "category" &&
             $_CDATA['input'] != "sm.priority"))) {

        /* ***** Sorting ***************************************** */
        if (isset($_GET['column']) && in_array($_GET['column'], array("title", "uri"))) {
          OS_setData("c.column", $_GET['column']);
          OS_setData("c.sortby", "col1");
          OS_setData("cf.textexclude", "");
          OS_setData("cf.textmatch", "");
        }
        if (isset($_GET['sortby']) && in_array($_GET['sortby'], array("col1", "col2")))
          OS_setData("c.sortby", $_GET['sortby']);

        $sqlData['orderby'] = " ORDER BY ".(($_VDATA['c.sortby'] == "col2") ? "`category`, " : "")."`{$_VDATA['c.column']}`";

        $_SDATA['orderby1'] = "<em>%1\$s</em>";
        $_SDATA['orderby2'] = "<a href=\"?sortby=%2\$s\">%1\$s</a>";
        $_SDATA['orderby3'] = "<a href=\"?column=%2\$s\"><small>%1\$s</small></a>";

        /* ***** Filters ***************************************** */
        $new = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}` WHERE `new`='true';")->fetchAll(PDO::FETCH_NUM);
        list($_CDATA['new']) = array_shift($new);

        $sqlData['filters'] = "";
        if ($_VDATA['cf.textexclude']) $sqlData['filters'] .= " `{$_VDATA['c.column']}` NOT LIKE '%".addslashes(stripslashes($_VDATA['cf.textexclude']))."%' AND";
        if ($_VDATA['cf.textmatch']) $sqlData['filters'] .= " `{$_VDATA['c.column']}` LIKE '%".addslashes(stripslashes($_VDATA['cf.textmatch']))."%' AND";
        if ($_VDATA['cf.category'] != "-") $sqlData['filters'] .= " `category`='{$_VDATA['cf.category']}' AND";
        if ($_VDATA['cf.new'] == "true") $sqlData['filters'] .= " `new`='true' AND";

        switch ($_VDATA['cf.status']) {
          case "OK":
          case "Orphan":
          case "Unread":
          case "Indexed":
            foreach ($_SDATA['noSearchSQL'] as $noSearch)
              $sqlData['filters'] .= " `uri` NOT ".(($noSearch{0} == "*") ? "REGEXP '".substr($noSearch, 1)."'": " LIKE '%{$noSearch}%'")." AND";
            if ($_VDATA['cf.status'] == "OK" || $_VDATA['cf.status'] == "Orphan") {
              $sqlData['filters'] .= " `status`='{$_VDATA['cf.status']}' AND `unlist`='false' AND";
            } else $sqlData['filters'] .= " (`status`='OK' OR `status`='Orphan') AND `body`".(($_VDATA['cf.status'] == "Indexed") ? "!" : "")."='' AND `unlist`='false' AND";
            break;
          case "NotOK":
            $sqlData['filters'] .= " `status`!='OK' AND `unlist`='false' AND";
            break;
          case "Blocked":
          case "Not Found":
          case "Added":
            $sqlData['filters'] .= " `status`='{$_VDATA['cf.status']}' AND";
            break;
          case "Unlisted":
            $build = "";
            foreach ($_SDATA['noSearchSQL'] as $noSearch)
              $build .= "`uri` ".(($noSearch{0} == "*") ? "REGEXP '".substr($noSearch, 1)."'": " LIKE '%{$noSearch}%'")." OR ";
            $sqlData['filters'] .= " ({$build}`unlist`='true') AND `status`!='Not Found' AND";
            break;
        }

        $_CDATA['nofilters'] = ($_VDATA['cf.textexclude'] === "" && $_VDATA['cf.textmatch'] === "" && $_VDATA['cf.category'] === "-" && $_VDATA['cf.status'] === "All" && $_VDATA['cf.new'] === "false") ? true : false;
        if ($sqlData['filters']) $sqlData['filters'] = " WHERE".preg_replace("/ AND$/", "", $sqlData['filters']);

        $count = $_DDATA['link']->query("SELECT COUNT(*) FROM `{$_DDATA['tablename']}`{$sqlData['filters']}{$sqlData['orderby']};")->fetchAll(PDO::FETCH_NUM);
        list($_CDATA['count']) = array_shift($count);

        $_CDATA['start'] = ($_CDATA['count'] <= $_VDATA['c.pagination']) ? 0 : $_GET['start'];
        $_CDATA['end'] = min($_CDATA['start'] + $_VDATA['c.pagination'], $_CDATA['count']);

        $_CDATA['list'] = $_DDATA['link']->query("SELECT `title`, `uri`, `category`, `status`, `md5`, `new`, `unlist`, `locked`, LENGTH(`body`) as `indexed`, `sm.list`, `sm.changefreq`, `sm.priority` FROM `{$_DDATA['tablename']}`{$sqlData['filters']}{$sqlData['orderby']} LIMIT {$_CDATA['start']}, {$_VDATA['c.pagination']};")->fetchAll();
        $_CDATA['rows'] = count($_CDATA['list']);
      }
  }
  if (@is_array($_CDATA['categories'])) sort($_CDATA['categories']);
}


/* ******************************************************************
******** Start HTML ********************************************** */
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <title>Orca Search - <?php echo $_LANG['026']; ?></title>
  <meta http-equiv="Content-type" content="text/html; charset=<?php echo $_VDATA['c.charset']; ?>;" /><?php
  if ($_CDATA['loggedIn']) { ?> 
    <meta http-equiv="Refresh" content="<?php echo ($_CDATA['cookietime'] - 30); ?>; URL=<?php echo $_SDATA['scheme'].'://'.$_SERVER['HTTP_HOST'], $_SERVER['PHP_SELF']; ?>?Timeout" /><?php
  } ?> 
  <link rel="stylesheet" type="text/css" href="control.css" />
</head>
<body id="osc_body">
  <?php if (!$_DDATA['online']) { ?> 
    <h3 class="warning"><?php echo $_LANG['03m']; ?></h3>
    <pre class="warning">
      <?php echo $_DDATA['errno'], " - ", $_DDATA['error']; ?> 
    </pre>

  <?php } else if (!$_CDATA['loggedIn']) {
    if (isset($_ERROR['error'])) { ?> 
      <ul id="error">
        <?php foreach ($_ERROR['error'] as $error) { ?> 
          <li>Error: <?php echo $error; ?></li>
        <?php } ?> 
      </ul>
    <?php } ?> 

    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="login">
      <h3><?php echo $_LANG['000']; ?></h3>
      <div>
        <label><?php echo $_LANG['001']; ?>: <input type="text" name="loginName" size="10" /></label>
        <label><?php echo $_LANG['002']; ?>: <input type="password" name="loginPass" size="10" /></label>
        <input type="submit" value="<?php echo $_LANG['003']; ?>" />
      </div>
    </form>
  <?php } else { ?> 
    <ul id="menu">
      <li class="first">Orca Search v<?php echo $_SDATA['version']; ?></li>
      <li><a href="?Logout"><?php echo $_LANG['020']; ?></a></li>
      <li><a href="?Stats"<?php if ($_VDATA['c.location'] == "Stats") echo " class=\"selected\""; ?>><?php echo $_LANG['021']; ?></a></li>
      <li><a href="?Spider"<?php if ($_VDATA['c.location'] == "Spider") echo " class=\"selected\""; ?>><?php echo $_LANG['022']; ?></a></li>
      <li><a href="?List"<?php if ($_VDATA['c.location'] == "List") echo " class=\"selected\""; ?>><?php echo $_LANG['023']; ?></a></li>
      <li><a href="?Search"<?php if ($_VDATA['c.location'] == "Search") echo " class=\"selected\""; ?>><?php echo $_LANG['024']; ?></a></li>
      <li><a href="?Tools"<?php if ($_VDATA['c.location'] == "Tools") echo " class=\"selected\""; ?>><?php echo $_LANG['025']; ?></a></li>
    </ul>

    <?php if (isset($_ERROR['error'])) { ?> 
      <ul id="error">
        <?php foreach ($_ERROR['error'] as $error) { ?> 
          <li><?php echo $_LANG['030']; ?>: <?php echo $error; ?></li>
        <?php } ?> 
      </ul>
    <?php }
    if (isset($_ERROR['success'])) { ?> 
      <ul id="success">
        <?php foreach ($_ERROR['success'] as $success) { ?> 
          <li><?php echo $success; ?></li>
        <?php } ?> 
      </ul>
    <?php } ?> 


    <?php switch ($_VDATA['c.location']) {
      case "Dump": /* ***** Config Dump *********************** */ ?> 
        <style type="text/css">
          #osc_body table#dump { font:normal 90% Arial,Geneva,sans-serif; margin:20px 5%; }
          #osc_body table#dump thead tr { color:#ffffff; background-color:#808080; }
          #osc_body table#dump thead tr th { padding:3px; border:1px outset #808080; }
          #osc_body table#dump tbody tr th, #osc_body table#dump tbody tr td { padding:3px 5px; border:1px solid #808080; }
          #osc_body table#dump tbody tr th { text-align:left; background-color:#eeeeee; }
        </style>
        <table cellspacing="0" border="0" id="dump">
          <thead>
            <tr>
              <th><?php echo $_LANG['01q']; ?></th>
              <th><?php echo $_LANG['01r']; ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th>PHP</th>
              <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
              <th>MySQL</th>
              <td><?php echo $_DDATA['link']->getAttribute(PDO::ATTR_SERVER_VERSION); ?></td>
            </tr><?php
            foreach ($_VDATA as $key => $value) { ?> 
              <tr>
                <th><?php echo $key; ?></th>
                <td><?php echo nl2br(str_replace("  ", "&nbsp; ", htmlspecialchars(print_r($value, true)))); ?></td>
              </tr><?php
            } ?> 
          </tbody>
        </table>
        <?php break;


      case "Tools": /* ***** Tools **************************** */ ?> 
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['090']; ?></h3>
          <ul>
            <li>
              <var><a href="?Dump"><?php echo $_LANG['01p']; ?></a></var>
              <h4><?php echo $_LANG['0a6']; ?></h4>
              <?php echo $_LANG['0a7']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><input type="text" size="15" maxlength="20" name="charset" value="<?php echo htmlspecialchars($_VDATA['c.charset']); ?>" /></var>
              <h4 title="<?php echo $_LANG['092']; ?>"><?php echo $_LANG['091']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="submit" name="control_Control" value="<?php echo $_LANG['010']; ?>" /></var>
              <h4><?php echo $_LANG['011']; ?></h4>
              <div></div>
            </li>
          </ul>
        </form>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['093']; ?></h3>
          <ul>
            <li>
              <var><input type="checkbox" name="enable" value="true"<?php if ($_VDATA['sm.enable'] == "true") echo " checked=\"checked\""; ?>" /></var>
              <h4><?php echo $_LANG['094']; ?></h4>
              <div></div>
            </li>
            <?php if ($_VDATA['sm.enable'] == "true") { ?> 
              <li class="drow">
                <var><input type="text" size="70" name="pathto" value="<?php echo htmlspecialchars($_VDATA['sm.pathto']); ?>" /></var>
                <h4><?php echo $_LANG['095']; ?></h4>
                <?php if ($_CDATA['smnf']) {
                  ?><span class="warning"><?php echo $_LANG['096']; ?></span><?php
                } else if ($_CDATA['smnw']) {
                  ?><span class="warning"><?php echo $_LANG['097']; ?></span><?php
                } ?> 
                <div></div>
              </li>
              <li>
                <var><input type="checkbox" name="gzip" value="true"<?php if ($_SDATA['zlib'] && $_VDATA['sm.gzip'] == "true") echo " checked=\"checked\""; ?>" <?php if (!$_SDATA['zlib']) echo " disabled=\"disabled\""; ?> /></var>
                <h4><?php echo $_LANG['098']; ?></h4>
                <?php if (!$_SDATA['zlib']) { ?><span class="warning"><?php echo $_LANG['099']; ?></span><?php } ?> 
                <div></div>
              </li>
              <li class="drow">
                <var><select name="domain" size="1"<?php if (!count($_CDATA['domains'])) echo " disabled=\"disabled\""; ?>>
                  <?php if (count($_CDATA['domains'])) {
                    reset ($_CDATA['domains']);
                    while (list($key,) = each($_CDATA['domains'])) { ?> 
                      <option value="<?php echo $key; ?>"<?php if ($key == $_VDATA['sm.domain']) echo " selected=\"selected\""; ?>><?php echo $key; ?></option><?php
                    }
                  } else { ?> 
                    <option><?php echo $_LANG['09c']; ?></option><?php
                  } ?>
                </select></var>
                <h4 title="<?php echo $_LANG['09b']; ?>"><?php echo $_LANG['09a']; ?></h4>
                <div></div>
              </li>
              <li>
                <var><input type="checkbox" name="unlisted" value="true"<?php if ($_VDATA['sm.unlisted'] == "true") echo " checked=\"checked\""; ?>" /></var>
                <h4 title="<?php echo $_LANG['09e']; ?>"><?php echo $_LANG['09d']; ?></h4>
                <div></div>
              </li>
              <li class="drow">
                <var><input type="checkbox" name="changefreq" value="true"<?php if ($_VDATA['sm.changefreq'] == "true") echo " checked=\"checked\""; ?>" /></var>
                <h4><?php echo $_LANG['09f']; ?></h4>
                <?php echo $_LANG['09g']; ?> 
                <div></div>
              </li>
            <?php } ?>
            <li<?php if ($_VDATA['sm.enable'] != "true") echo " class=\"drow\""; ?>>
              <var><input type="submit" name="sitemap_Control" value="<?php echo $_LANG['010']; ?>" /></var>
              <h4><?php echo $_LANG['011']; ?></h4>
              <div></div>
            </li>
          </ul>
          <?php if ($_VDATA['sm.enable'] == "true") { ?> 
            <h3><?php echo $_LANG['09h']; ?></h3>
            <ul>
              <li>
                <var><input type="submit" name="sitemap_Commit" value="<?php echo $_LANG['09k']; ?>"<?php if ($_CDATA['smnf'] || $_CDATA['smnw'] || !count($_CDATA['domains'])) echo " disabled=\"disabled\""; ?> /></var>
                <h4 title="<?php echo $_LANG['09i']; ?>"><?php echo $_LANG['09h']; ?></h4>
                <?php echo $_LANG['09j']; ?> 
                <div></div>
              </li>
            </ul>
          <?php } ?> 
        </form>


        <?php if ($count) { ?> 
          <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
            <h3><?php echo $_LANG['09l']; ?></h3>
            <ul>
              <li>
                <var><input type="checkbox" name="hide" value="true"<?php if ($_VDATA['jw.hide'] != "true") echo " checked=\"checked\""; ?> /></var>
                <h4><?php echo $_LANG['09m']; ?></h4>
                <div></div>
              </li>
              <?php if ($_VDATA['jw.hide'] == "false") { ?> 
                <li class="drow">
                  <var><input type="text" size="70" name="writer" value="<?php echo htmlspecialchars($_VDATA['jw.writer']); ?>" /></var>
                  <h4><?php echo $_LANG['09n']; ?></h4>
                  <?php if ($_CDATA['wnf']) {
                    ?><span class="warning"><?php echo $_LANG['09o']; ?></span><?php
                  } else if ($_CDATA['wer']) {
                    ?><span class="warning"><?php echo $_LANG['0a8']; ?></span><br />
                    <?php printf($_LANG['0a9'], htmlspecialchars($_VDATA['jw.writer']));
                  } ?> 
                  <div></div>
                </li>
                <li>
                  <var><input type="text" size="70" name="egg" value="<?php echo htmlspecialchars($_VDATA['jw.egg']); ?>" /></var>
                  <h4><?php echo $_LANG['09p']; ?></h4>
                  <?php if ($_CDATA['jnf']) {
                    ?><span class="warning"><?php echo $_LANG['096']; ?></span><?php
                  } else if ($_CDATA['jnw']) {
                    ?><span class="warning"><?php echo $_LANG['097']; ?></span><?php
                  } ?> 
                  <div></div>
                </li>
                <li class="drow">
                  <var><textarea rows="3" cols="40" name="remuri"><?php echo htmlspecialchars($_VDATA['jw.remuri']); ?></textarea></var>
                  <h4><?php echo $_LANG['09q']; ?></h4>
                  <?php echo $_LANG['09r']; ?> 
                  <div></div>
                </li>
                <li>
                  <var><input type="text" size="20" name="index" value="<?php echo htmlspecialchars($_VDATA['jw.index']); ?>" /></var>
                  <h4><?php echo $_LANG['09s']; ?></h4>
                  <?php echo $_LANG['09t']; ?> 
                  <div></div>
                </li>
                <li class="drow">
                  <var><input type="checkbox" name="ext" value="true"<?php if ($_VDATA['jw.ext'] == "true") echo " checked=\"checked\""; ?> /></var>
                  <h4 title="<?php echo $_LANG['09v']; ?>"><?php echo $_LANG['09u']; ?></h4>
                  <div></div>
                </li>
                <li>
                  <var><input type="text" size="4" name="pagination" value="<?php echo $_VDATA['jw.pagination']; ?>" /></var>
                  <h4 title="<?php echo $_LANG['09z']; ?>"><?php echo $_LANG['09y']; ?></h4>
                  <div></div>
                </li>
                <li class="drow">
                  <h4><?php echo $_LANG['0a0']; ?></h4>
                  <?php echo $_LANG['0a1']; ?>
                  <var><textarea rows="12" cols="78" name="template"><?php echo htmlspecialchars($_VDATA['jw.template']); ?></textarea></var>
                  <div></div>
                </li>
              <?php } ?>
              <li<?php if ($_VDATA['jw.hide'] != "false") echo " class=\"drow\""; ?>>
                <var><input type="submit" name="jwriter_Options" value="<?php echo $_LANG['010']; ?>" /></var>
                <h4><?php echo $_LANG['011']; ?></h4>
                <div></div>
              </li>
            </ul>
            <?php if ($_VDATA['jw.hide'] == "false") { ?> 
              <h3><?php echo $_LANG['0a2']; ?></h3>
              <ul>
                <li>
                  <var><input type="submit" name="jwriter_Commit" value="<?php echo $_LANG['0a4']; ?>"<?php if ($_CDATA['wnf'] || $_CDATA['jnf'] || $_CDATA['jnw']) echo " disabled=\"disabled\""; ?> /></var>
                  <h4><?php echo $_LANG['0a3']; ?></h4>
                  <?php printf($_LANG['0a5']."<br />\n" , sprintf("%01.2f", $_CDATA['indexmem'] / 1048576), $_CDATA['phpmem']); ?> 
                  <div></div>
                </li>
              </ul>
            <?php } ?> 
          </form>
        <?php }
        break;


      case "Search": /* ***** Search ************************** */ ?> 
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['0c0']; ?></h3>
          <ul>
            <li>
              <var><input type="submit" name="search_Purge" value="<?php echo $_LANG['0d2']; ?>" /></var>
              <h4 title="<?php echo $_LANG['0c2']; ?>"><?php echo $_LANG['0c1']; ?></h4>
              <div></div>
            </li>
            <li class="drow">
              <var><strong title="<?php echo $_LANG['0d3']; ?>"><?php echo ceil($_CDATA['cachekb']); ?></strong> kB <big>/</big>
                <input type="text" size="5" name="cachelimit" value="<?php echo $_VDATA['s.cachelimit']; ?>" /> kB</var>
              <h4 title="<?php echo $_LANG['0c4']; ?>"><?php echo $_LANG['0c3']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="checkbox" name="cachegzip" value="on"<?php if ($_VDATA['s.cachegzip'] == "on") echo " checked=\"checked\""; if ($_VDATA['s.cachegzip'] == "disabled") echo " disabled=\"disabled\""; ?> /></var>
              <h4><?php echo $_LANG['0c5']; ?></h4><?php
              if ($_CDATA['cachedno']) { ?> 
                <?php printf($_LANG['0c6'], $_CDATA['cachedno'], sprintf("%01.2f", $_CDATA['cachekb'] / $_CDATA['cachedno']));
              } else { ?> 
                <?php echo $_LANG['0c7'];
              }
              if ($_VDATA['s.cachegzip'] == "disabled") { ?><br />
                <span class="warning"><?php echo $_LANG['0c8']; ?></span><?php
              } ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><input type="submit" name="search_Cache" value="<?php echo $_LANG['010']; ?>" /></var>
              <h4><?php echo $_LANG['011']; ?></h4>
              <div></div>
            </li>
          </ul>
        </form>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['0c9']; ?></h3>
          <ul>
            <li>
              <var><textarea rows="6" cols="40" name="ignore"><?php echo htmlspecialchars($_VDATA['s.ignore']); ?></textarea></var>
              <h4><?php echo $_LANG['0ca']; ?></h4>
              <?php echo $_LANG['0cb']; ?>
              <div></div>
            </li>
            <li class="drow">
              <var><input type="text" size="5" maxlength="2" name="termlimit" value="<?php echo $_VDATA['s.termlimit']; ?>" /> <?php echo $_LANG['0ce']; ?></var>
              <h4 title="<?php echo $_LANG['0cd']; ?>"><?php echo $_LANG['0cc']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="text" size="5" maxlength="2" name="termlength" value="<?php echo $_VDATA['s.termlength']; ?>" /> <?php echo $_LANG['0ch']; ?></var>
              <h4 title="<?php echo $_LANG['0cg']; ?>"><?php echo $_LANG['0cf']; ?></h4>
              <div></div>
            </li>
            <li class="drow">
              <var><input type="checkbox" name="latinacc" value="true"<?php if ($_VDATA['s.latinacc'] == "true") echo " checked=\"checked\""; ?> /></var>
              <h4><?php echo $_LANG['0cs']; ?></h4>
              <?php echo $_LANG['0ct']; ?>
              <div></div>
            </li>
            <li><var><?php echo $_LANG['01t']; ?>: <input type="text" size="5" maxlength="5" name="weight0" value="<?php echo $_VDATA['s.weight'][0]; ?>" /><br />
                   <?php echo $_LANG['0cl']; ?>: <input type="text" size="5" maxlength="5" name="weight1" value="<?php echo $_VDATA['s.weight'][1]; ?>" /><br />
                   <?php echo $_LANG['0cm']; ?>: <input type="text" size="5" maxlength="5" name="weight2" value="<?php echo $_VDATA['s.weight'][2]; ?>" /><br />
                   <span title="<?php echo $_LANG['0co']; ?>"><?php echo $_LANG['0cn']; ?></span>: <input type="text" size="5" maxlength="5" name="weight3" value="<?php echo $_VDATA['s.weight'][3]; ?>" /><br />
                   <?php echo $_LANG['027']; ?>: <input type="text" size="5" maxlength="5" name="weight4" value="<?php echo $_VDATA['s.weight'][4]; ?>" /></var>
              <h4 title="<?php echo $_LANG['0cj']; ?>"><?php echo $_LANG['0ci']; ?></h4>
              <div></div>
            </li>
            <li class="drow">
              <var><?php echo $_LANG['0cq']; ?>: <input type="text" size="5" maxlength="5" name="weight5" value="<?php echo $_VDATA['s.weight'][5]; ?>" /><br />
                   <?php echo $_LANG['0cr']; ?>: <input type="text" size="5" maxlength="5" name="weight6" value="<?php echo $_VDATA['s.weight'][6]; ?>" /></var>
              <h4><?php echo $_LANG['0cp']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><textarea rows="3" cols="30" name="weightedtags"><?php echo htmlspecialchars($_VDATA['s.weightedtags']); ?></textarea></var>
              <h4><?php echo $_LANG['0cu']; ?></h4>
              <?php echo $_LANG['0cv']; ?> 
              <?php echo $_LANG['0j6']; ?> 
              <span class="warning"><?php echo $_LANG['012']; ?></span>: <?php echo $_LANG['0cw']; ?>
              <div></div>
            </li>
            <li class="drow">
              <var><input type="text" size="5" maxlength="4" name="resultlimit" value="<?php echo $_VDATA['s.resultlimit']; ?>" /> <?php echo $_LANG['0cy']; ?></var>
              <h4><?php echo $_LANG['0cx']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="text" size="5" maxlength="4" name="matchingtext" value="<?php echo $_VDATA['s.matchingtext']; ?>" /> <?php echo $_LANG['0ch']; ?></var>
              <h4><?php echo $_LANG['0cz']; ?></h4>
              <div></div>
            </li>
            <li class="drow">
              <var><input type="checkbox" name="orphans" value="show"<?php if ($_VDATA['s.orphans'] == "show") echo " checked=\"checked\""; ?> /></var>
              <h4 title="<?php echo $_LANG['0d1']; ?>"><?php echo $_LANG['0d0']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="submit" name="search_Edit" value="<?php echo $_LANG['010']; ?>" /></var>
              <h4><?php echo $_LANG['011']; ?></h4>
              <div></div>
            </li>
          </ul>
        </form>
        <?php break;


      case "Spider": /* ***** Spider ************************** */ ?> 
        <form action="<?php echo htmlspecialchars($_VDATA['sp.pathto']); ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['0i0']; ?></h3>
          <ul>
            <li>
              <var><label title="<?php echo $_LANG['0j9']; ?>"><?php echo $_LANG['0ir']; ?>:<input type="checkbox" name="fullscan" value="true"<?php if ($_VDATA['sp.fullscan'] == "true") echo " checked=\"checked\" disabled=\"disabled\""; ?> /></label> &nbsp;
              <?php if ($_VDATA['sp.lock'] == "true") {
                $spoo = time() - $_VDATA['sp.progress'];
                $swat = 30; ?> 
                  <input type="submit" name="spider_Force" value="<?php echo $_LANG['0i2']; ?>"<?php if ($_CDATA['snf'] || $_CDATA['ser'] || $spoo <= $swat) echo " disabled=\"disabled\""; ?> /></var>
                <h4><?php echo $_LANG['0i1']; ?></h4>
                <span class="warning"><?php echo $_LANG['0i3']; ?></span>
                <?php if ($spoo <= $swat) { echo $_LANG['0i4']; } else printf($_LANG['0i5'], $swat);
              } else { ?> 
                  <input type="submit" name="spider_Go" value="<?php echo $_LANG['01v']; ?>"<?php if ($_CDATA['snf'] || $_CDATA['ser']) echo " disabled=\"disabled\""; ?> /></var>
                <h4><?php echo $_LANG['0i6']; ?></h4>
                <?php if ($_VDATA['sp.time'] == -1) { ?> 
                  <span class="warning"><?php echo $_LANG['013']; ?></span>: <?php echo $_LANG['0i7'];
                }
              } ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><?php
                if ($_VDATA['sp.time'] == -1) {
                  echo $_LANG['0ia'];
                } else OS_countUp($_VDATA['sp.time']);
              ?></var>
              <input type="hidden" name="key" value="<?php echo $_VDATA['c.spkey']; ?>" />
              <input type="hidden" name="linkback" value="<?php echo $_SDATA['scheme'].'://'.$_SERVER['HTTP_HOST'], $_SERVER['PHP_SELF']; ?>" />
              <h4><?php echo $_LANG['0i9']; ?></h4>
              <div></div>
            </li>
          </ul>
        </form>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['0ib']; ?></h3>
          <ul>
            <li>
              <var><input type="text" size="90" name="pathto" value="<?php echo htmlspecialchars($_VDATA['sp.pathto']); ?>" /></var>
              <h4><?php echo $_LANG['0ic']; ?></h4>
              <?php if ($_CDATA['snf']) {
                ?><span class="warning"><?php echo $_LANG['0id']; ?></span><?php
              } else if ($_CDATA['ser']) {
                ?><span class="warning"><?php echo $_LANG['0ja']; ?></span><br />
                <?php printf($_LANG['0jb'], htmlspecialchars($_VDATA['sp.pathto']));
              } ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><textarea rows="3" cols="90" name="start" class="smooth"><?php echo htmlspecialchars($_VDATA['sp.start']); ?></textarea></var>
              <h4><?php echo $_LANG['0ie']; ?></h4>
              <div></div>
            </li>
            <li>
              <script type="text/javascript">
                function phpdisable() {
                  var chk = document.getElementById("trig_cron").checked;
                  document.getElementById("trig_inte").disabled = (chk) ? "disabled" : "";
                  document.getElementById("trig_emai").disabled = (chk) ? "disabled" : "";
                }
              </script>
              <table cellspacing="0" border="0" class="doubleopt">
                <caption><?php echo $_LANG['0ji']; ?></caption>
                <tbody>
                  <tr>
                    <td rowspan="2" class="padr">
                      <var><input type="checkbox" name="cron" id="trig_cron" value="true"<?php if ($_VDATA['sp.cron'] == "true") echo " checked=\"checked\""; ?>" onclick="phpdisable();" /></var>
                      <h4><?php echo $_LANG['0if']; ?></h4>
                      <span class="warning"><?php echo $_LANG['013']; ?></span>: <?php echo $_LANG['0ig']; ?> 
                      <div></div>
                    </td>
                    <td rowspan="2" class="mini">
                      <?php echo $_LANG['0jk']; ?> 
                    </td>
                    <td class="padl">
                      <var><input type="text" size="5" name="interval" id="trig_inte" value="<?php echo $_VDATA['sp.interval']; ?>"<?php if ($_VDATA['sp.cron'] == "true") echo " disabled=\"disabled\""; ?> /> Hours</var>
                      <h4 title="<?php echo $_LANG['0ii']; ?>"><?php echo $_LANG['0ih']; ?></h4>
                      <div></div>
                    </td>
                  </tr>
                  <tr>
                    <td class="padl">
                      <var><input type="text" size="45" name="email" id="trig_emai" value="<?php echo htmlspecialchars($_VDATA['sp.email']); ?>"<?php if ($_VDATA['sp.cron'] == "true") echo " disabled=\"disabled\""; ?> /></var>
                      <h4 title="<?php echo $_LANG['0ip']; ?>"><?php echo $_LANG['0io']; ?></h4>
                      <div></div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </li>
            <li class="drow">
              <var><input type="checkbox" name="seamless" value="true"<?php if ($_VDATA['sp.seamless'] == "true") echo " checked=\"checked\""; ?> /></var>
              <h4><?php echo $_LANG['0i8']; ?></h4>
              <?php echo $_LANG['0jl']; ?> 
              <div></div>
            </li>
            <li>
              <var><input type="text" size="7" name="pagelimit" value="<?php echo $_VDATA['sp.pagelimit']; ?>" /></var>
              <h4><?php echo $_LANG['0ij']; ?></h4>
              <div></div>
            </li>
            <li class="drow">
              <var><input type="text" size="7" name="filesizelimit" value="<?php echo (int)($_VDATA['sp.filesizelimit'] / 1024); ?>" /> kB</var>
              <h4><?php echo $_LANG['0jn']; ?></h4>
              <?php echo $_LANG['0jo']; ?> 
              <div></div>
            </li>
            <li>
              <var><input type="text" size="5" name="linkdepth" value="<?php echo $_VDATA['sp.linkdepth']; ?>" /></var>
              <h4><?php echo $_LANG['0jg']; ?></h4>
              <?php echo $_LANG['0jh']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><input type="text" size="20" name="defcat" value="<?php echo $_VDATA['sp.defcat']; ?>" /></var>
              <h4 title="<?php echo $_LANG['0il']; ?>"><?php echo $_LANG['0ik']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><textarea rows="4" cols="40" name="autocat"><?php echo htmlspecialchars($_VDATA['sp.autocat']); ?></textarea></var>
              <h4><?php echo $_LANG['0im']; ?></h4>
              <?php echo $_LANG['0in']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><input type="checkbox" name="cookies" value="true"<?php if ($_VDATA['sp.cookies'] == "true") echo " checked=\"checked\""; ?> /></var>
              <h4><?php echo $_LANG['0jc']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="checkbox" name="utf8" value="true"<?php if ($_VDATA['sp.utf8'] == "true") echo " checked=\"checked\""; ?> /></var>
              <h4><?php echo $_LANG['0is']; ?></h4>
              <?php echo $_LANG['0it'];
              if ($_CDATA['indexpages']) { ?> 
                <em><?php printf($_LANG['0iu'], sprintf("%01.1f", $_CDATA['utf8pages'] * 100 / $_CDATA['indexpages'])); ?></em>
              <?php } ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><?php
                foreach ($_MIME->ctype as $key => $value) {
                  if ($key != "none") { ?> 
                    <em><small<?php if (!$value->ready) echo " class=\"warning\" title=\"".sprintf($_LANG['0jm'], $value->handler)."\""; ?>>
                      <?php echo implode(", ", $value->mtypes); ?> 
                    </small></em> :
                    <input type="checkbox" name="typeIndex[]" value="<?php echo $key; ?>"<?php if ($value->index) echo " checked=\"checked\""; ?> /><br /><?php
                  }
                }
              ?></var>
              <h4><?php echo $_LANG['0iv']; ?></h4>
              <?php echo $_LANG['0jf']; ?>
              <?php if ($_MIME->needtemp() && !is_writable(".".DIRECTORY_SEPARATOR."temp")) { ?> 
                <hr class="msgsep" />
                <em class="warning"><?php echo $_LANG['0iq']; ?>:</em><br />
                <code><?php echo dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR."temp"; ?></code>
              <?php } ?>
              <div></div>
            </li>
            <li>
              <var><textarea rows="3" cols="30" name="mimeAccept"><?php echo htmlspecialchars($_VDATA['sp.type.accept']); ?></textarea></var>
              <h4><?php echo $_LANG['0jd']; ?></h4>
              <?php echo $_LANG['0je']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><textarea rows="4" cols="40" name="domains"><?php echo htmlspecialchars($_VDATA['sp.domains']); ?></textarea></var>
              <h4><?php echo $_LANG['0iw']; ?></h4>
              <?php echo $_LANG['0ix']; ?> 
              <div></div>
            </li>
            <li>
              <var><textarea rows="4" cols="50" name="require"><?php echo htmlspecialchars($_VDATA['sp.require']); ?></textarea></var>
              <h4 title="<?php echo $_LANG['0j8']; ?>"><?php echo $_LANG['0j7']; ?></h4>
              <?php echo $_LANG['0iz']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><textarea rows="6" cols="50" name="ignore"><?php echo htmlspecialchars($_VDATA['sp.ignore']); ?></textarea></var>
              <h4><?php echo $_LANG['0iy']; ?></h4>
              <?php echo $_LANG['0iz']; ?> 
              <div></div>
            </li>
            <li>
              <var><textarea rows="4" cols="40" name="extensions"><?php echo htmlspecialchars($_VDATA['sp.extensions']); ?></textarea></var>
              <h4><?php echo $_LANG['0j0']; ?></h4>
              <?php echo $_LANG['0j1']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><textarea rows="4" cols="40" name="remtitle"><?php echo htmlspecialchars($_VDATA['sp.remtitle']); ?></textarea></var>
              <h4><?php echo $_LANG['0j2']; ?></h4>
              <?php echo $_LANG['0j3']; ?> 
              <?php echo $_LANG['0iz']; ?> 
              <div></div>
            </li>
            <li>
              <var><textarea rows="3" cols="40" name="remtags"><?php echo htmlspecialchars($_VDATA['sp.remtags']); ?></textarea></var>
              <h4><?php echo $_LANG['0j4']; ?></h4>
              <?php echo $_LANG['0j5']; ?> 
              <?php echo $_LANG['0j6']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><input type="submit" name="spider_Edit" value="<?php echo $_LANG['010']; ?>" /></var>
              <h4><?php echo $_LANG['011']; ?></h4>
              <div></div>
            </li>
          </ul>
        </form>
        <?php break;


      case "Stats": /* ***** Statistics *********************** */ ?> 
        <?php if ($_VDATA['sp.lasttime'] != -1) { ?> 
          <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
            <h3><?php echo $_LANG['0l0']; ?></h3>
            <ul>
              <li>
                <var><?php OS_countUp($_VDATA['sp.time']); ?></var>
                <h4><?php echo $_LANG['0l1']; ?></h4>
                <div></div>
              </li>
              <li class="drow">
                <var><strong><?php printf("%01.2f", $_VDATA['sp.lasttime']); ?></strong> seconds</var>
                <h4 title="<?php echo $_LANG['0l3']; ?>"><?php echo $_LANG['0l2']; ?></h4>
                <div></div>
              </li>
              <li>
                <var><strong><?php printf("%01.2f", $_VDATA['sp.alldata'] / 1048576); ?></strong>MB</var>
                <h4><?php echo $_LANG['0l4']; ?></h4>
                <div></div>
              </li>
              <li class="drow">
                <var><strong><?php printf("%01.2f", $_CDATA['indexmem'] / 1048576); ?></strong>MB</var>
                <h4><?php echo $_LANG['0l5']; ?></h4>
                <div></div>
              </li>
              <li>
                <var><strong><?php echo ($_VDATA['sp.alldata']) ? sprintf("%01.1f", $_CDATA['indexmem'] * 100 / $_VDATA['sp.alldata']) : "--.-"; ?></strong>%</var>
                <h4><?php echo $_LANG['0l6']; ?></h4>
                <div></div>
              </li>
              <li class="drow">
                <var><strong><?php echo $_CDATA['allpages']; ?></strong></var>
                <h4><?php echo $_LANG['0lv']; ?></h4>
                <div></div>
              </li>
              <li>
                <var><strong><?php echo $_CDATA['indexpages']; ?></strong></var>
                <h4><?php echo $_LANG['0l8']; ?></h4>
                <div></div>
              </li>
              <li class="drow">
                <var><strong><?php echo $_CDATA['indexsrchd']; ?></strong></var>
                <h4 title="<?php echo ($_VDATA['s.orphans'] == "show") ? $_LANG['0l9'] : $_LANG['0la']; ?>"><?php echo $_LANG['0l7']; ?></h4>
                <div></div>
              </li>
              <li>
                <var><strong><?php echo $_CDATA['indexcats']; ?></strong></var>
                <h4><?php echo $_LANG['0lb']; ?></h4>
                <div></div>
              </li>
              <?php if ($_CDATA['indexpages']) { ?> 
                <li class="drow">
                  <var><?php foreach ($_CDATA['encodings'] as $encodings) {
                    if ($encodings['encoding'] == "-") $encodings['encoding'] = $_LANG['0le'];
                    echo "{$encodings['encoding']}: <strong>".sprintf("%01.1f", $encodings['num'] * 100 / $_CDATA['indexpages'])."%</strong><br />\n";
                  } ?></var>
                  <h4 title="<?php echo $_LANG['0ld']; ?>"><?php echo $_LANG['0lc']; ?></h4>
                  <div></div>
                </li>
              <?php } ?> 
            </ul>
          </form>
        <?php } ?> 

        <?php if ($_VDATA['s.cachetime']) { ?> 
          <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
            <h3><?php echo $_LANG['0lx']; ?></h3>
            <ul>
              <li>
                <var><strong><?php printf("%01.2f", $_CDATA['sperhour']); ?></strong></var>
                <h4><?php echo $_LANG['0ly']; ?></h4>
                <div></div>
              </li>
              <li class="drow">
                <var><strong><?php printf("%01.2f", $_CDATA['sperhour'] * 24); ?></strong></var>
                <h4><?php echo $_LANG['0m1']; ?></h4>
                <div></div>
              </li>
              <li>
                <var><strong><?php printf("%01.2f", $_CDATA['sravg']); ?></strong></var>
                <h4 title="<?php echo $_LANG['0m0']; ?>"><?php echo $_LANG['0lz']; ?></h4>
                <div></div>
              </li>
            </ul>
          </form>
        <?php } ?> 

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="optionform">
          <h3><?php echo $_LANG['0lf']; ?></h3>
          <ul>
            <li>
              <var><input type="submit" name="stats_Reset" value="<?php echo $_LANG['0li']; ?>" onclick="return confirm('<?php echo $_LANG['0lj']; ?>');" /></var>
              <h4><?php echo $_LANG['0lg']; ?></h4>
              <?php echo $_LANG['0lh']; ?> 
              <div></div>
            </li>
            <li class="drow">
              <var><?php OS_countUp($_VDATA['s.cachetime']); ?></var>
              <h4><?php echo $_LANG['0ln']; ?></h4>
              <div></div>
            </li>
            <li>
              <var><input type="text" size="5" maxlength="3" name="cachereset" value="<?php echo $_VDATA['s.cachereset']; ?>" /> Days</var>
              <h4 title="<?php echo $_LANG['0ll']; ?>"><?php echo $_LANG['0lk']; ?></h4>
              <div></div>
            </li>
            <li class="drow">
              <var>Address: <input type="text" size="45" name="cacheemail" value="<?php echo $_VDATA['s.cacheemail']; ?>" /></var>
              <h4>Email Query Log Before Reset</h4>
              <div></div>
            </li>
            <li>
              <var><input type="submit" name="stats_Autoreset" value="<?php echo $_LANG['010']; ?>" /></var>
              <h4><?php echo $_LANG['011']; ?></h4>
              <div></div>
            </li>
          </ul>
        </form>

        <?php $select = $_DDATA['link']->query("SELECT `astyped`, `hits`, `results`, `lasthit` FROM `{$_DDATA['tablestat']}` ORDER BY `hits` DESC, `lasthit` DESC;")->fetchAll();
        if (count($select)) { ?> 
          <table cellspacing="0" border="0" id="querylog">
            <thead>
              <tr>
                <td colspan="4"><?php echo $_LANG['0lo']; ?></td>
              </tr>
              <tr>
                <th title="<?php echo $_LANG['0lq']; ?>"><?php echo $_LANG['0lp']; ?></th>
                <th title="<?php echo $_LANG['0ls']; ?>"><?php echo $_LANG['0lr']; ?></th>
                <th title="<?php echo $_LANG['0lm']; ?>" class="nosort"><?php echo $_LANG['0lw']; ?></th>
                <th title="<?php echo $_LANG['0lu']; ?>"><?php echo $_LANG['0lt']; ?></th>
              </tr>
            </thead>
            <tbody>
              <?php $y = 1; $timeColl = array();
              foreach ($select as $row) { ?> 
                <tr<?php echo ($y++ % 2) ? " class=\"drow\"" : ""; ?>>
                  <th><?php echo htmlspecialchars($row['astyped']); ?></th>
                  <td><?php echo $row['hits']; ?></td>
                  <td><?php echo $row['results']; ?></td>
                  <td><?php
                    $timeColl[] = $row['lasthit'];
                    $diff = time() - $row['lasthit'];
                    $days = floor($diff / 86400);
                    if (!($days = floor($diff / 86400))) {
                      if (!($hours = floor($diff / 3600))) {
                        if (!($minutes = floor($diff / 60))) {
                          $final = $diff." {$_LANG['017']}";
                        } else $final = $minutes." {$_LANG['016']}";
                      } else $final = $hours." {$_LANG['015']}";
                    } else $final = $days." {$_LANG['014']}";
                    echo $final, " {$_LANG['018']}";
                  ?></td>
                </tr>
              <?php } ?> 
            </tbody>
          </table>
          <script type="text/javascript"><!--
            var qlnow = 1;
            var headers = document.getElementById("querylog").tHead.getElementsByTagName("th");
            for (var x = 0; x < headers.length; x++)
              if (headers[x].className != "nosort")
                headers[x].className = (x == qlnow) ? "on" : "off";
            headers[0].onclick = new Function("qlsort('asc', 0);");
            headers[1].onclick = new Function("qlsort('dsc', 1);");
            headers[3].onclick = new Function("qlsort('dsc', 3);");
            var times = [<?php foreach($timeColl as $tc) echo "$tc, "; ?>0];
            var qlrows = document.getElementById("querylog").tBodies[0].rows;
            var qldata = [];
            for (var x = 0; x < qlrows.length; x++) {
              qldata[x] = [];
              for (var y = 0; y < qlrows[x].cells.length; y++) {
                if (y != 3) {
                  qldata[x][y] = qlrows[x].cells[y].firstChild.nodeValue;
                  if (y == 1) qldata[x][y] = parseInt(qldata[x][y]);
                } else qldata[x][y] = times[x];
              }
            }
            function qlsort(direc, column) {
              if (column != qlnow) {
                headers[qlnow].className = "off";
                qldata.sort(new Function("a, b", "var col = " + column + ", dir = " + ((direc == "asc") ? 1 : -1) + "; var acol = (!col) ? a[col].toLowerCase() : a[col]; var bcol = (!col) ? b[col].toLowerCase() : b[col]; if (acol == bcol) return 0; return (acol > bcol) ? dir : -(dir);"));
                for (var x = 0; x < qlrows.length; x++) {
                  qlrows[x].cells[0].firstChild.nodeValue = qldata[x][0];
                  qlrows[x].cells[1].firstChild.nodeValue = qldata[x][1];
                  qlrows[x].cells[2].firstChild.nodeValue = qldata[x][2];
                  var now = new Date(qldata[x][3] * 1000);
                  var sfinal = "";
                  var diff = <?php echo time(); ?> - now.getTime() / 1000;
                  var days = Math.floor(diff / 86400);
                  if (!days) {
                    var hours = Math.floor(diff / 3600);
                    if (!hours) {
                      var minutes = Math.floor(diff / 60);
                      if (!minutes) {
                        sfinal = diff + " <?php echo $_LANG['017']; ?>";
                      } else sfinal = minutes + " <?php echo $_LANG['016']; ?>";
                    } else sfinal = hours + " <?php echo $_LANG['015']; ?>";
                  } else sfinal = days + " <?php echo $_LANG['014']; ?>";
                  qlrows[x].cells[3].firstChild.nodeValue = sfinal + " <?php echo $_LANG['018']; ?>";
                }
                qlnow = column;
                headers[qlnow].className = "on";
              }
            }
          // --></script>
        <?php }
        break;


      default: /* ***** Entry List ******************************* */
        if (!count($_CDATA['categories']) && ($_CDATA['command'] != "add" || !isset($_CDATA['add']) || $_CDATA['add'] == "Confirm")) { ?> 
          <h2><?php echo $_LANG['0f0']; ?></h2>
          <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="editform">
            <input type="submit" name="add_Add" value="<?php echo $_LANG['0f1']; ?>" title="<?php echo $_LANG['0f2']; ?>" />
          </form>

        <?php } else {
          if ($_CDATA['command'] == "action") {
            switch ($_CDATA['input']) {
              case "category": /* ***************************** */ ?> 
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="editform">
                  <h3><?php echo $_LANG['0f3']; ?></h3>
                  <ul>
                    <li><h4><?php echo $_LANG['0f4']; ?></h4>
                      <select name="categoryExist" size="1" title="<?php echo $_LANG['0f5']; ?>" onchange="document.getElementById('editform').categoryNew.disabled=(this.value!='-')?'disabled':'';">
                        <option value="-" selected="selected"><?php echo $_LANG['0f6']; ?> &gt;&gt;</option>
                        <?php foreach($_CDATA['categories'] as $category) { ?> 
                          <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php } ?> 
                      </select>
                      <input type="text" name="categoryNew" />
                      <?php if ($_GET['start']) { ?> 
                        <input type="hidden" name="start" value="<?php echo $_GET['start']; ?>" />
                      <?php } ?> 
                      <input type="hidden" name="actionIDs" value="<?php echo implode("::", $_POST['action']); ?>" />
                      <input type="hidden" name="Confirm" value="catConfirm" />
                    </li>
                    <li><h4><a href="<?php echo $_SERVER['PHP_SELF'], (($_GET['start']) ? "?start={$_GET['start']}" : ""); ?>" title="<?php echo $_LANG['0f7']; ?>"><?php echo $_LANG['01s']; ?></a></h4>
                      <input type="submit" name="action_Confirm" value="<?php echo $_LANG['010']; ?>" />
                    </li>
                  </ul>
                </form>
                <?php break;

              case "sm.changefreq": /* ************************ */ ?> 
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="editform">
                  <h3><?php echo $_LANG['0f9']; ?></h3>
                  <ul>
                    <li><h4><?php echo $_LANG['0fa']; ?></h4>
                      <select name="changefreq" size="1">
                        <?php while (list($key, $value) = each($_LANG['langcf'])) {
                          ?><option value="<?php echo $key; ?>"<?php if ($key == "weekly") echo " selected=\"selected\""; ?>><?php echo $value; ?></option>
                          <?php
                        } ?> 
                      </select>
                      <input type="hidden" name="actionIDs" value="<?php echo implode("::", $_POST['action']); ?>" />
                      <input type="hidden" name="Confirm" value="smcConfirm" />
                    </li>
                    <li><h4><a href="<?php echo $_SERVER['PHP_SELF'], (($_GET['start']) ? "?start={$_GET['start']}" : ""); ?>" title="<?php echo $_LANG['0f7']; ?>"><?php echo $_LANG['01s']; ?></a></h4>
                      <input type="submit" name="action_Confirm" value="<?php echo $_LANG['010']; ?>" />
                    </li>
                  </ul>
                </form>
                <?php break;

              case "sm.priority": /* ************************** */ ?> 
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="editform">
                  <h3><?php echo $_LANG['0fb']; ?></h3>
                  <ul>
                    <li><h4><?php echo $_LANG['0fc']; ?></h4>
                      <input type="text" name="priority" size="5" value="0.5" />
                      <input type="hidden" name="actionIDs" value="<?php echo implode("::", $_POST['action']); ?>" />
                      <input type="hidden" name="Confirm" value="smpConfirm" />
                    </li>
                    <li><h4><a href="<?php echo $_SERVER['PHP_SELF'], (($_GET['start']) ? "?start={$_GET['start']}" : ""); ?>" title="<?php echo $_LANG['0f7']; ?>"><?php echo $_LANG['01s']; ?></a></h4>
                      <input type="submit" name="action_Confirm" value="<?php echo $_LANG['010']; ?>" />
                    </li>
                  </ul>
                </form>
                <?php break;
            }

          } else if (($_CDATA['command'] == "edit" && isset($_CDATA['edit']) && $_CDATA['edit'] != "Confirm") ||
                     ($_CDATA['command'] == "add" && isset($_CDATA['add']) && $_CDATA['add'] != "Confirm")) { ?> 
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="editform">
              <h3><?php echo ($_CDATA['command'] == "edit") ? $_LANG['0fd'] : $_LANG['0fe']; ?></h3>
              <ul>
                <li><h4><?php echo $_LANG['027']; ?></h4>
                  <?php if ($_CDATA['command'] == "edit") { ?> 
                    <a href="<?php echo $_CDATA['row']['uri']; ?>"><?php echo $_CDATA['row']['uri']; ?></a>
                    <input type="hidden" name="uri" value="<?php echo $_CDATA['row']['uri']; ?>" />
                  <?php } else { ?> 
                    <input type="text" name="uri" value="<?php echo $_CDATA['row']['uri']; ?>" size="54" />
                  <?php } ?> 
                </li>
                <?php if ($_CDATA['command'] == "edit") { ?> 
                  <li><h4><?php echo $_LANG['0fg']; ?></h4>
                    <?php echo ($_CDATA['row']['encoding'] != "-") ? $_CDATA['row']['encoding'] : $_LANG['0fh']; ?> 
                  </li>
                <?php } ?> 
                <li><h4><?php echo $_LANG['01t'].(($_CDATA['row']['locked'] == "true") ? " &copy;" : ""); ?></h4>
                  <input type="text" name="title" value="<?php echo htmlspecialchars($_CDATA['row']['title']); ?>" size="54" />
                </li>
                <li><h4><?php echo $_LANG['0f4']; ?></h4>
                  <select name="categoryExist" size="1" title="<?php echo $_LANG['0f5']; ?>" onchange="document.getElementById('editform').categoryNew.disabled=(this.value!='-')?'disabled':'';">
                    <option value="-"><?php echo $_LANG['0f6']; ?> &gt;&gt;</option>
                    <?php foreach($_CDATA['categories'] as $category) { ?> 
                      <option value="<?php echo htmlspecialchars($category); ?>"<?php if ($_CDATA['row']['category'] == $category) echo " selected=\"selected\""; ?>><?php echo htmlspecialchars($category); ?></option>
                    <?php } ?> 
                  </select>
                  <input type="text" name="categoryNew" />
                  <?php if (count($_CDATA['categories'])) { ?> 
                    <script type="text/javascript"><!--
                      document.getElementById('editform').categoryNew.disabled = "disabled";
                    // --></script>
                  <?php }
                  if ($_CDATA['command'] == "edit") { ?> 
                    <input type="hidden" name="categoryNow" value="<?php echo $_CDATA['row']['category']; ?>" />
                  <?php } ?> 
                </li>
                <li><h4><?php echo $_LANG['0fj'].(($_CDATA['row']['locked'] == "true") ? " &copy;" : ""); ?></h4>
                  <textarea rows="4" cols="40" name="description"><?php echo $_CDATA['row']['description']; ?></textarea>
                </li>
                <li><h4><?php echo $_LANG['0fk'].(($_CDATA['row']['locked'] == "true") ? " &copy;" : ""); ?></h4>
                  <textarea rows="3" cols="40" name="keywords"><?php echo $_CDATA['row']['keywords']; ?></textarea>
                </li>
                <li><h4><?php echo $_LANG['0gb']; ?></h4>
                  <label><?php echo $_LANG['0fn']; ?> <input type="checkbox" name="unlist" value="true"<?php echo ($_CDATA['row']['unlist'] == "true") ? " checked=\"checked\"" : ""; ?> /></label> &nbsp;
                  <label><?php echo $_LANG['0fm']; ?> <input type="checkbox" name="locked" value="true"<?php echo ($_CDATA['row']['locked'] == "true") ? " checked=\"checked\"" : ""; ?> /></label>
                </li>
                <?php if ($_VDATA['sm.enable'] == "true") { ?> 
                  <li class="title"><h3><?php echo $_LANG['0fo']; ?></h3></li>
                  <li><h4><?php echo $_LANG['0fp']; ?></h4>
                    <input type="checkbox" name="smlist" value="true"<?php echo ($_CDATA['row']['sm.list'] == "true") ? " checked=\"checked\"" : ""; ?> />
                  </li>
                  <li><h4><?php echo $_LANG['0fs']; ?></h4>
                    <select name="changefreq" size="1"<?php if ($_VDATA['sm.changefreq'] == "true") echo " disabled=\"disabled\""; ?>>
                      <?php while (list($key, $value) = each($_LANG['langcf'])) {
                        ?><option value="<?php echo $key; ?>"<?php if ($_CDATA['row']['sm.changefreq'] == $key) echo " selected=\"selected\""; ?>><?php echo $value; ?></option>
                        <?php
                      } ?> 
                    </select>
                  </li>
                  <li><h4><?php echo $_LANG['0ft']; ?> (0.0 ~ 1.0)</h4>
                    <input type="text" name="priority" value="<?php echo $_CDATA['row']['sm.priority']; ?>" size="5" />
                  </li>
                <?php } ?> 
                <li><h4><a href="<?php echo $_SERVER['PHP_SELF'], (($_GET['start']) ? "?start={$_GET['start']}" : ""); ?>" title="<?php echo $_LANG['0f7']; ?>"><?php echo $_LANG['01s']; ?></a></h4>
                  <?php if ($_GET['start']) { ?> 
                    <input type="hidden" name="start" value="<?php echo $_GET['start']; ?>" />
                  <?php }
                  if ($_CDATA['command'] == "edit") { ?> 
                    <input type="hidden" name="md5" value="<?php echo $_CDATA['row']['md5']; ?>" />
                  <?php } ?> 
                  <input type="submit" name="<?php echo $_CDATA['command']; ?>_Confirm" value="<?php echo $_LANG['010']; ?>" />
                </li>
                <?php if ($_CDATA['command'] == "add") { ?> 
                  <li><p><small><?php echo $_LANG['012']; ?>: <?php echo $_LANG['0fu']; ?></small></p></li>
                <?php } ?> 
              </ul>
            </form>

          <?php } else { ?> 
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="mainlist">
              <table cellspacing="0" border="0">
                <thead>
                  <tr>
                    <th colspan="4">
                      <?php if ($_VDATA['c.column'] == "uri") {
                        if (count($_CDATA['categories']) > 1 && $_VDATA['cf.category'] == "-" ) {
                          printf(($_VDATA['c.sortby'] == "col1") ? $_SDATA['orderby1'] : $_SDATA['orderby2'], $_LANG['027'], "col1");
                        } else echo ($_VDATA['c.sortby'] == "col1") ? sprintf($_SDATA['orderby1'], $_LANG['027']) : $_LANG['027'];
                        echo " / ", sprintf($_SDATA['orderby3'], $_LANG['01t'], "title");
                      } else {
                        echo sprintf($_SDATA['orderby3'], $_LANG['027'], "uri"), " / ";
                        if (count($_CDATA['categories']) > 1 && $_VDATA['cf.category'] == "-") {
                          printf(($_VDATA['c.sortby'] != "col1") ? $_SDATA['orderby2'] : $_SDATA['orderby1'], $_LANG['01t'], "col1");
                        } else echo ($_VDATA['c.sortby'] == "col1") ? sprintf($_SDATA['orderby1'], $_LANG['01t']) : $_LANG['01t'];
                      } ?> 
                    </th>
                    <th class="min">
                      <?php if (count($_CDATA['categories']) > 1 && $_VDATA['cf.category'] == "-") {
                        printf(($_VDATA['c.sortby'] == "col2") ? $_SDATA['orderby1'] : $_SDATA['orderby2'], $_LANG['0f4'], "col2");
                      } else echo ($_VDATA['c.sortby'] == "col2") ? sprintf($_SDATA['orderby1'], $_LANG['0f4']) : $_LANG['0f4']; ?> 
                    </th>
                    <th class="min"><?php echo $_LANG['0fv']; ?></th>
                    <th class="min" colspan="3"><?php echo ($_VDATA['sm.enable'] == "true") ? $_LANG['0fo'] : $_LANG['0fw']; ?></th>
                  </tr>
                  <tr id="filters">
                    <th>
                      <?php echo $_LANG['0fx']; ?> 
                    </th>
                    <td class="min">
                      <input type="checkbox" name="new" value="true"<?php echo ($_VDATA['cf.new'] == "true") ? " checked=\"checked\"" : ""; ?><?php echo ($_CDATA['new']) ? " title=\"{$_LANG['0fy']}\"" : " title=\"{$_LANG['0fz']}\" disabled=\"disabled\""; ?>
                    /></td>
                    <td>
                      <label><?php echo $_LANG['0g0']; ?> 
                        <input type="text" name="textmatch" value="<?php echo htmlspecialchars($_VDATA['cf.textmatch']); ?>" title="<?php echo $_LANG['0g1']; ?>" />
                      </label>
                    </td>
                    <td>
                      <label><?php echo $_LANG['0g2']; ?> 
                        <input type="text" name="textexclude" value="<?php echo htmlspecialchars($_VDATA['cf.textexclude']); ?>" title="<?php echo $_LANG['0g3']; ?>" />
                      </label>
                    </td>
                    <td>
                      <?php if (count($_CDATA['categories']) > 1) { ?> 
                        <select name="category" size="1" title="<?php echo $_LANG['0g4']; ?>">
                          <option value="-"<?php echo ($_VDATA['cf.category'] == "-") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['0g6']; ?></option>
                          <?php foreach($_CDATA['categories'] as $category) { ?> 
                            <option value="<?php echo htmlspecialchars($category); ?>"<?php if ($_VDATA['cf.category'] == $category) echo " selected=\"selected\""; ?>><?php echo htmlspecialchars($category); ?></option>
                          <?php } ?> 
                        </select>
                      <?php } else echo "&ndash;"; ?> 
                    </td>
                    <td>
                      <select name="status" size="1" title="<?php echo $_LANG['0g5']; ?>">
                        <option value="All"<?php echo ($_VDATA['cf.status'] == "All") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['0g6']; ?></option>
                        <option value="OK"<?php echo ($_VDATA['cf.status'] == "OK") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01j']; ?></option>
                        <option value="NotOK"<?php echo ($_VDATA['cf.status'] == "NotOK") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01y']; ?></option>
                        <option value="Orphan"<?php echo ($_VDATA['cf.status'] == "Orphan") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01k']; ?></option>
                        <option value="Added"<?php echo ($_VDATA['cf.status'] == "Added") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01n']; ?></option>
                        <option value="Blocked"<?php echo ($_VDATA['cf.status'] == "Blocked") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01h']; ?></option>
                        <option value="Not Found"<?php echo ($_VDATA['cf.status'] == "Not Found") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01g']; ?></option>
                        <option value="Unlisted"<?php echo ($_VDATA['cf.status'] == "Unlisted") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01i']; ?></option>
                        <option value="Unread"<?php echo ($_VDATA['cf.status'] == "Unread") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01l']; ?></option>
                        <option value="Indexed"<?php echo ($_VDATA['cf.status'] == "Indexed") ? " selected=\"selected\"" : ""; ?>><?php echo $_LANG['01m']; ?></option>
                      </select>
                    </td>
                    <td colspan="3">
                      <input type="submit" name="filter_Set" value="<?php echo $_LANG['01u']; ?>" title="<?php echo $_LANG['0ge']; ?>" />
                      <input type="submit" name="filter_Clear" value="<?php echo $_LANG['0gf']; ?>" title="<?php echo $_LANG['0gg']; ?>"<?php if ($_CDATA['nofilters']) echo " disabled=\"disabled\""; ?>
                    /></td>
                  </tr>
                  <?php ob_start();
                    ?>  <tr>
                      <td class="actions" colspan="3">
                        <input type="checkbox" name="selectall" id="allTypeA" title="<?php echo $_LANG['0gh']; ?>" disabled="disabled" onclick="checkAll((this.checked)?'checked':'');" />
                        <select name="TypeA" id="TypeA" size="1" title="<?php echo $_LANG['0gi']; ?>"<?php if (!$_CDATA['rows']) echo " disabled=\"disabled\""; ?>>
                          <option value="null"><?php echo $_LANG['0gj']; ?></option>
                          <option value="delete"><?php echo $_LANG['0gk']; ?></option>
                          <option value="unlist"><?php echo $_LANG['0fl']; ?></option>
                          <option value="category"><?php echo $_LANG['0gn']; ?></option>
                          <option value="lock"><?php echo $_LANG['0ga']; ?></option>
                          <option value="respider"><?php echo $_LANG['0ff']; ?></option><?php
                          if ($_VDATA['sm.enable'] == "true") { ?> 
                            <option value="sm.unlist"><?php echo $_LANG['0go']; ?></option><?php
                            if ($_VDATA['sm.changefreq'] != "true") { ?> 
                              <option value="sm.changefreq"><?php echo $_LANG['0gq']; ?></option><?php
                            } ?> 
                            <option value="sm.priority"><?php echo $_LANG['0gr']; ?></option><?php
                          } ?> 
                        </select>
                        <input type="submit" name="action_TypeA" value="<?php echo $_LANG['01v']; ?>" title="<?php echo $_LANG['0gt']; ?>" onclick="return actionGo('TypeA');"<?php if (!$_CDATA['rows']) echo " disabled=\"disabled\""; ?> />
                        <?php echo $_LANG['0gu']; ?> <input type="submit" name="add_Add" value="<?php echo $_LANG['0f1']; ?>" title="<?php echo $_LANG['0f2']; ?>" />
                      </td>
                      <td colspan="3">
                        <?php if ($_CDATA['count'] > $_VDATA['c.pagination']) {
                          echo $_LANG['0gv']; ?>:
                          <?php for ($z = 0; $z < $_CDATA['count']; $z += $_VDATA['c.pagination']) {
                            if ($_CDATA['start'] != $z) { ?> 
                              <a href="<?php echo $_SERVER['PHP_SELF'], (($z) ? "?start=$z" : ""); ?>"><?php echo ($z / $_VDATA['c.pagination'] + 1); ?></a>
                            <?php } else { ?> 
                              <strong><?php echo ($z / $_VDATA['c.pagination'] + 1); ?></strong>
                            <?php }
                          }
                        } else echo "&ndash;"; ?> 
                      </td>
                      <td colspan="3">
                        <input type="text" name="showTypeA" size="3" maxlength="3" value="<?php echo $_VDATA['c.pagination']; ?>" title="<?php echo $_LANG['0gw']; ?> (10-999)" />
                        <input type="submit" name="show_TypeA" value="<?php echo $_LANG['01v']; ?>" onclick="if(document.getElementById('mainlist').showTypeA.value==pagination)return false;" />
                      </td>
                    </tr><?php
                  $_CDATA['paginateHTML'] = ob_get_contents();
                  ob_end_flush(); ?> 
                </thead>
                <tfoot>
                  <?php echo str_replace("TypeA", "TypeB", $_CDATA['paginateHTML']); ?> 
                </tfoot>
                <tbody>
                  <?php $y = 0;
                  foreach ($_CDATA['list'] as $row) { ?> 
                    <tr<?php echo ($y++ % 2) ? "" : " class=\"drow\""; ?>>
                      <th colspan="4" class="titlecol"><?php
                        $row['front'] = ($_VDATA['c.column'] == "uri") ? str_replace($_SDATA['scheme']."://", "", $row['uri']) : htmlspecialchars($row['title']);
                        $row['back'] = ($_VDATA['c.column'] == "uri") ? htmlspecialchars($row['title']) : str_replace($_SDATA['scheme']."://", "", $row['uri']); ?>
                        <input type="checkbox" name="action[]" value="<?php echo $row['md5']; ?>"
                        />&nbsp;<a href="<?php echo $row['uri']; ?>" <?php if ($row['new'] == "true") echo " class=\"strong\""; ?> title="<?php echo $row['back']; ?>"><?php echo ($row['front']) ? $row['front'] : "&ndash;"; ?></a><?php
                        if ($row['locked'] == "true") echo "&nbsp;<span title=\"{$_LANG['0fq']}\">&copy;</span>"; ?> 
                      </th>
                      <td><?php echo htmlspecialchars($row['category']); ?></td>
                      <?php
                        switch ($row['status']) {
                          case "Not Found":
                          case "Blocked":
                          case "Added":
                            echo "<td>", $_LANG['langst'][$row['rstat'] = $row['status']], "</td>";
                            break;
                          case "Orphan":
                          case "OK":
                            if ($row['unlist'] != "true") {
                              if ($row['status'] == "OK") {
                                foreach ($_SDATA['noSearch'] as $noSearch) {
                                  if (preg_match("/{$noSearch}/i", $row['uri'])) {
                                    echo "<td>", $_LANG['langst'][$row['rstat'] = "Unlisted"], "</td>";
                                    break 2;
                                  }
                                }
                              }
                              echo "<td", ((!$row['indexed']) ? " class=\"unread\"" : ""), ">", $_LANG['langst'][$row['rstat'] = $row['status']], "</td>";
                            } else echo "<td><strong>", $_LANG['langst'][$row['rstat'] = "Unlisted"], "</strong></td>";
                            break;
                          default: echo "<td>&nbsp;</td>";
                        }
                      ?> 
                      <?php if ($_VDATA['sm.enable'] == "true" && ($row['rstat'] == "OK" || ($row['rstat'] == "Orphan" && $_VDATA['s.orphans'] == "show") || ($row['rstat'] == "Unlisted" && $_VDATA['sm.unlisted'] == "true"))) {
                        if ($row['sm.list'] == "true") { ?> 
                          <td><?php
                            $height = 16 - round((float)$row['sm.priority'] * 16); ?> 
                            <div class="priority" title="Priority - <?php echo $row['sm.priority']; ?>">
                              <div style="height:<?php echo $height; ?>px;" title="<?php echo $_LANG['0ft']; ?> - <?php echo $row['sm.priority']; ?>">&nbsp;</div>
                            </div>
                          </td>
                          <td>&nbsp;<small title="<?php echo $_LANG['0fs']; ?> - <?php echo $_LANG['langcf'][$row['sm.changefreq']]; ?>"><?php echo strtoupper($row['sm.changefreq']{0}); ?></small></td>
                        <?php } else { ?>
                          <td><div class="priority disabled" title="<?php echo $_LANG['0gx']; ?>">&nbsp;</div></td>
                          <td><small title="<?php echo $_LANG['0gx']; ?>">&ndash;</small></td>
                        <?php }
                      } else { ?> 
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                      <?php } ?> 
                      <td><input type="submit" name="edit_<?php echo $row['md5']; ?>" value="<?php echo $_LANG['0fw']; ?>" title="<?php echo $_LANG['0fd']; ?>" /></td>
                    </tr>
                  <?php }
                  if (!$y) { ?> 
                    <tr>
                      <td colspan="9">
                        <?php echo $_LANG['0gy'];
                        if (!$_CDATA['nofilters']) { ?> 
                          &ndash; <input type="submit" name="filter_Clear" value="<?php echo $_LANG['0gz']; ?>" title="<?php echo $_LANG['0h0']; ?>" />
                        <?php } ?> 
                       </td>
                    </tr>
                  <?php } ?> 
                </tbody>
              </table>
              <script type="text/javascript"><!--
                function checkAll(dir) {
                  var boxList = document.getElementById('mainlist').getElementsByTagName('input');
                  for (var x = 0; x < boxList.length; x++) if (boxList[x].name == "action[]" || boxList[x].name == "selectall") boxList[x].checked = dir;
                  return false;
                }
                function anyChecked() {
                  var boxList = document.getElementById('mainlist').getElementsByTagName('input');
                  for (var x = 0; x < boxList.length; x++) if (boxList[x].name == "action[]" && boxList[x].checked) return true;
                  return false;
                }
                function actionGo(thisType) {
                  if (document.getElementById(thisType).value == 'null') return false;
                  if (anyChecked()) {
                    if (document.getElementById(thisType).value == 'delete') {
                      return confirm("<?php echo $_LANG['0h1']; ?>");
                    } else if (document.getElementById(thisType).value == 'respider') {
                      return confirm("<?php echo $_LANG['0g7']; ?>");
                    } else return true;
                  }
                  return false;
                }
                var pagination = <?php echo $_VDATA['c.pagination']; ?>;
                <?php if ($_CDATA['rows']) { ?> 
                  document.getElementById('mainlist').allTypeA.disabled = '';
                  document.getElementById('mainlist').allTypeB.disabled = '';
                <?php } ?> 
              // --></script>
              <?php if ($_GET['start']) { ?> 
                <div>
                  <input type="hidden" name="start" value="<?php echo $_GET['start']; ?>" />
                </div>
              <?php } ?> 
            </form>
          <?php }
        }

    }
  } ?> 

</body>
</html>