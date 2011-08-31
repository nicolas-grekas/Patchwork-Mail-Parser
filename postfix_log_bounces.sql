CREATE TABLE `postfix_log_bounces` (
  `bounced_recipient` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `bounce_type` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `bounce_reason` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `auth_whitelist` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `auth_message_id` int(11) NOT NULL DEFAULT '0',
  `auth_sent_time` datetime DEFAULT NULL,
  `bounced_sender` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `bounced_message_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `bounce_message_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `bounce_parser` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `bounce_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
