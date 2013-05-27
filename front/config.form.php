<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2013 by the TimelineTicket Development Team.

   https://forge.indepnet.net/projects/timelineticket
   ------------------------------------------------------------------------

   LICENSE

   This file is part of TimelineTicket project.

   TimelineTicket plugin is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   TimelineTicket plugin is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with TimelineTicket plugin. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   TimelineTicket plugin
   @copyright Copyright (c) 2013-2013 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://forge.indepnet.net/projects/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

define('GLPI_ROOT', '../../..');

include (GLPI_ROOT . "/inc/includes.php");

Html::header($LANG['plugin_timelineticket'][1], $_SERVER["PHP_SELF"], "plugins", "timelineticket");

if (Session::haveRight("config", "r") 
      || Session::haveRight("profile", "w")) {

   $ptConfig = new PluginTimelineticketConfig();
   $grplevel = new PluginTimelineticketGroupLevel();
   
   if (isset($_POST["reconstructStates"])) {
      ini_set("max_execution_time", "0");
      ini_set("memory_limit", "-1");
      $ptState = new PluginTimelineticketState();
      $ptState->reconstructTimeline();
      Html::back();
      
   } else if (isset($_POST["reconstructGroups"])) {
      ini_set("max_execution_time", "0");
      ini_set("memory_limit", "-1");
      $ptGroup = new PluginTimelineticketAssignGroup();
      $ptGroup->reconstrucTimeline();
      Html::back();
      
   } else if (isset($_POST["add_groups"]) 
               || isset($_POST["delete_groups"])) {

      $grplevel->update($_POST);
      Html::back();
      
   } else if (isset($_POST["update"])) {

      $ptConfig->update($_POST);
      Html::back();
      
   } else {
   
      $ptConfig->showReconstructForm();

      $ptConfig->getFromDB(1);
      $ptConfig->showForm();
      Html::footer();
      
   }
}

?>