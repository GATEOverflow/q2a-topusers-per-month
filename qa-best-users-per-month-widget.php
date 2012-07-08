<?php

class qa_best_users_per_month_widget {
	
	function allow_template($template)
	{
		$allow=false;
		
		switch ($template)
		{
			case 'activity':
			case 'qa':
			case 'questions':
			case 'hot':
			case 'ask':
			case 'categories':
			case 'question':
			case 'tag':
			case 'tags':
			case 'unanswered':
			case 'user':
			case 'users':
			case 'search':
			case 'admin':
			case 'custom':
				$allow=true;
				break;
		}
		
		return $allow;
	}
	
	function allow_region($region)
	{
		$allow=false;
		
		switch ($region)
		{
			case 'main':
			case 'side':
				$allow=true;
				break;
			case 'full':					
				break;
		}
		
		return $allow;
	}

	function output_widget($region, $place, $themeobject, $template, $request, $qa_content)
	{
		/* Settings */
		$maxusers = 4;			// max users to display in widget
		$adminID = 1;			// if you want the admin not to be considered in the userpoints list, define his id here (set 0 if admin should be displayed)
		$showReward = true; 	// false to hide rewards
		
		$langActUsers = qa_lang_html('qa_best_users_lang/best_users');		// language string for 'best users'
		$pointsLang = qa_lang_html('qa_best_users_lang/points'); 			// language string for 'points'
		$rewardHtml = '<p class="rewardlist" title="'.qa_lang_html('qa_best_users_lang/reward_title').'"><b>'.qa_lang_html('qa_best_users_lang/rewardline_widget').'</b><br />'.qa_lang_html('qa_best_users_lang/reward_1').'<br />'.qa_lang_html('qa_best_users_lang/reward_2').'</p>';
		
		
		/* 	CSS: 
		
			you can style the best-users-box by css: .bestusers
			define height and width of images using: .bestusers img
			
			For instance, for my template I used the following css (add these lines to qa-styles.css): 
			.bestusers { width: 184px;padding:10px 0 10px 8px;margin-bottom:20px; border:2px solid #CFdd00;-moz-border-radius:14px 3px 3px 14px;border-radius:14px 3px 3px 14px; }
			.bestusers ol { margin:0; padding-left:20px; }
			.bestusers li { position:relative; clear:both; height:40px; }
			.bestusers li .qa-avatar-link { display:inline-block; border:1px solid #CCCCCC; margin-right:4px; vertical-align:top; }
			.uscore { position:absolute; top:15px; left:40px; font-size:11px; color:#545454; }
			.rewardlist { clear:both; width:120px; padding:2px 7px; background: rgba(50,50,50,0.3); font-size:11px; color:#454545; margin:10px 0 0 50px; cursor:default; border:1px solid #C0CC50; }
		*/

		
		// compare userscores from last month to userpoints now (this query is considering new users that do not exist in qa_userscores) 
		// as we order by mpoints the query returns best users first, and we do not need to sort by php: arsort($scores)
		$queryRecentScores = qa_db_query_sub("SELECT qa_userpoints.userid, qa_userpoints.points - COALESCE(qa_userscores.points,0) AS mpoints 
								FROM `qa_userpoints`
								LEFT JOIN `qa_userscores` on qa_userpoints.userid=qa_userscores.userid 
									AND YEAR(qa_userscores.date) = YEAR(CURDATE()) 
									AND MONTH(qa_userscores.date) = MONTH(CURDATE())
								WHERE qa_userpoints.userid != ".$adminID."
								ORDER BY mpoints DESC, qa_userpoints.userid DESC;");
			// thanks srini.venigalla for helping me with advanced mysql
			// http://stackoverflow.com/questions/11085202/calculate-monthly-userscores-between-two-tables-using-mysql

		
		// save all userscores in array $scores
		$scores = array();
		while ( ($row = qa_db_read_one_assoc($queryRecentScores,true)) !== null ) {
			$scores[$row['userid']] = $row['mpoints'];
		}

		// save userids in array $userids that we need to get their usernames by qa_userids_to_handles()
		$userids = array();
		$cnt = 0;
		foreach ($scores as $userId => $val) {
			$userids[++$cnt] = $userId;
			// max users to store in array, had to be commented out as blocked users came into play (see below) 
			// if($cnt >= $maxusers) break;
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
				// check if user is blocked
				if (! (QA_USER_FLAGS_USER_BLOCKED & $user['flags'])) {
					// points below user name, check CSS descriptions for .bestusers
					$bestusers .= "<li>" . qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'], $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], qa_opt('avatar_users_size'), false) . " " . qa_get_one_user_html($usernames[$userId], false).' <p class="uscore">'.$val.' '.$pointsLang.'</p></li>
					';

					// max users to display 
					if(++$nrUsers >= $maxusers) break;
				}
			}
		}
		$bestusers .= "</ol>";

		// output into theme
		$themeobject->output('<div class="bestusers">');
		
		// if you want the month displayed in your language uncomment the following block, 
		// and comment out the line: $monthName = date('m/Y'); 
		// you have to define your language code as well, e.g. en_US, fr_FR, de_DE
		/* 
		$localcode = "de_DE"; 
		setlocale (LC_TIME, $localcode); 
		$monthName = strftime("%B %G", strtotime( date('F')) ); // %B for full month name, %b for abbreviation
		*/
		$monthName = date('m/Y'); // 2 digit month and 4 digit year
		
		$themeobject->output('<div style="font-size:14px;margin-bottom:18px;"><b>'.$langActUsers.'</b> <span style="font-size:12px;">'.$monthName.'</span></div>'); 
		$themeobject->output( $bestusers );
		
		// display reward info
		if($showReward) {
			$themeobject->output( $rewardHtml );
		}
		$themeobject->output('</div>');
	}

}

/*
	Omit PHP closing tag to help avoid accidental output
*/