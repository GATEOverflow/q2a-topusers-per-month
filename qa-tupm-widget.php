<?php

class qa_tupm_widget {
	
	function allow_template($template)
	{
		return true;
	}
	
	function allow_region($region)
	{
		return ($region != 'side');
	}

	function output_widget($region, $place, $themeobject, $template, $request, $qa_content)
	{
		$maxusers = qa_opt('qa-tupm-widget-count');
		$hideadmin = qa_opt('qa-tupm-hide-admin');			
		$showReward = qa_opt('qa-tupm-reward-enable');
		
		$langActUsers = qa_lang_html('qa_tupm_lang/top_users');
		$pointsLang = qa_lang_html('qa_tupm_lang/points'); 			
		$rewardHtml = qa_opt('qa-tupm-reward-html');
//		$rewardHtml = '<p class="rewardlist" title="'.qa_lang_html('qa_best_users_lang/reward_title').'"><b>'.qa_lang_html('qa_best_users_lang/rewardline_widget').'</b><br />'.qa_lang_html('qa_best_users_lang/reward_1').'<br />'.qa_lang_html('qa_best_users_lang/reward_2').'</p>';
		//$rewardHtml = '<p class="rewardlist" title="'.qa_lang_html('qa_best_users_lang/reward_title').'"><b>'.qa_lang_html('qa_best_users_lang/rewardline_widget').'</b><br />Monthly Topper Reward'.'<br />'.'</p><iframe style="width:120px;height:240px;" marginwidth="0" marginheight="0" scrolling="no" frameborder="0" src="//ws-in.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&OneJS=1&Operation=GetAdHtml&MarketPlace=IN&source=ac&ref=tf_til&ad_type=product_link&tracking_id=gc0a41-21&marketplace=amazon&region=IN&placement=B00DQG9DDU&asins=B00DQG9DDU&linkId=&show_border=true&link_opens_in_new_window=true"></iframe>';
	//	$rewardHtml = '<p class="rewardlist" title="'.qa_lang_html('qa_best_users_lang/reward_title').'"><b>'.qa_lang_html('qa_best_users_lang/rewardline_widget').'</b><br />Monthly Topper: Rs. 500 <a href="https://www.amazon.in/gp/product/B00KGE2ER2/gcrnsts?ie=UTF8&keywords=gift%20card&qid=1453072385&ref_=sr_1_1&sr=8-1"> gift card</a>'.'<br />';

		
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
		$suffix = " and ^userpoints.userid not in (select userid from ^users where flags & ".QA_USER_FLAGS_USER_BLOCKED." = 1";
		if($hideadmin){
			$suffix .=" or level >= ".QA_USER_LEVEL_ADMIN;
		}
		$suffix .=")";
		$queryRecentScores = qa_db_query_sub("SELECT ^userpoints.userid, ^userpoints.points - COALESCE(^userscores.points,0) AS mpoints 
								FROM `^userpoints`
								LEFT JOIN `^userscores` on ^userpoints.userid=^userscores.userid
									AND DATE_FORMAT(^userscores.date,'%Y') like '".date("Y")."' 
                                                                        AND DATE_FORMAT(^userscores.date,'%m') like '".date("m")."'  
			
								WHERE 
								^userpoints.userid != ".$adminID.$suffix.
								" ORDER BY mpoints DESC, ^userpoints.userid DESC limit ".$maxusers.";"
								);
		
		// save all userscores in array $scores
		$scores = array();
		while ( ($row = qa_db_read_one_assoc($queryRecentScores,true)) !== null ) {
			$scores[] = $row;
		}

		// save userids in array $userids that we need to get their usernames by qa_userids_to_handles()
		
		// initiate output string
		$topusers = "<ol>";
		$nrUsers = 0;
		
		foreach ($scores as $user) {
			// no users with 0 points
			$userId = $user['userid'];
			$val = $user['mpoints'];
			if($val>0) {
				$user = qa_db_select_with_pending( qa_db_user_account_selectspec($userId, true) );
					$topusers .= "<li>" . qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'], $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], qa_opt('avatar_users_size'), false) . " " . qa_get_one_user_html($user['handle'], false).' <p class="uscore">'.$val.' '.$pointsLang.'</p></li>
					';
					// max users to display 
					if(++$nrUsers >= $maxusers) break;
			}
		}
		$topusers .= "</ol>";

		// output into theme
		$themeobject->output('<div class="topusers">');
		
		// if you want the month displayed in your language uncomment the following block, 
		// and comment out the line: $monthName = date('m/Y'); 
		// you have to define your language code as well, e.g. en_US, fr_FR, de_DE
		/* 
		$localcode = "de_DE"; 
		setlocale (LC_TIME, $localcode); 
		$monthName = strftime("%B %G", strtotime( date('F')) ); // %B for full month name, %b for abbreviation
		*/
		$monthName = date('m/Y'); // 2 digit month and 4 digit year
		
		$themeobject->output('<div class="qa-widget-title tupm-title"><a href="'.qa_html('topusers').'">'.$langActUsers.'</a> <span class="qa-widget-span">'.$monthName.'</span></div>'); 
		$themeobject->output( $topusers );
		
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
