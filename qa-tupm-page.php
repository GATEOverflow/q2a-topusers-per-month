<?php

class qa_tupm_page {

	// initialize db-table 'userscores' if it does not exist yet
	function init_queries($tableslc) {
		$queries = array();
		$tablename=qa_db_add_table_prefix('monthlytoppers');
		$new = false;
		$tablename1=qa_db_add_table_prefix('userscores');
		$tablename2=qa_db_add_table_prefix('userpoints');
		if(!in_array($tablename, $tableslc)) {
			$new = true;
			$queries[] = "CREATE TABLE IF NOT EXISTS `".$tablename."` (
				`date` date NOT NULL,
				`userid` int(10) unsigned NOT NULL,
				`points` int(11) NOT NULL DEFAULT '0',
				KEY `userid` (`userid`),
				KEY `date` (`date`),
				PRIMARY KEY(userid, date)
					)
					";
		}
		if(!in_array($tablename1, $tableslc)) {
			$queries[] = "CREATE TABLE IF NOT EXISTS `".$tablename1."` (
				`date` date NOT NULL,
				`userid` int(10) unsigned NOT NULL,
				`points` int(11) NOT NULL DEFAULT '0',
				KEY `userid` (`userid`),
				KEY `date` (`date`),
				PRIMARY KEY(userid, date)
					)
					";
			$queries[] = "insert into  ".$tablename1." (userid, points, date) select userid, points, CURDATE() as date from ".$tablename2." order by userid asc";
		}
		else if($new){
			$select = "select min(date) as date from ^userscores";
			$result = qa_db_query_sub($select);
			$mindate = qa_db_read_one_value($result);
			$mdate = strtotime($mindate); 
			$select = "select max(date) as date from ^userscores";
			$result = qa_db_query_sub($select);
			$maxdate = qa_db_read_one_value($result);
			$mxdate = strtotime($maxdate);
			while($mdate < $mxdate){
				$insert = "insert into ".$tablename." (date, userid, points) select a.date, a.userid, b.points - COALESCE(a.points,0) AS mpoints from ".$tablename1." a, ".$tablename1." b where a.userid = b.userid and a.date = '".date("Y-m-d", $mdate)."' and b.date between (a.date + interval 1 day) and (a.date + interval 59 day)  group by a.userid,a.points,b.points  having mpoints>0";
				$queries[] = $insert;
				$select = "select min(date) as date from ^userscores where date > '".date("Y-m-d", $mdate)."'";
				$result = qa_db_query_sub($select);
				$mindate = qa_db_read_one_value($result);
				$mdate = strtotime($mindate); 

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
		$eventname=qa_db_add_table_prefix('tupmevent');
		$events = qa_db_read_one_value(qa_db_query_raw("show events where name like '$eventname'"), true);

		if(!$events){
			$queries[] = "CREATE EVENT if not exists $eventname
				ON SCHEDULE EVERY 1 MONTH 
				starts	concat(str_to_date ( concat(concat(DATE_FORMAT(NOW(), '%Y'),month(NOW() + interval 1 month)),\"-1\"), '%Y%m-%d'), ' 00:00:00')
				DO
				BEGIN
				insert into  ".$tablename1." (userid, points, date) select userid, points, CURDATE() as date from ".$tablename2." order by userid asc;
			insert into ".$tablename." (date, userid, points) select CURDATE() - interval 1 month as date, a.userid, a.points - COALESCE(b.points,0) AS mpoints from ".$tablename1." a, ".$tablename1." b where a.userid = b.userid and a.date = CURDATE() and b.date between (a.date - interval 35 day) and (a.date - interval 25 day)  group by a.userid,a.points,b.points  having mpoints>0;

			END ";
		}
		if(qa_opt('qa-tupm-weekly-enable'))
		{
			$tablename=qa_db_add_table_prefix('weeklytoppers');
			if(!in_array($tablename, $tableslc)) {
				$queries[] = "CREATE TABLE IF NOT EXISTS `".$tablename."` (
					`date` date NOT NULL,
					`userid` int(10) unsigned NOT NULL,
					`points` int(11) NOT NULL DEFAULT '0',
					KEY `userid` (`userid`),
					KEY `date` (`date`)
						)
						";
			}
			$tablename1=qa_db_add_table_prefix('userscores_weekly');
			if(!in_array($tablename1, $tableslc)) {
				$queries[]= "CREATE TABLE IF NOT EXISTS `".$tablename1."` (
					`date` date NOT NULL,
					`userid` int(10) unsigned NOT NULL,
					`points` int(11) NOT NULL DEFAULT '0',
					KEY `userid` (`userid`),
					KEY `date` (`date`)
						)
						";
				$queries[] = "insert into  ".$tablename1." (userid, points, date) select userid, points, CURDATE() as date from ".$tablename2." order by userid asc";
			}
			$eventname=qa_db_add_table_prefix('tupmweeklyevent');
			$events = qa_db_read_one_value(qa_db_query_raw("show events where name like '$eventname'"), true);
			if(!$events){
				$queries[] = "CREATE EVENT if not exists $eventname
					ON SCHEDULE EVERY 1 WEEK
					starts concat(str_to_date(concat(yearweek(NOW() + interval 1 week), 'Monday'), '%X%V %W'), ' 00:00:00')
					DO
					BEGIN
					insert into  ".$tablename1." (userid, points, date) select userid, points, CURDATE() as date from ".$tablename2." order by userid asc;
				insert into ".$tablename." (date, userid, points) select CURDATE() - interval 1 week as date, a.userid, a.points - COALESCE(b.points,0) AS mpoints from ".$tablename1." a, ".$tablename1." b where a.userid = b.userid and a.date = CURDATE() and b.date between (a.date - interval 10 day) and (a.date - interval 4 day)  group by a.userid,a.points,b.points  having mpoints>0;

				END ";
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



		/* start */
		// ── Resolve first available snapshot date ────────────────────────────
		$lastListDate  = date('Y-m-01');
		$firstListDate = '2012-10-01';
		$qfd = qa_db_query_sub('SELECT `date` FROM `^userscores` ORDER BY `date` ASC LIMIT 1');
		$fdr = qa_db_read_one_assoc($qfd, true);
		if ($fdr) $firstListDate = $fdr['date'];
		$firstListDate = date('Y-m-01', strtotime($firstListDate . ' -1 month'));

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

		// ── User exclusion clause ─────────────────────────────────────────────
		$excl = 'not in (select userid from ^users where flags & ' . QA_USER_FLAGS_USER_BLOCKED . ' = 1';
		if ($hideadmin) $excl .= ' or level >= ' . QA_USER_LEVEL_ADMIN;
		$excl .= ')';

		// ── Monthly scores ────────────────────────────────────────────────────
		$fetchLimit = ($maxusers + 1); // fetch one extra to detect next page
		if ($isCurrentMonth) {
			// Current month: live delta = userpoints − snapshot at month start
			$snapRes  = qa_db_query_sub("SELECT MAX(`date`) AS d FROM `^userscores` WHERE `date` >= '$intervalStart' AND `date` < '$intervalEnd'");
			$snapRow  = qa_db_read_one_assoc($snapRes, true);
			$snapDate = ($snapRow && $snapRow['d']) ? $snapRow['d'] : null;
			$jc       = $snapDate ? "us.date = '$snapDate'" : '1=0';
			$qMonth   = qa_db_query_sub(
				"SELECT up.userid, up.points - COALESCE(us.points, 0) AS mpoints
				 FROM `^userpoints` up
				 LEFT JOIN `^userscores` us ON up.userid = us.userid AND $jc
				 WHERE up.userid $excl
				 HAVING mpoints > 0
				 ORDER BY mpoints DESC
				 LIMIT $fetchLimit OFFSET $offset"
			);
		} else {
			// Past month: prefer qa_monthlytoppers (pre-computed by Event Scheduler / seed).
			// Fall back to live snapshot delta from qa_userscores if monthlytoppers has no
			// data for this month (e.g. before the cron ever ran for it).
			$chk    = qa_db_query_sub("SELECT COUNT(*) AS cnt FROM `^monthlytoppers` WHERE `date` = '$intervalStart'");
			$chkRow = qa_db_read_one_assoc($chk, true);
			if ($chkRow && (int)$chkRow['cnt'] > 0) {
				// Use monthlytoppers – changes here are immediately reflected
				$qMonth = qa_db_query_sub(
					"SELECT mt.userid, mt.points AS mpoints
					 FROM `^monthlytoppers` mt
					 WHERE mt.date = '$intervalStart' AND mt.userid $excl
					 ORDER BY mpoints DESC
					 LIMIT $fetchLimit OFFSET $offset"
				);
			} else {
				// Fallback: compute delta from two consecutive userscores snapshots
				$qMonth = qa_db_query_sub(
					"SELECT us1.userid, us1.points - COALESCE(us2.points, 0) AS mpoints
					 FROM `^userscores` us1
					 LEFT JOIN (SELECT userid, points FROM `^userscores` WHERE `date` = '$intervalStart') AS us2
					   ON us1.userid = us2.userid
					 WHERE us1.date = '$intervalEnd' AND us1.userid $excl
					 HAVING mpoints > 0
					 ORDER BY mpoints DESC
					 LIMIT $fetchLimit OFFSET $offset"
				);
			}
		}
		$monthlyScores = array();
		while (($row = qa_db_read_one_assoc($qMonth, true)) !== null) {
			$monthlyScores[] = $row;
		}
		$hasNextPage = count($monthlyScores) > $maxusers;
		if ($hasNextPage) array_pop($monthlyScores);

		// ── Weekly sections – all weeks whose Monday falls in the chosen month ─
		$weekSections = array();
		if ($weeklyEnabled) {
			// R3: true current Monday — date('N') = 1 (Mon) … 7 (Sun)
			$pdow    = (int)date('N');
			$trueMon = $isCurrentMonth ? date('Y-m-d', strtotime('-' . ($pdow - 1) . ' days')) : null;

			// Fetch snapshot dates in a window around the month
			$rangeStart = date('Y-m-d', strtotime($intervalStart . ' -7 days'));
			$rangeEnd   = date('Y-m-d', strtotime($intervalEnd   . ' +7 days'));
			$qSnap = qa_db_query_sub(
				"SELECT DISTINCT `date` FROM `^userscores_weekly`
				 WHERE `date` >= '$rangeStart' AND `date` <= '$rangeEnd'
				 ORDER BY `date` ASC"
			);
			$snapDates = array();
			while (($row = qa_db_read_one_assoc($qSnap, true)) !== null) {
				$snapDates[] = $row['date'];
			}

			$wnum = 0;
			$cnt  = count($snapDates);
			for ($i = 0; $i < $cnt; $i++) {
				$wStart = $snapDates[$i];
				if ($wStart < $intervalStart || $wStart >= $intervalEnd) continue;
				$wnum++;
				$wEnd   = isset($snapDates[$i + 1]) ? $snapDates[$i + 1] : null;
				$wEndDt = date('Y-m-d', strtotime($wStart . ' +6 days'));
				$wLabel = date('M j', strtotime($wStart)) . ' &ndash; ' . date('M j', strtotime($wEndDt));

				// R3: distinguish truly-ongoing vs stale-last-snapshot (scheduler missed a Monday)
				$isCurrent = ($wEnd === null && $trueMon !== null && $wStart === $trueMon);
				$isMissed  = ($wEnd === null && (!$isCurrent)); // last snap but NOT this week's Mon

				if ($isCurrent || $isMissed) {
					// Live delta from this snapshot to now (may span >1 week if missed)
					$qWeek = qa_db_query_sub(
						"SELECT up.userid, up.points - COALESCE(ws.points, 0) AS wpoints
						 FROM `^userpoints` up
						 LEFT JOIN `^userscores_weekly` ws ON up.userid = ws.userid AND ws.date = '$wStart'
						 JOIN `^users` u ON u.userid = up.userid
						 WHERE u.loggedin > NOW() - interval $activedays day
						   AND up.userid $excl
						 HAVING wpoints > 0
						 ORDER BY wpoints DESC
						 LIMIT $wMaxusers"
					);
				} else {
					// Past week: clean delta between two consecutive snapshots
					$qWeek = qa_db_query_sub(
						"SELECT ws1.userid, ws1.points - COALESCE(ws2.points, 0) AS wpoints
						 FROM `^userscores_weekly` ws1
						 LEFT JOIN `^userscores_weekly` ws2
						   ON ws1.userid = ws2.userid AND ws2.date = '$wStart'
						 WHERE ws1.date = '$wEnd' AND ws1.userid $excl
						 HAVING wpoints > 0
						 ORDER BY wpoints DESC
						 LIMIT $wMaxusers"
					);
				}
				$wScores = array();
				while (($row = qa_db_read_one_assoc($qWeek, true)) !== null) {
					$wScores[] = $row;
				}
				$weekSections[] = array(
					'num'     => $wnum,
					'label'   => $wLabel,
					'current' => $isCurrent,
					'missed'  => $isMissed,
					'scores'  => $wScores,
				);
			}

			// R3: if the true current Monday is in this month but has no snapshot at all,
			// add a placeholder card so the admin knows data is missing for this week.
			if ($trueMon !== null && $trueMon >= $intervalStart && $trueMon < $intervalEnd
					&& !in_array($trueMon, $snapDates)) {
				$wnum++;
				$wEndDt = date('Y-m-d', strtotime($trueMon . ' +6 days'));
				$weekSections[] = array(
					'num'    => $wnum,
					'label'  => date('M j', strtotime($trueMon)) . ' &ndash; ' . date('M j', strtotime($wEndDt)),
					'current' => false,
					'missed'  => true,
					'nosnap'  => true,
					'scores'  => array(),
				);
			}
		}

		// ── Batch-fetch user accounts (deduplicated) ──────────────────────────
		$allIds = array();
		foreach ($monthlyScores as $s) $allIds[] = $s['userid'];
		foreach ($weekSections   as $ws) {
			foreach ($ws['scores'] as $s) $allIds[] = $s['userid'];
		}
		$userCache = array();
		foreach (array_unique($allIds) as $uid) {
			$userCache[$uid] = qa_db_select_with_pending(qa_db_user_account_selectspec((int)$uid, true));
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
				$html .= '<p class="tupm-empty">No weekly snapshot data available for this month.</p>';
			} else {
				$html .= '<div class="tupm-weeks-grid">';
				foreach ($weekSections as $ws) {
					if ($ws['current']) {
						$tcls      = 'tupm-week-title cur';
						$curSuffix = ' <small style="font-weight:400;opacity:.8">(ongoing)</small>';
					} elseif (!empty($ws['nosnap'])) {
						// R3: true current week but scheduler never took the Monday snapshot
						$tcls      = 'tupm-week-title missed';
						$curSuffix = ' <small>&#9888; snapshot missing</small>';
					} elseif (!empty($ws['missed'])) {
						// R3: last known snapshot but scheduler missed the next Monday
						$tcls      = 'tupm-week-title missed';
						$curSuffix = ' <small>&#9888; spans multiple weeks (next snapshot missed)</small>';
					} else {
						$tcls      = 'tupm-week-title';
						$curSuffix = '';
					}
					$body = !empty($ws['nosnap'])
						? '<p class="tupm-week-empty">No baseline snapshot &mdash; use Admin &rarr; Take Weekly Snapshot to fix.</p>'
						: $this->tupm_rank_list($ws['scores'], 'wpoints', $hidepoints, $pointsLang, $userCache, $avatarSize, true);
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
