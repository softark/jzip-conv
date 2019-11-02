-- データ長
-- $pref_kana_mb_len = 6
-- $town_kana_mb_len = 19
-- $block_kana_mb_len = 41
-- $street_kana_mb_len = 261
-- $pref_mb_len = 4
-- $town_mb_len = 10
-- $block_mb_len = 35
-- $street_mb_len = 248

-- 大口事業所のデータ長
-- $biz_kana_mb_len = 93
-- $biz_mb_len = 69
-- $pref_mb_len = 4
-- $town_mb_len = 10
-- $block_mb_len = 11
-- $street_mb_len = 42

DROP TABLE IF EXISTS `zip_data`;
CREATE TABLE IF NOT EXISTS `zip_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ag_code` varchar(5) DEFAULT NULL,
  `zip_code` varchar(7) DEFAULT NULL,
  `pref_kana` varchar(8) DEFAULT NULL,
  `town_kana` varchar(30) DEFAULT NULL,
  `block_kana` varchar(60) DEFAULT NULL,
  `street_kana` varchar(300) DEFAULT NULL,
  `pref` varchar(8) DEFAULT NULL,
  `town` varchar(20) DEFAULT NULL,
  `block` varchar(50) DEFAULT NULL,
  `street` varchar(300) DEFAULT NULL,
  `m_zips` int(1) DEFAULT NULL,
  `m_banchis` int(1) DEFAULT NULL,
  `chomes` int(1) DEFAULT NULL,
  `m_blocks` int(1) DEFAULT NULL,
  `biz` int(1) DEFAULT 0,
  `biz_type` int(1) DEFAULT NULL,
  `biz_ser` int(1) DEFAULT NULL,
  `company_kana` varchar(100) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ag_code` (`ag_code`),
  KEY `zip_code` (`zip_code`),
  KEY `pref_kana` (`pref_kana`),
  KEY `town_kana` (`town_kana`),
  KEY `block_kana` (`block_kana`),
  KEY `street_kana` (`street_kana`),
  KEY `pref` (`pref`),
  KEY `town` (`town`),
  KEY `block` (`block`),
  KEY `street` (`street`),
  KEY `company_kana` (`company_kana`),
  KEY `company` (`company`),
  KEY `biz_flags` (`biz`, `biz_type`, `biz_ser`),
  KEY `flags` (`m_zips`, `m_banchis`, `chomes`, `m_blocks`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

DROP TABLE IF EXISTS `zip_hist`;
CREATE TABLE `zip_hist` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY  KEY,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ym` varchar(8) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `zip_hist`
  ADD KEY `created_at` (`created_at`);
