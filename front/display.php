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

// This file exists only to neutralise a route; it holds no entry point of its own.
//
// Display is a presentation class: it renders the ticket tab through static methods and has
// no table of its own -- plugin_timelineticket_install() creates none. It was nonetheless
// reachable, like the history itemtypes, through the generic list route of GLPI 11, which
// confronts the global plugin right and then asks the search engine to list
// glpi_plugin_timelineticket_displays. MySQL answers 1146, that is a 500 any authenticated
// holder of the plugin right could replay at will, feeding the SQL error log and, on an
// instance that has not masked its traces, naming the table and a fragment of the query.
//
// The class has been taken out of CommonDBTM in the same move -- which is what it always
// was -- so the .form.php counterpart now stops at GenericFormController::checkIsValidClass(),
// on "not a DB object", before reaching the database.

throw new AccessDeniedHttpException();
