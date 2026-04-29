<?php

/*
   ------------------------------------------------------------------------
   TimelineTicket
   Copyright (C) 2013-2025 by the TimelineTicket Development Team.

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
   @copyright Copyright (C) 2013-2025 TimelineTicket team
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

    $migration = new Migration(PLUGIN_TIMELINETICKET_VERSION);

    AssignState::install($migration);

    AssignGroup::install($migration);

    AssignUser::install($migration);

    Grouplevel::install($migration);

    Config::install($migration);

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

    AssignState::uninstall();
    AssignGroup::uninstall();
    AssignUser::uninstall();
    Grouplevel::uninstall();
    Config::uninstall();

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }

    Profile::removeRightsFromSession();
}


function plugin_timelineticket_ticket_add(Ticket $item)
{
    AssignState::addAssignState($item);
}


function plugin_timelineticket_ticket_update(Ticket $item)
{
    if (in_array('status', $item->updates)) {
//        \Toolbox::logInfo($item);
        AssignState::addNewAssignState($item);
    }
    //If status go to Ticket::WAITING, Ticket::SOLVED, Ticket::CLOSED
//    AssignGroup::checkAssignGroup($item);
//    AssignUser::checkAssignUser($item);
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
        $sopt[9131]['table']         = 'glpi_plugin_timelineticket_assigngroups';
        $sopt[9131]['field']         = 'groups_id';
        $sopt[9131]['name']          = _n("Timeline of ticket", "Timeline of tickets", 2, "timelineticket")." - ".__('Assigned to')." - ".__('Group');
        $sopt[9131]['forcegroupby']  = true;
        $sopt[9131]['massiveaction'] = false;
        $sopt[9131]['nosort']        = true;
        $sopt[9131]['joinparams']    = ['jointype' => 'child'];

        $sopt[9132]['table']         = 'glpi_plugin_timelineticket_assignusers';
        $sopt[9132]['field']         = 'users_id';
        $sopt[9132]['name']          = _n("Timeline of ticket", "Timeline of tickets", 2, "timelineticket")." - ".__('Assigned to')." - ".__('Technician');
        $sopt[9132]['forcegroupby']  = true;
        $sopt[9132]['massiveaction'] = false;
        $sopt[9132]['nosort']        = true;
        $sopt[9132]['joinparams']    = ['jointype' => 'child'];
    }
    return $sopt;
}

function plugin_timelineticket_giveItem($type, $ID, $data, $num)
{
    $searchopt = Search::getOptions($type);
    $table = $searchopt[$ID]["table"];
    $field = $searchopt[$ID]["field"];

    switch ($table.'.'.$field) {
        case "glpi_plugin_timelineticket_grouplevels.groups":
            if (empty($data['raw']["ITEM_".$num])) {
                $out=__('None');
            } else {
                $out= "";
                $groups = json_decode($data['raw']["ITEM_".$num], true);
                if (!empty($groups)) {
                    foreach ($groups as $key => $val) {
                        $out .= Dropdown::getDropdownName("glpi_groups", $val)."<br>";
                    }
                }
            }
            return $out;

        case "glpi_plugin_timelineticket_assigngroups.groups_id":
            global $DB;
            $tickets_id = (int) ($data['id'] ?? 0);
            if (!$tickets_id) {
                return "";
            }
            $ticket = new Ticket();
            $ticket->getFromDB($tickets_id);

            $iterator = $DB->request([
                'SELECT' => ['groups_id', 'date', 'delay'],
                'FROM'   => 'glpi_plugin_timelineticket_assigngroups',
                'WHERE'  => ['tickets_id' => $tickets_id],
                'ORDER'  => ['date ASC'],
            ]);

            $a_out       = [];
            $a_groupname = [];
            foreach ($iterator as $row) {
                $gid = (int) $row['groups_id'];
                if ($row['delay'] === null) {
                    $enddate = $ticket->fields['status'] == Ticket::CLOSED
                        ? $ticket->fields['closedate']
                        : $_SESSION["glpi_currenttime"];
                    $time = strtotime($enddate) - strtotime($row['date']);
                } else {
                    $time = (int) $row['delay'];
                }
                if (!isset($a_out[$gid])) {
                    $a_out[$gid]       = 0;
                    $group             = new Group();
                    $group->getFromDB($gid);
                    $a_groupname[$gid] = $group->getLink();
                }
                $a_out[$gid] += $time;
            }

            if (empty($a_out)) {
                return "";
            }

            $a_out_comp = [];
            foreach ($a_out as $gid => $time) {
                $a_out_comp[] = $a_groupname[$gid] . " : " . Html::timestampToString($time, true, false);
            }
            return implode("<br/>", $a_out_comp);

        case "glpi_plugin_timelineticket_assignusers.users_id":
            global $DB;
            $tickets_id = (int) ($data['id'] ?? 0);
            if (!$tickets_id) {
                return "";
            }
            $ticket = new Ticket();
            $ticket->getFromDB($tickets_id);

            $iterator = $DB->request([
                'SELECT' => ['users_id', 'date', 'delay'],
                'FROM'   => 'glpi_plugin_timelineticket_assignusers',
                'WHERE'  => ['tickets_id' => $tickets_id],
                'ORDER'  => ['date ASC'],
            ]);

            $a_out      = [];
            $a_username = [];
            foreach ($iterator as $row) {
                $uid = (int) $row['users_id'];
                if ($row['delay'] === null) {
                    $enddate = $ticket->fields['status'] == Ticket::CLOSED
                        ? $ticket->fields['closedate']
                        : $_SESSION["glpi_currenttime"];
                    $time = strtotime($enddate) - strtotime($row['date']);
                } else {
                    $time = (int) $row['delay'];
                }
                if (!isset($a_out[$uid])) {
                    $a_out[$uid]      = 0;
                    $user             = new User();
                    $user->getFromDB($uid);
                    $a_username[$uid] = $user->getLink();
                }
                $a_out[$uid] += $time;
            }

            if (empty($a_out)) {
                return "";
            }

            $a_out_comp = [];
            foreach ($a_out as $uid => $time) {
                $a_out_comp[] = $a_username[$uid] . " : " . Html::timestampToString($time, true, false);
            }
            return implode("<br/>", $a_out_comp);
    }
    return "";
}


//function plugin_timelineticket_addLeftJoin(
//    $itemtype,
//    $ref_table,
//    $new_table,
//    $linkfield,
//    &$already_link_tables
//)
//{
//    switch ($itemtype) {
//        case 'Ticket':
//            if ($new_table.".".$linkfield ==
//                       "glpi_plugin_timelineticket_assigngroups.plugin_timelineticket_assigngroups_id") {
//
//                $out['LEFT JOIN'] = [
//                    'glpi_plugin_timelineticket_assigngroups' => [
//                        'ON' => [
//                            'glpi_tickets'  => 'id',
//                            'glpi_plugin_timelineticket_assigngroups'  => 'tickets_id',
//                        ],
//                    ],
//                ];
//                return $out;
//            }
//            break;
//    }
//    return "";
//}
