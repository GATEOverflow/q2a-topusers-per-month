<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-plugin/example-page/qa-example-lang-default.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: US English language phrases for example plugin


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

	/* this file is UTF-8 encoded, you can use your language characters without worries */
	
	return array(
		// widget + page
		'best_users' => 'Top Users',			// your language string for 'best users'
		'points' => 'Points',						// your language string for 'points'
		'rewardline_widget' => 'Rewards', 	// tell your users about monthly rewards/premiums
		'reward_1' => 'Reward 1: 200 points', 			// this is for the 1st winner
		'reward_2' => 'Reward 2: 100 points',			// this is for the 2nd winner
		'reward_title' => 'Learn and contribute to get the rewards!', // the mousetip when mouse is over reward field: <p class="rewardlist" title="x">...</p>
		
		// on page only
		'page_title' => 'Top Users of the month (Top 20)', // best users of each month (top 20)
		'choose_month' => 'Choose month:', 
		'rewardline_onpage' => 'Monthly rewards: 1. Prize: 200 points | 2. Prize: 100 points', // tell your users about monthly rewards/premiums
		
		// subnavigation on all users page
		'subnav_title' => 'Top Users', // best users of the month
	);
	

/*
	Omit PHP closing tag to help avoid accidental output
*/
