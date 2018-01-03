<?php /* ***** JPEG indexing code ******************************** */

$_MIME->ctype['jpg'] = new OS_ContentType(array("image/jpeg"), true);
$_MIME->ctype['jpg']->indexer = "indexJPG";
$_MIME->ctype['jpg']->ctypes = array("jpg", "jpeg", "jfif");

function indexJPG(&$page) {
  global $_XDATA, $_VDATA;

  if ($page->intostat == true) {
    $size = getimagesize($_XDATA['tempfile'], $info);

    if (isset($info['APP13'])) {
      $iptc = iptcparse($info["APP13"]);

      if (is_array($iptc)) {
        $page->title = (isset($iptc["2#005"][0])) ? $iptc["2#005"][0] : "";
        $page->body = (isset($iptc["2#120"][0])) ? $iptc["2#120"][0] : "";
        $page->keywords = (isset($iptc["2#025"]) && count($iptc["2#025"])) ? implode(" ", $iptc["2#025"]) : "";
        $page->keywords .= (isset($iptc["2#020"]) && count($iptc["2#020"])) ? " ".implode(" ", $iptc["2#020"]) : "";

        $page->title = preg_replace("/[^a-z0-9\-\\/\"'(),.:;!\$& *%#~+=_?]/i", "", $page->title);
        $page->body = $page->description = preg_replace("/[^a-z0-9\-\\/\"'(),.:;!\$& *%#~+=_?]/i", "", $page->body);
        $page->keywords = preg_replace("/[^a-z0-9\-\\/\"'(),.:;!\$& *%#~+=_?]/i", "", $page->keywords);

        if ($_VDATA['sp.utf8'] == "true") {
          $page->body = utf8_encode($page->body);
          $page->title = utf8_encode($page->title);
          $page->description = utf8_encode($page->description);
          $page->keywords = utf8_encode($page->keywords);
        }

        return 1;
      }
    }
  } else return -1;
  return 0;
}
