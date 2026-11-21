<?php

/**
 * Period (week/month) points derived from the dated rows behind each ^userpoints column,
 * so no point snapshots are needed. Results are cached in ^tupm_cache.
 */
class qa_tupm_points
{
	const TZ = 'Asia/Kolkata';      // period boundaries are local midnights
	const TTL = 900;                // seconds a live period stays cached
	const KEEP = 500;               // users kept per cached period (enough for paging and filters)
	const KEEP_DAYS = 70;           // per-day totals kept (covers the current and previous month)

	// pupi-pc history formula ids for components whose source rows carry no date
	const F_ASELECTS = 3, F_ASELECTEDS = 4, F_REACTS = 3000, F_ACTIVEDAY = 5000;

	public static function monthStart($ts = null)
	{
		$d = new DateTime('@' . ($ts ?? time()));
		$d->setTimezone(new DateTimeZone(self::TZ));
		return $d->format('Y-m-01');
	}

	public static function weekStart($ts = null)
	{
		$d = new DateTime('@' . ($ts ?? time()));
		$d->setTimezone(new DateTimeZone(self::TZ));
		$d->modify('-' . ((int)$d->format('N') - 1) . ' days');
		return $d->format('Y-m-d');
	}

	/** Local date (Y-m-d, midnight in TZ) to the UTC datetime string the database uses. */
	private static function utc($localDate)
	{
		$d = new DateTime($localDate . ' 00:00:00', new DateTimeZone(self::TZ));
		$d->setTimezone(new DateTimeZone('UTC'));
		return $d->format('Y-m-d H:i:s');
	}

	/**
	 * Ranked [[userid, points], ...] for the period starting on $start ('month' or 'week'), best first.
	 * Past periods come from ^monthlytoppers / ^weeklytoppers (frozen there on first use).
	 * The current period = cached per-day totals for completed days + a live query for today.
	 */
	public static function scores($kind, $start)
	{
		$end = self::periodEnd($kind, $start);
		$table = $kind === 'month' ? 'monthlytoppers' : 'weeklytoppers';
		$isPast = self::utc($end) <= gmdate('Y-m-d H:i:s');

		if ($isPast) {
			$rows = self::frozen($table, $start);
			if ($rows) {
				return $rows;
			}
		}

		$key = $kind . ':' . $start;
		$row = self::cacheGet($key);
		if ($row && time() - (int)$row['t'] < self::TTL) {
			return json_decode($row['data'], true);
		}

		// One request recomputes (and fills per-day totals); others keep serving the stale copy meanwhile
		$lock = qa_db_add_table_prefix('tupm');
		$got = (int)qa_db_read_one_value(qa_db_query_sub('SELECT GET_LOCK($, #)', $lock, $row ? 0 : 20), true);
		if (!$got) {
			return $row ? json_decode($row['data'], true) : [];
		}
		try {
			$fresh = self::cacheGet($key);
			if ($fresh && time() - (int)$fresh['t'] < self::TTL) {
				return json_decode($fresh['data'], true);
			}
			if ($isPast) {
				$scores = self::finished($start, $end);
				self::freeze($table, $start, $scores);
			} else {
				$today = self::today();
				$points = self::daySums($start, $today);
				foreach (self::compute(self::utc($today), gmdate('Y-m-d H:i:s', time() + 60), false) as [$uid, $pts]) {
					$points[$uid] = ($points[$uid] ?? 0) + $pts;
				}
				$scores = self::rank($points);
				$prev = date('Y-m-d', strtotime($start . ($kind === 'month' ? ' -1 month' : ' -7 days')));
				if (!self::frozen($table, $prev)) {
					self::freeze($table, $prev, self::finished($prev, $start));
				}
			}
			$top = array_slice($scores, 0, self::KEEP);
			qa_db_query_sub('REPLACE INTO ^tupm_cache (ckey, computed, data) VALUES ($, NOW(), $)', $key, json_encode($top));
			return $top;
		} finally {
			qa_db_query_sub('SELECT RELEASE_LOCK($)', $lock);
		}
	}

	public static function periodEnd($kind, $start)
	{
		return date('Y-m-d', strtotime($start . ($kind === 'month' ? ' +1 month' : ' +7 days')));
	}

	/** Drop all cached rankings and per-day totals; they are rebuilt from the source tables on demand. */
	public static function clearCache()
	{
		qa_db_query_sub('DELETE FROM ^tupm_cache');
		qa_db_query_sub('DELETE FROM ^tupm_daily');
	}

