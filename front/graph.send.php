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

use Glpi\Exception\Http\BadRequestHttpException;

// file[]=x made the cast below emit an "Array to string conversion" warning and then look
// for a file literally named "Array": refuse anything that is not a string up front.
if (($uid = Session::getLoginUserID(false))
    && isset($_GET["file"])
    && is_string($_GET["file"])) {
    // The file name is built as "<users_id>_<name>.<extension>": reject anything that
    // does not follow that shape instead of letting list()/explode() return nulls.
    $parts = explode("_", (string) $_GET["file"], 2);
    $userID = $parts[0] ?? '';
    $filename = $parts[1] ?? '';
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $resolved = realpath(GLPI_GRAPH_DIR . "/" . $_GET["file"]);
    $base_dir = realpath(GLPI_GRAPH_DIR);
    if (count($parts) === 2
        && $extension !== ''
        && ctype_digit($userID)
        && (int) $userID === (int) $uid
        && $resolved !== false
        && $base_dir !== false
        && strpos($resolved, $base_dir . DIRECTORY_SEPARATOR) === 0
        && file_exists($resolved)) {
        return Toolbox::getFileAsResponse($resolved, 'glpi.' . $extension);
    } else {
        throw new BadRequestHttpException('Unauthorized access to this file');
    }
}

// Every other path used to fall off the end of the script and answer 200 with an empty body,
// which let a caller tell "no file parameter" apart from "file present but refused" and so
// enumerate, by difference, the entries of GLPI_GRAPH_DIR whose name starts with its own id.
// Reject explicitly instead, so all refusals look alike.
//
// This endpoint is in fact dead code: nothing in the plugin writes to GLPI_GRAPH_DIR any more
// -- the charts are drawn in the browser by Google Charts -- and no caller references this
// file. It should be deleted outright rather than kept alive without a consumer; a guard no
// feature exercises is a guard nobody will maintain correctly.
throw new BadRequestHttpException('Unauthorized access to this file');
