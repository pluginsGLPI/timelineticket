## Timelineticket plugin for GLPI

[![License](https://img.shields.io/badge/License-GNU%20AGPL%20v3-blue.svg?style=flat-square)](https://github.com/pluginsGLPI/timelineticket/blob/master/LICENSE)
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

---

### Outbound network call — Google Charts

The Gantt charts are drawn by **Google Charts**. The bootstrap script is served locally
(`public/js/google-charts/loader.js`, injected only on the pages that actually draw a chart),
but that loader still fetches its rendering modules from `https://www.gstatic.com/charts/`
(and, for map charts, `ajax.googleapis.com` / `maps.googleapis.com`) when a chart is displayed.

**The charts are disabled by default.** Because that call leaves the network from the browser of
a logged-in user, drawing them is a decision of the operator, not a side effect of installing or
upgrading the plugin. Turn them on in *Setup > Plugins > TimelineTicket*, option **Draw the
timeline as a chart (Google Charts)**. While the option is off, no request is made to Google and
the tabs keep their detail tables — the chart area is simply not rendered.

Consequences for restricted installations:

* an instance with no outbound Internet access, or with a strict `script-src 'self'` CSP,
  displays no chart;
* every display of a timeline chart sends a request to Google from the browser of the
  logged-in user (IP address and `Referer` leave the network).

To allow the charts through a proxy or a content security policy, authorise
`https://www.gstatic.com/charts/`. Scope the exception to that path rather than to the whole
domain, so that only the rendering modules of the library may execute in the origin of GLPI:

```
script-src 'self' https://www.gstatic.com/charts/;
```

When the modules cannot be reached, the tab now displays an explicit notice in place of the
chart instead of staying blank.

The rendering modules are deliberately not bundled with the plugin: the Google Charts terms of
service require the library to be loaded from Google's own servers and allow neither its
redistribution nor its self-hosting, so only the bootstrap loader, which Google publishes as a
standalone file, is served locally. An instance that must not reach Google at all has to give up
the timeline tabs rather than expect a local copy of the modules.

### Appel réseau sortant — Google Charts

Les diagrammes de Gantt sont rendus par **Google Charts**. Le script d'amorçage est servi
localement (`public/js/google-charts/loader.js`, injecté uniquement sur les pages qui affichent
réellement un graphique), mais ce chargeur récupère toujours ses modules de rendu depuis
`https://www.gstatic.com/charts/` (et, pour les cartes, `ajax.googleapis.com` /
`maps.googleapis.com`) au moment de l'affichage.

**Les graphiques sont désactivés par défaut.** Cet appel sortant part du navigateur d'un
utilisateur connecté : leur affichage relève donc d'une décision de l'exploitant, et non d'un
effet de bord de l'installation ou de la mise à jour du plugin. Ils s'activent dans
*Configuration > Plugins > TimelineTicket*, option **Afficher la chronologie sous forme de
graphique (Google Charts)**. Tant que l'option est désactivée, aucune requête n'est émise vers
Google et les onglets conservent leurs tableaux de détail — seule la zone du graphique n'est pas
rendue.

Conséquences pour les installations cloisonnées :

* une instance sans accès Internet sortant, ou sous CSP stricte `script-src 'self'`,
  n'affiche aucun graphique ;
* chaque affichage d'une chronologie émet une requête vers Google depuis le navigateur de
  l'utilisateur connecté (adresse IP et `Referer` sortent du réseau).

Pour autoriser les graphiques derrière un proxy ou une CSP, il faut ouvrir
`https://www.gstatic.com/charts/`. L'exception doit porter sur ce chemin et non sur le domaine
entier, afin que seuls les modules de rendu de la bibliothèque puissent s'exécuter dans
l'origine de GLPI :

```
script-src 'self' https://www.gstatic.com/charts/;
```

Lorsque les modules restent injoignables, l'onglet affiche désormais un message explicite à la
place du graphique au lieu de rester vide.

Les modules de rendu ne sont volontairement pas embarqués dans le plugin : les conditions
d'utilisation de Google Charts imposent que la bibliothèque soit chargée depuis les serveurs de
Google et n'autorisent ni sa redistribution ni son auto-hébergement. Seul le script d'amorçage,
que Google publie comme fichier autonome, est donc servi localement. Une instance qui ne doit en
aucun cas joindre Google doit renoncer aux onglets de chronologie plutôt que d'attendre une copie
locale des modules.
