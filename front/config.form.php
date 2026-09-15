<?php

/**
 * -------------------------------------------------------------------------
 * TimelineTicket
 * Copyright (C) 2013-2026 by the TimelineTicket Development Team.
 *
 * https://github.com/pluginsGLPI/timelineticket
 * ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of TimelineTicket project.
 *
 * TimelineTicket plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TimelineTicket plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with TimelineTicket plugin. If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------
 *
 * @copyright Copyright (C) 2013-2025 TimelineTicket team
 * @license   AGPL License 3.0 or (at your option) any later version
 * @link      https://github.com/pluginsGLPI/timelineticket
 * @package   TimelineTicket plugin
 * @since     2013
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * --------------------------------------------------------------------------
 */

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Timelineticket\AssignGroup;
use GlpiPlugin\Timelineticket\AssignState;
use GlpiPlugin\Timelineticket\AssignUser;
use GlpiPlugin\Timelineticket\Config;
use GlpiPlugin\Timelineticket\Grouplevel;

// Refuse access explicitly before emitting any output, instead of rendering a header
// followed by an empty body when the operator holds neither required right.
if (!Session::haveRight("config", READ)
    && !Session::haveRight("plugin_timelineticket_ticket", UPDATE)) {
    throw new AccessDeniedHttpException();
}

Html::header(__('Setup'), '', "config", "plugin");

$ptConfig = new Config();
$grplevel = new Grouplevel();

if (isset($_POST["reconstructStates"])) {
    // Global, all-entity rebuild: restrict to config administrators.
    Session::checkRight("config", UPDATE);
    reconstructAllTimelines(new AssignState());
    Html::back();
} elseif (isset($_POST["reconstructGroups"])) {
    Session::checkRight("config", UPDATE);
    reconstructAllTimelines(new AssignGroup());
    Html::back();
} elseif (isset($_POST["reconstructUsers"])) {
    Session::checkRight("config", UPDATE);
    reconstructAllTimelines(new AssignUser());
    Html::back();
} elseif (isset($_POST["reconstructTicket"])) {
    $tickets_id = (int) ($_POST['tickets_id'] ?? 0);
    if ($tickets_id <= 0) {
        Html::back();
    }
    // Rebuilding a ticket's timeline deletes and re-inserts its plugin
    // rows: this is a write operation, so require UPDATE (rights + entity
    // access) on the targeted ticket, not just READ.
    $ticket = new Ticket();
    $ticket->check($tickets_id, UPDATE);
    $ptState = new AssignState();
    $ptState->reconstructTimeline($tickets_id);
    $ptGroup = new AssignGroup();
    $ptGroup->reconstructTimeline($tickets_id);
    $ptUser = new AssignUser();
    $ptUser->reconstructTimeline($tickets_id);
    Html::back();
} elseif (isset($_POST["add_groups"])
           || isset($_POST["delete_groups"])) {
    // Enforce rights + entity access on the targeted Grouplevel row
    // (entity-scoped dropdown) before mutating it.
    $grplevel->check((int) ($_POST['id'] ?? 0), UPDATE);
    $grplevel->update($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    // Global plugin config (singleton): saving requires config UPDATE.
    Session::checkRight("config", UPDATE);
    $ptConfig->update($_POST);
    Html::back();
} else {
    // The three buttons of that form trigger a global, all-entity rebuild, which the branches
    // above gate on config UPDATE: a plugin technician was offered buttons whose submission
    // could only end on an access denied page. Show the form to the operators who may use it.
    if (Session::haveRight("config", UPDATE)) {
        $ptConfig->showReconstructForm();
    }

    // The global add_waiting setting (Config singleton, id 1) is a config-admin
    // setting: only disclose it to operators holding config READ. A plugin
    // technician (plugin_timelineticket_ticket UPDATE) legitimately reaches this
    // page for the per-ticket rebuild form above, but must not see the global
    // plugin configuration they cannot save anyway.
    if (Session::haveRight("config", READ)) {
        $ptConfig->getFromDB(1);
        $ptConfig->showConfigForm();
    }
    Html::footer();
}

/**
 * Run a global timeline rebuild and report the outcome to the operator.
 *
 * reconstructTimeline() runs in a transaction and rolls it back before rethrowing, so a failed
 * rebuild leaves the previous content in place. Without this catch the operator only got a bare
 * error page, with no way to tell whether the table had been emptied or not.
 *
 * @param AssignState|AssignGroup|AssignUser $handler Timeline table to rebuild
 *
 * @return void
 */
function reconstructAllTimelines(AssignState|AssignGroup|AssignUser $handler): void
{
    try {
        $handler->reconstructTimeline();
        Session::addMessageAfterRedirect(__s('Timeline rebuilt', 'timelineticket'), false, INFO);
    } catch (Throwable $e) {
        global $PHPLOGGER;
        $PHPLOGGER->error('Unable to rebuild the timeline.', ['exception' => $e]);
        Session::addMessageAfterRedirect(
            __s('The timeline rebuild failed, no change has been applied', 'timelineticket'),
            false,
            ERROR,
        );
    }
}
