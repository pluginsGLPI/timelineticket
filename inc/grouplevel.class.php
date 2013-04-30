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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketGrouplevel extends CommonDropdown {

   static function getTypeName($nb=0) {
      global $LANG;

      return $LANG['plugin_timelineticket']['config'][5];
   }
   
   function getAdditionalFields() {
      global $LANG;

      return array(array('name'  => 'rank',
                         'label' => $LANG['rulesengine'][10],
                         'type'  => 'text',
                         'list'  => true));
   }
   
   function displaySpecificTypeField($ID, $field = array()) {

      switch ($field['type']) {
         case 'groups' :
            echo $this->fields[$field['name']];
            break;
      }
   }
   
   /**
    * Define tabs to display
    *
    * @param $options array
   **/
   function defineTabs($options=array()) {
      global $LANG;

      $ong = array();
      //$ong['empty'] = $this->getTypeName();
      $this->addStandardTab(__CLASS__, $ong, $options);

      return $ong;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      if (!$withtemplate) {
         if ($item->getType()==$this->getType()) {
            return $this->getTypeName();
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()==__CLASS__) {
         self::showGrouplevel($item);
         self::showAddGroup($item);
      }
      return true;
   }

   static function showGrouplevel($item) {
      global $LANG, $DB, $CFG_GLPI;

      echo "<div class='center'>";
      
      //self::showAddLevel();

      // We retrieve the filled data in DB.
      //$restrict = getEntitiesRestrictRequest('', "glpi_plugin_timelineticket_grouplevels", '', '', false);
      $restrict = "`id` = " . $item->getID();
      $configs = getAllDatasFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
      $options = array();
      if (!empty($configs)) {
         
         echo "<form action='" .Toolbox::getItemTypeFormURL('PluginTimelineticketConfig')."' method='post'>";
         echo "<table class='tab_cadre_fixe' cellpadding='5'>";
         echo "<tr class='tab_bg_1 center'>";
         echo "<th colspan='2'>" . $LANG['plugin_timelineticket']['config'][6] . "</th>";
         echo "</tr>";

         // Display data with option to delete a row.
         foreach ($configs as $config) {

            $groups = json_decode($config["groups"], true);

            if (!empty($groups)) {

               foreach ($groups as $key => $val) {

                  echo "<tr class='tab_bg_1 center'>";
                  echo "<td>";
                  echo Dropdown::getDropdownName("glpi_groups", $val);
                  echo "</td>";
                  echo "<td>";
                  Html::showSimpleForm(Toolbox::getItemTypeFormURL('PluginTimelineticketConfig'), 'delete_groups', $LANG['buttons'][6], array('delete_groups' => 'delete_groups',
                      'id' => $config["id"],
                      '_groups_id_assign' => $val
                          ), $CFG_GLPI["root_doc"] . "/pics/delete.png");
                  echo " </td>";
                  echo "</tr>";
               }
            }
         }
         echo "</table>";
         Html::closeForm();
      }
      
   }
   
   static function showAddGroup($item) {
      global $LANG;

      echo "<form action='" .Toolbox::getItemTypeFormURL('PluginTimelineticketConfig')."' method='post'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'>";
      echo "<tr class='tab_bg_1 center'>";
      echo "<th>" . $LANG['common'][35] . "</th>";
      echo "<th>&nbsp;</th>";
      echo "</tr>";
      echo "<tr class='tab_bg_1 center'>";
      echo "<td>";
      Dropdown::show('Group', array('name' => '_groups_id_assign',
          'entity' => $_SESSION["glpiactiveentities"],
          'condition' => '`is_assign`'));
      echo "</td>";
      echo "<td><input type='hidden' name='id' value='".$item->getID()."'>";
      echo "<input type='submit' name='add_groups' value='" . $LANG["buttons"][8] . " groupe' class='submit' ></td>";
      echo "</tr>";
      echo "</table>";
      Html::closeForm();

   }
   
   static function addGroup($params) {
      
      $values = array();
      
      $restrict = "`id` = " . $params['id'];
      $configs = getAllDatasFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);

      $groups = array();
      if (!empty($configs)) {
         foreach ($configs as $config) {
            if (!empty($config["groups"])) {
               $groups = json_decode($config["groups"], true);
               if (count($groups) > 0) {
                  if (!in_array($params["_groups_id_assign"], $groups)) {
                     array_push($groups, $params["_groups_id_assign"]);
                  }
               } else {
                  $groups = array($params["_groups_id_assign"]);
               }
            } else {
               $groups = array($params["_groups_id_assign"]);
            }
         }
      }

      $group = json_encode($groups);

      $values['id'] = $params['id'];
      $values['groups'] = $group;
      
      return $values;

   }
   
   static function deleteGroup($params) {
      
      $values = array();
      
      $restrict = "`id` = " . $params['id'];
      $configs = getAllDatasFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);

      $groups = array();
      if (!empty($configs)) {
         foreach ($configs as $config) {
            if (!empty($config["groups"])) {
               $groups = json_decode($config["groups"], true);
               if (count($groups) > 0) {
                  if (($key = array_search($params["_groups_id_assign"], $groups)) !== false) {
                     unset($groups[$key]);
                  }
               }
            }
         }
      }

      if (count($groups) > 0) {
         $group = json_encode($groups);
      } else {
         $group = "";
      }
      
      $values['id'] = $params['id'];
      $values['groups'] = $group;
      
      return $values;

   }
}

?>