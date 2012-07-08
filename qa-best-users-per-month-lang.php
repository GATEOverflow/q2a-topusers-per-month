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
		'best_users' => 'Klügste Köpfe',			// your language string for 'best users'
		'points' => 'Punkte',						// your language string for 'points'
		'rewardline_widget' => 'Prämien je Monat', 	// tell your users about monthly rewards/premiums
		'reward_1' => 'Platz 1: 20 Euro', 			// this is for the 1st winner
		'reward_2' => 'Platz 2: 10 Euro',			// this is for the 2nd winner
		'reward_title' => 'Jeden Monat erhalten die besten Mitglieder eine Prämie!', // the mousetip when mouse is over reward field: <p class="rewardlist" title="x">...</p>
		
		// on page only
		'page_title' => 'Beste Mitglieder je Monat (Top 20)', // best users of each month (top 20)
		'choose_month' => 'Monat wählen:', 
		'rewardline_onpage' => 'Monatliche Prämien: 1. Platz: 20 Euro | 2. Platz: 10 Euro', // tell your users about monthly rewards/premiums
	);
	

/*
	Omit PHP closing tag to help avoid accidental output
*/