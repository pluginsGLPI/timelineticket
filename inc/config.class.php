<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2016 by the TimelineTicket Development Team.

   https://github.com/pluginsGLPI/timelineticket
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
   @copyright Copyright (c) 2013-2016 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://github.com/pluginsGLPI/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketConfig extends CommonDBTM {

   function showReconstructForm() {
      
      echo "<form method='POST' action=\"".$this->getFormURL()."\">";
       
      echo "<table class='tab_cadre_fixe'>";
      
      echo "<tr>";
      echo "<th>";
      _e('Setup');
      echo "&nbsp;".__('(Can take many time if you have many tickets)', 'timelineticket');
      echo "</th>";
      echo "</tr>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td align='center'>";
      
      echo "<br/><input type='submit' name='reconstructStates' class='submit' value=\""._sx('button', 'Reconstruct states timeline for all tickets', 'timelineticket')."\" >";
      echo "<br/><br/><input type='submit' name='reconstructGroups' class='submit' value=\""._sx('button', 'Reconstruct groups timeline for all tickets', 'timelineticket')."\" >";
      echo "<br/><br/><div class='red'>".__('Warning : it may be that the reconstruction of groups does not reflect reality because it concern only groups which have the Requester flag to No and Assigned flag to Yes', 'timelineticket')."</div>";
      
      echo "</td>";
      echo "</table>";
      Html::closeForm();
   }
   
   
   
   function showForm() {
      
      echo "<form method='POST' action=\"".$this->getFormURL()."\">";
       
      echo "<table class='tab_cadre_fixe'>";
      
      echo "<tr><th colspan='2'>";
      _e('Flags');
      echo "</th></tr>";
      
      echo "<tr class='tab_bg_1 top'><td>".__('Input time on groups / users when ticket is waiting', 'timelineticket')."</td>";
      echo "<td>";
      Dropdown::showYesNo("add_waiting",$this->fields["add_waiting"]);
      echo "</td></tr>";
      
      echo "<tr><th colspan='2'>";
      echo "<input type='hidden' name='id' value='1'>";
      echo "<input type=\"submit\" name=\"update\" class=\"submit\"
         value=\""._sx('button', 'Save')."\" ></th></tr>";
      echo "</table>";
      Html::closeForm();
   }
   
   
   
   static function createFirstConfig() {
      
      $conf = new self();
      if (!$conf->getFromDB(1)) {

         $conf->add(array(
            'id'          => 1,
            'add_waiting' => 1));
      }
   }
   
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      // can exists for template
      if ($item->getType() == 'PluginTimelineticketGrouplevel') {
         return _sx('button', 'Add an item');
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      PluginTimelineticketGrouplevel::showAddGroup($item);
      return true;
   }
   
}
?>