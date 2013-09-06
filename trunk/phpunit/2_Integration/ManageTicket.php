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

class ManageTicket extends PHPUnit_Framework_TestCase {
   
   protected static $storedate;
   
   /*
    * Create a ticket
    * 01/ Create a new ticket
    * 02/ Wait 2 s
    * 03/ Assign to group 1
    * 04/ wait 2 s
    * 05/ Put in waiting state
    * 06/ wait 1 s
    * 07/ remove group 1 (=> automatic status new)
    * 08/ wait 1 s
    * 09/ assign group 2 (=> automatic status assign)
    * 10/ wait 1 s
    * 11/ Put in waiting state
    * 12/ wait 1 s
    * 13/ assign group 1 (status waiting keep)
    * 14/ remove group 2
    * 15/ wait 2s
    * 16/ put in state assign
    * 17/ wait 1s
    * 18/ solve the ticket
    * 19/ wait 1s
    * 20/ close the ticket
    */
    
    
    
   public function testManageTicket() {
      global $DB, $CFG_GLPI;

      $DB->connect();
    
      $_SESSION['glpiactive_entity'] = 0;
      $CFG_GLPI['root_doc'] = "http://127.0.0.1/fusion0.83/";
      
      $plugin = new Plugin();
      $plugin->getFromDBbyDir("timelineticket");
      $plugin->activate($plugin->fields['id']);
      Plugin::load("timelineticket");

      Session::loadLanguage("en_GB");
      
      $ticket        = new Ticket();
      $group         = new Group();
      $group_ticket  = new Group_Ticket();
      $GLPIlog       = new GLPIlogs();
      
      $_SESSION['plugin_timelineticket_date'] = array();
      
      $group->add(array('name' => 'grtech1'));
      $group->add(array('name' => 'grtech2'));
      
      // * 01/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate = array( '1' => $_SESSION["glpi_currenttime"]);
      
      $input = array();
      $input['name'] = 'Pb with the ticket';
      $input['content'] = 'I have a problem with the ticket';
      $tickets_id = $ticket->add($input);

      $GLPIlog->testSQLlogs('01/');
      $GLPIlog->testPHPlogs('01/');
      
      // * 02/
      sleep(2);
      
      // * 03/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[3] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['_itil_assign']['_type'] = 'group';
      $input['_itil_assign']['groups_id'] = 1;
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('03/');
      $GLPIlog->testPHPlogs('03/');

      $a_db = getAllDatasFromTable('glpi_groups_tickets');
      $a_ref = array();

      $a_ref[1] = array(
          'id'          => '1',
          'tickets_id'  => '1',
          'groups_id'   => '1',
          'type'        => '2'
      );
      $this->assertEquals($a_ref, $a_db, 'May have ticket assigned to group1');
      
      // * 04/
      sleep(2);
      
      // * 05/  
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[5] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['status'] = 'waiting';
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('05/');
      $GLPIlog->testPHPlogs('05/');
      
      $ticket->getFromDB(1);
      $this->assertEquals('waiting', $ticket->fields['status'], 'May have status waiting');
      
      // * 06/
      sleep(1);
      
      // * 07/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[7] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = 1;
      $input['itickets_id'] = $tickets_id;
      $group_ticket->check($input['id'], 'w');
      $group_ticket->delete($input);
      
      $GLPIlog->testSQLlogs('07/');
      $GLPIlog->testPHPlogs('07/');
      
      $a_db = getAllDatasFromTable('glpi_groups_tickets');
      $this->assertEquals(array(), $a_db, 'May have no group assigned');
      
      // * 08/
      sleep(1);
      
      // * 09/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[9] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['_itil_assign']['_type'] = 'group';
      $input['_itil_assign']['groups_id'] = 2;
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('09/');
      $GLPIlog->testPHPlogs('09/');

      $a_db = getAllDatasFromTable('glpi_groups_tickets');
      $a_ref = array();

      $a_ref[2] = array(
          'id'          => '2',
          'tickets_id'  => '1',
          'groups_id'   => '2',
          'type'        => '2'
      );
      $this->assertEquals($a_ref, $a_db, 'May have ticket assigned to group2');
      $ticket->getFromDB(1);
      $this->assertEquals('assign', $ticket->fields['status'], '(09/) Status is assign');
      
      // * 10/
      sleep(1);
      
      // * 11/  
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[11] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['status'] = 'waiting';
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('11/');
      $GLPIlog->testPHPlogs('11/');
      
      // * 12/
      sleep(1);
      
      // * 13/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[13] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['_itil_assign']['_type'] = 'group';
      $input['_itil_assign']['groups_id'] = 1;
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('13/');
      $GLPIlog->testPHPlogs('13/');

      $ticket->getFromDB(1);
      $this->assertEquals('waiting', $ticket->fields['status'], '(13/)May have always status waiting');
      
      // * 14/
      $input = array();
      $input['id'] = 2;
      $input['itickets_id'] = $tickets_id;
      $group_ticket->check($input['id'], 'w');
      $group_ticket->delete($input);
      
      $GLPIlog->testSQLlogs('14/');
      $GLPIlog->testPHPlogs('14/');

      $a_db = getAllDatasFromTable('glpi_groups_tickets');
      $a_ref = array();

      $a_ref[3] = array(
          'id'          => '3',
          'tickets_id'  => '1',
          'groups_id'   => '1',
          'type'        => '2'
      );
      $this->assertEquals($a_ref, $a_db, '(14/) May have ticket assigned to group1');
      $ticket->getFromDB(1);
      $this->assertEquals('waiting', $ticket->fields['status'], '(14/) Status is waiting');
      
      // * 15/
      sleep(2);
      
      // * 16/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[16] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['status'] = 'assign';
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('16/');
      $GLPIlog->testPHPlogs('16/');
      
      // * 17/
      sleep(1);
      
      // * 18/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[18] = $_SESSION["glpi_currenttime"];
      $input = array();
      $input['id'] = $tickets_id;
      $input['solution'] = "solution";
      $ticket->update($input);
      
      $GLPIlog->testSQLlogs('18/');
      $GLPIlog->testPHPlogs('18/');
      
      $ticket->getFromDB(1);
      $this->assertEquals('solved', $ticket->fields['status'], '(18/) Status is solved');
      
      
      // * 19/
      sleep(1);
      
      // * 20/
      $_SESSION["glpi_currenttime"] = date("Y-m-d H:i:s");
      $a_storedate[20] = $_SESSION["glpi_currenttime"];
      $fup = new TicketFollowup();
      $input = array();
      $input['tickets_id'] = $tickets_id;
      $input['add_close'] = 'add_close';
      $fup->add($input);
      
      $GLPIlog->testSQLlogs('20/');
      $GLPIlog->testPHPlogs('20/');
      
      $ticket->getFromDB(1);
      $this->assertEquals('closed', $ticket->fields['status'], '(19/) Status is closed');
      
      self::$storedate = $a_storedate;
   }   
   
   
   
