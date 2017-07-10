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

define ("PLUGIN_TIMELINETICKET_VERSION","9.1+1.0");

function plugin_version_timelineticket() {

   return array('name'           => _n("Timeline of ticket", "Timeline of tickets", 2, "timelineticket"),
                'minGlpiVersion' => '9.1',
                'version'        => PLUGIN_TIMELINETICKET_VERSION,
                'homepage'       => 'https://github.com/pluginsGLPI/timelineticket',
                'license'        => 'AGPLv3+',
                'author'         => 'Nelly Mahu-Lasson && David Durieux && Xavier Caillaud');
}



function plugin_init_timelineticket() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['timelineticket'] = true;

   $Plugin = new Plugin();
   if ($Plugin->isActivated('timelineticket')) { // check if plugin is active

      $PLUGIN_HOOKS['change_profile']['timelineticket'] = array('PluginTimelineticketProfile', 'initProfile');
      
      Plugin::registerClass('PluginTimelineticketProfile', array('addtabon' => 'Profile'));
      
      if(Session::haveRightsOr('plugin_timelineticket_ticket', array(READ, UPDATE))) {
      
         Plugin::registerClass('PluginTimelineticketDisplay',
                             array('addtabon' => array('Ticket')));
      }
      $PLUGIN_HOOKS['item_purge']['timelineticket']     =  array(
            'Ticket'       => 'plugin_timelineticket_ticket_purge',
            'Group_Ticket' => array('PluginTimelineticketAssignGroup', 'deleteGroupTicket'),
            'Ticket_User'  => array('PluginTimelineticketAssignUser', 'deleteUserTicket')
          );

      $PLUGIN_HOOKS['item_add']['timelineticket']       = array(
            'Ticket'=>'plugin_timelineticket_ticket_add',
            'Group_Ticket' => array('PluginTimelineticketAssignGroup', 'addGroupTicket'),
            'Ticket_User'  => array('PluginTimelineticketAssignUser', 'addUserTicket')
          );
      $PLUGIN_HOOKS['item_update']['timelineticket']    = array(
            'Ticket' => 'plugin_timelineticket_ticket_update'
          );

      if (Session::haveRight("config", UPDATE)
            || Session::haveRight('plugin_timelineticket_ticket', UPDATE)) {// Config page
         $PLUGIN_HOOKS['config_page']['timelineticket'] = 'front/config.form.php';
      }
   }
}



function plugin_timelineticket_check_prerequisites() {

   // Checking of the GLPI version
   if (version_compare(GLPI_VERSION,'9.1','lt')
         || version_compare(GLPI_VERSION,'9.2','ge')) {
      echo 'This plugin requires GLPI >= 9.1';
      return false;
   }
   return true;
}



function plugin_timelineticket_check_config() {
   return true;
}

?>
