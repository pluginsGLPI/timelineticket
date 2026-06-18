## Timelineticket plugin for GLPI

[![License](https://img.shields.io/badge/License-GNU%20v3-blue.svg?style=flat-square)](https://github.com/pluginsGLPI/timelineticket/blob/master/LICENSE)
[![Web](https://img.shields.io/badge/Web-Infotel-blue.svg?style=flat-square)](https://blogglpi.infotel.com)
[![Translate](https://img.shields.io/badge/Translate-Transifex-cyan)](https://explore.transifex.com/infotelGLPI/GLPI_timelineticket/)

---

### English

This plugin adds a **Timeline** tab to every GLPI ticket, recording status changes, group assignments, and technician assignments in real time.

* **Status history**: Gantt chart and detail table showing time spent in each ticket status, with start/end dates and durations.
* **Group history**: Gantt chart and cross-table (group × status) showing time each technician group was assigned per status. Supports grouping via configurable **service levels**.
* **Technician history**: Gantt chart and cross-table (technician × status) showing assignment durations per status.
* **Assignment swimlane**: Kanban-like view with one column per GLPI status; cards represent groups, technicians, followups, tasks, solutions, and validations, linked by chronological SVG arrows. Filterable by event type.
* **Lateness indicator**: Displays time overdue if the ticket's due date has been exceeded.
* One-click **history reconstruction** per ticket or globally for all tickets.
* **mydashboard widget**: Bar chart showing the number of technician assignments per ticket over a configurable period.

**[Full English documentation →](docs/en/index.md)**

---

### Français

Ce plugin ajoute un onglet **Chronologie** sur chaque ticket GLPI, enregistrant en temps réel les changements de statut, les affectations de groupe et les affectations de technicien.

* **Historique des statuts** : graphique de Gantt et tableau de détail montrant le temps passé dans chaque statut du ticket, avec dates et durées.
* **Historique des groupes** : graphique de Gantt et tableau croisé (groupe × statut) indiquant le temps passé par chaque groupe de techniciens dans chaque statut. Supporte le regroupement via des **niveaux de service** configurables.
* **Historique des techniciens** : graphique de Gantt et tableau croisé (technicien × statut) avec les durées d'affectation par statut.
* **Swimlane d'affectation** : vue Kanban avec une colonne par statut GLPI ; les cartes représentent les groupes, techniciens, suivis, tâches, solutions et validations, reliés par des flèches SVG chronologiques. Filtrable par type d'événement.
* **Indicateur de retard** : affiche la durée de dépassement si la date d'échéance du ticket est passée.
* **Reconstruction de l'historique** en un clic, ticket par ticket ou pour tous les tickets.
* **Widget mydashboard** : graphique en barres du nombre d'affectations de techniciens par ticket sur une période configurable.

**[Documentation complète en français →](docs/fr/index.md)**
