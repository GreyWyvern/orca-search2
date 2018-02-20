<?php /* ***** PDF indexing code ********************************* */

// See index.pdf.txt for installation and usage instructions

$_MIME->ctype['pdf'] = new OS_ContentType(array("application/pdf"), true);

// ***** pdftotext executable location
// You may need to place this file in a shared folder with executable
// permissions such as /usr/bin/
// Make sure PHP has permission to execute the file (CHMOD 755 or 775)

// $_MIME->ctype['pdf']->handler = ".\\plugins\\pdftotext.exe";  // Windows server
$_MIME->ctype['pdf']->handler = './plugins/pdftotext';           // *nix server

$_MIME->ctype['pdf']->indexer = "indexPDF";
$_MIME->ctype['pdf']->ctypes = array("pdf");

function indexPDF(&$page) {
  global $_MIME, $_XDATA, $_VDATA;

  if ($page->intostat == true) {
    ob_start();

    // pdftotext version < 3.04
    // passthru("{$_MIME->ctype['pdf']->handler} -htmlmeta -nopgbrk -q {$_XDATA['tempfile']} -");

    // pdftotext version >= 3.04
    passthru("{$_MIME->ctype['pdf']->handler} -nopgbrk -q {$_XDATA['tempfile']} -");

    $pdfoutput = ob_get_contents();
    ob_end_clean();
    if ($pdfoutput) {
      $page->body = preg_replace(array("/^.+?<pre>/s", "/<\/pre>.+$/s", "/[\n\r\t]/", "/\s\s+/"), " ", $pdfoutput);

      preg_match("/<title[^>]*>(.*?)<\/title>/is", $pdfoutput, $match);
      if (isset($match[1])) $page->title = strip_tags($match[1]);

      preg_match("/<meta\sname=\"Subject\"\scontent=([^>]+)>/", $pdfoutput, $match);
      if (isset($match[1])) $page->description = trim($match[1], "\"\n\r\t ");

      if ($_VDATA['sp.utf8'] == "true") {
        $page->body = utf8_encode($page->body);
        $page->title = utf8_encode($page->title);
        $page->description = utf8_encode($page->description);
      }
    }
    return 1;
  } else return -1;
  return 0;
}
