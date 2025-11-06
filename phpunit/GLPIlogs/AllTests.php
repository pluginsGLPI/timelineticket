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

class GLPIlogs extends PHPUnit_Framework_TestCase {

   public function testSQLlogs($message = '') {
      
      $filecontent = '';
      $filecontent = file_get_contents(GLPI_ROOT."/files/_log/sql-errors.log");
      
      $this->assertEquals($filecontent, '', 'sql-errors.log not empty ('.$message.')');      
   }
   
   
   
   public function testPHPlogs($message = '') {
      
      $filecontent = '';
      $filecontent = file_get_contents(GLPI_ROOT."/files/_log/php-errors.log");
      
      $this->assertEquals($filecontent, '', 'php-errors.log not empty ('.$message.')');      
   } 
   
}



class GLPIlogs_AllTests  {

   public static function suite() {
      
      $suite = new PHPUnit_Framework_TestSuite('GLPIlogs');
      return $suite;
   }
}

