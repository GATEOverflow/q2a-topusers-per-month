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
		// R2: manual monthly snapshot — INSERT IGNORE so re-running same date is safe
		else if (qa_clicked('tupm-snap-monthly-button')) {
			$raw      = isset($_POST['tupm-snap-date-m']) ? preg_replace('/[^0-9\-]/', '', $_POST['tupm-snap-date-m']) : '';
			$snapDate = ($raw && strtotime($raw)) ? date('Y-m-d', strtotime($raw)) : date('Y-m-d');

			// Step 1: snapshot live totals into userscores (mirrors scheduler step 1)
			qa_db_query_sub("INSERT IGNORE INTO ^userscores (userid, points, `date`) SELECT userid, points, '$snapDate' FROM ^userpoints ORDER BY userid ASC");

			// Step 2: compute the previous month's delta into monthlytoppers (mirrors scheduler step 2).
			// Always uses the 1st of the previous month as the monthlytoppers key, regardless of which
			// day of the month $snapDate is — so backdating to Jun-01 or entering Jun-06 both finalize May correctly.
			// Uses INSERT IGNORE: if monthlytoppers already has rows for that month they are not overwritten.
			qa_db_query_sub("INSERT IGNORE INTO ^monthlytoppers (date, userid, points)
				SELECT DATE_FORMAT('$snapDate', '%Y-%m-01') - INTERVAL 1 MONTH AS date,
				       a.userid,
				       a.points - COALESCE(b.points, 0) AS mpoints
				FROM ^userscores a
				JOIN ^userscores b ON a.userid = b.userid
				WHERE a.date = '$snapDate'
				  AND b.date BETWEEN (a.date - INTERVAL 35 DAY) AND (a.date - INTERVAL 25 DAY)
				HAVING mpoints > 0");

			$prevMonLabel = date('F Y', strtotime(date('Y-m-01', strtotime($snapDate)) . ' -1 month'));
			$ok = 'Monthly snapshot taken for ' . $snapDate . '. '
			    . 'Scores for ' . $prevMonLabel . ' computed into monthlytoppers where a matching previous snapshot was found (INSERT IGNORE — existing rows preserved).';
		}
		// R2: manual weekly snapshot — DELETE then INSERT so duplicate dates are replaced
		else if (qa_clicked('tupm-snap-weekly-button')) {
			$raw      = isset($_POST['tupm-snap-date-w']) ? preg_replace('/[^0-9\-]/', '', $_POST['tupm-snap-date-w']) : '';
			$snapDate = ($raw && strtotime($raw)) ? date('Y-m-d', strtotime($raw)) : date('Y-m-d');
			qa_db_query_sub("DELETE FROM ^userscores_weekly WHERE `date` = '$snapDate'");
			qa_db_query_sub("INSERT INTO ^userscores_weekly (userid, points, `date`) SELECT userid, points, '$snapDate' FROM ^userpoints ORDER BY userid ASC");
			$ok = 'Weekly snapshot taken for ' . $snapDate . '. Any existing rows for this date were replaced.';
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

		// ── R2: Snapshot management ───────────────────────────────────────────
		$lastMonSnap = qa_db_read_one_value(qa_db_query_sub("SELECT MAX(`date`) FROM `^userscores`"), true);
		$lastWkSnap  = qa_db_read_one_value(qa_db_query_sub("SELECT MAX(`date`) FROM `^userscores_weekly`"), true);
		$sdow        = (int)date('N'); // 1=Mon … 7=Sun
		$snapTrueMon = date('Y-m-d', strtotime('-' . ($sdow - 1) . ' days'));

		$monColor = 'green'; $monIcon = '&#10003;';
		$monNote  = $lastMonSnap ? 'Last: ' . $lastMonSnap . '.' : 'No snapshots found.';
		if (!$lastMonSnap) {
			$monColor = 'red'; $monIcon = '&#10007;';
		} elseif (substr($lastMonSnap, 0, 7) !== date('Y-m')) {
			$monColor = '#cc6600'; $monIcon = '&#9888;';
			$monNote  = 'Last: ' . $lastMonSnap . ' — no snapshot for this month yet.';
		}

		$wkColor = 'green'; $wkIcon = '&#10003;';
		$wkNote  = $lastWkSnap ? 'Last: ' . $lastWkSnap . '.' : 'No weekly snapshots found.';
		if (!$lastWkSnap) {
			$wkColor = 'red'; $wkIcon = '&#10007;';
		} elseif ($lastWkSnap < $snapTrueMon) {
			$daysSince = (int)floor((time() - strtotime($lastWkSnap)) / 86400);
			$wkColor   = '#cc6600'; $wkIcon = '&#9888;';
			$wkNote    = 'Last: ' . $lastWkSnap . ' (' . $daysSince . 'd ago) — snapshot for ' . $snapTrueMon . ' (this Monday) is missing.';
		}

		$snapStatusHtml = '<table style="border-collapse:collapse;width:100%;font-size:.9em">
			<tr><td style="padding:3px 8px;width:90px"><strong>Monthly</strong></td>
			    <td style="padding:3px 8px"><span style="color:' . $monColor . '">' . $monIcon . '</span> ' . $monNote . '</td></tr>
			<tr><td style="padding:3px 8px"><strong>Weekly</strong></td>
			    <td style="padding:3px 8px"><span style="color:' . $wkColor . '">' . $wkIcon . '</span> ' . $wkNote . '</td></tr>
		</table>';

		$fields[] = array(
				'label' => 'Snapshot Status',
				'type'  => 'static',
				'value' => $snapStatusHtml,
				);
		$fields[] = array(
				'label' => 'Monthly snapshot date',
				'tags'  => 'NAME="tupm-snap-date-m"',
				'value' => date('Y-m-01'),
				'type'  => 'text',
				'note'  => 'YYYY-MM-DD. Captures live point totals into qa_userscores under the date you enter. '
				         . 'Default is the 1st of this month — so even if you run this on the 6th, it records as if the cron ran on the 1st. '
				         . 'INSERT IGNORE: if a snapshot already exists for that date it is not overwritten.',
				);
		$fields[] = array(
				'label' => 'Weekly snapshot date',
				'tags'  => 'NAME="tupm-snap-date-w"',
				'value' => $snapTrueMon,
				'type'  => 'text',
				'note'  => 'YYYY-MM-DD. Captures live point totals into qa_userscores_weekly under the date you enter. '
				         . 'Default is this Monday — so taking it on Wednesday still records as if the cron ran on Monday. '
				         . 'You can also use next Monday\'s date: the missed week will show a slightly inflated delta '
				         . 'but next week\'s delta will start near-zero (both snapshots taken close together). '
				         . 'Existing rows for the same date are deleted and replaced.',
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
						'label' => 'Take Monthly Snapshot',
						'tags'  => 'NAME="tupm-snap-monthly-button"',
					     ),
					array(
						'label' => 'Take Weekly Snapshot',
						'tags'  => 'NAME="tupm-snap-weekly-button"',
					     ),
					),
			    );
	}


}
