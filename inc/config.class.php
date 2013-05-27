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

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketConfig extends CommonDBTM {

   function showReconstructForm() {
      global $LANG;
      
      echo "<form method='POST' action=\"".$this->getFormURL()."\">";
       
      echo "<table class='tab_cadre_fixe'>";
      
      echo "<tr>";
      echo "<th>";
      echo $LANG['common'][12];
      echo "&nbsp;".$LANG['plugin_timelineticket']['config'][3];
      echo "</th>";
      echo "</tr>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td align='center'>";
      
      echo "<br/><input type='submit' name='reconstructStates' class='submit' value=\"".$LANG['plugin_timelineticket']['config'][1]."\" >";
      echo "<br/><br/><input type='submit' name='reconstructGroups' class='submit' value=\"".$LANG['plugin_timelineticket']['config'][2]."\" >";
      echo "<br/><br/><div class='red'>".$LANG['plugin_timelineticket']['config'][4]."</div>";
      
      echo "</td>";
      echo "</table>";
      Html::closeForm();
   }
   
   function showForm() {
      global $LANG;
      
      echo "<form method='POST' action=\"".$this->getFormURL()."\">";
       
      echo "<table class='tab_cadre_fixe'>";
      
      echo "<tr><th colspan='2'>";
      echo $LANG['plugin_timelineticket']['config'][7];
      echo "</th></tr>";
      
      echo "<tr class='tab_bg_1 top'><td>".$LANG['plugin_timelineticket']['config'][8]."</td>";
      echo "<td>";
      Dropdown::showYesNo("drop_waiting",$this->fields["drop_waiting"]);
      echo "</td></tr>";
      
      echo "<tr><th colspan='2'>";
      echo "<input type='hidden' name='id' value='1'>";
      echo "<input type=\"submit\" name=\"update\" class=\"submit\"
         value=\"".$LANG["buttons"][2]."\" ></th></tr>";
      echo "</table>";
      Html::closeForm();
   }
   
   static function createFirstConfig() {
      
      $conf = new self();
      if (!$conf->getFromDB(1)) {

         $conf->add(array(
            'id' => 1,
            'drop_waiting' => 0));
      }
   }
   
}

?>