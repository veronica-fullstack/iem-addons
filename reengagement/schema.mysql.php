<?php

// an array of tables that are created.
// we need this so if the addon is uninstalled, we know what we need to clean up.
$tables = ['reengagements', 'reengagement_statistics', 'reengagement_statistics_newsletters', 'reengagements_config', 'reengagements_list', 'reengagements_listinfo', 'reengagements_subscriber'];

// the actual queries we're going to run.
$queries = [];

$queries[] = 'CREATE TABLE IF NOT EXISTS  %%TABLEPREFIX%%reengagements (
		reengageid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		reengagename VARCHAR(200),
		reengage_typeof VARCHAR(100),
		duration_type VARCHAR(100),
		reengagedetails TEXT,
		createdate INT DEFAULT 0,
		userid INT DEFAULT 0 REFERENCES %%TABLEPREFIX%%users(userid),
		jobid INT DEFAULT 0,
		jobstatus CHAR(1) DEFAULT NULL,
		lastsent INT DEFAULT 0
	) CHARACTER SET=utf8mb4 ENGINE=INNODB
	';

$queries[] = 'CREATE TABLE IF NOT EXISTS  %%TABLEPREFIX%%reengagement_statistics (
		reengage_statid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		reengageid INT NOT NULL DEFAULT 0,
		jobid INT NOT NULL DEFAULT 0,
		starttime INT NOT NULL DEFAULT 0,
		finishtime INT NOT NULL DEFAULT 0,
		hiddenby INT NOT NULL DEFAULT 0
	) CHARACTER SET=utf8mb4 ENGINE=INNODB
	';

$queries[] = 'CREATE TABLE IF NOT EXISTS %%TABLEPREFIX%%reengagements_config (
		  `Id` int(11) NOT NULL AUTO_INCREMENT,
		  `Variable_Name` varchar(255) NOT NULL,
		  `Variable_Value` varchar(255) NOT NULL,
		  PRIMARY KEY (`Id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1
	';

$queries[] = "CREATE TABLE IF NOT EXISTS %%TABLEPREFIX%%reengagements_list (
		  `rl_id` int(11) NOT NULL AUTO_INCREMENT,
		  `listid` int(11) NOT NULL,
		  `total_records` int(11) NOT NULL,
		  `last_sync` int(11) NOT NULL,
		  `last_track` int(11) NOT NULL DEFAULT 0,
		  `sync_status` enum('n','p','c') NOT NULL DEFAULT 'n',
		  PRIMARY KEY (`rl_id`),
		  UNIQUE KEY `list_id` (`listid`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1
	";

$queries[] = "CREATE TABLE IF NOT EXISTS %%TABLEPREFIX%%reengagements_listinfo (
		  `listinfo_id` int(11) NOT NULL AUTO_INCREMENT,
		  `reengageid` int(11) NOT NULL,
		  `listids` varchar(255) NOT NULL,
		  `subscriberid` int(11) NOT NULL,
		  `numberofdays` varchar(255) NOT NULL,
		  `transferdate` int(11) DEFAULT '0',
		  PRIMARY KEY (`listinfo_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1
	";

$queries[] = 'CREATE TABLE IF NOT EXISTS %%TABLEPREFIX%%reengagements_subscriber (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `listid` int(11) NOT NULL,
		  `subscriberid` int(11) NOT NULL,
		  `last_click` int(11) NOT NULL,
		  `last_open` int(11) NOT NULL,
		  `subscribedate` int(11) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `subscriber_id` (`subscriberid`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1
	';

$queries[] = 'CREATE TABLE IF NOT EXISTS  %%TABLEPREFIX%%reengagement_statistics_newsletters (
		reengage_statid INT NOT NULL DEFAULT 0 REFERENCES %%TABLEPREFIX%%reengagement_statistics(reengage_statid),
		newsletter_statid INT NOT NULL DEFAULT 0 REFERENCES %%TABLEPREFIX%%stats_newsletters(statid)
	) CHARACTER SET=utf8mb4 ENGINE=INNODB
	';

$queries[] = 'CREATE UNIQUE INDEX %%TABLEPREFIX%%reengage_stats_newsletters_reengage_news ON %%TABLEPREFIX%%reengagement_statistics_newsletters(reengage_statid, newsletter_statid)';

$queries[] = "INSERT INTO %%TABLEPREFIX%%reengagements_config (`Id`, `Variable_Name`, `Variable_Value`) VALUES (1, 'Last_Sync', '0'), (2, 'Last_List_Sync', '0')";
