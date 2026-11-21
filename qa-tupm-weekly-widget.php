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
		$avatarSize = (int)qa_opt('avatar_users_size');
		$weekStart  = qa_tupm_points::weekStart();
		$scores     = qa_tupm_points::ranked('week', $weekStart, (int)$maxusers, 0, $hideadmin, $activedays);

		$topusers = "<ol>";
		
		foreach ($scores as $user) {
			$val = $user['points'];
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
		$weekName = date('Y M d', strtotime($weekStart)) . ' - ' . date('M d', strtotime($weekStart . ' +6 days'));
		
		$themeobject->output('<div class="qa-widget-title tupm-title"><a href="'.qa_path('topusers').'">'.$langActUsers.'</a> <span class="qa-widget-span">'.$weekName.'</span></div>');
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
