
DROP TABLE IF EXISTS `glpi_plugin_timelineticket_states`;

CREATE TABLE `glpi_plugin_timelineticket_states` (
   `id` int unsigned NOT NULL AUTO_INCREMENT,
   `tickets_id` int unsigned NOT NULL DEFAULT '0',
   `date` timestamp NULL DEFAULT NULL,
   `old_status` varchar(255) DEFAULT NULL,
   `new_status` varchar(255) DEFAULT NULL,
   `delay` int unsigned DEFAULT NULL,
   PRIMARY KEY (`id`),
   KEY `tickets_id` (`tickets_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



DROP TABLE IF EXISTS `glpi_plugin_timelineticket_assigngroups`;

CREATE TABLE `glpi_plugin_timelineticket_assigngroups` (
   `id` int unsigned NOT NULL AUTO_INCREMENT,
   `tickets_id` int unsigned NOT NULL DEFAULT '0',
   `date` timestamp NULL DEFAULT NULL,
   `groups_id` varchar(255) DEFAULT NULL,
   `begin` int unsigned DEFAULT NULL,
   `delay` int unsigned DEFAULT NULL,
   PRIMARY KEY (`id`),
   KEY `tickets_id` (`tickets_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



DROP TABLE IF EXISTS `glpi_plugin_timelineticket_assignusers`;

CREATE TABLE `glpi_plugin_timelineticket_assignusers` (
   `id` int unsigned NOT NULL AUTO_INCREMENT,
   `tickets_id` int unsigned NOT NULL DEFAULT '0',
   `date` timestamp NULL DEFAULT NULL,
   `users_id` varchar(255) DEFAULT NULL,
   `begin` int unsigned DEFAULT NULL,
   `delay` int unsigned DEFAULT NULL,
   PRIMARY KEY (`id`),
   KEY `tickets_id` (`tickets_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



DROP TABLE IF EXISTS `glpi_plugin_timelineticket_grouplevels`;

CREATE TABLE `glpi_plugin_timelineticket_grouplevels` (
   `id` int unsigned NOT NULL AUTO_INCREMENT,
   `entities_id` int unsigned NOT NULL DEFAULT '0',
   `is_recursive` tinyint NOT NULL DEFAULT '0',
   `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
   `groups` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
   `rank` smallint(6) NOT NULL DEFAULT '0',
   `comment` text collate utf8mb4_unicode_ci,
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


DROP TABLE IF EXISTS `glpi_plugin_timelineticket_configs`;

CREATE TABLE `glpi_plugin_timelineticket_configs` (
   `id` int unsigned NOT NULL AUTO_INCREMENT,
   `add_waiting` int unsigned NOT NULL DEFAULT '1',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
