<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2022 by the TimelineTicket Development Team.

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
   @copyright Copyright (c) 2013-2022 TimelineTicket team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      https://github.com/pluginsGLPI/timelineticket
   @since     2013

   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketConfig extends CommonDBTM {

   function showReconstructForm() {

      echo "<form method='POST' action=\"" . $this->getFormURL() . "\">";

      echo "<table class='tab_cadre_fixe'>";

      echo "<tr>";
      echo "<th class='center'>";
      echo __('Setup');
      echo "&nbsp;" . __('(Can take many time if you have many tickets)', 'timelineticket');
      echo "</th>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td align='center'>";

      echo Html::submit(_sx('button', 'Reconstruct states timeline for all tickets', 'timelineticket'), ['name' => 'reconstructStates', 'class' => 'btn btn-primary']);
      echo Html::submit(_sx('button', 'Reconstruct groups timeline for all tickets', 'timelineticket'), ['name' => 'reconstructGroups', 'class' => 'btn btn-primary']);
      echo "<br/><br/><div class='alert alert-important alert-warning d-flex'>";
      echo  __('Warning : it may be that the reconstruction of groups does not reflect reality because it concern only groups which have the Requester flag to No and Assigned flag to Yes', 'timelineticket');
      echo "</div>";

      echo "</td>";
      echo "</table>";
      Html::closeForm();
   }


   function showConfigForm() {

      echo "<form method='POST' action=\"" . $this->getFormURL() . "\">";

      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th>";
      echo __('Flags');
      echo "</th></tr>";

      echo "<tr class='tab_bg_1 top'><td>" . __('Input time on groups / users when ticket is waiting', 'timelineticket') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("add_waiting", $this->fields["add_waiting"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>";
      echo Html::hidden('id', ['value' => 1]);
      echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
      echo "</td></tr>";
      echo "</table>";
      Html::closeForm();
   }


   static function createFirstConfig() {

      $conf = new self();
      if (!$conf->getFromDB(1)) {

         $conf->add([
                       'id'          => 1,
                       'add_waiting' => 1]);
      }
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      // can exists for template
      if ($item->getType() == 'PluginTimelineticketGrouplevel') {
         return _sx('button', 'Add an item');
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      PluginTimelineticketGrouplevel::showAddGroup($item);
      return true;
   }

}

