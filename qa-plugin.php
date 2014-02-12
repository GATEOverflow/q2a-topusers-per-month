<?php
/*
	Plugin Name: Best Users per Month
	Plugin URI: https://github.com/echteinfachtv/q2a-best-users-per-month
	Plugin Description: Displays the best users (with most points) of the current month in a widget and on a separate page
	Plugin Version: 1.2
	Plugin Date: 2012-07-10
	Plugin Author: q2apro.com
	Plugin Author URI: http://www.q2apro.com/
	Plugin License: GPLv3
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI: https://raw.github.com/echteinfachtv/q2a-best-users-per-month/master/qa-plugin.php

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.gnu.org/licenses/gpl.html
	
*/

if ( !defined('QA_VERSION') )
{
	header('Location: ../../');
	exit;
}

// widget
qa_register_plugin_module('widget', 'qa-best-users-per-month-widget.php', 'qa_best_users_per_month_widget', 'Best Users per Month');

// page
qa_register_plugin_module('page', 'qa-best-users-per-month-page.php', 'qa_best_users_per_month_page', 'Best Users Page');

// language file
qa_register_plugin_phrases('qa-best-users-per-month-lang.php', 'qa_best_users_lang');

// change default users page, add subnavigation "best users"
qa_register_plugin_layer('qa-best-users-per-month-layer.php', 'Add Subnav to Page Layer');



/* custom functions */
function get_year_months($date1, $date2) {
   $time1  = strtotime($date1);
   $time2  = strtotime($date2);
   $my     = date('mY', $time2);

   $months = array(date('Y-m-01', $time1));

   while($time1 < $time2) {
	  $time1 = strtotime(date('Y-m-d', $time1).' +1 month');
	  if(date('mY', $time1) != $my && ($time1 < $time2))
		 $months[] = date('Y-m-01', $time1);
   }

   $months[] = date('Y-m-01', $time2);
   return $months;
}



/*
	Omit PHP closing tag to help avoid accidental output
*/