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

class Install extends PHPUnit_Framework_TestCase {

   public function testInstall($verify=1) {
      global $DB;
          
      $DB->connect();

      if (file_exists("save.sql") AND $verify == '0') {
         
         $query = "SHOW TABLES";
         $result = $DB->query($query);
         while ($data=$DB->fetchArray($result)) {
            $DB->query("DROP TABLE ".$data[0]);
         }
         
         $res = $DB->runFile("save.sql");
         $this->assertTrue($res, "Fail: SQL Error during import saved GLPI DB");
         
         echo "======= Import save.sql file =======\n";
         
         $TimelineticketInstall = new TimelineticketInstall();
         $TimelineticketInstall->testDB(TRUE);
         
      } else {      
         $query = "SHOW TABLES";
         $result = $DB->query($query);
         while ($data=$DB->fetchArray($result)) {
            if (strstr($data[0], "timelineticket")) {
               $DB->query("DROP TABLE ".$data[0]);
            }
         }
         
         passthru("cd ../tools && /usr/local/bin/php -f cli_install.php");

         Session::loadLanguage("en_GB");

         $TimelineticketInstall = new TimelineticketInstall();
         $TimelineticketInstall->testDB(TRUE);

         passthru("mysqldump -h ".$DB->dbhost." -u ".$DB->dbuser." -p".$DB->dbpassword." ".$DB->dbdefault." > save.sql");
      }
      
      $GLPIlog = new GLPIlogs();
      $GLPIlog->testSQLlogs();
      $GLPIlog->testPHPlogs();
   }
}



class Install_AllTests  {

   public static function suite() {

      $suite = new PHPUnit_Framework_TestSuite('Install');
      return $suite;
   }
}

