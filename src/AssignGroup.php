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

namespace GlpiPlugin\Timelineticket;

use Calendar;
use CommonDBTM;
use CommonITILActor;
use DBConnection;
use Dropdown;
use Entity;
use Group;
use Group_Ticket;
use Html;
use Migration;
use Ticket;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class AssignGroup extends CommonDBTM
{


    public static function addGroupTicket(Group_Ticket $item)
    {

        if ($item->fields['type'] == CommonITILActor::ASSIGN) {
            $ptAssignGroup = new self();
            $ticket        = new Ticket();
            $ticket->getFromDB($item->fields['tickets_id']);
//            $calendar     = new Calendar();
//            $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );
            $datedebut    = $ticket->fields['date'];
//            if ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
//            } else {
            // cas 24/24 - 7/7
            $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
//            }
            $ok = 1;

            $ptConfig = new Config();
            $ptConfig->getFromDB(1);
            if ($ptConfig->fields["add_waiting"] == 0
                && $ticket->fields["status"] == Ticket::WAITING) {
                $ok = 0;
            }
            if ($ok) {
                $input               = [];
                $input['tickets_id'] = $item->fields['tickets_id'];
                $input['groups_id']  = $item->fields['groups_id'];
                $input['date']       = $_SESSION["glpi_currenttime"];
                $input['begin']      = $delay;
                $ptAssignGroup->add($input);
            }
        }
    }



    public static function deleteGroupTicket(Group_Ticket $item)
    {
        global $DB;

        $ticket        = new Ticket();
        $ptAssignGroup = new self();

        $ticket->getFromDB($item->fields['tickets_id']);

//        $calendar     = new Calendar();
//        $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );

        $iterator = $DB->request([
            'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
            'FROM' => self::getTable(),
            'WHERE' => [
                'tickets_id' => $item->fields['tickets_id'],
                'groups_id' => $item->fields['groups_id'],
                'delay' => NULL,
            ],
        ]);
        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $datedebut = $data['datedebut'];
                $input['id'] = $data['id'];
            }
        } else {
            return;
        }

        if (!$datedebut) {
            $delay = 0;
            // Utilisation calendrier
//        } elseif ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//            $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
//            if ($delay == 0 || is_null($delay)) {
//                $delay = 1;
//            }
        } else {
            // cas 24/24 - 7/7
            $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
        }

        $input['delay'] = $delay;
        $ptAssignGroup->update($input);
    }


    public static function checkAssignGroup(Ticket $ticket)
    {
        global $DB;

        $ok       = 0;
        $ptConfig = new Config();
        $ptConfig->getFromDB(1);
        if ($ptConfig->fields["add_waiting"] == 0) {
            $ok = 1;
        }

        if ($ok && in_array("status", $ticket->updates)
            && isset($ticket->oldvalues["status"])
            && $ticket->oldvalues["status"] == Ticket::WAITING) {
            if ($ticket->countGroups(CommonITILActor::ASSIGN)) {
                foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $d) {
                    $ptAssignGroup = new self();
//                    $calendar      = new Calendar();
//                    $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );
                    $datedebut     = $ticket->fields['date'];
//                    if ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                        $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
//                    } else {
                        // cas 24/24 - 7/7
                        $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
//                    }

                    $input               = [];
                    $input['tickets_id'] = $ticket->getID();
                    $input['groups_id']  = $d["groups_id"];
                    $input['date']       = $_SESSION["glpi_currenttime"];
                    $input['begin']      = $delay;
                    $ptAssignGroup->add($input);
                }
            }
        } elseif ($ok && in_array("status", $ticket->updates)
            && isset($ticket->fields["status"])
            && $ticket->fields["status"] == Ticket::WAITING) {
            if ($ticket->countGroups(CommonITILActor::ASSIGN)) {
                foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $d) {
//                    $calendar      = new Calendar();
//                    $calendars_id  = Entity::getUsedConfig('calendars_strategy', $ticket->fields['entities_id'], 'calendars_id', 0);
                    $ptAssignGroup = new self();

                    $iterator = $DB->request([
                        'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
                        'FROM' => self::getTable(),
                        'WHERE' => [
                            'tickets_id' => $ticket->getID(),
                            'groups_id' => $d["groups_id"],
                            'delay' => NULL,
                        ],
                    ]);
                    if (count($iterator) > 0) {
                        foreach ($iterator as $data) {
                            $datedebut = $data['datedebut'];
                            $input['id'] = $data['id'];
                        }
                    } else {
                        return;
                    }

                    if (!$datedebut) {
                        $delay = 0;
                        // Utilisation calendrier
//                    } elseif ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                        $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
                    } else {
                        // cas 24/24 - 7/7
                        $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
                    }

                    $input['delay'] = $delay;
                    $ptAssignGroup->update($input);
                }
            }
        } elseif (in_array("status", $ticket->updates)
            && isset($ticket->input["status"])
            && ($ticket->input["status"] == Ticket::SOLVED
                || $ticket->input["status"] == Ticket::CLOSED)) {
            if ($ticket->countGroups(CommonITILActor::ASSIGN)) {
                foreach ($ticket->getGroups(CommonITILActor::ASSIGN) as $d) {
//                    $calendar      = new Calendar();
//                    $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );
                    $ptAssignGroup = new self();

                    $iterator = $DB->request([
                        'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
                        'FROM' => self::getTable(),
                        'WHERE' => [
                            'tickets_id' => $ticket->getID(),
                            'groups_id' => $d["groups_id"],
                            'delay' => NULL,
                        ],
                    ]);
                    if (count($iterator) > 0) {
                        foreach ($iterator as $data) {
                            $datedebut = $data['datedebut'];
                            $input['id'] = $data['id'];
                        }
                    } else {
                        return;
                    }

                    if (!$datedebut) {
                        $delay = 0;
                        // Utilisation calendrier
//                    } elseif ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                        $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
                    } else {
                        // cas 24/24 - 7/7
                        $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
                    }

                    $input['delay'] = $delay;
                    $ptAssignGroup->update($input);
                }
            }
        }
    }



    /*
     * type = new or delete
     */
    public function insertGroupChange(Ticket $ticket, $date, $groups_id, $type)
    {

//        $calendar = new Calendar();

        if ($type == 'new') {
//            $calendars_id = Entity::getUsedConfig(
//                'calendars_strategy',
//                $ticket->fields['entities_id'],
//                'calendars_id',
//                0
//            );
//            if ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                $begin = $calendar->getActiveTimeBetween(
//                    Tool::convertDateToRightTimezoneForCalendarUse($ticket->fields['date']),
//                    Tool::convertDateToRightTimezoneForCalendarUse($date)
//                );
//            } else {
                // cas 24/24 - 7/7
                $begin = strtotime($date) - strtotime($ticket->fields['date']);
//            }

            $this->add(['tickets_id' => $ticket->getField("id"),
                'date'       => $date,
                'groups_id'  => $groups_id,
                'begin'      => $begin]);
        } elseif ($type == 'delete') {
            $a_dbentry = $this->find(["tickets_id" => $ticket->getField("id"),
                "groups_id"  => $groups_id,
                "delay"      => null], [], 1);
            if (count($a_dbentry) == 1) {
                $input        = current($a_dbentry);
//                $calendars_id = Entity::getUsedConfig(
//                    'calendars_strategy',
//                    $ticket->fields['entities_id'],
//                    'calendars_id',
//                    0
//                );
//                if ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                    $input['delay'] = $calendar->getActiveTimeBetween(
//                        Tool::convertDateToRightTimezoneForCalendarUse($input['date']),
//                        Tool::convertDateToRightTimezoneForCalendarUse($date)
//                    );
//                } else {
                    // cas 24/24 - 7/7
                    $input['delay'] = strtotime($date) - strtotime($input['date']);
//                }
                $this->update($input);
            }
        }
    }


    public static function showGroupTimeline(Ticket $ticket)
    {
        global $DB;

        $req = $DB->request([
            'FROM' => self::getTable(),
            'WHERE' => ['tickets_id' => $ticket->getField('id')],
            'ORDER' => ['id ASC'],
        ]);
        if (count($req)) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>";
            $groups = [];
            $nb     = 0;
            $size   = count($req);
            foreach ($req as $data) {
                $nb++;
                $date  = strtotime($data['date']);
                $class = ($size == $nb) ? 'now' : 'checked';
                $groups[$date . '_groups_id'] = [
                    'timestamp' => $date,
                    'label'     => Dropdown::getDropdownName(
                        "glpi_groups",
                        $data['groups_id']
                    ) . " (" . Html::timestampToString(
                        $data['delay'],
                        true
                    ) . ")",
                    'class'     => $class];
            }
            $title = __('Ticket assign group history', 'timelineticket');
            echo "<div class='center'>";
            Html::showDatesTimelineGraph([
                'title'   => $title,
                'dates'   => $groups,
                'add_now' => false,
            ]);
            echo "</div>";
            echo "</td>";
            echo "</tr>";
        }
    }

    /*
    * Function to reconstruct timeline for all tickets
    */

    public function reconstructTimeline($id = 0)
    {
        global $DB;

        if ($id == 0) {
            $DB->delete($this->getTable(), [1]);
        } else {
            $DB->delete($this->getTable(), ['tickets_id' => $id]);
        }

        $criteria = [
            'SELECT' => 'id',
            'FROM' => 'glpi_tickets'
        ];
        if ($id > 0) {
            $criteria['WHERE'] = ['id' => $id];
        }
        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {

            $queryGroup = [
                'SELECT' => '*',
                'FROM' => 'glpi_logs',
                'WHERE'     => [
                    'itemtype_link'  => 'Group',
                    'items_id'  => $data['id'],
                    'itemtype'  => 'Ticket',
                    'id_search_option'  => 8
                ],
                'ORDERBY' => 'date_mod ASC',
            ];

            $resultGroup = $DB->request($queryGroup);

            if (count($resultGroup) > 0) {
                foreach ($resultGroup as $dataGroup) {
                    if ($dataGroup['new_value'] != null) {
                        $start     = Toolbox::strpos($dataGroup['new_value'], "(");
                        $end       = Toolbox::strpos($dataGroup['new_value'], ")");
                        $length    = $end - $start;
                        $groups_id = Toolbox::substr($dataGroup['new_value'], $start + 1, $length - 1);

                        $group = new Group();
                        if ($group->getFromDB($groups_id)) {
                            $ticket = new Ticket();
                            $ticket->getFromDB($data['id']);
                            $this->insertGroupChange($ticket, $dataGroup['date_mod'], $groups_id, 'new');
                        }
                    } elseif ($dataGroup['old_value'] != null) {
                        $start     = Toolbox::strpos($dataGroup['old_value'], "(");
                        $end       = Toolbox::strpos($dataGroup['old_value'], ")");
                        $length    = $end - $start;
                        $groups_id = Toolbox::substr($dataGroup['old_value'], $start + 1, $length - 1);

                        $group = new Group();
                        if ($group->getFromDB($groups_id)) {
                            $ticket = new Ticket();
                            $ticket->getFromDB($data['id']);
                            $this->insertGroupChange($ticket, $dataGroup['date_mod'], $groups_id, 'delete');
                        }
                    }
                }
            }
        }
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `tickets_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        `date` timestamp NULL DEFAULT NULL,
                        `groups_id` varchar(255) DEFAULT NULL,
                        `begin` int unsigned NULL,
                        `delay` int(11) NULL,
                        PRIMARY KEY (`id`),
                        KEY `tickets_id` (`tickets_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }

        $query = "ALTER TABLE `$table` CHANGE `delay` `delay` int(11) DEFAULT NULL;";
        $DB->doQuery($query);
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
