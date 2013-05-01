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
                         'list'  => true),
                     array('name'  => 'groups',
                         'label' => $LANG['plugin_timelineticket']['config'][6],
                         'type'  => 'groups',
                         'list'  => true));
   }
   
   function displaySpecificTypeField($ID, $field = array()) {
      global $LANG, $CFG_GLPI;
      
      switch ($field['type']) {
         case 'groups' :
            $groups = json_decode($this->fields[$field['name']], true);
            if (!empty($groups)) {
               echo "<table class='tab_cadrehov' cellpadding='5'>";
               foreach ($groups as $key => $val) {

                  echo "<tr class='tab_bg_1 center'>";
                  echo "<td>";
                  echo Dropdown::getDropdownName("glpi_groups", $val);
                  echo "</td>";
                  echo "<td>";
                  Html::showSimpleForm(Toolbox::getItemTypeFormURL('PluginTimelineticketConfig'), 
                                       'delete_groups', 
                                       $LANG['buttons'][6], 
                                       array('delete_groups' => 'delete_groups',
                                              'id' => $ID,
                                              '_groups_id_assign' => $val
                                                  ), 
                                       $CFG_GLPI["root_doc"] . "/pics/delete.png");
                  echo " </td>";
                  echo "</tr>";
                  
               }
               
               echo "</table>";
            } else {
               echo $LANG['common'][49];
            }
            break;
      }
   }
   
   function getSearchOptions() {
      global $LANG;

      $tab                          = parent::getSearchOptions();

      $tab[11]['table']             = 'glpi_plugin_timelineticket_grouplevels';
      $tab[11]['field']             = 'rank';
      $tab[11]['name']              = $LANG['rulesengine'][10];
      $tab[11]['massiveaction']     = false;
      
      $tab[12]['table']           = 'glpi_plugin_timelineticket_grouplevels';
      $tab[12]['field']           = 'groups';
      $tab[12]['name']            = $LANG['plugin_timelineticket']['config'][6];
      $tab[12]['massiveaction']   = false;
      $tab[12]['nosearch']        = true;
      return $tab;
   }
   
   /**
    * Define tabs to display
    *
    * @param $options array
   **/
   function defineTabs($options=array()) {
      global $LANG;

      $ong = array();
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
         self::showAddGroup($item);
      }
      return true;
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
      
      $used = json_decode($item->fields["groups"], true);

      Dropdown::show('Group', array('name' => '_groups_id_assign',
                                     'entity' => $item->fields["entities_id"],
                                     'condition' => '`is_assign`',
                                     'entity_sons' => $item->fields["is_recursive"],
                                     'used' => $used));
      echo "</td>";
      echo "<td><input type='hidden' name='id' value='".$item->getID()."'>";
      echo "<input type='submit' name='add_groups' value='" . $LANG["buttons"][8] . " groupe' class='submit' ></td>";
      echo "</tr>";
      echo "</table>";
      Html::closeForm();

   }
   
   function getLaskRank() {
      
      $restrict = getEntitiesRestrictRequest('',"glpi_plugin_timelineticket_grouplevels",'','',true);
      $restrict .= "ORDER BY rank DESC LIMIT 1";
      $configs = getAllDatasFromTable("glpi_plugin_timelineticket_grouplevels", $restrict);
      if (!empty($configs)) {
         foreach ($configs as $config) {
            return $config['rank'];
         }
      }
   }
   
   function post_getEmpty() {
      
      $this->fields['rank'] = self::getLaskRank() + 1;
   }
   
   function prepareInputForUpdate($params) {
      
      if (isset($params["add_groups"])) {
         $input = array();
         
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

         $input['id'] = $params['id'];
         $input['groups'] = $group;
         
      } else if (isset($params["delete_groups"])) {
         
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
         
         $input['id'] = $params['id'];
         $input['groups'] = $group;
         
         
      } else {
         $input = $params;
      }
      return $input;
   }
}

?>