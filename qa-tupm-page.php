<?php

class qa_tupm_page {

	// Tables for frozen period results and the derived-points cache, plus indexes the period queries rely on
	function init_queries($tableslc) {
		$queries = array();
		foreach (array('monthlytoppers', 'weeklytoppers') as $t) {
			if (!in_array(qa_db_add_table_prefix($t), $tableslc)) {
				$queries[] = 'CREATE TABLE IF NOT EXISTS ^' . $t . ' (`date` date NOT NULL, `userid` int(10) unsigned NOT NULL, `points` int(11) NOT NULL DEFAULT 0, PRIMARY KEY (userid, date), KEY `date` (`date`)) ENGINE=InnoDB';
			}
		}
		if (!in_array(qa_db_add_table_prefix('tupm_cache'), $tableslc)) {
			$queries[] = 'CREATE TABLE IF NOT EXISTS ^tupm_cache (ckey VARCHAR(32) NOT NULL PRIMARY KEY, computed DATETIME NOT NULL, data MEDIUMTEXT NOT NULL) ENGINE=InnoDB CHARSET=utf8mb4';
		}
		if (!in_array(qa_db_add_table_prefix('tupm_daily'), $tableslc)) {
			$queries[] = 'CREATE TABLE IF NOT EXISTS ^tupm_daily (day DATE NOT NULL, userid INT UNSIGNED NOT NULL, points INT NOT NULL, PRIMARY KEY (day, userid)) ENGINE=InnoDB';
		}
		$indexes = array(
			'pupi_pc_history' => array('tupm_formula_time' => 'formula, event_time, user_id, point_sign'),
			'exam_results' => array('tupm_datetime' => 'datetime, userid, examid', 'tupm_user_exam_time' => 'userid, examid, datetime'),
			'uservotes' => array('tupm_created' => 'votecreated, postid, vote'),
		);
		foreach ($indexes as $t => $list) {
			if (!in_array(qa_db_add_table_prefix($t), $tableslc)) {
				continue;
			}
			$have = qa_db_read_all_values(qa_db_query_sub('SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = $', qa_db_add_table_prefix($t)));
			foreach ($list as $name => $cols) {
				if (!in_array($name, $have)) {
					$queries[] = 'ALTER TABLE ^' . $t . ' ADD INDEX ' . $name . ' (' . $cols . '), ALGORITHM=INPLACE, LOCK=NONE';
				}
			}
		}
		if(qa_opt('qa-tupm-payout-enable'))
		{
			$eventname=qa_db_add_table_prefix('tupmevent_payout');
			$events = qa_db_read_one_value(qa_db_query_raw("show events where name like '$eventname'"), true);

			if(!$events){
				$opttable = qa_db_add_table_prefix('options');
				$queries[] = "CREATE EVENT if not exists $eventname
					ON SCHEDULE EVERY 1 MONTH 
					starts	concat(str_to_date ( concat(concat(DATE_FORMAT(NOW(), '%Y'),month(NOW() + interval 1 month)),\"-1\"), '%Y%m-%d'), ' 00:00:00')
					DO
					BEGIN
					update $opttable set content = (select content from $opttable where title like 'monthly_verified_count') where title like 'last_monthly_verified_count';
				update $opttable set content = '0' where title like 'monthly_verified_count';
				END";
			}
		}
		return $queries;
	}

	var $directory;
	var $urltoroot;

	function load_module($directory, $urltoroot)
	{
		$this->directory=$directory;
		$this->urltoroot=$urltoroot;
	}

	// for display in admin interface under admin/pages
	function suggest_requests() 
	{	
		return array(
				array(
					'title' => 'Top Users per Month Page', // title of page
					'request' => 'topusers', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				     ),
			    );
	}

	// for url query
	function match_request($request)
	{
		if ($request=='topusers') {
			return true;
		}

		return false;
	}

	/**
	 * Renders a ranked list of users with standard competition ranking (1,2,2,4…).
	 * $compact=false → full monthly row; $compact=true → compact weekly card row.
	 */
	function tupm_rank_list($scores, $ptKey, $hidepoints, $pointsLang, $userCache, $avatarSize, $compact)
	{
		$rowHtml  = '';
		$rank     = 0;
		$prevPts  = null;
		$tieCount = 0;
		$rowCount = 0;

		foreach ($scores as $row) {
			$pts = (int)$row[$ptKey];
			if ($pts <= 0) continue;

			// Standard competition ranking: ties share rank, next rank skips
			if ($prevPts === null || $pts < $prevPts) {
				$rank    += 1 + $tieCount;
				$tieCount = 0;
			} else {
				$tieCount++;
			}
			$prevPts = $pts;
			$rowCount++;

			$uid  = $row['userid'];
			$user = isset($userCache[$uid]) ? $userCache[$uid] : null;
			if (!$user) continue;

			$bc = ($rank === 1) ? 'gold' : (($rank === 2) ? 'silver' : (($rank === 3) ? 'bronze' : 'other'));

			if ($compact) {
				$av       = qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'],
				                $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], 22, true);
				$rowHtml .= '<div class="tupm-week-row">'
				          . '<span class="tupm-wbadge ' . $bc . '">' . $rank . '</span>'
				          . '<span class="tupm-wavatar">' . $av . '</span>'
				          . '<span class="tupm-wname">' . qa_get_one_user_html($user['handle'], false) . '</span>';
				if (!$hidepoints) $rowHtml .= '<span class="tupm-wpts">' . $pts . '</span>';
				$rowHtml .= '</div>';
			} else {
				$bt       = ($rank <= 3) ? $rank : '#' . $rank;
				$rc       = 'tupm-row rank-' . min($rank, 4);
				$av       = qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'],
				                $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], $avatarSize, true);
				$rowHtml .= '<div class="' . $rc . '">'
				          . '<span class="tupm-badge ' . $bc . '">' . $bt . '</span>'
				          . '<span class="tupm-avatar">' . $av . '</span>'
				          . '<span class="tupm-name">' . qa_get_one_user_html($user['handle'], false) . '</span>';
				if (!$hidepoints) $rowHtml .= '<span class="tupm-pts">' . $pts . ' <small>' . $pointsLang . '</small></span>';
				$rowHtml .= '</div>';
			}
		}

