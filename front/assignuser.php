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
// Since GLPI 11, LegacyItemtypeRouteListener::findPluginClass() resolves
// /marketplace/timelineticket/front/<itemtype>.php to the plugin class as soon as no file of
// that name exists, and hands the request to GenericListController. That controller confronts
// one thing only -- $class::canView(), that is the global right plugin_timelineticket_ticket
// in read -- then lets the search engine list the table in raw SQL. None of the guards the
// plugin posts elsewhere is consulted on that path: the history tables carry no entities_id,
// so isEntityAssign() is false and no entity restriction is added; canViewItem(), which
// replays Ticket::can($tickets_id, READ), is never called because the search engine does not
// instantiate the rows; and the plugin declares no plugin_timelineticket_addDefaultWhere hook.
// Any profile holding the plugin right in read -- the right granted at installation, and
// typically given to every technical profile -- could therefore read the whole assignment
// history of the instance, every ticket and every entity confounded, without ever opening a
// ticket.
//
// These three itemtypes have no standalone list to offer: they are read through the ticket
// tab, the dashboard widget and the three reports, all of which replay the ticket visibility
// perimeter. The file existing is enough for LegacyRouterListener to serve it and for the
// itemtype listener to stand down. Should a list ever be wanted, it would have to come with
// rawSearchOptions() and a plugin_timelineticket_addDefaultWhere hook replaying
// Tool::getTicketVisibilityCriteria() and the entity restriction through a subquery on
// glpi_tickets, the way the reports already do -- not with the deletion of this file.
//
// The .form.php counterpart needs no such file: GenericFormController loads the row and
// confronts $object->can($id, READ), which does go through canViewItem().

throw new AccessDeniedHttpException();
