<?php

	class qa_best_users_per_month_page {
		
		// initialize db-table 'userscores' if it does not exist yet
		function init_queries($tableslc) {
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
					'title' => 'Best Users per Month Page', // title of page
					'request' => 'bestusers', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}
		
		// for url query
		function match_request($request)
		{
			if ($request=='bestusers') {
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
			$creditDeveloper = true;	// leave this true if you like this plugin, it sets one hidden link to my q2a-forum from the best-user-page only
			
			
			/* TRANSFER LANGUAGE STRINGS */
			$lang_page_title = qa_lang_html('qa_best_users_lang/page_title');
			$lang_choose_month = qa_lang_html('qa_best_users_lang/choose_month');
			$lang_best_users = qa_lang_html('qa_best_users_lang/best_users');
			$lang_points = qa_lang_html('qa_best_users_lang/points');
			
			// keep for EETV:
			$showRewardOnTop = '<p style="font-size:14px;width:650px;margin-left:2px;line-height:140%;">Monatliche Pr&auml;mien: 1. Platz: <b>20 Euro</b> | 2. Platz: <b>10 Euro</b> </p>';
			// * uncomment for plugin release:
			// $showRewardOnTop = '<p style="font-size:14px;width:650px;margin-left:2px;line-height:140%;">' .qa_lang_html('qa_best_users_lang/rewardline_onpage') . "</p>";
			
			
			/* start */
			$qa_content=qa_content_prepare();

			// add sub navigation (remove for plugin release)
			// $qa_content['navigation']['sub']=qa_users_sub_navigation();
			
			$qa_content['title'] = $lang_page_title; // list title

			// counter for custom html output
			$c = 2;
			
			
			// * uncomment for plugin release:
			// get first date of dropdown list (e.g. 03/2012)
			// Note: for better performance set the $firstListDate by hand to your first date in qa_userscores (probably date of installation)
			// $firstListDate = '2012-04-01'; // eetv: do not show February as gmf was founded in March
			// ... and comment out the following lines: 
			 
			$queryFirstDate = qa_db_query_sub("SELECT `date` FROM `qa_userscores` ORDER BY `date` ASC LIMIT 1;"); 
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
			arsort($dropdown_options);

			
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
				
				// if you want to display a button, uncomment: 
				/*
				'buttons' => array(
					'ok' => array(
						'tags' => 'NAME="buttonOK"',
						'label' => 'Zeigen',
						'value' => '1',
					),
				),
				*/
				
			);

			// we need to do another query to get the userscores of the recent month
			if($chosenMonth == date("Y-m-01") ) {
				// calculate userscores from recent month
				$queryRecentScores = qa_db_query_sub("
										SELECT qa_userpoints.userid, qa_userpoints.points - COALESCE(qa_userscores.points,0) AS mpoints 
										FROM `qa_userpoints`
										LEFT JOIN `qa_userscores` on qa_userpoints.userid=qa_userscores.userid 
											AND YEAR(qa_userscores.date) = YEAR(CURDATE()) 
											AND MONTH(qa_userscores.date) = MONTH(CURDATE())
										WHERE qa_userpoints.userid != ".$adminID."
										ORDER BY mpoints DESC, qa_userpoints.userid DESC;");
				// thanks srini.venigalla for helping me with advanced mysql
				// http://stackoverflow.com/questions/11085202/calculate-monthly-userscores-between-two-tables-using-mysql
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
										AND ul.userid != ".$adminID."
										ORDER BY mpoints DESC;"
									);
				// thanks raina77ow for helping me with mysql
				// http://stackoverflow.com/questions/11178599/mysql-get-difference-between-two-values-in-one-table-multiple-userids
			}


			// save all userscores in array
			$scores = array();
			while ( ($row = qa_db_read_one_assoc($queryRecentScores,true)) !== null ) {
				$scores[$row['userid']] = $row['mpoints'];
			}

			// save userids in array that we need for qa_userids_to_handles()
			$userids = array();
			$cnt = 0;
			foreach ($scores as $userId => $val) {
				$userids[++$cnt] = $userId;
			}
			
			// get handles (i.e. usernames) in array usernames
			$usernames = qa_userids_to_handles($userids);

			// initiate output string
			$bestusers = "<ol>";
			$nrUsers = 0;
			foreach ($scores as $userId => $val) {
				// no users with 0 points, and no blocked users!
				if($val>0) {
					$currentUser = $usernames[$userId];
					$user = qa_db_select_with_pending( qa_db_user_account_selectspec($currentUser, false) );
					// check if user is blocked, do not list them
					if (! (QA_USER_FLAGS_USER_BLOCKED & $user['flags'])) {
						// points below user name, check CSS descriptions for .bestusers
						$bestusers .= "<li>" . qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'], $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], qa_opt('avatar_users_size'), false) . " " . qa_get_one_user_html($usernames[$userId], false).' <p class="uscore">'.$val.' '.$lang_points.'</p></li>'; 
						
						// max users to display 
						if(++$nrUsers >= $maxusers) break;
					}
				}
			}
			$bestusers .= "</ol>";

			
			/* output into theme */
			$qa_content['custom'.++$c]='<div class="bestusers" style="border-radius:0; padding:35px 48px; margin-top:-2px;">';
			
			// convert date to display m/Y, 2 digit month and 4 digit year
			$monthName = date("m/Y", strtotime($chosenMonth) );
			
			$qa_content['custom'.++$c]='<div style="font-size:16px;margin-bottom:18px;"><b>'.$lang_best_users.' '.$monthName.'</b></div>'; 
			$qa_content['custom'.++$c]= $bestusers;
			
			$qa_content['custom'.++$c]='</div>';
			
			// make bestusers list bigger on page and style the dropdown
			$qa_content['custom'.++$c] = '<style type="text/css">#dropdown .qa-form-wide-label { width:120px; text-align:center; } #dropdown .qa-form-wide-data { width:120px; text-align:center; }</style>'; 
			
			// jquery workaround (or call it hack) to select the current month in dropdown
			$qa_content['custom'.++$c] = ' <script type="text/javascript">$(document).ready(function(){  $("select#dropdown_select").val(\''.$intervalStart.'\') }); </script>';
			
			// as I said, this is one chance to say thank you
			if($creditDeveloper) {
				$qa_content['custom'.++$c] = "<a style='display:none' href='http://www.gute-mathe-fragen.de/'>Gute Mathe-Fragen! * Dein bestes Mathe-Forum</a>";
			}
			

			// WIDGET CALL: we want the best-user-widget also to be displayed on this page
			$widget['title'] = "Best Users per Month";
			$module=qa_load_module('widget', $widget['title']);
			$region = "side";
			$place = "high";
			$qa_content['widgets'][$region][$place][] = $module;
			

			return $qa_content;
		}
		
	};
	

/*
	Omit PHP closing tag to help avoid accidental output
*/