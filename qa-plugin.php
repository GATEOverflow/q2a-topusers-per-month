<?php

if ( !defined('QA_VERSION') )
{
	header('Location: ../../');
	exit;
}

qa_register_plugin_module('widget', 'qa-tupm-widget.php', 'qa_tupm_widget', 'Top Users per Month Widget');

qa_register_plugin_module('page', 'qa-tupm-page.php', 'qa_tupm_page', 'Top Users per Month Page');
qa_register_plugin_module('module', 'qa-tupm-admin.php', 'qa_tupm_admin', 'Top Users per Month Admin');

qa_register_plugin_phrases('qa-tupm-lang.php', 'qa_tupm_lang');

qa_register_plugin_layer('qa-tupm-layer.php', 'Top Users Layer');



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
