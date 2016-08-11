<?php

class qa_html_theme_layer extends qa_html_theme_base
{

	function doctype(){
	
		qa_html_theme_base::doctype();

		global $qa_request;
		// adds subnavigation to pages bestusers and users
		if($qa_request == 'bestusers' || $qa_request == 'users' ) {
			$this->content['navigation']['sub']['bestusers']= array(
					'label' => qa_lang_html('qa_best_users_lang/subnav_title'),
					'url' => qa_path_html('bestusers'),
					'selected' => ($qa_request == 'bestusers')
				
			);
		}
		
	}

}
