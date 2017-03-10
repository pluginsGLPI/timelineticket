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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginTimelineticketProfile extends Profile {

      static $rightname = "config";

      /*
       * Old profile names:
       *
       *    timelineticket
       */

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      if ($item->getType() == 'Profile'
          && $item->fields['interface'] == 'central') {
         return self::createTabEntry('TimelineTicket');
      }
   }



   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      $pfProfile = new self();
      $pfProfile->showForm($item->getID());
      return TRUE;
   }


   /**
    * Show profile form
    *
    * @param int $profiles_id
    * @param bool $openform
    * @param bool $closeform
    * @return nothing
    * @internal param int $items_id id of the profile
    * @internal param value $target url of target
    */
   function showForm($profiles_id=0, $openform=TRUE, $closeform=TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);

      $rights = $this->getRightsGeneral();
      $profile->displayRightsChoiceMatrix($rights, array('canedit'    => $canedit,
                                                      'default_class' => 'tab_bg_2',
                                                      'title'         => __('General', 'timelineticket')));

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $profiles_id));
         echo Html::submit(_sx('button', 'Save'), array('name' => 'update'));
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }

   static function uninstallProfile() {
      $pfProfile = new self();
      $a_rights = $pfProfile->getRightsGeneral();
      foreach ($a_rights as $data) {
         ProfileRight::deleteProfileRights(array($data['field']));
      }
   }

   static function getRightsGeneral() {
      $rights = array(
          array('rights'    => array(READ => __('Read'), UPDATE => __('Update')),
                'label'     => __('Ticket'),
                'field'     => 'plugin_timelineticket_ticket'),
      );

      return $rights;
   }

   static function addDefaultProfileInfos($profiles_id, $rights) {
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if (!countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'")) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   /**
    * @param $profiles_id
    * @internal param int $ID
    */
   static function createFirstAccess($profiles_id) {
      include_once(GLPI_ROOT."/plugins/timelineticket/inc/profile.class.php");
      $profile = new self();
      foreach ($profile->getRightsGeneral() as $right) {
         self::addDefaultProfileInfos($profiles_id,
                                      array($right['field'] => ALLSTANDARDRIGHT));
      }
   }

   static function removeRightsFromSession() {
      $profile = new self();
      foreach ($profile->getRightsGeneral() as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
      ProfileRight::deleteProfileRights(array($right['field']));

   }

   static function migrateProfiles() {

      //Get all rights from the old table
      $profiles = getAllDatasFromTable(getTableForItemType(__CLASS__));

      //Load mapping of old rights to their new equivalent
//      $oldrights = self::getOldRightsMappings();

      //For each old profile : translate old right the new one
      foreach ($profiles as $id => $profile) {
         switch ($profile['right']) {
            case 'r' :
               $value = READ;
               break;
            case 'w':
               $value = ALLSTANDARDRIGHT;
               break;
            case 0:
            default:
               $value = 0;
               break;
         }
         //Write in glpi_profilerights the new timelineticket right
         if (isset($oldrights[$profile['type']])) {
            //There's one new right corresponding to the old one
            if (!is_array($oldrights[$profile['type']])) {
               self::addDefaultProfileInfos($profile['profiles_id'],
                                            array($oldrights[$profile['type']] => $value));
            } else {
               //One old right has been splitted into serveral new ones
               foreach ($oldrights[$profile['type']] as $newtype) {
                  self::addDefaultProfileInfos($profile['profiles_id'],
                                               array($newtype => $value));
               }
            }
         }
      }
   }

   /**
   * Init profiles during installation :
   * - add rights in profile table for the current user's profile
   * - current profile has all rights on the plugin
   */
   static function initProfile() {
      $pfProfile = new self();
      $profile   = new Profile();
      $a_rights  = $pfProfile->getRightsGeneral();

      foreach ($a_rights as $data) {
         if (countElementsInTable("glpi_profilerights", "`name` = '".$data['field']."'") == 0) {
            ProfileRight::addProfileRights(array($data['field']));
            $_SESSION['glpiactiveprofile'][$data['field']] = 0;
         }
      }

      // Add all rights to current profile of the user
      if (isset($_SESSION['glpiactiveprofile'])) {
         $dataprofile       = array();
         $dataprofile['id'] = $_SESSION['glpiactiveprofile']['id'];
         $profile->getFromDB($_SESSION['glpiactiveprofile']['id']);
         foreach ($a_rights as $info) {
            if (is_array($info)
                && ((!empty($info['itemtype'])) || (!empty($info['rights'])))
                  && (!empty($info['label'])) && (!empty($info['field']))) {

               if (isset($info['rights'])) {
                  $rights = $info['rights'];
               } else {
                  $rights = $profile->getRightsFor($info['itemtype']);
               }
               foreach ($rights as $right => $label) {
                  $dataprofile['_'.$info['field']][$right] = 1;
                  $_SESSION['glpiactiveprofile'][$data['field']] = $right;
               }
            }
         }
         $profile->update($dataprofile);
      }
   }
}

?>