		if ($rowCount === 0) {
			return $compact
				? '<p class="tupm-week-empty">No data</p>'
				: '<p class="tupm-empty">No data available for this period.</p>';
		}
		return $compact
			? $rowHtml
			: '<div class="tupm-ranked-list">' . $rowHtml . '</div>';
	}

	function process_request($request)
	{
		$maxusers      = max(1, (int)qa_opt('qa-tupm-page-count'));
		$page          = max(1, (int)(isset($_GET['tupm_page']) ? $_GET['tupm_page'] : 1));
		$offset        = ($page - 1) * $maxusers;
		$hideadmin     = qa_opt('qa-tupm-hide-admin');
		$hidepoints    = qa_opt('qa-tupm-hide-points');
		$showReward    = qa_opt('qa-tupm-reward-enable');
		$weeklyEnabled = qa_opt('qa-tupm-weekly-enable');
		$activedays    = max(1, (int)qa_opt('qa-tupm-active-days'));
		$avatarSize    = (int)qa_opt('avatar_users_size');
		$pointsLang    = qa_lang_html('qa_tupm_lang/points');
		$wMaxusers     = $maxusers; // weekly cards on page use same limit as monthly section



		// ── Month range: from the first frozen month to the current local month ─
		$lastListDate  = qa_tupm_points::monthStart();
		$firstListDate = qa_db_read_one_value(qa_db_query_sub('SELECT MIN(`date`) FROM `^monthlytoppers`'), true);
		$firstListDate = $firstListDate ? date('Y-m-01', strtotime($firstListDate)) : $lastListDate;

		// ── Resolve chosen month from GET ─────────────────────────────────────
		$chosenMonth = $lastListDate;
		if (isset($_GET['tupm_month'])) {
			$raw    = preg_replace('/[^0-9\-]/', '', $_GET['tupm_month']);
			$parsed = date('Y-m-01', strtotime($raw));
			if ($parsed && $parsed >= $firstListDate && $parsed <= $lastListDate) {
				$chosenMonth = $parsed;
			}
		}

		$intervalStart  = $chosenMonth;
		$intervalEnd    = date('Y-m-01', strtotime($chosenMonth . ' +1 month'));
		$monthName      = date('F Y',    strtotime($chosenMonth));
		$isCurrentMonth = ($chosenMonth === $lastListDate);
		$prevMonth      = date('Y-m-01', strtotime($chosenMonth . ' -1 month'));
		$nextMonth      = $intervalEnd;
		$hasPrev        = ($prevMonth >= $firstListDate);
		$hasNext        = !$isCurrentMonth;

		// ── Monthly scores (fetch one extra to detect a next page) ───────────
		$userCache     = array();
		$monthlyScores = array();
		foreach (qa_tupm_points::ranked('month', $chosenMonth, $maxusers + 1, $offset, $hideadmin) as $u) {
			$userCache[$u['userid']] = $u;
			$monthlyScores[] = array('userid' => $u['userid'], 'mpoints' => $u['points']);
		}
		$hasNextPage = count($monthlyScores) > $maxusers;
		if ($hasNextPage) array_pop($monthlyScores);

		// ── Weekly sections – all weeks whose Monday falls in the chosen month ─
		$weekSections = array();
		if ($weeklyEnabled) {
			$thisWeek = qa_tupm_points::weekStart();
			$wStart   = date('Y-m-d', strtotime('monday this week', strtotime($intervalStart)));
			if ($wStart < $intervalStart) $wStart = date('Y-m-d', strtotime($wStart . ' +7 days'));
			$wnum = 0;
			for (; $wStart < $intervalEnd && $wStart <= $thisWeek; $wStart = date('Y-m-d', strtotime($wStart . ' +7 days'))) {
				$wnum++;
				$isCurrent = ($wStart === $thisWeek);
				$wScores   = array();
				foreach (qa_tupm_points::ranked('week', $wStart, $wMaxusers, 0, $hideadmin, $isCurrent ? $activedays : 0) as $u) {
					$userCache[$u['userid']] = $u;
					$wScores[] = array('userid' => $u['userid'], 'wpoints' => $u['points']);
				}
				$weekSections[] = array(
					'num'     => $wnum,
					'label'   => date('M j', strtotime($wStart)) . ' &ndash; ' . date('M j', strtotime($wStart . ' +6 days')),
					'current' => $isCurrent,
					'scores'  => $wScores,
				);
			}
		}

		// ── Initialise Q2A page ───────────────────────────────────────────────
		$qa_content = qa_content_prepare();
		$qa_content['description'] = 'Top contributors on GATE Overflow each month, ranked by points earned from questions, answers and votes.';
		$qa_content['navigation']['sub'] = qa_users_sub_navigation();
		$qa_content['title']             = qa_lang_html('qa_tupm_lang/page_title') . ' (Top ' . $maxusers . ')';

		// ── Page-scoped CSS ───────────────────────────────────────────────────
		$qa_content['head_lines'][] = '<style>
.tupm-month-nav{display:flex;align-items:center;justify-content:center;gap:14px;margin:10px 0 22px;flex-wrap:wrap}
.tupm-nav-btn{display:inline-flex;align-items:center;padding:7px 18px;border-radius:20px;background:#f0f2f5;color:#333;text-decoration:none;font-size:.9em;border:1px solid #ddd;white-space:nowrap}
.tupm-nav-btn:hover{background:#e0e4ea;text-decoration:none;color:#000}
.tupm-nav-disabled{opacity:.38;pointer-events:none}
.tupm-month-label{font-size:1.25em;font-weight:700;color:#1a1a2e;min-width:140px;text-align:center}
.tupm-section{background:#fff;border:1px solid #e4e6ea;border-radius:10px;margin-bottom:26px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.07)}
.tupm-section-hdr{padding:14px 20px;font-size:1.05em;font-weight:700;color:#fff;background:linear-gradient(135deg,#1a1a2e,#16213e)}
.tupm-section-hdr.wk{background:linear-gradient(135deg,#0f3460,#16213e)}
.tupm-ranked-list{padding:10px 14px}
.tupm-row{display:flex;align-items:center;gap:11px;padding:9px 11px;border-radius:8px;margin-bottom:5px;border-left:4px solid transparent}
.tupm-row.rank-1{background:#fffbe6;border-color:#f5c518}
.tupm-row.rank-2{background:#f6f6f6;border-color:#b0b0b0}
.tupm-row.rank-3{background:#fff5ee;border-color:#cd7f32}
.tupm-row.rank-4{background:#fafafa;border-color:#e9ecef}
.tupm-row:hover{filter:brightness(.97)}
.tupm-badge{min-width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.84em;flex-shrink:0}
.tupm-badge.gold{background:#f5c518;color:#333}
.tupm-badge.silver{background:#bdbdbd;color:#fff}
.tupm-badge.bronze{background:#cd7f32;color:#fff}
.tupm-badge.other{background:#e9ecef;color:#666;font-size:.78em}
.tupm-avatar{flex-shrink:0;line-height:0}
.tupm-avatar img{border-radius:50%}
.tupm-name{flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tupm-name a{color:#1a1a2e;text-decoration:none}
.tupm-name a:hover{text-decoration:underline}
.tupm-pts{font-weight:700;color:#0f3460;white-space:nowrap;font-size:.93em}
.tupm-pts small{font-weight:400;color:#888;font-size:.82em}
.tupm-empty{color:#999;text-align:center;padding:24px;font-style:italic;margin:0}
.tupm-weeks-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:14px;padding:14px}
.tupm-week-card{border:1px solid #e4e6ea;border-radius:8px;overflow:hidden}
.tupm-week-title{background:#f0f4f8;padding:8px 12px;font-weight:700;font-size:.85em;color:#0f3460;border-bottom:1px solid #e4e6ea}
.tupm-week-title.cur{background:#e3f2fd;color:#0277bd}
.tupm-week-body{padding:6px 8px}
.tupm-week-row{display:flex;align-items:center;gap:5px;padding:4px 3px;border-bottom:1px solid #f5f5f5;font-size:.85em}
.tupm-week-row:last-child{border:none}
.tupm-wbadge{min-width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72em;font-weight:800;flex-shrink:0}
.tupm-wbadge.gold{background:#f5c518;color:#333}
.tupm-wbadge.silver{background:#bdbdbd;color:#fff}
.tupm-wbadge.bronze{background:#cd7f32;color:#fff}
.tupm-wbadge.other{background:#e9ecef;color:#666}
.tupm-wavatar{flex-shrink:0;line-height:0}
.tupm-wavatar img{border-radius:50%;width:22px!important;height:22px!important}
.tupm-wname{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tupm-wname a{color:#333;text-decoration:none;font-weight:600}
.tupm-wname a:hover{text-decoration:underline}
.tupm-wpts{font-weight:700;color:#0f3460;font-size:.82em;white-space:nowrap}
.tupm-week-empty{color:#aaa;font-size:.82em;padding:12px;text-align:center;font-style:italic}
.tupm-week-title.missed{background:#fff3e0;color:#e65100;border-bottom:1px solid #ffcc80}
.tupm-reward-banner{background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#2e7d32}
.tupm-pagination{display:flex;align-items:center;justify-content:center;gap:10px;padding:12px 14px;border-top:1px solid #eee;font-size:.9em}
.tupm-pg-btn{padding:5px 14px;border-radius:16px;background:#f0f2f5;color:#1a1a2e;text-decoration:none;border:1px solid #ddd;font-weight:600}
.tupm-pg-btn:hover{background:#e0e4ea;text-decoration:none}
.tupm-pg-info{color:#666;font-size:.88em}
</style>';

		// ── Assemble page HTML ────────────────────────────────────────────────
		$html = '';

		// Optional reward banner at the top
		if ($showReward) {
			$rewardHtml  = qa_opt('qa-tupm-reward-html');
			$count       = (float)qa_opt('monthly_verified_count');
			$factor      = (float)qa_opt('verifyplus_monthly_factor');
			$monthly_pay = $factor * $count * 750;
			if ($monthly_pay > 100) {
				$rewardHtml = str_replace('[monthly_budget]', number_format($monthly_pay), $rewardHtml);
				$html .= '<div class="tupm-reward-banner">' . $rewardHtml . '</div>';
			}
		}

		// Month navigation bar: ‹ Prev  |  Month Year  |  Next ›
		$html .= '<div class="tupm-month-nav">';
		$html .= $hasPrev
			? '<a class="tupm-nav-btn" href="' . qa_path_html('topusers', array('tupm_month' => $prevMonth)) . '">&#8249;&nbsp;' . date('M Y', strtotime($prevMonth)) . '</a>'
			: '<span class="tupm-nav-btn tupm-nav-disabled">&#8249;</span>';
		$html .= '<span class="tupm-month-label">' . $monthName . '</span>';
		$html .= $hasNext
			? '<a class="tupm-nav-btn" href="' . qa_path_html('topusers', array('tupm_month' => $nextMonth)) . '">' . date('M Y', strtotime($nextMonth)) . '&nbsp;&#8250;</a>'
			: '<span class="tupm-nav-btn tupm-nav-disabled">&#8250;</span>';
		$html .= '</div>';

		// ── Section 1: Top Users of the Month ────────────────────────────────
		$curLabel = $isCurrentMonth ? ' <small style="font-weight:400;opacity:.75">(so far)</small>' : '';
		$html .= '<div class="tupm-section">';
		$html .= '<div class="tupm-section-hdr">&#127942; Top Users &mdash; ' . $monthName . $curLabel . '</div>';
		$html .= $this->tupm_rank_list($monthlyScores, 'mpoints', $hidepoints, $pointsLang, $userCache, $avatarSize, false);
		// Pagination for monthly section
		if ($page > 1 || $hasNextPage) {
			$baseParams = ($chosenMonth !== $lastListDate) ? array('tupm_month' => $chosenMonth) : array();
			$html .= '<div class="tupm-pagination">';
			if ($page > 1) {
				$prev = array_merge($baseParams, array('tupm_page' => $page - 1));
				$html .= '<a class="tupm-pg-btn" href="' . qa_path_html('topusers', $prev) . '">&laquo; Prev</a>';
			}
			$html .= '<span class="tupm-pg-info">Page ' . $page . '</span>';
			if ($hasNextPage) {
				$next = array_merge($baseParams, array('tupm_page' => $page + 1));
				$html .= '<a class="tupm-pg-btn" href="' . qa_path_html('topusers', $next) . '">Next &raquo;</a>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';

		// ── Section 2: Weekly Breakdown (all weeks in the month) ─────────────
		if ($weeklyEnabled) {
			$html .= '<div class="tupm-section">';
			$html .= '<div class="tupm-section-hdr wk">&#128197; Weekly Breakdown &mdash; ' . $monthName . '</div>';
			if (empty($weekSections)) {
				$html .= '<p class="tupm-empty">No weekly data available for this month.</p>';
			} else {
				$html .= '<div class="tupm-weeks-grid">';
				foreach ($weekSections as $ws) {
					$tcls      = $ws['current'] ? 'tupm-week-title cur' : 'tupm-week-title';
					$curSuffix = $ws['current'] ? ' <small style="font-weight:400;opacity:.8">(ongoing)</small>' : '';
					$body      = $this->tupm_rank_list($ws['scores'], 'wpoints', $hidepoints, $pointsLang, $userCache, $avatarSize, true);
					$html .= '<div class="tupm-week-card">'
					       . '<div class="' . $tcls . '">Week&nbsp;' . $ws['num'] . ' &middot; ' . $ws['label'] . $curSuffix . '</div>'
					       . '<div class="tupm-week-body">' . $body . '</div>'
					       . '</div>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}

		$qa_content['custom'] = $html;
		return $qa_content;
	}

};


/*
   Omit PHP closing tag to help avoid accidental output
 */
