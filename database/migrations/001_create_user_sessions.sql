-- Eseguire questa migration PRIMA di pubblicare il codice che usa
-- config/auth.php. Gli OTT correnti vengono importati con 30 giorni di
-- validita per non perdere le code IndexedDB gia presenti sui dispositivi.

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_token_hash` (`token_hash`),
  KEY `idx_user_sessions_user_active` (`user_id`, `revoked_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `user_sessions`
  (`user_id`, `token_hash`, `created_at`, `expires_at`, `last_used_at`)
SELECT
  `id`,
  SHA2(`OTT`, 256),
  NOW(),
  DATE_ADD(NOW(), INTERVAL 30 DAY),
  NOW()
FROM `users`
WHERE `OTT` IS NOT NULL
  AND `OTT` <> '';

