<?php

class qa_tupm_page {

	// initialize db-table 'userscores' if it does not exist yet
	function init_queries($tableslc) {
		$queries = array();
		$tablename=qa_db_add_table_prefix('monthlytoppers');
		$new = false;
		if(!in_array($tablename, $tableslc)) {
			$new = true;
			$queries[] = "CREATE TABLE IF NOT EXISTS `".$tablename."` (
				`date` date NOT NULL,
				`userid` int(10) unsigned NOT NULL,
				`points` int(11) NOT NULL DEFAULT '0',
				KEY `userid` (`userid`),
				KEY `date` (`date`)
					)
					";
		}
		$tablename=qa_db_add_table_prefix('userscores');
		if(!in_array($tablename, $tableslc)) {
			return "CREATE TABLE IF NOT EXISTS `".$tablename."` (
				`date` date NOT NULL,
				`userid` int(10) unsigned NOT NULL,
				`points` int(11) NOT NULL DEFAULT '0',
				KEY `userid` (`userid`),
				KEY `date` (`date`)
					)
					";
		}
		else if($new){
			$select = "select min(date) as date from ^userscores";
			$result = qa_db_query_sub($select);
			$mindate = qa_db_read_one_value($result);
			$mdate = strtotime($mindate); 
			$select = "select max(date) as date from ^userscores";
			$result = qa_db_query_sub($select);
			$maxdate = qa_db_read_one_value($result);
			$mxdate = strtotime($maxdate);
			while($mdate < $mxdate){
				$insert = "insert into ^monthlytoppers (date, userid, points) select a.date, a.userid, b.points - COALESCE(a.points,0) AS mpoints from qa_userscores a,qa_userscores b where a.userid = b.userid and a.date = '".date("Y-m-d", $mdate)."' and b.date between (a.date + interval 1 day) and (a.date + interval 59 day)  group by a.userid,a.points,b.points  having mpoints>0";
				$queries[] = $insert;
				$select = "select min(date) as date from ^userscores where date > '".date("Y-m-d", $mdate)."'";
				$result = qa_db_query_sub($select);
				$mindate = qa_db_read_one_value($result);
				$mdate = strtotime($mindate); 

			} 
		}
		return $queries;
	}

	var $directory;
	var $urltoroot;

	function load_module($directory, $urltoroot)
	{
		$this->directory=$directory;
		$this->urltoroot=$urltoroot;
	}

	// for display in admin interface under admin/pages
	function suggest_requests() 
	{	
		return array(
				array(
					'title' => 'Top Users per Month Page', // title of page
					'request' => 'topusers', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				     ),
			    );
	}

	// for url query
	function match_request($request)
	{
		if ($request=='topusers') {
			return true;
		}

		return false;
	}

	function process_request($request)
	{

		/* SETTINGS */
		$maxusers = 20; 			// max users to display 
		$adminID = 1;				// if you want the admin not considered in the userpoints list, define his id here (set 0 if admin should be in)
		$showReward = true; 		// false to hide rewards


		$lang_page_title = qa_lang_html('qa_top_users_lang/page_title');
		$lang_choose_month = qa_lang_html('qa_top_users_lang/choose_month');
		$lang_top_users = qa_lang_html('qa_top_users_lang/top_users');
		$lang_points = qa_lang_html('qa_top_users_lang/points');


		$showRewardOnTop = '<p style="font-size:14px;width:650px;margin-left:2px;line-height:140%;">' .qa_lang_html('qa_best_users_lang/rewardline_onpage') . "</p>";


		/* start */
		$qa_content=qa_content_prepare();

		// add sub navigation (remove for plugin release)
		// $qa_content['navigation']['sub']=qa_users_sub_navigation();

		$qa_content['title'] = $lang_page_title; // list title

		// counter for custom html output
		$c = 2;


		// get first month to show in dropdown list (e.g. 10/2012)
		$firstListDate = '2012-10-01'; // default
		$queryFirstDate = qa_db_query_sub("SELECT `date` FROM `^userscores` ORDER BY `date` ASC LIMIT 1;"); 
		while ( ($row = qa_db_read_one_assoc($queryFirstDate,true)) !== null ) {
			$firstListDate = $row['date'];
			break;
		}


		// last entry of dropdown list
		// -1 month, to also show the "first point interval" from all 0 userscores to all first saved userscores
		$firstListDate = date("Y-m-01", strtotime($firstListDate."-1 month") );

		// first entry of dropdown list
		$lastListDate = date("Y-m-01");
		// if you want last month as default use
		// $lastListDate = date("Y-m-01", strtotime("last month") );

		// this month as default
		$chosenMonth = date("Y-m-01");
		// if you want last month as default use
		// $chosenMonth = date("Y-m", strtotime("last month") ); 

		// we received post data, user has chosen a month
		if( qa_post_text('request') ) {
			$chosenMonth = qa_post_text('request');
			// sanitize string, keep only 0-9 and - (maybe I am to suspicious?)
			$chosenMonth = preg_replace("/[^0-9\-]/i", '', $chosenMonth);
		}

		// get interval start from chosen month
		$intervalStart = date("Y-m-01", strtotime($chosenMonth) ); // 05/2012 becomes 2012-05-01
		$intervalEnd = date("Y-m-01", strtotime($chosenMonth."+1 month") ); // 05/2012 becomes 2012-06-01


		$dropdown_options=array();

		// list all available months in array
		foreach(get_year_months($firstListDate, $lastListDate) as $value){
			// array index is <option value> (see html), saved string is label, e.g. $dropdown_options['2012-05-01'] = "month";
			$dropdown_options[$value] = date("m/Y", strtotime($value) );
		}
		// sort so that latest month is on top
		krsort($dropdown_options);


		// output reward on top
		if($showReward) {
			$qa_content['custom'.++$c] = $showRewardOnTop;
		}

		// output dropdown form for choosing the months
		$qa_content['form']=array(
				'tags' => 'METHOD="POST" ACTION="'.qa_self_html().'"',

				'style' => 'wide', // options: light, wide, tall, basic

				// 'ok' => qa_post_text('buttonOK') ? 'Chosen Month: '.qa_post_text('request') : null,

				// 'title' => 'Form title',

				'fields' => array(
					'request' => array(
						'id' => 'dropdown', 
						'label' => $lang_choose_month, 
						'tags' => 'NAME="request" onchange="this.form.submit()" id="dropdown_select"',
						'type' => 'select',
						'options' => $dropdown_options,
						//'value' => '2012-05-01', // qa_html($request),
						//'error' => qa_html('Another error'),
						),
					),


				);

		// we need to do another query to get the userscores of the recent month
		if($chosenMonth == date("Y-m-01") ) {
			// calculate userscores from recent month
			$suffix = " and ^userpoints.userid not in (select userid from ^users where flags & ".QA_USER_FLAGS_USER_BLOCKED." = 1)";
			$queryRecentScores = qa_db_query_sub("SELECT ^userpoints.userid, ^userpoints.points - COALESCE(^userscores.points,0) AS mpoints 
					FROM `^userpoints`
					LEFT JOIN `^userscores` on ^userpoints.userid=^userscores.userid
					AND DATE_FORMAT(^userscores.date,'%Y') like '".date("Y")."' 
					AND DATE_FORMAT(^userscores.date,'%m') like '".date("m")."'  

					WHERE 
					^userpoints.userid != ".$adminID.$suffix.
					" ORDER BY mpoints DESC limit ".$maxusers.";"
					);

		}
		else {
			// calculate userscores for given month
			$queryRecentScores = qa_db_query_sub("
					SELECT ul.userid, 
					ul.points - COALESCE(uf.points, 0) AS mpoints 
					FROM `^userscores` ul 
					LEFT JOIN (SELECT userid, points FROM `^userscores` WHERE `date` = '".$intervalStart."') AS uf
					ON uf.userid = ul.userid
					WHERE ul.date = '".$intervalEnd."'
					AND ul.userid != ".$adminID.$suffix.
					" ORDER BY mpoints DESC limit ".$maxusers.";"
					);
		}
		// save all userscores in array $scores
		$scores = array();
		while ( ($row = qa_db_read_one_assoc($queryRecentScores,true)) !== null ) {
			$scores[] = $row;
		}
		// initiate output string
		$bestusers = "<ol>";
		$nrUsers = 0;

		foreach ($scores as $user) {
			// no users with 0 points
			$userId = $user['userid'];
			$val = $user['mpoints'];
			if($val>0) {
				$user = qa_db_select_with_pending( qa_db_user_account_selectspec($userId, true) );
				// points below user name, check CSS descriptions for .bestusers
				$bestusers .= "<li>" . qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'], $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], qa_opt('avatar_users_size'), false) . " " . qa_get_one_user_html($user['handle'], false).' <p class="uscore">'.$val.' '.$pointsLang.'</p></li>
					';
				// max users to display 
				if(++$nrUsers >= $maxusers) break;
			}
		}
		$bestusers .= "</ol>";


		/* output into theme */
		$qa_content['custom'.++$c]='<div class="topusers" style="border-radius:0; padding:35px 48px; margin-top:-2px;">';

		// convert date to display m/Y, 2 digit month and 4 digit year
		$monthName = date("m/Y", strtotime($chosenMonth) );

		$qa_content['custom'.++$c]='<div style="font-size:16px;margin-bottom:18px;"><b>'.$lang_best_users.' '.$monthName.'</b></div>'; 
		$qa_content['custom'.++$c]= $bestusers;

		$qa_content['custom'.++$c]='</div>';

		// make bestusers list bigger on page and style the dropdown
		$qa_content['custom'.++$c] = '<style type="text/css">#dropdown .qa-form-wide-label { width:120px; text-align:center; } #dropdown .qa-form-wide-data { width:120px; text-align:center; }</style>'; 

		// jquery workaround (or call it hack) to select the current month in dropdown
		$qa_content['custom'.++$c] = ' <script type="text/javascript">$(document).ready(function(){  $("select#dropdown_select").val(\''.$intervalStart.'\') }); </script>';




		return $qa_content;
	}

};


/*
   Omit PHP closing tag to help avoid accidental output
 */