   public function testStates() {
      global $DB;

      $DB->connect();
      
      $a_storedate_temp = self::$storedate;
      $a_states = getAllDatasFromTable('glpi_plugin_timelineticket_states', '', FALSE, 'id');
      
      $this->assertEquals(9, count($a_states), 'Number of lines in states table of plugin');
      
      $a_ref = array(
          'id'          => '1',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[1],
          'old_status'  => '',
          'new_status'  => 'new',
          'delay'       => '0'
      );      
      $this->assertEquals($a_ref, $a_states[1], '(01/)Status New');
      
      $a_ref = array(
          'id'          => '2',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[3],
          'old_status'  => 'new',
          'new_status'  => 'assign',
          'delay'       => (strtotime($a_storedate_temp[3]) - strtotime($a_storedate_temp[1]))
      );      
      $this->assertEquals($a_ref, $a_states[2], '(03/)Status Assign');
      
      $a_ref = array(
          'id'          => '3',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[5],
          'old_status'  => 'assign',
          'new_status'  => 'waiting',
          'delay'       => (strtotime($a_storedate_temp[5]) - strtotime($a_storedate_temp[3]))
      );      
      $this->assertEquals($a_ref, $a_states[3], '(05/)Status Waiting');
      
      $a_ref = array(
          'id'          => '4',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[7],
          'old_status'  => 'waiting',
          'new_status'  => 'new',
          'delay'       => (strtotime($a_storedate_temp[7]) - strtotime($a_storedate_temp[5]))
      );      
      $this->assertEquals($a_ref, $a_states[4], '(07/)Status New');
      
      $a_ref = array(
          'id'          => '5',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[9],
          'old_status'  => 'new',
          'new_status'  => 'assign',
          'delay'       => (strtotime($a_storedate_temp[9]) - strtotime($a_storedate_temp[7]))
      );      
      $this->assertEquals($a_ref, $a_states[5], '(09/)Status Assign');
      
      $a_ref = array(
          'id'          => '6',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[11],
          'old_status'  => 'assign',
          'new_status'  => 'waiting',
          'delay'       => (strtotime($a_storedate_temp[11]) - strtotime($a_storedate_temp[9]))
      );      
      $this->assertEquals($a_ref, $a_states[6], '(11/)Status Waiting');
      
      $a_ref = array(
          'id'          => '7',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[16],
          'old_status'  => 'waiting',
          'new_status'  => 'assign',
          'delay'       => (strtotime($a_storedate_temp[16]) - strtotime($a_storedate_temp[11]))
      );      
      $this->assertEquals($a_ref, $a_states[7], '(16/)Status Assign');
      
      $a_ref = array(
          'id'          => '8',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[18],
          'old_status'  => 'assign',
          'new_status'  => 'solved',
          'delay'       => (strtotime($a_storedate_temp[18]) - strtotime($a_storedate_temp[16]))
      );      
      $this->assertEquals($a_ref, $a_states[8], '(18/)Status Solved');
      
      $a_ref = array(
          'id'          => '9',
          'tickets_id'   => '1',
          'date'        => $a_storedate_temp[20],
          'old_status'  => 'solved',
          'new_status'  => 'closed',
          'delay'       => (strtotime($a_storedate_temp[20]) - strtotime($a_storedate_temp[18]))
      );      
      $this->assertEquals($a_ref, $a_states[9], '(20/)Status Closed');     
   }

   
   
