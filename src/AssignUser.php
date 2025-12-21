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
use DbUtils;
use Entity;
use Html;
use Migration;
use Ticket;
use Ticket_User;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class AssignUser extends CommonDBTM
{

    public static function addUserTicket(Ticket_User $item)
    {

        if ($item->fields['type'] == CommonITILActor::ASSIGN) {
            $ptAssignUser = new self();
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
//            if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
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
                $input['users_id']   = $item->fields['users_id'];
                $input['date']       = $_SESSION["glpi_currenttime"];
                $input['begin']      = $delay;
                $ptAssignUser->add($input);
            }
        }
    }


    public static function deleteUserTicket(Ticket_User $item)
    {
        global $DB;

        $ticket       = new Ticket();
        $ptAssignUser = new self();

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
                'users_id' => $item->fields['users_id'],
                'delay' => null,
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
        } else {
            // cas 24/24 - 7/7
            $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
        }

        $input['delay'] = $delay;
        $ptAssignUser->update($input);
    }


    public static function checkAssignUser(Ticket $ticket)
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
            if ($ticket->countUsers(CommonITILActor::ASSIGN)) {
                foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {
                    $ptAssignUser = new self();
//                    $calendar     = new Calendar();
//                    $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );
                    $datedebut    = $ticket->fields['date'];
//                    if ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                        $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
//                    } else {
                        // cas 24/24 - 7/7
                        $delay = strtotime($_SESSION["glpi_currenttime"]) - strtotime($datedebut);
//                    }

                    $input               = [];
                    $input['tickets_id'] = $ticket->getID();
                    $input['users_id']   = $d["users_id"];
                    $input['date']       = $_SESSION["glpi_currenttime"];
                    $input['begin']      = $delay;
                    $ptAssignUser->add($input);
                }
            }
        } elseif ($ok && in_array("status", $ticket->updates)
            && isset($ticket->fields["status"])
            && $ticket->fields["status"] == Ticket::WAITING) {
            if ($ticket->countUsers(CommonITILActor::ASSIGN)) {
                foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {
//                    $calendar     = new Calendar();
//                    $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );
                    $ptAssignUser = new self();

                    $iterator = $DB->request([
                        'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
                        'FROM' => self::getTable(),
                        'WHERE' => [
                            'tickets_id' => $ticket->getID(),
                            'users_id' => $d["users_id"],
                            'delay' => null,
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
                    $ptAssignUser->update($input);
                }
            }
        } elseif (in_array("status", $ticket->updates)
            && isset($ticket->input["status"])
            && ($ticket->input["status"] == Ticket::SOLVED
                || $ticket->input["status"] == Ticket::CLOSED)) {
            if ($ticket->countUsers(CommonITILActor::ASSIGN)) {
                foreach ($ticket->getUsers(CommonITILActor::ASSIGN) as $d) {
//                    $calendar     = new Calendar();
//                    $calendars_id = Entity::getUsedConfig(
//                        'calendars_strategy',
//                        $ticket->fields['entities_id'],
//                        'calendars_id',
//                        0
//                    );
                    $ptAssignUser = new self();

                    $iterator = $DB->request([
                        'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
                        'FROM' => self::getTable(),
                        'WHERE' => [
                            'tickets_id' => $ticket->getID(),
                            'users_id' => $d["users_id"],
                            'delay' => null,
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
                    $ptAssignUser->update($input);
                }
            }
        }
    }


   /*
    * type = new or delete
    */
    public function insertUserChange(Ticket $ticket, $date, $users_id, $type)
    {
        global $DB;

//        $calendar = new Calendar();

        if ($type == 'new') {
//            $calendars_id = Entity::getUsedConfig(
//                'calendars_strategy',
//                $ticket->fields['entities_id'],
//                'calendars_id',
//                0
//            );

//            if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
////                $begin = $calendar->getActiveTimeBetween(
////                    Tool::convertDateToRightTimezoneForCalendarUse($ticket->fields['date']),
////                    Tool::convertDateToRightTimezoneForCalendarUse($date)
////                );
//                $begin = $calendar->getActiveTimeBetween(
//                    $ticket->fields['date'],
//                    $date
//                );
//            } else {
               // cas 24/24 - 7/7
                $begin = strtotime($date) - strtotime($ticket->fields['date']);
//            }

            $this->add(['tickets_id' => $ticket->getID(),
                          'date'       => $date,
                          'users_id'   => $users_id,
                          'begin'      => $begin]);

        } elseif ($type == 'delete') {

            $iterator = $DB->request([
                'SELECT' => ['MAX' => 'date AS datedebut', 'id'],
                'FROM' => self::getTable(),
                'WHERE' => [
                    'tickets_id' => $ticket->getID(),
                    'users_id' => $users_id,
                    'delay' => null,
                ],
            ]);
            if (count($iterator) > 0) {
                foreach ($iterator as $data) {
                    $datedebut = $data['datedebut'];
                    $input['id'] = $data['id'];

                    //                $calendars_id = Entity::getUsedConfig(
//                    'calendars_strategy',
//                    $ticket->fields['entities_id'],
//                    'calendars_id',
//                    0
//                );
                    if (!$datedebut) {
                        $delay = 0;
                        // Utilisation calendrier
//                    } elseif ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
//                        $delay = $calendar->getActiveTimeBetween($datedebut, $_SESSION["glpi_currenttime"]);
                    } else {
                        // cas 24/24 - 7/7
                        $delay = strtotime($date) - strtotime($datedebut);
                    }
//                if ($calendars_id > 0
// && $calendar->getFromDB($calendars_id)) {
////                    $input['delay'] = $calendar->getActiveTimeBetween(
////                        $input['date'],
////                        Tool::convertDateToRightTimezoneForCalendarUse($date)
////                    );
//                    $input['delay'] = $calendar->getActiveTimeBetween(
//                        $input['date'],
//                        $date
//                    );
//                } else {
                    // cas 24/24 - 7/7
//                    Toolbox::logInfo($input['date']);
//                    Toolbox::logInfo($date);
//                    $input['delay'] = strtotime($date) - strtotime($datedebut);

                    $input['delay'] = $delay;
//                }

                    $this->update($input);
                }
            }
        }
    }



    public static function showUserTimeline(Ticket $ticket)
    {
        global $DB;

        $req = $DB->request([
          'FROM' => self::getTable(),
          'WHERE' => [
              'tickets_id' => $ticket->getField('id'),
          ],
          'ORDER'  => 'id ASC'
        ]);
        if ($req->numrows()) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>";
            $users = [];
            $nb    = 0;
            $size  = count($req);
            foreach ($req as $data) {
                $nb++;
                $date  = strtotime($data['date']);
                $class = 'checked';
                if ($size == $nb) {
                    $class = 'now';
                }
                $users[$date . '_users_id'] = [
                'timestamp' => $date,
                'label'     => getUserName($data['users_id']) . " (" . Html::timestampToString($data['delay']) . ")",
                'class'     => $class];
            }
            $title = __('Ticket assign technician history', 'timelineticket');
            echo "<div class='center'>";
            Html::showDatesTimelineGraph([
                                         'title'   => $title,
                                         'dates'   => $users,
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
            $queryUser = [
                'SELECT' => '*',
                'FROM' => 'glpi_logs',
                'WHERE'     => [
                    'itemtype_link'  => 'User',
                    'items_id'  => $data['id'],
                    'itemtype'  => 'Ticket',
                    'id_search_option'  => 5
                ],
                'ORDERBY' => 'date_mod ASC',
            ];

            $resultUser = $DB->request($queryUser);

            if (count($resultUser) > 0) {
                foreach ($resultUser as $dataUser) {
                    if ($dataUser['new_value'] != null) {
                        $start     = Toolbox::strpos($dataUser['new_value'], "(");
                        $end       = Toolbox::strpos($dataUser['new_value'], ")");
                        $length    = $end - $start;
                        $users_id = Toolbox::substr($dataUser['new_value'], $start + 1, $length - 1);

                        $user = new User();
                        if ($user->getFromDB($users_id)) {
                            $ticket = new Ticket();
                            $ticket->getFromDB($data['id']);
                            $this->insertUserChange($ticket, $dataUser['date_mod'], $users_id, 'new');
                        }
                    } elseif ($dataUser['old_value'] != null) {
                        $start     = Toolbox::strpos($dataUser['old_value'], "(");
                        $end       = Toolbox::strpos($dataUser['old_value'], ")");
                        $length    = $end - $start;
                        $users_id = Toolbox::substr($dataUser['old_value'], $start + 1, $length - 1);

                        $user = new User();
                        if ($user->getFromDB($users_id)) {
                            $ticket = new Ticket();
                            $ticket->getFromDB($data['id']);
                            $this->insertUserChange($ticket, $dataUser['date_mod'], $users_id, 'delete');
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
                        `users_id` varchar(255) DEFAULT NULL,
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
