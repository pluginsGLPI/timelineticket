#!/bin/bash

#
# -------------------------------------------------------------------------
# TimelineTicket
# Copyright (C) 2013-2026 by the TimelineTicket Development Team.
#
# https://github.com/pluginsGLPI/timelineticket
# ------------------------------------------------------------------------
#
# LICENSE
#
# This file is part of TimelineTicket project.
#
# TimelineTicket plugin is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# TimelineTicket plugin is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with TimelineTicket plugin. If not, see <http://www.gnu.org/licenses/>.
#
# ------------------------------------------------------------------------
#
# @copyright Copyright (C) 2013-2025 TimelineTicket team
# @license   AGPL License 3.0 or (at your option) any later version
# @link      https://github.com/pluginsGLPI/timelineticket
# @package   TimelineTicket plugin
# @since     2013
#            http://www.gnu.org/licenses/agpl-3.0-standalone.html
# --------------------------------------------------------------------------
#

xgettext *.php */*.php */*/*.php --copyright-holder='Timelineticket Development Team' --package-name='GLPI - Timelineticket plugin' --package-version='0.90+1.1' -o locales/glpi.pot -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po  \
	--keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t \
	--keyword=_ex:1c,2,3t --keyword=_nx:1c,2,3,5t --keyword=_sx:1c,2,3t