   public function testGroups() {
      global $DB;

      $DB->connect();
      
      $a_storedate_temp = self::$storedate;
      $a_states = getAllDatasFromTable('glpi_plugin_timelineticket_assigngroups', '', FALSE, 'id');
      
      $this->assertEquals(3, count($a_states), 'Number of lines in assigngroup table of plugin');
      
      $ticket   = new Ticket();
      $calendar = new Calendar();
      $ticket->getFromDB(1);
      
      $calendars_id = EntityData::getUsedConfig('calendars_id', $ticket->fields['entities_id']);

      // * 07/
      if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
         $begin = $calendar->getActiveTimeBetween ($ticket->fields['date'], $a_storedate_temp[3]);
      } else {
         // case 24/24 - 7/7
         $begin = strtotime($a_storedate_temp[3])-strtotime($ticket->fields['date']);
      }
      $a_ref = array(
          'id'          => '1',
          'tickets_id'  => '1',
          'date'        => $a_storedate_temp[3],
          'groups_id'   => '1',
          'begin'       => $begin,
          'delay'       => (strtotime($a_storedate_temp[7]) - strtotime($a_storedate_temp[3]))
      );      
      $this->assertEquals($a_ref, $a_states[1], '(07/) Group 1');
      
      // * 14/
      if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
         $begin = $calendar->getActiveTimeBetween ($ticket->fields['date'], $a_storedate_temp[9]);
      } else {
         // case 24/24 - 7/7
         $begin = strtotime($a_storedate_temp[9])-strtotime($ticket->fields['date']);
      }
      $a_ref = array(
          'id'          => '2',
          'tickets_id'  => '1',
          'date'        => $a_storedate_temp[9],
          'groups_id'   => '2',
          'begin'       => $begin,
          'delay'       => (strtotime($a_storedate_temp[13]) - strtotime($a_storedate_temp[9]))
      );      
      $this->assertEquals($a_ref, $a_states[2], '(14/) Group 2');
      
      // * 20/
      if ($calendars_id > 0 && $calendar->getFromDB($calendars_id)) {
         $begin = $calendar->getActiveTimeBetween ($ticket->fields['date'], $a_storedate_temp[13]);
      } else {
         // case 24/24 - 7/7
         $begin = strtotime($a_storedate_temp[13])-strtotime($ticket->fields['date']);
      }
      $a_ref = array(
          'id'          => '3',
          'tickets_id'  => '1',
          'date'        => $a_storedate_temp[13],
          'groups_id'   => '1',
          'begin'       => $begin,
          'delay'       => NULL
      );      
      $this->assertEquals($a_ref, $a_states[3], '(20/) Group 1');
      
   }
 }



class ManageTicket_AllTests  {

   public static function suite() {
    
      $suite = new PHPUnit_Framework_TestSuite('ManageTicket');
      return $suite;
   }
}

?>