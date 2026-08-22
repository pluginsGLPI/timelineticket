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

if (($uid = Session::getLoginUserID(false))
    && isset($_GET["file"])) {
    list($userID, $filename) = explode("_", $_GET["file"], 2);
    $resolved = realpath(GLPI_GRAPH_DIR . "/" . $_GET["file"]);
    $base_dir = realpath(GLPI_GRAPH_DIR);
    if (($userID == $uid)
        && $resolved !== false
        && $base_dir !== false
        && strpos($resolved, $base_dir . DIRECTORY_SEPARATOR) === 0
        && file_exists($resolved)) {
        list($fname, $extension) = explode(".", $filename);
        return Toolbox::getFileAsResponse($resolved, 'glpi.' . $extension);
    } else {
        throw new BadRequestHttpException('Unauthorized access to this file');
    }
}
