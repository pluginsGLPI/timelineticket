# Documentation — TimelineTicket Plugin for GLPI

**License:** GNU AGPL v3+  
**Authors:** Nelly Mahu-Lasson, David Durieux, Xavier Caillaud  
**Repository:** https://github.com/pluginsGLPI/timelineticket

---

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Configuration](#configuration)
   - [Option: waiting time](#option-waiting-time)
   - [Service levels (groups)](#service-levels-groups)
4. [Features](#features)
   - [Timeline tab on the ticket](#timeline-tab-on-the-ticket)
   - [Status history](#status-history)
   - [Detail by technician group](#detail-by-technician-group)
   - [Detail by technician](#detail-by-technician)
   - [Assignment swimlane](#assignment-swimlane)
   - [Rebuilding history](#rebuilding-history)
5. [Statistics (Ticket tab)](#statistics-ticket-tab)
6. [Dashboard widget (mydashboard)](#dashboard-widget-mydashboard)
7. [Rights management](#rights-management)
8. [Uninstallation](#uninstallation)

---

## Overview

The **TimelineTicket** plugin adds a **Timeline** tab to every GLPI ticket. It records status changes, group assignments, and technician assignments in real time, then displays this data as:

- **Gantt charts** (Google Charts) showing the time spent in each status or by each actor
- **Summary tables** with durations per status, per group, and per technician
- A visual **swimlane** grouping all ticket events (statuses, groups, technicians, followups, tasks, solutions, validations) by status lane

---

## Installation

1. Download the plugin from [GitHub](https://github.com/pluginsGLPI/timelineticket) or the GLPI marketplace.
2. Extract the archive into the `plugins/` (or `marketplace/`) directory of your GLPI installation.
3. Run `composer install --no-dev` in the plugin directory.
4. Log in to GLPI as an administrator.
5. Go to **Setup › Plugins**, then click **Install** and **Enable** for *Timeline of tickets*.

---

## Configuration

Access: **Setup › General setup › Timeline tab** (or from the plugin configuration page)  
(Required right: `config UPDATE` or `plugin_timelineticket_ticket UPDATE`)

### Option: waiting time

| Field | Default value | Description |
|-------|---------------|-------------|
| **Count time on groups/technicians when ticket is waiting** | Yes | If enabled (`add_waiting = 1`), time spent in **Pending** (waiting) status is included in the total time calculation for assigned groups and technicians. If disabled, this time is excluded. |

### Service levels (groups)

Access: **Setup › Dropdowns › Service levels** (`Grouplevel` module)

Service levels allow you to **group multiple GLPI groups under a single label** in the group Gantt chart. Instead of displaying each group individually, groups belonging to the same service level are merged under the level name.

| Field | Description |
|-------|-------------|
| **Name** | Service level name displayed on the chart |
| **Position (rank)** | Display order of levels in the chart |
| **Associated groups** | List of GLPI groups included in this service level |

Each service level is entity-scoped and can be set as recursive.

---

## Features

### Timeline tab on the ticket

A **Timeline** tab (hourglass icon) is added to every ticket form in the central interface. It is visible to profiles with the `plugin_timelineticket_ticket` right (read or update).

The tab displays:
- The **calendar in use** for the ticket (entity calendar)
- A **lateness indicator** if the due date has been exceeded (duration calculated via the calendar, or 24/7 if no calendar is configured)
- An informational note: *"This view displays time spent by status, group, technician. The display does not use working hours."*
- The four sections described below

---

### Status history

Section **Result details (Statuses)** — includes:

1. **Status Gantt chart** (Google Charts Timeline): each bar represents the time spent in a given status.
2. **Detail table** with columns:
   - Old status / New status
   - Start date / End date
   - Duration (human-readable format: d h min s)
3. **Grand total** of durations shown in the tab footer.

The current status (if the ticket is not closed) is shown with its duration calculated in real time from the last transition.

---

### Detail by technician group

Section **Result details (Groups in charge of the ticket)** — includes:

1. **Group Gantt chart**: each group (or service level) is represented by a bar on the timeline. If service levels are configured, groups belonging to a level are merged under the level name.
2. **Cross-table**: one row per group, one column per GLPI status, showing the cumulative duration for each group × status combination.

---

### Detail by technician

Section **Result details (Technicians in charge of the ticket)** — includes:

1. **Technician Gantt chart**: each technician is represented by a bar.
2. **Cross-table**: one row per technician, one column per GLPI status, showing the cumulative duration for each technician × status combination.

---

### Assignment swimlane

Section **Assignment swimlane** — a column-based (Kanban-like) view where:

- **Each column corresponds to a GLPI status** (New, In progress — Assigned, In progress — Planned, Pending, Solved, Closed, and Approval if applicable)
- **Each card in a column** represents an event that occurred while the ticket was in that status

#### Event types

| Type | Color | Description |
|------|-------|-------------|
| **Group** | Blue | Technician group assignment |
| **Technician** | Red | Technician assignment |
| **Followup** | Cyan | Followup added (public or private) |
| **Task** | Amber | Task added (public or private) |
| **Solution** | Green | Solution added (always in the Solved column) |
| **Validation** | Purple | Validation request (always in the Approval column) |

#### Filters

A toolbar allows **filtering by event type**: All, Groups, Technicians, Followups, Tasks, Solutions, Validations. Filters hide non-selected cards and **redraw the progression arrows** accordingly.

#### Progression arrows

SVG arrows connect cards of the same type in chronological order, indicating the sequence of events. A dashed arrow indicates a backward transition (e.g. reopening of a ticket).

---

### Rebuilding history

From the ticket's Timeline tab, a **Reconstruct history for this ticket** button regenerates the data from GLPI logs for that single ticket.

From the **plugin configuration page**, three buttons allow rebuilding the global history:
- **Reconstruct states timeline for all tickets**: re-reads GLPI logs (`glpi_logs`, `id_search_option = 12`) for all tickets.
- **Reconstruct technician groups timeline for all tickets**
- **Reconstruct technicians timeline for all tickets**

> These operations may take a long time on large databases.

---

## Statistics (Ticket tab)

Via the `SHOW_ITEM_STATS` hook, the plugin adds additional statistics to the native GLPI **Statistics** tab on tickets (duration per status, duration per group/technician for closed tickets).

---

## Dashboard widget (mydashboard)

If the **mydashboard** plugin is active, a widget is available in the dashboard:

| Widget | Description |
|--------|-------------|
| **Number of assignments per technician to a ticket** | Bar chart showing, for technicians in the selected group(s), the number of times each has been assigned to a ticket over the chosen period |

**Widget parameters:**
- Period (start date / end date)
- Granularity: by **day**, by **week**, or by **month**
- Filtered technician groups (default: groups of the logged-in user)
- Entity and recursiveness

---

## Rights management

Access: **Administration › Profiles › [profile] › Timeline of tickets tab**  
(Tab visible only for central interface profiles)

| Right | Field | Values |
|-------|-------|--------|
| **Ticket** | `plugin_timelineticket_ticket` | `READ` (read) / `UPDATE` (update + configuration access) |

At installation, the Super-Admin profile receives `READ + UPDATE` (value `3`).

- `READ` grants access to the Timeline tab on tickets.
- `UPDATE` additionally grants access to the plugin configuration page.

---

## Uninstallation

1. Go to **Setup › Plugins**.
2. Click **Disable** then **Uninstall** for *Timeline of tickets*.

> **Warning:** Uninstalling removes all plugin tables:
> - `glpi_plugin_timelineticket_configs` (configuration)
> - `glpi_plugin_timelineticket_assignstates` (status history)
> - `glpi_plugin_timelineticket_assigngroups` (group history)
> - `glpi_plugin_timelineticket_assignusers` (technician history)
> - `glpi_plugin_timelineticket_grouplevels` (service levels)
>
> All recorded history data is permanently lost.
