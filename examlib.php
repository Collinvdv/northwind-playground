<?php
/**
 * Shared code of the graded exams (exampage.php, behind exam-groupby.php and
 * exam-innerjoin.php) and their teacher page
 * (exam-admin.php). Require after lib.php.
 *
 * MySQL is read-only, so submissions are JSON files in exam-data/ (blocked
 * by exam-data/.htaccess and .ddev/nginx/deny-private.conf): one file per
 * student, exam and day, "<set>-<YYYY-MM-DD>-<student number>.json", so
 * every student only ever writes their own file and 150 students never wait
 * for each other. The exam is taken on several days: each day is a separate
 * session (a student who comes back another day starts over), and the
 * teacher page shows one day at a time. See examDay().
 *
 * A student record:
 *   id, email, firstName, lastName, name, started, last (activity), submitted (null or time),
 *   tokenHash   the login of one browser (see examLogin()); null = free
 *   answers[q]  sql (latest editor text, autosaved), savedAt,
 *               checkedSql, status, message, at, attempts, everCorrect (last Check),
 *               override {points, note, at} (set by the teacher)
 *   history[]   every Check: q, at, status, message, sql, final
 */

declare(strict_types=1);

const EXAM_DATA_DIR = __DIR__ . '/exam-data';
const EXAM_NAME_MAX = 40;                 // first name, last name: each
const EXAM_EMAIL_PATTERN = '/^(r\d{7})@student\.thomasmore\.be$/';
const EXAM_EMAIL_HTML_PATTERN = '[rR][0-9]{7}@student\.thomasmore\.be';
const EXAM_LOGIN_TTL = 43200;             // seconds the login cookie lives (12 h)
const EXAM_DEFAULT_POINTS = 10;           // per assignment, unless the exam set says otherwise
const EXAM_TIMEZONE = 'Europe/Brussels';  // times in the files and on the pages

date_default_timezone_set(EXAM_TIMEZONE);

/** Start the PHP session (teacher page only) with a cookie that scripts cannot read. */
function examSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nw_exam');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);
}

/** One exam from exam-sets/<slug>.php, with exercise numbers and defaults like loadChapters(). */
function loadExamSet(string $slug): array
{
    $exam = require __DIR__ . '/exam-sets/' . basename($slug) . '.php';
    $exam['slug'] = basename($slug);
    foreach ($exam['exercises'] as $i => &$ex) {
        $ex['number'] = $i + 1;
        $ex['type'] = $ex['type'] ?? 'sql';     // 'erd': a design assignment (checkErdExercise)
        $ex['ordered'] = $ex['ordered'] ?? false;
        $ex['columns'] = $ex['columns'] ?? false;
        $ex['points'] = $ex['points'] ?? EXAM_DEFAULT_POINTS;
    }
    unset($ex);
    return $exam;
}

/** A first or last name as stored: trimmed, single spaces, at most EXAM_NAME_MAX characters. */
function examCleanName(string $name): string
{
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));
    return mb_substr($name, 0, EXAM_NAME_MAX);
}

/** The student number ("r1095457") of a school e-mail address, or null when the format is wrong. */
function examStudentId(string $email): ?string
{
    return preg_match(EXAM_EMAIL_PATTERN, strtolower(trim($email)), $m) ? $m[1] : null;
}

/**
 * The exam day the storage functions work on: today, unless set. The
 * student page sets it from the login cookie (an exam that runs past
 * midnight stays on its day), the teacher page from the day picker.
 */
function examDay(?string $day = null): string
{
    static $current = null;
    if ($day !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $current = $day;
    }
    return $current ?? date('Y-m-d');
}

function examFile(string $set, string $id, ?string $day = null): string
{
    return EXAM_DATA_DIR . '/' . basename($set) . '-' . ($day ?? examDay()) . '-' . preg_replace('/[^a-z0-9]/', '', $id) . '.json';
}