	private static function cacheGet($key)
	{
		return qa_db_read_one_assoc(qa_db_query_sub('SELECT UNIX_TIMESTAMP(computed) t, data FROM ^tupm_cache WHERE ckey = $', $key), true);
	}

	private static function today()
	{
		return (new DateTime('now', new DateTimeZone(self::TZ)))->format('Y-m-d');
	}

	/** Final ranking of a finished period: from day totals while they are kept, else one full query. */
	private static function finished($start, $end)
	{
		if ($start >= date('Y-m-d', strtotime('-' . self::KEEP_DAYS . ' days'))) {
			return self::rank(self::daySums($start, $end));
		}
		return self::compute(self::utc($start), self::utc($end));
	}

	/** userid => points summed over completed days in [$from, $to), computing any day not yet cached. */
	private static function daySums($from, $to)
	{
		$done = array_flip(qa_db_read_all_values(qa_db_query_sub(
			'SELECT SUBSTRING(ckey, 5) FROM ^tupm_cache WHERE ckey >= $ AND ckey < $', 'day:' . $from, 'day:' . $to
		)));
		for ($d = $from; $d < $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
			if (isset($done[$d])) {
				continue;
			}
			$next = date('Y-m-d', strtotime($d . ' +1 day'));
			qa_db_query_sub('DELETE FROM ^tupm_daily WHERE day = $', $d);
			// Day totals keep negatives so period sums stay exact
			foreach (array_chunk(self::compute(self::utc($d), self::utc($next), false), 1000) as $chunk) {
				$values = [];
				foreach ($chunk as [$uid, $pts]) {
					$values[] = "('" . $d . "', " . (int)$uid . ', ' . (int)$pts . ')';
				}
				qa_db_query_sub('INSERT INTO ^tupm_daily (day, userid, points) VALUES ' . implode(',', $values));
			}
			qa_db_query_sub("REPLACE INTO ^tupm_cache (ckey, computed, data) VALUES ($, NOW(), '')", 'day:' . $d);
		}
		$old = date('Y-m-d', strtotime('-' . self::KEEP_DAYS . ' days'));
		qa_db_query_sub('DELETE FROM ^tupm_daily WHERE day < $', $old);
		qa_db_query_sub("DELETE FROM ^tupm_cache WHERE ckey LIKE 'day:%' AND ckey < $", 'day:' . $old);

		$points = [];
		foreach (qa_db_read_all_assoc(qa_db_query_sub('SELECT userid, SUM(points) p FROM ^tupm_daily WHERE day >= $ AND day < $ GROUP BY userid', $from, $to)) as $r) {
			$points[$r['userid']] = (int)$r['p'];
		}
		return $points;
	}

	private static function rank($points, $positiveOnly = true)
	{
		$points = array_filter($points, function ($p) use ($positiveOnly) {
			return $positiveOnly ? $p > 0 : $p != 0;
		});
		arsort($points);
		$out = [];
		foreach ($points as $uid => $pts) {
			$out[] = [(int)$uid, (int)$pts];
		}
		return $out;
	}

	private static function frozen($table, $start)
	{
		$rows = [];
		foreach (qa_db_read_all_assoc(qa_db_query_sub("SELECT userid, points FROM ^$table WHERE `date` = $ AND points > 0 ORDER BY points DESC, userid DESC LIMIT #", $start, self::KEEP)) as $r) {
			$rows[] = [(int)$r['userid'], (int)$r['points']];
		}
		return $rows;
	}

	private static function freeze($table, $start, $scores)
	{
		if (!$scores || qa_db_read_one_value(qa_db_query_sub("SELECT COUNT(*) FROM ^$table WHERE `date` = $", $start))) {
			return;
		}
		foreach (array_chunk($scores, 1000) as $chunk) {
			$values = [];
			foreach ($chunk as [$uid, $pts]) {
				$values[] = "('" . $start . "', " . (int)$uid . ', ' . (int)$pts . ')';
			}
			qa_db_query_sub("INSERT IGNORE INTO ^$table (`date`, userid, points) VALUES " . implode(',', $values));
		}
	}

	private static function tableExists($name)
	{
		static $tables = null;
		if ($tables === null) {
			$tables = array_flip(qa_db_read_all_values(qa_db_query_sub('SHOW TABLES')));
		}
		return isset($tables[qa_db_add_table_prefix($name)]);
	}

