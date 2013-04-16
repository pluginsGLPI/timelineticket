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


function plugin_version_timelineticket() {
   global $LANG;

   return array('name'           => $LANG['plugin_timelineticket'][1],
                'minGlpiVersion' => '0.83.3',
                'version'        => '0.83+1.1',
                'author'         => 'Nelly Mahu-Lasson && David Durieux');
}



function plugin_init_timelineticket() {
   global $PLUGIN_HOOKS, $LANG;

   $PLUGIN_HOOKS['csrf_compliant']['timelineticket'] = true;


   Plugin::registerClass('PluginTimelineticketState',
                          array('addtabon' => array('Ticket')));

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
   
   if (Session::haveRight("configuration", "r") || Session::haveRight("profile", "w")) {// Config page
      $PLUGIN_HOOKS['config_page']['timelineticket'] = 'front/config.form.php';
   }
}



function plugin_timelineticket_check_prerequisites() {

   // Checking of the GLPI version
   if (version_compare(GLPI_VERSION,'0.83.3','lt') || version_compare(GLPI_VERSION,'0.84','ge')) {
      echo "This plugin requires GLPI >= 0.83.3";
      return false;
   }
   return true;
}



function plugin_timelineticket_check_config() {
   return true;
}

?>