/** Days with submissions, newest first: ['2026-10-28' => number of students]. */
function examDays(string $set): array
{
    $days = [];
    foreach (glob(EXAM_DATA_DIR . '/' . basename($set) . '-*.json') ?: [] as $file) {
        if (preg_match('/-(\d{4}-\d{2}-\d{2})-r\d{7}\.json$/', $file, $m)) {
            $days[$m[1]] = ($days[$m[1]] ?? 0) + 1;
        }
    }
    krsort($days);
    return $days;
}

/**
 * Files from before exams were stored per day ("<set>-<id>.json") move to the
 * day they were started, with their log. A file whose new name is taken stays.
 */
function examMigrateLegacy(string $set): void
{
    foreach (glob(EXAM_DATA_DIR . '/' . basename($set) . '-r*.json') ?: [] as $file) {
        if (!preg_match('/-(r\d{7})\.json$/', $file, $m)) {
            continue;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            continue;             // unreadable: leave it for the teacher
        }
        $day = date('Y-m-d', strtotime($data['started'] ?? $data['last'] ?? 'now'));
        $target = examFile($set, $m[1], $day);
        if (file_exists($target) || !@rename($file, $target)) {
            continue;
        }
        $oldLog = EXAM_DATA_DIR . '/log/' . basename($file) . 'l';
        if (is_file($oldLog)) {
            @rename($oldLog, EXAM_DATA_DIR . '/log/' . basename($target) . 'l');
        }
        @unlink(substr($file, 0, -strlen('.json')) . '.lock');
    }
}

