<?php
class qa_tupm_admin {

	function allow_template($template)
	{
		return ($template!='admin');
	}

	function option_default($option) {

		switch($option) {
			case 'tupm-plugin-css':
				return '
.topusers ol{ list-style:decimal; padding-left: 22px; margin-left: 0;}
.topusers li  {
    margin-bottom: 15px;
}
.topusers .qa-widget-side a {
	vertical-align:middle;
                                        display:initial;}
.topusers img{
float:left;
}
.topusers-span {
    display: inline-block;
    margin-left: 10px;
	vertical-align:middle;
}
.topusers .uscore{
font-family: arial;
}
.tupm-title {
    font-family: Arial;
    font-weight: bold;
margin-top: 10px;
}
.topusers-page{
font-size: large;
}
.topusers-page .tupm-title {
margin-bottom: 10px;
}

				';
			case 'qa-tupm-plugin-title':
				return 'Top Monthly Users';
			case 'qa-tupm-widget-count':
				return '10';
			case 'qa-tupm-weekly-widget-count':
				return '10';
			case 'qa-tupm-page-count':
				return '7';
			case 'qa-tupm-hide-admin':
				return '';
			case 'qa-tupm-hide-points':
				return '';
			case 'qa-tupm-reward-enable':
				return '';
			case 'qa-tupm-payout-enable':
				return '';
			case 'qa-tupm-reward-html':
				return '200 points';
			case 'qa-tupm-weekly-enable':
				return '';
			case 'qa-tupm-weekly-hide-admin':
				return '';
			case 'qa-tupm-weekly-hide-points':
				return '';
			case 'qa-tupm-weekly-reward-enable':
				return '';
			case 'qa-tupm-weekly-reward-html':
				return '200 points';
			case 'qa-tupm-active-days':
				return '30';
			default:
				return null;

		}
	}
	function admin_form(&$qa_content)
	{

		//	Process form input

		$ok = null;
		if (qa_clicked('tupm-save-button')) {
			// Whitelist allowed text/number options (fix: prevent mass-assignment)
			$allowed_text_opts = array(
				'tupm-plugin-css', 'qa-tupm-plugin-title', 'qa-tupm-widget-count',
				'qa-tupm-weekly-widget-count', 'qa-tupm-page-count', 'qa-tupm-reward-html',
				'qa-tupm-weekly-reward-html', 'qa-tupm-active-days',
			);
			foreach ($allowed_text_opts as $key) {
				if (isset($_POST[$key])) {
					qa_opt($key, $_POST[$key]);
				}
			}
			// Checkboxes are absent from POST when unchecked — default to '0'
			$checkbox_opts = array(
				'qa-tupm-hide-admin', 'qa-tupm-hide-points', 'qa-tupm-reward-enable',
				'qa-tupm-payout-enable', 'qa-tupm-weekly-enable', 'qa-tupm-weekly-hide-admin',
				'qa-tupm-weekly-hide-points', 'qa-tupm-weekly-reward-enable',
			);
			foreach ($checkbox_opts as $key) {
				qa_opt($key, isset($_POST[$key]) ? '1' : '0');
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
		else if (qa_clicked('tupm-clear-cache-button')) {
			qa_tupm_points::clearCache();
			$ok = 'Cached rankings cleared; they are recomputed on the next page view.';
		}
		//	Create the form for display

		// Health check: MySQL Event Scheduler status
		$evresult = qa_db_query_raw("SHOW VARIABLES LIKE 'event_scheduler'");
		$evrow = qa_db_read_one_assoc($evresult, true);
		$scheduler_on = ($evrow && strtoupper(isset($evrow['Value']) ? $evrow['Value'] : '') === 'ON');
		$scheduler_html = $scheduler_on
			? '<span style="color:green;font-weight:bold;">&#10003; ON</span>'
			: '<span style="color:red;font-weight:bold;">&#10007; OFF &mdash; run <code>SET GLOBAL event_scheduler = ON;</code> in MySQL to enable scheduled snapshots.</span>';

		$fields = array();

		$fields[] = array(
				'label' => 'MySQL Event Scheduler',
				'type' => 'static',
				'value' => $scheduler_html,
				);

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
				'label' => 'Top Users Count in Widget',
				'tags' => 'NAME="qa-tupm-widget-count"',
				'value' => qa_opt('qa-tupm-widget-count'),
				'type' => 'number',
				);
		$fields[] = array(
				'label' => 'Top Users Count in Page',
				'tags' => 'NAME="qa-tupm-page-count"',
				'value' => qa_opt('qa-tupm-page-count'),
				'type' => 'number',
				);
		$fields[] = array(
				'label' => 'Active User Window (days)',
				'tags' => 'NAME="qa-tupm-active-days"',
				'value' => qa_opt('qa-tupm-active-days'),
				'type' => 'number',
				'note' => 'Users inactive longer than this many days are excluded from both widgets.',
				);
		$fields[] = array(
				'label' => 'Enable Rewards',
				'tags' => 'NAME="qa-tupm-reward-enable"',
				'value' => qa_opt('qa-tupm-reward-enable'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Enable Payouts',
				'tags' => 'NAME="qa-tupm-payout-enable"',
				'value' => qa_opt('qa-tupm-payout-enable'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Reward HTML',
				'tags' => 'NAME="qa-tupm-reward-html"',
				'value' => qa_opt('qa-tupm-reward-html'),
				'type' => 'textarea',
				'rows' => 20,
				);
		$fields[] = array(
				'label' => 'Hide admins from Top Users List',
				'tags' => 'NAME="qa-tupm-hide-admin"',
				'value' => qa_opt('qa-tupm-hide-admin'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Hide points from Top Users List',
				'tags' => 'NAME="qa-tupm-hide-points"',
				'value' => qa_opt('qa-tupm-hide-points'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Enable Weekly Toppers',
				'tags' => 'NAME="qa-tupm-weekly-enable"',
				'value' => qa_opt('qa-tupm-weekly-enable'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Top Users Count in Weekly Widget',
				'tags' => 'NAME="qa-tupm-weekly-widget-count"',
				'value' => qa_opt('qa-tupm-weekly-widget-count'),
				'type' => 'number',
				);
		$fields[] = array(
				'label' => 'Enable Weekly Rewards',
				'tags' => 'NAME="qa-tupm-weekly-reward-enable"',
				'value' => qa_opt('qa-tupm-weekly-reward-enable'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Weekly Reward HTML',
				'tags' => 'NAME="qa-tupm-weekly-reward-html"',
				'value' => qa_opt('qa-tupm-weekly-reward-html'),
				'type' => 'textarea',
				'rows' => 20,
				);
		$fields[] = array(
				'label' => 'Hide admins from Weekly Top Users List',
				'tags' => 'NAME="qa-tupm-weekly-hide-admin"',
				'value' => qa_opt('qa-tupm-weekly-hide-admin'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => 'Hide points from Weekly Top Users List',
				'tags' => 'NAME="qa-tupm-weekly-hide-points"',
				'value' => qa_opt('qa-tupm-weekly-hide-points'),
				'type' => 'checkbox',
				);

		$cached = qa_db_read_one_assoc(qa_db_query_sub("SELECT MAX(computed) c, SUM(ckey LIKE 'day:%') d FROM ^tupm_cache"), true);
		$fields[] = array(
				'label' => 'Points cache',
				'type'  => 'static',
				'value' => 'Week and month points are derived from dated posts, votes, edits, exams and activity; rankings are cached for '
				         . (qa_tupm_points::TTL / 60) . ' minutes. Last refresh: ' . qa_html($cached['c'] ?: 'never') . ' UTC; '
				         . (int)$cached['d'] . ' completed days cached. Finished periods are frozen into monthlytoppers / weeklytoppers. Bonus points are not dated and are not counted.',
				);

		return array(
				'ok' => ($ok && !isset($error)) ? $ok : null,

				'fields' => $fields,

				'buttons' => array(
					array(
						'label' => qa_lang_html('main/save_button'),
						'tags' => 'NAME="tupm-save-button"',
					     ),
					array(
						'label' => qa_lang_html('admin/reset_options_button'),
						'tags' => 'NAME="tupm-reset-button"',
					     ),
					array(
						'label' => 'Clear cache',
						'tags'  => 'NAME="tupm-clear-cache-button"',
					     ),
					),
			    );
	}


}
