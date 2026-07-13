-- Migration 002: guide imams reference table + mosques.guide_imam_id
-- Status: APPLIED to development DB (guide imams feature, June 2026)
-- The legacy free-text column mosques.guide_imam is kept for backward
-- compatibility; new code reads COALESCE(guide_imams.display_name, mosques.guide_imam).

CREATE TABLE IF NOT EXISTS `guide_imams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_name` varchar(100) DEFAULT NULL,
  `family_name` varchar(100) DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `display_name_normalized` varchar(100) NOT NULL,
  `is_confirmed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `mosques`
  ADD COLUMN `guide_imam_id` int(11) NULL DEFAULT NULL,
  ADD KEY `idx_mosques_guide_imam_id` (`guide_imam_id`);