/** A student's record, or null when they never logged in. */
function examLoad(string $set, string $id): ?array
{
    $file = examFile($set, $id);
    if (!is_readable($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/** The lock file of a student's record; the record itself is replaced on every write. */
function examLockFile(string $set, string $id): string
{
    return substr(examFile($set, $id), 0, -strlen('.json')) . '.lock';
}

/** Append-only log of every text a student's answers had: exam-data/log/<set>-<id>.jsonl */
function examLogFile(string $set, string $id): string
{
    return EXAM_DATA_DIR . '/log/' . basename(examFile($set, $id)) . 'l';
}

/** A folder inside exam-data/, created when needed. */
function examDir(string $name): string
{
    $dir = EXAM_DATA_DIR . '/' . $name;
    if (!is_dir($dir) && !@mkdir($dir, 0775) && !is_dir($dir)) {
        throw new RuntimeException('exam-data/ is not writable.');
    }
    return $dir;
}

/**
 * Read-modify-write a student's record under an exclusive lock. $change gets
 * the record (a fresh, empty one for a new student) and returns it.
 * Never call this from inside $change: a second lock on the same file waits forever.
 *
 * Answers must never get lost, so:
 * - the lock is a separate .lock file and the record is written to a temp
 *   file that then replaces it (rename): a reader always sees the whole old
 *   or the whole new record, and a failed write leaves the old one;
 * - a record that cannot be read is never replaced by an empty one: it is
 *   copied to exam-data/broken/ and the save fails;
 * - every changed editor text also goes into the student's log ($why says
 *   by what), from which the teacher can restore it.
 */
function examUpdate(string $set, string $id, callable $change, string $why = 'save'): array
{
    $file = examFile($set, $id);
    $lock = @fopen(examLockFile($set, $id), 'c');
    if ($lock === false) {
        throw new RuntimeException('exam-data/ is not writable.');
    }
    try {
        flock($lock, LOCK_EX);
        $raw = is_file($file) ? file_get_contents($file) : '';
        if ($raw === false) {
            throw new RuntimeException('Cannot read ' . basename($file) . '.');
        }
        $data = $raw === '' ? null : json_decode($raw, true);
        if ($raw !== '' && !is_array($data)) {
            @copy($file, examDir('broken') . '/' . basename($file, '.json') . '-' . date('Ymd-His') . '.json');
            throw new RuntimeException(basename($file) . ' cannot be read, a copy is in exam-data/broken/.');
        }
        $old = $data ?? ['id' => $id, 'started' => date('c'), 'submitted' => null, 'tokenHash' => null, 'answers' => [], 'history' => []];
        $data = $change($old);
        $data['last'] = date('c');

        $log = '';
        foreach ($data['answers'] ?? [] as $q => $a) {
            if (array_key_exists('sql', $a) && $a['sql'] !== ($old['answers'][$q]['sql'] ?? null)) {
                $log .= json_encode(['at' => $data['last'], 'q' => (int) $q, 'why' => $why, 'sql' => $a['sql']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            }
        }
        if ($log !== '') {
            examDir('log');
            file_put_contents(examLogFile($set, $id), $log, FILE_APPEND | LOCK_EX);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if ($json === false || @file_put_contents($tmp, $json) !== strlen($json) || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot write ' . basename($file) . ' (disk full?).');
        }
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** The student's log, oldest first: [{at, q, why, sql}]. */
function examLog(string $set, string $id): array
{
    $lines = is_readable(examLogFile($set, $id)) ? file(examLogFile($set, $id), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    return array_values(array_filter(array_map(fn($l) => json_decode($l, true), $lines ?: []), 'is_array'));
}

// ---------------------------------------------------------------------------
// Exam settings (set by the teacher on exam-admin.php)
// ---------------------------------------------------------------------------

/** Not "<set>-*.json": examAllStudents() would read it as a student. */
function examSettingsFile(string $set): string
{
    return EXAM_DATA_DIR . '/settings-' . basename($set) . '.json';
}

/**
 * What the teacher saved, else the exam set's defaults ('password' in
 * exam-sets/<set>.php). Empty password: nobody can log in.
 */
function examSettings(string $set): array
{
    $data = is_readable(examSettingsFile($set)) ? json_decode((string) file_get_contents(examSettingsFile($set)), true) : null;
    return (is_array($data) ? $data : []) + ['password' => (string) (loadExamSet($set)['password'] ?? '')];
}

function examSaveSettings(string $set, array $settings): void
{
    if (@file_put_contents(examSettingsFile($set), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('exam-data/ is not writable.');
    }
}

// ---------------------------------------------------------------------------
// Login: one browser per student
// ---------------------------------------------------------------------------

/**
 * The login is a cookie "<id>.<token>.<day>"; the record keeps a hash of the token.
 * The cookie outlives a closed browser (EXAM_LOGIN_TTL), so the student just
 * continues. Another computer cannot take over a started exam with the same
 * e-mail address: the teacher frees the login first ("Allow a new login").
 */
function examCookieName(string $set): string
{
    return 'nw_exam_' . preg_replace('/[^a-z0-9]/', '_', $set);
}

function examSetCookie(string $set, string $value, int $expires): void
{
    setcookie(examCookieName($set), $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** [id, token] from this browser's cookie, or null. */
function examCookie(string $set): ?array
{
    $parts = explode('.', (string) ($_COOKIE[examCookieName($set)] ?? ''), 3);
    $parts[2] ??= date('Y-m-d');  // cookie from before exams were stored per day
    return count($parts) === 3 && preg_match('/^r\d{7}$/', $parts[0]) && preg_match('/^[a-f0-9]{32}$/', $parts[1])
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[2]) ? $parts : null;
}

/** The logged-in student's record, or null. */
function examCurrentStudent(string $set): ?array
{
    $cookie = examCookie($set);
    if ($cookie === null) {
        return null;
    }
    examDay($cookie[2]);
    $record = examLoad($set, $cookie[0]);
    $hash = $record['tokenHash'] ?? null;
    return is_string($hash) && hash_equals($hash, hash('sha256', $cookie[1])) ? $record : null;
}

/**
 * Log in (first time or again). Returns the record, or an error message for the form.
 * The same browser may always log in again; another one only while the login is free.
 * Every login needs the exam password the teacher set; without one the exam is closed.
 */
function examLogin(string $set, string $first, string $last, string $email, string $password = ''): array|string
{
    $expected = examSettings($set)['password'];
    if ($expected === '') {
        return 'The exam is not open yet. Wait until your teacher gives the exam password.';
    }
    if (!hash_equals($expected, trim($password))) {
        sleep(1);                 // slow down guessing
        return 'Wrong exam password. Your teacher gives it at the start of the exam.';
    }
    $first = examCleanName($first);
    $last = examCleanName($last);
    $id = examStudentId($email);
    $namePattern = "/^\p{L}[\p{L}\p{M} '’.-]*$/u";
    if (!preg_match($namePattern, $first) || !preg_match($namePattern, $last)) {
        return 'Type your first and last name (letters only).';
    }
    if ($id === null) {
        return 'Type your school e-mail address: r + 7 digits + @student.thomasmore.be, for example r1095457@student.thomasmore.be.';
    }
    $cookie = examCookie($set);
    $token = $cookie !== null && $cookie[0] === $id ? $cookie[1] : bin2hex(random_bytes(16));
    $refused = false;
    $day = examDay(date('Y-m-d'));   // a login always starts or continues today's exam
    $record = examUpdate($set, $id, function (array $d) use ($first, $last, $id, $token, &$refused) {
        if (($d['tokenHash'] ?? null) !== null && !hash_equals($d['tokenHash'], hash('sha256', $token))) {
            $refused = true;
            return $d;
        }
        $d['tokenHash'] = hash('sha256', $token);
        $d['firstName'] = $first;
        $d['lastName'] = $last;
        $d['name'] = $first . ' ' . $last;
        $d['email'] = $id . '@student.thomasmore.be';
        return $d;
    });
    if ($refused) {
        return 'The exam of ' . $id . ' was already started in another browser. Ask your teacher to allow a new login.';
    }
    examSetCookie($set, $id . '.' . $token . '.' . $day, time() + EXAM_LOGIN_TTL);
    return $record;
}

/** Log out: forget the cookie and free the login, so the student can log in elsewhere. */
function examLogout(string $set): void
{
    $student = examCurrentStudent($set);
    if ($student !== null) {
        examUpdate($set, $student['id'], fn(array $d) => ['tokenHash' => null] + $d);
    }
    examSetCookie($set, '', time() - 3600);
}

// ---------------------------------------------------------------------------
// Answers
// ---------------------------------------------------------------------------

/**
 * The latest editor text of one assignment (not checked). $why: 'autosave'
 * (timer or leaving the page), 'run', 'submit'. An autosave never replaces
 * a stored text with an empty one: an editor that came up empty must not
 * wipe the answer. Check, Run and Submit store what the student sends.
 */
function examSaveText(string $set, string $id, int $q, string $sql, string $why = 'save'): array
{
    return examUpdate($set, $id, function (array $d) use ($q, $sql, $why) {
        if ($why === 'autosave' && trim($sql) === '' && trim((string) ($d['answers'][$q]['sql'] ?? '')) !== '') {
            return $d;
        }
        $d['answers'][$q]['sql'] = $sql;
        $d['answers'][$q]['savedAt'] = date('c');
        return $d;
    }, $why);
}

/** Put one Check into a record: the last one per assignment counts, every one goes into the history. */
function examApplyCheck(array $d, int $q, string $sql, array $check, bool $final = false): array
{
    $now = date('c');
    $prev = $d['answers'][$q] ?? [];
    $d['answers'][$q] = [
        'sql' => $sql,
        'savedAt' => $now,
        'checkedSql' => $sql,
        'status' => $check['status'],
        'message' => $check['message'],
        'at' => $now,
        'attempts' => ($prev['attempts'] ?? 0) + ($final ? 0 : 1),
        // Once solved, a later wrong attempt still shows on the teacher page.
        'everCorrect' => ($prev['everCorrect'] ?? false) || $check['status'] === 'correct',
    ] + $prev;
    $d['history'][] = ['q' => $q, 'at' => $now, 'status' => $check['status'], 'message' => $check['message'], 'sql' => $sql, 'final' => $final];
    return $d;
}

function examRecordAttempt(string $set, string $id, int $q, string $sql, array $check): array
{
    return examUpdate($set, $id, fn(array $d) => examApplyCheck($d, $q, $sql, $check), 'check');
}

/**
 * Final submit (by the student, or by the teacher for everyone still open):
 * every assignment whose latest text was not checked yet is checked now, so
 * what was in the editor is the answer. Then the exam is locked.
 */
function examFinalize(string $set, string $id, array $exam, PDO $pdo): array
{
    $record = examLoad($set, $id) ?? [];
    $checks = [];
    foreach ($exam['exercises'] as $ex) {
        $a = $record['answers'][$ex['number']] ?? [];
        $sql = (string) ($a['sql'] ?? '');
        if (trim($sql) !== '' && ($a['checkedSql'] ?? null) !== $sql) {
            $checks[$ex['number']] = [$sql, checkAnswer($pdo, $ex, $sql)];
        }
    }
    return examUpdate($set, $id, function (array $d) use ($checks) {
        foreach ($checks as $q => [$sql, $check]) {
            $d = examApplyCheck($d, $q, $sql, $check, true);
        }
        $d['submitted'] = $d['submitted'] ?? date('c');
        return $d;
    }, 'submit');
}

// ---------------------------------------------------------------------------
// Teacher
// ---------------------------------------------------------------------------

/**
 * Remove a student's submission (teacher). The file moves to exam-data/deleted/
 * with the time in its name, so a mistake can be undone by moving it back.
 * The student's login stops working; logging in again starts an empty exam.
 */
function examDelete(string $set, string $id): void
{
    $file = examFile($set, $id);
    $dir = examDir('deleted');
    $lock = @fopen(examLockFile($set, $id), 'c');
    if ($lock === false) {
        throw new RuntimeException('exam-data/ is not writable.');
    }
    try {
        flock($lock, LOCK_EX);    // wait for a save of this student that is still running
        if (is_file($file)) {
            rename($file, $dir . '/' . basename($file, '.json') . '-' . date('Ymd-His') . '.json');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Every student of one exam on examDay(), sorted by last name, then first name. */
function examAllStudents(string $set): array
{
    $students = [];
    foreach (glob(EXAM_DATA_DIR . '/' . basename($set) . '-' . examDay() . '-r*.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data) && isset($data['id'], $data['name'])) {
            $students[] = $data;
        }
    }
    usort($students, fn($a, $b) => strcasecmp(($a['lastName'] ?? '') . ' ' . $a['name'], ($b['lastName'] ?? '') . ' ' . $b['name']));
    return $students;
}

/** Teacher's points for one assignment (null clears the override). */
function examSetOverride(string $set, string $id, int $q, ?float $points, string $note): array
{
    return examUpdate($set, $id, function (array $d) use ($q, $points, $note) {
        if ($points === null) {
            unset($d['answers'][$q]['override']);
        } else {
            $d['answers'][$q]['override'] = ['points' => $points, 'note' => $note, 'at' => date('c')];
        }
        return $d;
    });
}

/**
 * Points of one assignment: the teacher's override, otherwise all points when
 * the last Check is correct and 0 otherwise. ['points', 'auto', 'max', 'override' => bool]
 */
function examPoints(array $student, array $exercise): array
{
    $a = $student['answers'][$exercise['number']] ?? [];
    $auto = ($a['status'] ?? '') === 'correct' ? (float) $exercise['points'] : 0.0;
    $override = isset($a['override']['points']);
    return ['points' => $override ? (float) $a['override']['points'] : $auto, 'auto' => $auto, 'max' => $exercise['points'], 'override' => $override];
}

/** Total points and maximum of a student. */
function examTotal(array $student, array $exam): array
{
    $total = 0.0;
    $max = 0;
    foreach ($exam['exercises'] as $ex) {
        $p = examPoints($student, $ex);
        $total += $p['points'];
        $max += $p['max'];
    }
    return [$total, $max];
}

/** Number of assignments whose last checked query is correct. */
function examScore(array $student): int
{
    return count(array_filter($student['answers'] ?? [], fn($a) => ($a['status'] ?? '') === 'correct'));
}

/** True when the latest editor text differs from the last checked query. */
function examEditedAfterCheck(?array $answer): bool
{
    return $answer !== null && trim((string) ($answer['sql'] ?? '')) !== '' && ($answer['sql'] ?? '') !== ($answer['checkedSql'] ?? null);
}

/** 7.5 → "7.5", 10.0 → "10". */
function examNum(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

/** HH:MM:SS of an ISO date, for tables. */
function examTime(?string $iso): string
{
    return $iso ? date('H:i:s', strtotime($iso)) : '';
}
