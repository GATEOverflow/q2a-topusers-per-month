<?php  

	// CONNECT TO DATABASE
	require_once( '../../../qa-config.php' );
	mysql_connect(QA_MYSQL_HOSTNAME, QA_MYSQL_USERNAME, QA_MYSQL_PASSWORD) or die(mysql_error());
	mysql_select_db(QA_MYSQL_DATABASE) or die(mysql_error());
	
	// get current month from today
	$date = date('Y-m-d');
	
	// MONTH 
	// to avoid double entries, check if cronjob was not already run for THIS MONTH
	$checkResult = mysql_query("SELECT `date` FROM `qa_userscores` 
									WHERE YEAR(`date`) = YEAR('".$date."') 
									AND MONTH(`date`) = MONTH('".$date."');") or die(mysql_error());
	if (mysql_num_rows($checkResult) > 0) { 
		// echo "Userscores for month ".date('Y-m')." already exist";
		die();
	}
	else {
		// copy userid and userpoints to our qa_userscores table
		mysql_query("INSERT INTO `qa_userscores` (userid, points, date) SELECT userid, points, '".$date."' AS date from `qa_userpoints` ORDER BY userid ASC;") or die(mysql_error());
	}
	
?>
