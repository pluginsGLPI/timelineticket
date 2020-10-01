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

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', '../../..');
   require GLPI_ROOT . "/inc/includes.php";
   restore_error_handler();

   error_reporting(E_ALL | E_STRICT);
   ini_set('display_errors','On');
}

class TimelineticketInstall extends PHPUnit_Framework_TestCase {

   public function testDB($run=FALSE) {
      global $DB;
       
      if ($run == FALSE) {
         return;
      }
      
       $comparaisonSQLFile = "plugin_timelineticket-empty.sql";
       // See http://joefreeman.co.uk/blog/2009/07/php-script-to-compare-mysql-database-schemas/
       
       $file_content = file_get_contents("../../timelineticket/install/mysql/".$comparaisonSQLFile);
       $a_lines = explode("\n", $file_content);
       
       $a_tables_ref = array();
       $current_table = '';
       foreach ($a_lines as $line) {
          if (strstr($line, "CREATE TABLE ")) {
             $matches = array();
             preg_match("/`(.*)`/", $line, $matches);
             $current_table = $matches[1];
          } else {
             if (preg_match("/^`/", trim($line))) {
                $s_line = explode("`", $line);
                $s_type = explode("COMMENT", $s_line[2]);
                $s_type[0] = trim($s_type[0]);
                $s_type[0] = str_replace(" COLLATE utf8_unicode_ci", "", $s_type[0]);
                $s_type[0] = str_replace(" CHARACTER SET utf8", "", $s_type[0]);
                $a_tables_ref[$current_table][$s_line[1]] = str_replace(",", "", $s_type[0]);
             }
          }
       }
       
      // * Get tables from MySQL
      $a_tables_db = array();
      $a_tables = array();
      // SHOW TABLES;
      $query = "SHOW TABLES";
      $result = $DB->query($query);
      while ($data=$DB->fetchArray($result)) {
         if (strstr($data[0], "timelineticket")){
            $data[0] = str_replace(" COLLATE utf8_unicode_ci", "", $data[0]);
            $data[0] = str_replace("( ", "(", $data[0]);
            $data[0] = str_replace(" )", ")", $data[0]);
            $a_tables[] = $data[0];
         }
      }
      
      foreach($a_tables as $table) {
         $query = "SHOW COLUMNS FROM ".$table;
         $result = $DB->query($query);
         while ($data=$DB->fetchArray($result)) {
            $construct = $data['Type'];
//            if ($data['Type'] == 'text') {
//               $construct .= ' COLLATE utf8_unicode_ci';
//            }
            if ($data['Type'] == 'text') {
               if ($data['Null'] == 'NO') {
                  $construct .= ' NOT NULL';
               } else {
                  $construct .= ' DEFAULT NULL';
               }
            } else if ($data['Type'] == 'longtext') {
               if ($data['Null'] == 'NO') {
                  $construct .= ' NOT NULL';
               } else {
                  $construct .= ' DEFAULT NULL';
               }
            } else {
               if ((strstr($data['Type'], "char")
                       OR $data['Type'] == 'datetime'
                       OR strstr($data['Type'], "int"))
                       AND $data['Null'] == 'YES'
                       AND $data['Default'] == '') {
                  $construct .= ' DEFAULT NULL';
               } else {               
                  if ($data['Null'] == 'YES') {
                     $construct .= ' NULL';
                  } else {
                     $construct .= ' NOT NULL';
                  }
                  if ($data['Extra'] == 'auto_increment') {
                     $construct .= ' AUTO_INCREMENT';
                  } else {
//                     if ($data['Type'] != 'datetime') {
                        $construct .= " DEFAULT '".$data['Default']."'";
//                     }
                  }
               }
            }
            $a_tables_db[$table][$data['Field']] = $construct;
         }         
      }

      $a_tables_ref_tableonly = array();
      foreach ($a_tables_ref as $table=>$data) {
         $a_tables_ref_tableonly[] = $table;
      }
      $a_tables_db_tableonly = array();
      foreach ($a_tables_db as $table=>$data) {
         $a_tables_db_tableonly[] = $table;
      }
      
       // Compare
      $tables_toremove = array_diff($a_tables_db_tableonly, $a_tables_ref_tableonly);
      $tables_toadd = array_diff($a_tables_ref_tableonly, $a_tables_db_tableonly);

      // See tables missing or to delete
      $this->assertEquals(count($tables_toadd), 0, 'Tables missing '.print_r($tables_toadd, TRUE));
      $this->assertEquals(count($tables_toremove), 0, 'Tables to delete '.print_r($tables_toremove, TRUE));

      // See if fields are same
      foreach ($a_tables_db as $table=>$data) {
         if (isset($a_tables_ref[$table])) {
            $fields_toremove = array_diff_assoc($data, $a_tables_ref[$table]);
            $fields_toadd = array_diff_assoc($a_tables_ref[$table], $data);
            $diff = "======= DB ============== Ref =======> ".$table."\n";
            $diff .= print_r($data, TRUE);
            $diff .= print_r($a_tables_ref[$table], TRUE);

            // See tables missing or to delete
            $this->assertEquals(count($fields_toadd), 0, 'Fields missing/not good in '.$table.' '.print_r($fields_toadd, TRUE)." into ".$diff);
            $this->assertEquals(count($fields_toremove), 0, 'Fields to delete in '.$table.' '.print_r($fields_toremove, TRUE)." into ".$diff);

         }
      }
   }
}

require_once 'Install/AllTests.php';
require_once 'Update/AllTests.php';

class TimelineticketInstall_AllTests  {

   public static function suite() {

      $suite = new PHPUnit_Framework_TestSuite('TimelineticketInstall');
      $suite->addTest(Install_AllTests::suite());
      $suite->addTest(Update_AllTests::suite());
      return $suite;
   }
}

