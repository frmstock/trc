CREATE TABLE IF NOT EXISTS `myusers`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `terminal_id` bigint(20) UNSIGNED NOT NULL,
  `list` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `update_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `terminal_id`(`terminal_id`) USING BTREE,
  CONSTRAINT `myusers_ibfk_1` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;
