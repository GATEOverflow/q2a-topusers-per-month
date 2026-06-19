<?php

class qa_tupm_weekly_widget {
	
	function allow_template($template)
	{
		return true;
	}
	
	function allow_region($region)
	{
		return ($region == 'side');
	}

	function output_widget($region, $place, $themeobject, $template, $request, $qa_content)
	{
		$maxusers = qa_opt('qa-tupm-weekly-widget-count');
		$hideadmin = qa_opt('qa-tupm-weekly-hide-admin');			
		$showReward = qa_opt('qa-tupm-weekly-reward-enable');
	$hidepoints = qa_opt('qa-tupm-weekly-hide-points');	
		$langActUsers = qa_lang_html('qa_tupm_lang/top_users');
		$pointsLang = qa_lang_html('qa_tupm_lang/points'); 			
		$rewardHtml = qa_opt('qa-tupm-weekly-reward-html');
$activedays = max(1, (int)qa_opt('qa-tupm-active-days'));
		$suffix = " ^userpoints.userid not in (select userid from ^users where flags & ".QA_USER_FLAGS_USER_BLOCKED." = 1";
		if($hideadmin){
			$suffix .=" or level >= ".QA_USER_LEVEL_ADMIN;
		}
		$suffix .=") and ^userpoints.points > ".(int)qa_opt('points_base');
		// P3: resolve MAX(date) once in PHP — avoids a correlated subquery evaluated per row
		$maxDateRes  = qa_db_query_sub("SELECT MAX(`date`) AS d FROM `^userscores_weekly`");
		$maxDateRow  = qa_db_read_one_assoc($maxDateRes, true);
		$maxWeekDate = ($maxDateRow && $maxDateRow['d']) ? $maxDateRow['d'] : '1970-01-01';
		// R3: detect if the scheduler missed the current week's Monday snapshot
		$wdow       = (int)date('N'); // 1=Mon … 7=Sun
		$wTrueMon   = date('Y-m-d', strtotime('-' . ($wdow - 1) . ' days'));
		$snapMissed = ($maxWeekDate !== '1970-01-01' && $maxWeekDate < $wTrueMon);
		// P1: SELECT display fields from ^users in one query — no per-user round-trip
		$avatarSize = (int)qa_opt('avatar_users_size');
		$queryRecentScores = qa_db_query_sub("SELECT ^userpoints.userid,
								^userpoints.points - COALESCE(^userscores_weekly.points,0) AS mpoints,
								^users.flags, ^users.email, ^users.handle,
								^users.avatarblobid, ^users.avatarwidth, ^users.avatarheight
								FROM `^userpoints`
								LEFT JOIN `^userscores_weekly` ON ^userpoints.userid=^userscores_weekly.userid
									AND ^userscores_weekly.date = '$maxWeekDate'
								JOIN ^users ON ^users.userid = ^userpoints.userid
								WHERE ^users.loggedin > NOW() - interval $activedays day
								AND ".$suffix.
								" ORDER BY mpoints DESC, ^userpoints.userid DESC LIMIT ".$maxusers.";"
								);
		
		// save all userscores in array $scores
		$scores = array();
		while ( ($row = qa_db_read_one_assoc($queryRecentScores,true)) !== null ) {
			$scores[] = $row;
		}

		$topusers = "<ol>";
		
		foreach ($scores as $user) {
			// no users with 0 points
			$val = $user['mpoints'];
			if($val>0) {
				// P1: display fields already in $user — no extra DB call needed
				$topusers .= "<li>" . qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'], $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], $avatarSize, true) . "<span class=\"topusers-span\">" . qa_get_one_user_html($user['handle'], false);
				if(!$hidepoints)
					$topusers .=' <p class="uscore">'.$val.' '.$pointsLang.'</p></span></li>
					';
			}
		}
		$topusers .= "</ol>";

		// output into theme
		$themeobject->output('<div class="topusers">');
		
		// if you want the month displayed in your language uncomment the following block, 
		// and comment out the line: $monthName = date('M Y'); 
		// you have to define your language code as well, e.g. en_US, fr_FR, de_DE
		/* 
		$localcode = "de_DE"; 
		setlocale (LC_TIME, $localcode); 
		$monthName = strftime("%B %G", strtotime( date('F')) ); // %B for full month name, %b for abbreviation
		*/
		$week = getStartAndEndDate(date('W'),date('Y'));
		$weekName = $week['week_start']." - ".$week['week_end']; 
		
		$themeobject->output('<div class="qa-widget-title tupm-title"><a href="'.qa_path('topusers').'">'.$langActUsers.'</a> <span class="qa-widget-span">'.$weekName.'</span></div>');
		// R3: warn if the Monday snapshot was missed — data covers more than one week
		if ($snapMissed) {
			$themeobject->output('<p style="font-size:.78em;color:#cc6600;margin:2px 0 5px;padding:0 2px">&#9888; Data since '.htmlspecialchars($maxWeekDate).' ('.$wTrueMon.' snapshot missing)</p>');
		}
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
