# Documentation — Plugin TimelineTicket pour GLPI

**Licence :** GNU AGPL v3+  
**Auteurs :** Nelly Mahu-Lasson, David Durieux, Xavier Caillaud  
**Dépôt :** https://github.com/pluginsGLPI/timelineticket

---

## Table des matières

1. [Présentation](#présentation)
2. [Installation](#installation)
3. [Configuration](#configuration)
   - [Option : temps en attente](#option--temps-en-attente)
   - [Niveaux de service (groupes)](#niveaux-de-service-groupes)
4. [Fonctionnalités](#fonctionnalités)
   - [Onglet Chronologie sur le ticket](#onglet-chronologie-sur-le-ticket)
   - [Historique des statuts](#historique-des-statuts)
   - [Détail par groupe de techniciens](#détail-par-groupe-de-techniciens)
   - [Détail par technicien](#détail-par-technicien)
   - [Swimlane d'affectation](#swimlane-daffectation)
   - [Reconstruction de l'historique](#reconstruction-de-lhistorique)
5. [Statistiques (onglet Ticket)](#statistiques-onglet-ticket)
6. [Widget tableau de bord (mydashboard)](#widget-tableau-de-bord-mydashboard)
7. [Gestion des droits](#gestion-des-droits)
8. [Désinstallation](#désinstallation)

---

## Présentation

Le plugin **TimelineTicket** ajoute un onglet **Chronologie** sur chaque ticket GLPI. Il enregistre en temps réel les changements de statut, d'affectation de groupe et d'affectation de technicien, puis restitue ces données sous forme de :

- **Graphiques de Gantt** (Google Charts) montrant les intervalles de temps passés dans chaque statut ou par chaque acteur
- **Tableaux de synthèse** avec les durées par statut, par groupe et par technicien
- Un **swimlane** visuel regroupant tous les événements du ticket (statuts, groupes, techniciens, suivis, tâches, solutions, validations) par couloir de statut

---

## Installation

1. Télécharger le plugin depuis [GitHub](https://github.com/pluginsGLPI/timelineticket) ou la marketplace GLPI.
2. Décompresser l'archive dans le dossier `plugins/` (ou `marketplace/`) de votre GLPI.
3. Exécuter `composer install --no-dev` dans le dossier du plugin.
4. Se connecter à GLPI en tant qu'administrateur.
5. Aller dans **Configuration › Plugins**, cliquer sur **Installer** puis **Activer** pour *Timeline of tickets*.

---

## Configuration

Accès : **Configuration › Configuration générale › onglet Timeline** (ou lien depuis la page de configuration du plugin)  
(Droit requis : `config UPDATE` ou `plugin_timelineticket_ticket UPDATE`)

### Option : temps en attente

| Champ | Valeur par défaut | Description |
|-------|-------------------|-------------|
| **Comptabiliser le temps sur groupes/techniciens lorsque le ticket est en attente** | Oui | Si activé (`add_waiting = 1`), le temps passé en statut **En attente** est inclus dans le calcul du temps total des groupes et techniciens affectés. Si désactivé, ce temps est exclu. |

### Niveaux de service (groupes)

Accès : **Configuration › Listes déroulantes › Niveaux de service** (module `Grouplevel`)

Les niveaux de service permettent de **regrouper plusieurs groupes GLPI sous un même libellé** dans le graphique de Gantt des groupes. Au lieu d'afficher chaque groupe individuellement, les groupes appartenant au même niveau de service sont regroupés sous le nom du niveau.

| Champ | Description |
|-------|-------------|
| **Nom** | Nom du niveau de service affiché sur le graphique |
| **Position (rank)** | Ordre d'affichage des niveaux dans le graphique |
| **Groupes associés** | Liste des groupes GLPI inclus dans ce niveau de service |

Chaque niveau de service est défini par entité et peut être récursif.

---

## Fonctionnalités

### Onglet Chronologie sur le ticket

Un onglet **Chronologie** (icône sablier) est ajouté sur la fiche de chaque ticket dans l'interface centrale. Il est visible pour les profils ayant le droit `plugin_timelineticket_ticket` (lecture ou mise à jour).

L'onglet affiche :
- Le **calendrier utilisé** pour le ticket (calendrier de l'entité)
- Un **indicateur de retard** si la date d'échéance est dépassée (durée de retard calculée via le calendrier ou en 24/24 7/7 si aucun calendrier)
- Une note informative : *"Cette vue affiche le temps passé par statut, groupe, technicien. L'affichage n'utilise pas les horaires de travail."*
- Les quatre sections décrites ci-dessous

---

### Historique des statuts

Section **Résultats détaillés (Statuts)** — inclut :

1. **Graphique de Gantt des statuts** (Google Charts Timeline) : chaque barre représente la durée dans un statut donné.
2. **Tableau de détail** avec les colonnes :
   - Ancien statut / Nouveau statut
   - Date de début / Date de fin
   - Durée (format lisible : j h min s)
3. **Total général** des durées affiché en pied de page de l'onglet.

Le statut courant (si le ticket n'est pas clôturé) est affiché avec sa durée calculée en temps réel depuis la dernière transition.

---

### Détail par groupe de techniciens

Section **Résultats détaillés (Groupes en charge du ticket)** — inclut :

1. **Graphique de Gantt des groupes** : chaque groupe (ou niveau de service) est représenté par une barre sur la timeline. Si des niveaux de service sont configurés, les groupes appartenant à un niveau sont regroupés sous son nom.
2. **Tableau croisé** : une ligne par groupe, une colonne par statut GLPI, avec la durée cumulée passée dans chaque combinaison groupe × statut.

---

### Détail par technicien

Section **Résultats détaillés (Techniciens en charge du ticket)** — inclut :

1. **Graphique de Gantt des techniciens** : chaque technicien est représenté par une barre.
2. **Tableau croisé** : une ligne par technicien, une colonne par statut GLPI, avec la durée cumulée par combinaison technicien × statut.

---

### Swimlane d'affectation

Section **Swimlane d'affectation** — vue en colonnes (Kanban-like) où :

- **Chaque colonne correspond à un statut GLPI** (Nouveau, En cours — Affecté, En cours — Planifié, En attente, Résolu, Clos, et Validation si applicable)
- **Chaque carte dans une colonne** représente un événement survenu pendant que le ticket était dans ce statut

#### Types d'événements représentés

| Type | Couleur | Description |
|------|---------|-------------|
| **Groupe** | Bleu | Affectation d'un groupe de techniciens |
| **Technicien** | Rouge | Affectation d'un technicien |
| **Suivi** | Cyan | Ajout d'un suivi (public ou privé) |
| **Tâche** | Amber | Ajout d'une tâche (publique ou privée) |
| **Solution** | Vert | Ajout d'une solution (toujours dans la colonne Résolu) |
| **Validation** | Violet | Demande de validation (toujours dans la colonne Validation) |

#### Filtres

Une barre d'outils permet de **filtrer par type d'événement** : Tous, Groupes, Techniciens, Suivis, Tâches, Solutions, Validations. Les filtres masquent les cartes non sélectionnées et **retraçent les flèches** de progression en conséquence.

#### Flèches de progression

Des flèches SVG relient chronologiquement les cartes de même type, indiquant l'ordre des événements. Une flèche en pointillés renforcés indique un retour en arrière (ex. : réouverture).

---

### Reconstruction de l'historique

Depuis l'onglet Chronologie du ticket, un bouton **Reconstruire l'historique pour ce ticket** permet de régénérer les données depuis les journaux GLPI pour un seul ticket.

Depuis la **page de configuration du plugin**, trois boutons permettent de reconstruire l'historique global :
- **Reconstruire la chronologie des statuts pour tous les tickets** : relit les journaux GLPI (`glpi_logs`, `id_search_option = 12`) pour tous les tickets.
- **Reconstruire la chronologie des groupes pour tous les tickets**
- **Reconstruire la chronologie des techniciens pour tous les tickets**

> Ces opérations peuvent prendre du temps sur de grandes bases.

---

## Statistiques (onglet Ticket)

Via le hook `SHOW_ITEM_STATS`, le plugin affiche des statistiques supplémentaires dans l'onglet **Statistiques** natif de GLPI sur les tickets (durée par statut, durée par groupe/technicien sur les tickets clos).

---

## Widget tableau de bord (mydashboard)

Si le plugin **mydashboard** est actif, un widget est disponible dans le tableau de bord :

| Widget | Description |
|--------|-------------|
| **Nombre d'affectations par technicien à un ticket** | Graphique en barres affichant, pour les techniciens du ou des groupes sélectionnés, le nombre de fois qu'ils ont été affectés à un ticket sur la période choisie |

**Paramètres du widget :**
- Période (date de début / date de fin)
- Granularité : par **jour**, par **semaine** ou par **mois**
- Groupes de techniciens filtrés (par défaut : les groupes de l'utilisateur connecté)
- Entité et récursivité

---

## Gestion des droits

Accès : **Administration › Profils › [profil] › onglet Timeline of tickets**  
(Onglet visible uniquement pour les profils en mode interface centrale)

| Droit | Champ | Valeurs |
|-------|-------|---------|
| **Ticket** | `plugin_timelineticket_ticket` | `READ` (lecture) / `UPDATE` (mise à jour + accès config) |

À l'installation, le profil Super-Admin reçoit le droit `READ + UPDATE` (valeur `3`).

- Le droit `READ` donne accès à l'onglet Chronologie sur les tickets.
- Le droit `UPDATE` donne en plus accès à la page de configuration du plugin.

---

## Désinstallation

1. Aller dans **Configuration › Plugins**.
2. Cliquer sur **Désactiver** puis **Désinstaller** pour *Timeline of tickets*.

> **Attention :** La désinstallation supprime toutes les tables du plugin :
> - `glpi_plugin_timelineticket_configs` (configuration)
> - `glpi_plugin_timelineticket_assignstates` (historique des statuts)
> - `glpi_plugin_timelineticket_assigngroups` (historique des groupes)
> - `glpi_plugin_timelineticket_assignusers` (historique des techniciens)
> - `glpi_plugin_timelineticket_grouplevels` (niveaux de service)
>
> Toutes les données d'historique enregistrées sont perdues.
