<?php  

	// CONNECT TO DATABASE
	require_once( '../../qa-config.php' );
	mysql_connect(QA_MYSQL_HOSTNAME, QA_MYSQL_USERNAME, QA_MYSQL_PASSWORD) or die(mysql_error());
	mysql_select_db(QA_MYSQL_DATABASE) or die(mysql_error());
	
	// create table if it does not exist
	$checkResult = mysql_query("CREATE TABLE IF NOT EXISTS `qa_userscores` (
								  `date` date NOT NULL,
								  `userid` int(10) unsigned NOT NULL,
								  `points` int(11) NOT NULL DEFAULT '0',
								  KEY `userid` (`userid`),
								  KEY `date` (`date`)
								)")  or die(mysql_error());

?>