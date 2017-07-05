<?php 

include "os2/config.php";
include "os2/head.php";

?><html>
<head>
  <title>Orca Search <?php echo $_SDATA['version']; ?></title>

  <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $_VDATA['c.charset']; ?>" />

  <style type="text/css">

body {
  background-color:#ffffff;
  font:normal 100% Arial,sans-serif;
}

  </style>
</head>

<body>

  <h1>Orca Search <?php echo $_SDATA['version']; ?></h1>

  <?php include "os2/body.xhtml.php"; ?>

</body>
</html>