<?php
class qa_tupm_admin {

	function allow_template($template)
	{
		return ($template!='admin');
	}

	function option_default($option) {

		switch($option) {
			case 'tupm-plugin-css':
				return '.qa-top-search  {
					background-color :cornsilk;
				}

				.qa-top-search-title {
font: bold;
				}

				.qa-top-search-item {
margin: 3px;
	font-size: x-small;
color: white;
       padding-right: 2px;
       padding-left: 2px;
				}

				';
			case 'qa-tupm-plugin-title':
				return 'Top Monthly Users';
			case 'qa-tupm-widget-count':
				return '10';
			case 'qa-tupm-page-count':
				return '20';
			case 'qa-tupm-reward-enable':
				return '';
			case 'qa-tupm-reward-html':
				return '';
			default:
				return null;

		}
	}
	function admin_form(&$qa_content)
	{

		//	Process form input

		$ok = null;
		if (qa_clicked('tupm-save-button')) {
			foreach($_POST as $i => $v) {

				qa_opt($i,$v);
			}

			$ok = qa_lang('admin/options_saved');
		}
		else if (qa_clicked('tupm-reset-button')) {
			foreach($_POST as $i => $v) {
				$def = $this->option_default($i);
				if($def !== null) qa_opt($i,$def);
			}
			$ok = qa_lang('admin/options_reset');
		}			
		//	Create the form for display


		$fields = array();


		$fields[] = array(
				'label' => 'Top Users per Month custom css',
				'tags' => 'NAME="tupm-plugin-css"',
				'value' => qa_opt('tupm-plugin-css'),
				'type' => 'textarea',
				'rows' => 20
				);
		$fields[] = array(
				'label' => 'Top Users per Month Title',
				'tags' => 'NAME="qa-tupm-plugin-title"',
				'value' => qa_opt('qa-tupm-plugin-title'),
				'type' => 'text',
				);
		$fields[] = array(
				'label' => 'Top Search Display Count',
				'tags' => 'NAME="qa-topsearch-plugin-count"',
				'value' => qa_opt('qa-topsearch-plugin-count'),
				'type' => 'text',
				);
		$fields[] = array(
				'label' => 'No. of Previous Days to Query searches',
				'tags' => 'NAME="qa-topsearch-plugin-interval-days"',
				'value' => qa_opt('qa-topsearch-plugin-interval-days'),
				'type' => 'text',
				);
		$fields[] = array(
				'label' => 'Search Type',
				'tags' => 'NAME="qa-topsearch-plugin-param"',
				'value' => qa_opt('qa-topsearch-plugin-param'),
				'type' => 'select',
				'options' => array('search'=> 'search','tagsearch'=> 'tagsearch'),
				);
		$fields[] = array(
				'label' => 'Change to Recent Searches',
				'tags' => 'NAME="qa-topsearch-plugin-recent"',
				'value' => qa_opt('qa-topsearch-plugin-recent'),
				'type' => 'checkbox',
				);


		return array(
				'ok' => ($ok && !isset($error)) ? $ok : null,

				'fields' => $fields,

				'buttons' => array(
					array(
						'label' => qa_lang_html('main/save_button'),
						'tags' => 'NAME="tupm_save_button"',
					     ),
					array(
						'label' => qa_lang_html('admin/reset_options_button'),
						'tags' => 'NAME="tupm_reset_button"',
					     ),
					),
			    );
	}


}
