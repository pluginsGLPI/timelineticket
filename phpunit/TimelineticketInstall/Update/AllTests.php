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

class Update extends PHPUnit_Framework_TestCase {

   public function testUpdate() {
      
      $Update = new Update();
//      $Update->Update("2.3.3");
//      $Update->Update("2.1.3");
   }
   
   
   function Update($version = '') {
      global $DB;

      if ($version == '') {
         return;
      }
      echo "#####################################################\n
            ######### Update from version ".$version."###############\n
            #####################################################\n";
      $GLPIInstall = new GLPIInstall();
      $GLPIInstall->testInstall();
      
      $query = "SHOW TABLES";
      $result = $DB->query($query);
      while ($data=$DB->fetchArray($result)) {
         if (strstr($data[0], "timelineticket")) {
            $DB->query("DROP TABLE ".$data[0]);
         }
      }
      $query = "DELETE FROM `glpi_displaypreferences` 
         WHERE `itemtype` LIKE 'PluginTimelineticket%'";
      $DB->query($query);
      
      // ** Insert in DB
      $res = $DB->runFile(PLUGIN_TIMELINETICKET_DIR ."/phpunit/TimelineticketInstall/Update/mysql/i-".$version.".sql");
      $this->assertTrue($res, "Fail: SQL Error during insert version ".$version);
      
      passthru("cd ../tools/ && /usr/local/bin/php -f cli_install.php");
      
      $TimelineticketInstall = new TimelineticketInstall();
      $TimelineticketInstall->testDB(TRUE);

      $GLPIlog = new GLPIlogs();
      $GLPIlog->testSQLlogs();
      $GLPIlog->testPHPlogs();
      
   }
}



class Update_AllTests  {

   public static function suite() {

      $suite = new PHPUnit_Framework_TestSuite('Update');
      return $suite;
      
   }
}

