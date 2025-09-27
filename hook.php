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

use GlpiPlugin\Timelineticket\AssignGroup;
use GlpiPlugin\Timelineticket\AssignState;
use GlpiPlugin\Timelineticket\AssignUser;
use GlpiPlugin\Timelineticket\Config;
use GlpiPlugin\Timelineticket\Grouplevel;
use GlpiPlugin\Timelineticket\Profile;

function plugin_timelineticket_install()
{
    global $DB;

    $migration = new Migration(11);

    // installation

    if (!$DB->tableExists("glpi_plugin_timelineticket_assignstates")) {
        $query = "CREATE TABLE `glpi_plugin_timelineticket_assignstates` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `tickets_id` int unsigned NOT NULL DEFAULT '0',
                  `date` timestamp NULL DEFAULT NULL,
                  `old_status` varchar(255) DEFAULT NULL,
                  `new_status` varchar(255) DEFAULT NULL,
                  `delay` int(11) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query);
    }
    if (!$DB->tableExists("glpi_plugin_timelineticket_assigngroups")) {
        $query = "CREATE TABLE `glpi_plugin_timelineticket_assigngroups` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `tickets_id` int unsigned NOT NULL DEFAULT '0',
                  `date` timestamp NULL DEFAULT NULL,
                  `groups_id` varchar(255) DEFAULT NULL,
                  `begin` int unsigned NULL,
                  `delay` int(11) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query);
    }

    if (!$DB->tableExists("glpi_plugin_timelineticket_assignusers")) {
        $query = "CREATE TABLE `glpi_plugin_timelineticket_assignusers` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `tickets_id` int unsigned NOT NULL DEFAULT '0',
                  `date` timestamp NULL DEFAULT NULL,
                  `users_id` varchar(255) DEFAULT NULL,
                  `begin` int unsigned NULL,
                  `delay` int(11) NULL,
                  PRIMARY KEY (`id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query);
    }

    if (!$DB->tableExists("glpi_plugin_timelineticket_grouplevels")) {
        $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_timelineticket_grouplevels` (
               `id` int unsigned NOT NULL AUTO_INCREMENT,
               `entities_id` int unsigned NOT NULL DEFAULT '0',
               `is_recursive` tinyint  NOT NULL default '0',
               `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
               `groups` longtext collate utf8mb4_unicode_ci,
               `rank` smallint NOT NULL default '0',
               `comment` text collate utf8mb4_unicode_ci,
               PRIMARY KEY (`id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    if (!$DB->tableExists("glpi_plugin_timelineticket_configs")) {
        $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_timelineticket_configs` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `add_waiting` int unsigned NOT NULL DEFAULT '1',
              PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    $status =  ['new'           => Ticket::INCOMING,
                'assign'        => Ticket::ASSIGNED,
                'plan'          => Ticket::PLANNED,
                'waiting'       => Ticket::WAITING,
                'solved'        => Ticket::SOLVED,
                'closed'        => Ticket::CLOSED];

    // Update field in tables
    foreach (['glpi_plugin_timelineticket_assignstates'] as $table) {
        // Migrate datas
        foreach ($status as $old => $new) {
            $query = "UPDATE `$table`
                   SET `old_status` = '$new'
                   WHERE `old_status` = '$old'";
            $DB->doQuery($query);

            $query = "UPDATE `$table`
                   SET `new_status` = '$new'
                   WHERE `new_status` = '$old'";
            $DB->doQuery($query);
        }
    }

    $query = "ALTER TABLE `glpi_plugin_timelineticket_assignstates` CHANGE `delay` `delay` int(11) DEFAULT NULL;";
    $DB->doQuery($query);
    $query = "ALTER TABLE `glpi_plugin_timelineticket_assigngroups` CHANGE `delay` `delay` int(11) DEFAULT NULL;";
    $DB->doQuery($query);
    $query = "ALTER TABLE `glpi_plugin_timelineticket_assignusers` CHANGE `delay` `delay` int(11) DEFAULT NULL;";
    $DB->doQuery($query);

    if (!$DB->tableExists("glpi_plugin_timelineticket_assignstates")) {
        $query = "RENAME TABLE `glpi_plugin_timelineticket_states` TO `glpi_plugin_timelineticket_assignstates`;";
        $DB->doQuery($query);
    }
    Config::createFirstConfig();

    if (isset($_SESSION['glpiactiveprofile'])
            && isset($_SESSION['glpiactiveprofile']['id'])) {
        Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    }

    $migration = new Migration("9.1+1.0");
    $migration->dropTable('glpi_plugin_timelineticket_profiles');
    return true;
}

function plugin_timelineticket_item_stats($item)
{
    AssignState::showStateTimeline($item);
    AssignGroup::showGroupTimeline($item);
    AssignUser::showUserTimeline($item);
}

function plugin_timelineticket_uninstall()
{
    global $DB;

    $tables = ["glpi_plugin_timelineticket_assignstates",
        "glpi_plugin_timelineticket_assigngroups",
        "glpi_plugin_timelineticket_assignusers",
        "glpi_plugin_timelineticket_grouplevels",
        "glpi_plugin_timelineticket_profiles",
        "glpi_plugin_timelineticket_configs"];

    foreach ($tables as $table) {
         $DB->dropTable($table, true);
    }

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }

    Profile::removeRightsFromSession();
}

function plugin_timelineticket_ticket_update(Ticket $item)
{
    if (in_array('status', $item->updates)) {
        // Instantiation of the object from the class AssignState
        $ptState = new AssignState();

        // Insertion the changement in the database
        $ptState->createFollowup(
            $item,
            $_SESSION["glpi_currenttime"],
            $item->oldvalues['status'],
            $item->fields['status']
        );
        // calcul du délai + insertion dans la table
    }

    AssignGroup::checkAssignGroup($item);
    AssignUser::checkAssignUser($item);
}


function plugin_timelineticket_ticket_add(Ticket $item)
{
    // Instantiation of the object from the class AssignState
    $followups = new AssignState();

    $followups->createFollowup($item, $item->input['date'], '', Ticket::INCOMING);

    if ($item->input['status'] != Ticket::INCOMING) {
        $followups->createFollowup($item, $item->input['date'], Ticket::INCOMING, $item->input['status']);
    }
}


function plugin_timelineticket_ticket_purge(Ticket $item)
{
    // Instantiation of the object from the class AssignState
    $ticketstate = new AssignState();
    $user = new AssignUser();
    $group = new AssignGroup();

    $ticketstate->deleteByCriteria(['tickets_id' => $item->getField("id")]);
    $user->deleteByCriteria(['tickets_id' => $item->getField("id")]);
    $group->deleteByCriteria(['tickets_id' => $item->getField("id")]);
}

function plugin_timelineticket_getDropdown()
{
    if (Plugin::isPluginActive("timelineticket")) {
        return [Grouplevel::class => Grouplevel::getTypeName(2)];
    } else {
        return [];
    }
}

// Define dropdown relations
function plugin_timelineticket_getDatabaseRelations()
{
    if (Plugin::isPluginActive("timelineticket")) {
        return ["glpi_entities" => ["glpi_plugin_timelineticket_grouplevels" => "entities_id"]];
    } else {
        return [];
    }
}


function plugin_timelineticket_getAddSearchOptions($itemtype)
{
    $sopt = [];
    if ($itemtype == 'Ticket') {
        $sopt[9131]['table']     = 'glpi_groups';
        $sopt[9131]['field']     = 'completename';
        $sopt[9131]['name']      = "timelineticket - ".__('Assigned to')." - ".__('Group');
        $sopt[9131]['datatype']  = 'dropdown';
        $sopt[9131]['forcegroupby']  = true;
        $sopt[9131]['massiveaction'] = false;
        $sopt[9131]['condition']     = 'is_assign';
        $sopt[9131]['joinparams']    = ['beforejoin'
        => ['table' => 'glpi_plugin_timelineticket_assigngroups',
                'joinparams'
                => ['jointype'  => 'child']]];

        $sopt[9132]['table']     = 'glpi_users';
        $sopt[9132]['field']     = 'name';
        $sopt[9132]['name']      = "timelineticket - ".__('Assigned to')." - ".__('Technician');
        $sopt[9132]['datatype']  = 'dropdown';
        $sopt[9132]['forcegroupby']  = true;
        $sopt[9132]['massiveaction'] = false;
        $sopt[9132]['condition']     = 'is_assign';
        $sopt[9132]['joinparams']    = ['beforejoin'
        => ['table' => 'glpi_plugin_timelineticket_assignusers',
                'joinparams'
                => ['jointype'  => 'child']]];
    }
    return $sopt;
}

function plugin_timelineticket_giveItem($type, $ID, $data, $num)
{
    $searchopt= Search::getOptions($type);
    $table=$searchopt[$ID]["table"];
    $field=$searchopt[$ID]["field"];

    switch ($table.'.'.$field) {
        case "glpi_plugin_timelineticket_grouplevels.groups":
            if (empty($data["ITEM_$num"])) {
                $out=__('None');
            } else {
                $out= "";
                $groups = json_decode($data["ITEM_$num"], true);
                if (!empty($groups)) {
                    foreach ($groups as $key => $val) {
                        $out .= Dropdown::getDropdownName("glpi_groups", $val)."<br>";
                    }
                }
            }
            return $out;
            break;

        case "glpi_plugin_timelineticket_assigngroups.groups_id":
            $ptAssignGroup = new AssignGroup();
            $group = new Group();
            $ticket = new Ticket();
            $out = "";
            $a_out = [];
            $a_groupname = [];
            if (!isset($data["ITEM_$num"])
                    or !strstr($data["ITEM_$num"], '$$')) {
                return "";
            }
            $splitg = explode("$$$$", $data["ITEM_$num"]);
            foreach ($splitg as $datag) {
                $split = explode("$$", $datag);
                $group->getFromDB($split[0]);
                $ptAssignGroup->getFromDB($split[1]);
                $time = $ptAssignGroup->fields['delay'];
                if ($ptAssignGroup->fields['delay'] === null) {
                    $ticket->getFromDB($data["ITEM_0"]);

                    $calendar = new Calendar();
                    $calendars_id = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
                    $datedebut = $ptAssignGroup->fields['date'];
                    $enddate = $_SESSION["glpi_currenttime"];
                    if ($ticket->fields['status'] == Ticket::CLOSED) {
                        $enddate = $ticket->fields['closedate'];
                    }

                    if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
                        $time = $calendar->getActiveTimeBetween($datedebut, $enddate);
                    } else {
                        // cas 24/24 - 7/7
                        $time = strtotime($enddate)-strtotime($datedebut);
                    }
                } elseif ($ptAssignGroup->fields['delay'] == 0) {
                    $time = 0;
                }
                $a_groupname[$group->fields['id']] = $group->getLink();
                if (isset($a_out[$group->fields['id']])) {
                    $a_out[$group->fields['id']] += $time;
                } else {
                    $a_out[$group->fields['id']] = $time;
                }
            }
            $a_out_comp = [];
            foreach ($a_out as $groups_id => $time) {
                $a_out_comp[] = $a_groupname[$groups_id]." : ".Html::timestampToString($time, true, false);
            }

            $out = implode("<hr/>", $a_out_comp);
            return $out;
            break;
    }
    return "";
}


function plugin_timelineticket_addLeftJoin(
    $itemtype,
    $ref_table,
    $new_table,
    $linkfield,
    &$already_link_tables
)
{
    switch ($itemtype) {
        case 'Ticket':
            if ($new_table.".".$linkfield ==
                       "glpi_plugin_timelineticket_assigngroups.plugin_timelineticket_assigngroups_id") {
                return " LEFT JOIN `glpi_plugin_timelineticket_assigngroups` "
                . " ON (`glpi_tickets`.`id` = `glpi_plugin_timelineticket_assigngroups`.`tickets_id` )  ";
            }
            break;
    }
    return "";
}
