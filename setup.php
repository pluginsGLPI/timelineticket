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

define("PLUGIN_TIMELINETICKET_VERSION", "10.0+1.1");

if (!defined("PLUGIN_TIMELINETICKET_DIR")) {
   define("PLUGIN_TIMELINETICKET_DIR", Plugin::getPhpDir("timelineticket"));
   define("PLUGIN_TIMELINETICKET_NOTFULL_DIR", Plugin::getPhpDir("timelineticket",false));
   define("PLUGIN_TIMELINETICKET_WEBDIR", Plugin::getWebDir("timelineticket"));
}

function plugin_version_timelineticket() {

   return ['name'         => _n("Timeline of ticket", "Timeline of tickets", 2, "timelineticket"),
           'version'      => PLUGIN_TIMELINETICKET_VERSION,
           'homepage'     => 'https://github.com/pluginsGLPI/timelineticket',
           'license'      => 'AGPLv3+',
           'author'       => 'Nelly Mahu-Lasson && David Durieux && Xavier Caillaud',
           'requirements' => [
              'glpi' => [
                 'min' => '10.0',
                 'max' => '11.0',
                 'dev' => false
              ]
           ]
   ];
}


function plugin_init_timelineticket() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['timelineticket'] = true;

   // add autoload for vendor
   include_once(PLUGIN_TIMELINETICKET_DIR . "/vendor/autoload.php");

   if (Plugin::isPluginActive('timelineticket')) { // check if plugin is active

      $PLUGIN_HOOKS['change_profile']['timelineticket'] = ['PluginTimelineticketProfile', 'initProfile'];

      $PLUGIN_HOOKS['show_item_stats']['timelineticket']    = [
         'Ticket' => 'plugin_timelineticket_item_stats'
      ];

      Plugin::registerClass('PluginTimelineticketProfile', ['addtabon' => 'Profile']);

      if (Session::haveRightsOr('plugin_timelineticket_ticket', [READ, UPDATE])) {

         Plugin::registerClass('PluginTimelineticketDisplay',
                               ['addtabon' => ['Ticket']]);
      }
      $PLUGIN_HOOKS['item_purge']['timelineticket'] = [
         'Ticket'       => 'plugin_timelineticket_ticket_purge',
         'Group_Ticket' => ['PluginTimelineticketAssignGroup', 'deleteGroupTicket'],
         'Ticket_User'  => ['PluginTimelineticketAssignUser', 'deleteUserTicket']
      ];

      $PLUGIN_HOOKS['item_add']['timelineticket']    = [
         'Ticket'       => 'plugin_timelineticket_ticket_add',
         'Group_Ticket' => ['PluginTimelineticketAssignGroup', 'addGroupTicket'],
         'Ticket_User'  => ['PluginTimelineticketAssignUser', 'addUserTicket']
      ];
      $PLUGIN_HOOKS['item_update']['timelineticket'] = [
         'Ticket' => 'plugin_timelineticket_ticket_update'
      ];

      if (Session::haveRight("config", UPDATE)
          || Session::haveRight('plugin_timelineticket_ticket', UPDATE)) {// Config page
         $PLUGIN_HOOKS['config_page']['timelineticket'] = 'front/config.form.php';
      }
      if (Plugin::isPluginActive('mydashboard')) {
         $PLUGIN_HOOKS['mydashboard']['timelineticket'] = ["PluginTimelineticketDashboard"];
      }
   }
}

/**
 * @return bool
 */
function plugin_timelineticket_check_prerequisites() {

   if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
      echo "Run composer install --no-dev in the plugin directory<br>";
      return false;
   }

   return true;
}
