CREATE TABLE `options` (
  `optionid` int(11) NOT NULL AUTO_INCREMENT,
  `table_prefix` varchar(255) NOT NULL DEFAULT '',
  `password_hasher` varchar(255) NOT NULL DEFAULT 'MD5Hasher',
  `filepath` varchar(255) NOT NULL DEFAULT 'uploads/',
  `currency_symbol` varchar(1) NOT NULL DEFAULT '$',
  `large-icons` tinyint(1) DEFAULT '1',
  `logErrors` tinyint(1) DEFAULT '0',
  `includeCustom` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`optionid`)
);
