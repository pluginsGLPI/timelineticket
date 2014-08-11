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


function displayMigrationMessage ($id, $msg="") {
   // display nothing
}


class GLPIInstall extends PHPUnit_Framework_TestCase {

   public function testInstall() {
      global $DB;

      $query = "SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'";
      $result = $DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         $DB->query("DROP VIEW ".$data[0]);
      }

      $query = "SHOW TABLES";
      $result = $DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         $DB->query("DROP TABLE ".$data[0]);
      }

      include_once (GLPI_ROOT . "/inc/dbmysql.class.php");
      include_once (GLPI_CONFIG_DIR . "/config_db.php");

      // Install a fresh 0.84 DB
      $DB  = new DB();
      $res = $DB->runFile(GLPI_ROOT ."/install/mysql/glpi-0.84.1-empty.sql");
      $this->assertTrue($res, "Fail: SQL Error during install");
      $DB->query("UPDATE `glpi_configs` SET `version`='0.84.3' WHERE `id`='1'");

      // update default language
      $query = "UPDATE `glpi_configs`
                SET `language` = 'fr_FR'";
      $this->assertTrue($DB->query($query), "Fail: can't set default language");
      $query = "UPDATE `glpi_users`
                SET `language` = 'fr_FR'";
      $this->assertTrue($DB->query($query), "Fail: can't set users language");

      $GLPIlog = new GLPIlogs();
      $GLPIlog->testSQLlogs();
      $GLPIlog->testPHPlogs();
   }
}



class GLPIInstall_AllTests  {

   public static function suite() {

      $suite = new PHPUnit_Framework_TestSuite('GLPIInstall');
      return $suite;
   }
}

?>
?>