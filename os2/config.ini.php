<?php /* ***** Orca Search - User Setup ************************** */


/* ***** MySQL *************************************************** */
$_DDATA['hostname'] = "hostname";     // Usually "localhost"
$_DDATA['username'] = "username";     // MySQL account username
$_DDATA['password'] = "password";     // MySQL account password
$_DDATA['database'] = "database";     // A MySQL database assigned to your account
$_DDATA['tablename'] = "orcasearch";  // Orca Search table prefix


/* ***** Admin *************************************************** */
$_SDATA['adminName'] = "admin";       // Control Panel username
$_SDATA['adminPass'] = "password";    // Control Panel password
$_SDATA['directory'] = "os2";         // Install directory


/* ***** Protocol ************************************************ */
//detect protocol from server vars
$_SDATA['protocol'] = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http");
//detect CDN-provided SSL such as CloudFlare Flexible SSL
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
	$_SDATA['protocol'] = $_SERVER['HTTP_X_FORWARDED_PROTO'];
}
//$_SDATA['protocol'] = "https";      // uncomment for manual override of protocol


/* ***** Additional Indexed MIME-types *************************** */
// include "plugins/index.jpg.php";
// include "plugins/index.pdf.php";


/* ***** JWriter tweaks ****************************************** */
// ini_set("memory_limit", "16M");
