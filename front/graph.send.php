<?php


include ('../../../inc/includes.php');

Session ::checkLoginUser();

if (isset($_GET["switchto"])) {
   $_SESSION['glpigraphtype'] = $_GET["switchto"];
   Html::back();
}

if (($uid = Session::getLoginUserID(false))
    && isset($_GET["file"])) {

   list($userID,$filename) = explode("_", $_GET["file"]);
   if (($userID == $uid)
       && file_exists(GLPI_GRAPH_DIR."/".$_GET["file"])) {

      list($fname,$extension)=explode(".", $filename);
      Toolbox::sendFile(GLPI_GRAPH_DIR."/".$_GET["file"], 'glpi.'.$extension);
   } else {
      Html::displayErrorAndDie(__('Unauthorized access to this file'), true);
   }
}
