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

Html::header(__('Setup'), '', "config", "plugin");

if (Session::haveRight("config", READ)
      || Session::haveRight("plugin_timelineticket_ticket", UPDATE)) {
    $ptConfig = new Config();
    $grplevel = new Grouplevel();

    if (isset($_POST["reconstructStates"])) {
        ini_set("max_execution_time", "0");
        ini_set("memory_limit", "-1");
        $ptState = new AssignState();
        $ptState->reconstructTimeline();
        Html::back();
    } elseif (isset($_POST["reconstructGroups"])) {
        ini_set("max_execution_time", "0");
        ini_set("memory_limit", "-1");
        $ptGroup = new AssignGroup();
        $ptGroup->reconstructTimeline();
        Html::back();
    } elseif (isset($_POST["reconstructUsers"])) {
        ini_set("max_execution_time", "0");
        ini_set("memory_limit", "-1");
        $ptUser = new AssignUser();
        $ptUser->reconstructTimeline();
        Html::back();
    } elseif (isset($_POST["reconstructTicket"])) {
        ini_set("max_execution_time", "0");
        ini_set("memory_limit", "-1");
        $ptState = new AssignState();
        $ptState->reconstructTimeline($_POST['tickets_id']);
        $ptGroup = new AssignGroup();
        $ptGroup->reconstructTimeline($_POST['tickets_id']);
        $ptUser = new AssignUser();
        $ptUser->reconstructTimeline($_POST['tickets_id']);
        Html::back();
    } elseif (isset($_POST["add_groups"])
               || isset($_POST["delete_groups"])) {
        $grplevel->update($_POST);
        Html::back();
    } elseif (isset($_POST["update"])) {
        $ptConfig->update($_POST);
        Html::back();
    } else {
        $ptConfig->showReconstructForm();

        $ptConfig->getFromDB(1);
        $ptConfig->showConfigForm();
        Html::footer();
    }
}