	/**
	 * Points earned in [$s, $e) (UTC datetimes) per user as [[userid, points], ...], best first.
	 * Bonus points are not dated and are left out.
	 */
	public static function compute($s, $e, $positiveOnly = true)
	{
		require_once QA_INCLUDE_DIR . 'db/points.php';
		$m = [];
		foreach (qa_db_points_calculations() as $field => $calc) {
			$m[$field] = (int)$calc['multiple'];
		}
		$mul = function ($f) use ($m) {
			return $m[$f] ?? 0;
		};
		$points = [];
		$add = function ($rows, $multiple) use (&$points) {
			if (!$multiple) {
				return;
			}
			foreach ($rows as $r) {
				$points[$r['u']] = ($points[$r['u']] ?? 0) + $multiple * (int)$r['n'];
			}
		};

		// Questions, answers, comments posted
		$typeField = ['Q' => 'qposts', 'A' => 'aposts', 'C' => 'cposts'];
		$types = array_keys(array_filter($typeField, $mul));
		if ($types) {
			$rows = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT userid u, type t, COUNT(*) n FROM ^posts WHERE type IN ($) AND created >= $ AND created < $ AND userid IS NOT NULL GROUP BY userid, type',
				$types, $s, $e
			));
			foreach ($rows as $r) {
				$add([$r], $mul($typeField[$r['t']]));
			}
		}

		// Votes given, by the voter
		$givenField = ['Q' => ['qupvotes', 'qdownvotes'], 'A' => ['aupvotes', 'adownvotes'], 'C' => ['cupvotes', 'cdownvotes']];
		$anyGiven = false;
		foreach ($givenField as $pair) {
			$anyGiven = $anyGiven || $mul($pair[0]) || $mul($pair[1]);
		}
		if ($anyGiven) {
			$rows = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT v.userid u, LEFT(p.type, 1) t, SUM(v.vote > 0) up, SUM(v.vote < 0) down FROM ^uservotes v JOIN ^posts p ON p.postid = v.postid
				 WHERE v.votecreated >= $ AND v.votecreated < $ AND v.vote <> 0 GROUP BY v.userid, t',
				$s, $e
			));
			foreach ($rows as $r) {
				if (isset($givenField[$r['t']])) {
					$add([['u' => $r['u'], 'n' => $r['up']]], $mul($givenField[$r['t']][0]));
					$add([['u' => $r['u'], 'n' => $r['down']]], $mul($givenField[$r['t']][1]));
				}
			}
		}

		// Votes received: per post, capped points at period end minus capped points at period start.
		// Counts at a past time = current post counts minus votes created since; changed votes count from creation.
		$votedField = ['Q' => ['qvoteds', 'q'], 'A' => ['avoteds', 'a'], 'C' => ['cvoteds', 'c']];
		if ($mul('qvoteds') || $mul('avoteds') || $mul('cvoteds') || $mul('upvoteds') || $mul('downvoteds')) {
			$rows = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT p.userid, LEFT(p.type, 1) t, p.upvotes - x.us ue, p.downvotes - x.ds de, p.upvotes - x.us - x.ui us, p.downvotes - x.ds - x.di ds FROM (
					SELECT postid, SUM(vote > 0 AND votecreated < $) ui, SUM(vote < 0 AND votecreated < $) di,
						SUM(vote > 0 AND votecreated >= $) us, SUM(vote < 0 AND votecreated >= $) ds
					FROM ^uservotes WHERE votecreated >= $ AND vote <> 0 GROUP BY postid
				) x JOIN ^posts p ON p.postid = x.postid WHERE p.userid IS NOT NULL AND (x.ui > 0 OR x.di > 0)',
				$e, $e, $e, $e, $s
			));
			$caps = [];
			foreach ($votedField as $t => [$field, $pt]) {
				$caps[$t] = [(int)qa_opt("points_per_{$pt}_voted_up"), (int)qa_opt("points_{$pt}_voted_max_gain"), (int)qa_opt("points_per_{$pt}_voted_down"), (int)qa_opt("points_{$pt}_voted_max_loss")];
			}
			foreach ($rows as $r) {
				if (!isset($caps[$r['t']])) {
					continue;
				}
				[$up, $upMax, $down, $downMax] = $caps[$r['t']];
				$capped = function ($u, $d) use ($up, $upMax, $down, $downMax) {
					return min($up * $u, $upMax) - min($down * $d, $downMax);
				};
				$uid = $r['userid'];
				$delta = $mul($votedField[$r['t']][0]) * ($capped($r['ue'], $r['de']) - $capped($r['us'], $r['ds']))
					+ $mul('upvoteds') * ($r['ue'] - $r['us']) + $mul('downvoteds') * ($r['de'] - $r['ds']);
				if ($delta) {
					$points[$uid] = ($points[$uid] ?? 0) + $delta;
				}
			}
		}

		// Accepted answers, active days and blog reactions have no dated source row; pupi-pc logs them
		if (self::tableExists('pupi_pc_history')) {
			$formulaField = [self::F_ASELECTS => 'aselects', self::F_ASELECTEDS => 'aselecteds', self::F_REACTS => 'pupi_ur_reacts', self::F_ACTIVEDAY => 'activedays'];
			$formulas = array_keys(array_filter($formulaField, $mul));
			if ($formulas) {
				$rows = qa_db_read_all_assoc(qa_db_query_sub(
					'SELECT user_id u, formula f, SUM(IF(formula = #, 1, point_sign)) n FROM ^pupi_pc_history
					 WHERE formula IN (#) AND event_time >= $ AND event_time < $ GROUP BY user_id, formula',
					self::F_ACTIVEDAY, $formulas, $s, $e
				));
				foreach ($rows as $r) {
					$add([$r], $mul($formulaField[$r['f']]));
				}
			}
		}

		// Edits: posts edited by the user for the first time in the period
		if ($mul('edits') && self::tableExists('edit_history')) {
			$add(qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT e.userid u, COUNT(DISTINCT e.postid) n FROM ^edit_history e
				 WHERE e.updated >= $ AND e.updated < $ AND e.userid IS NOT NULL
				   AND NOT EXISTS (SELECT 1 FROM ^edit_history p WHERE p.postid = e.postid AND p.userid = e.userid AND p.updated < $)
				 GROUP BY e.userid',
				$s, $e, $s
			)), $mul('edits'));
		}

		if ($mul('exams') && self::tableExists('exams')) {
			$add(qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT userid u, COUNT(*) n FROM ^exams WHERE created >= $ AND created < $ AND userid IS NOT NULL GROUP BY userid',
				$s, $e
			)), $mul('exams'));
		}

		// Exams taken: first attempt of an exam falls in the period
		if ($mul('examstaken') && self::tableExists('exam_results')) {
			$add(qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT r.userid u, COUNT(DISTINCT r.examid) n FROM ^exam_results r
				 WHERE r.datetime >= $ AND r.datetime < $ AND r.userid IS NOT NULL
				   AND NOT EXISTS (SELECT 1 FROM ^exam_results p FORCE INDEX (tupm_user_exam_time) WHERE p.userid = r.userid AND p.examid = r.examid AND p.datetime < $)
				 GROUP BY r.userid',
				$s, $e, $s
			)), $mul('examstaken'));
		}

		if ($mul('blogs') && self::tableExists('blogs')) {
			$add(qa_db_read_all_assoc(qa_db_query_sub(
				"SELECT userid u, COUNT(*) n FROM ^blogs WHERE type = 'B' AND created >= $ AND created < $ AND userid IS NOT NULL GROUP BY userid",
				$s, $e
			)), $mul('blogs'));
		}

		return self::rank($points, $positiveOnly);
	}

	/**
	 * One page of ranked users with display fields, after excluding blocked users,
	 * optionally admins and users not seen within $activeDays.
	 */
	public static function ranked($kind, $start, $limit, $offset = 0, $hideAdmin = false, $activeDays = 0)
	{
		$scores = self::scores($kind, $start);
		if (!$scores) {
			return [];
		}
		$out = [];
		$skipped = 0;
		// Filter in slices so only a few user rows are read per request
		foreach (array_chunk($scores, max(50, ($limit + $offset) * 2)) as $chunk) {
			$ids = array_column($chunk, 0);
			$users = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT userid, flags, level, email, handle, avatarblobid, avatarwidth, avatarheight, loggedin > NOW() - INTERVAL # DAY AS active FROM ^users WHERE userid IN (#)',
				max(1, (int)$activeDays), $ids
			), 'userid');
			foreach ($chunk as [$uid, $pts]) {
				$u = $users[$uid] ?? null;
				if (!$u || ($u['flags'] & QA_USER_FLAGS_USER_BLOCKED) || ($hideAdmin && $u['level'] >= QA_USER_LEVEL_ADMIN) || ($activeDays && !$u['active'])) {
					continue;
				}
				if ($skipped < $offset) {
					$skipped++;
					continue;
				}
				$u['points'] = $pts;
				$out[] = $u;
				if (count($out) >= $limit) {
					return $out;
				}
			}
		}
		return $out;
	}
}
