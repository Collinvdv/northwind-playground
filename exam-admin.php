<?php
/**
 * Teacher page of the graded exams (?set= picks one of exam-sets/): every student with the status and points
 * per assignment (hover a cell for the assignment and the submitted query),
 * a detail view that runs a student's answer again and shows its actual
 * result next to the expected one, points that overrule the automatic ones,
 * the history of every Check, and a CSV export.
 *
 * Protected by EXAM_ADMIN_PASS in .env; without it the page answers 403.
 *   exam-admin.php?set=<set>&day=<Y-m-d>               overview of one exam day (&refresh=1 reloads every 15 s)
 *   exam-admin.php?set=<set>&day=<Y-m-d>&s=<id>&q=<n>  one student, one assignment
 *   exam-admin.php?set=<set>&day=<Y-m-d>&csv=1         download that day's answers
 * Without day: today, or the last day with submissions.
 *   POST op=points|clear-points|allow-login|reopen|delete|restore|submit-all|exam-password
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/examlib.php';

// Which exam: ?set=<file in exam-sets/>, by default the first one.
$sets = array_map(fn(string $f) => basename($f, '.php'), glob(__DIR__ . '/exam-sets/*.php') ?: []);
if (!$sets) {
    notFound('Exam set');
}
$set = in_array($_GET['set'] ?? '', $sets, true) ? (string) $_GET['set'] : $sets[0];
$base = 'exam-admin.php?set=' . $set;
$exam = loadExamSet($set);
$count = count($exam['exercises']);

if ($config['EXAM_ADMIN_PASS'] === '') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Set EXAM_ADMIN_PASS in .env to use this page.\n");
}

examSessionStart();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$loginError = '';
if ($isPost && isset($_POST['logout'])) {
    unset($_SESSION['exam_admin']);
    header('Location: ' . $base, true, 303);
    exit;
}
if ($isPost && isset($_POST['password'])) {
    if (hash_equals($config['EXAM_ADMIN_PASS'], (string) $_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['exam_admin'] = true;
        header('Location: ' . $base, true, 303);
        exit;
    }
    sleep(1);                 // slow down guessing
    $loginError = 'Wrong password.';
}
$admin = !empty($_SESSION['exam_admin']);
session_write_close();

if (!$admin) {
    pageStart('Exam results', 'exam');
    ?>
  <form method="post" class="exam-login">
    <h1>Exam results</h1>
    <label for="password">Teacher password</label>
    <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
    <?php if ($loginError !== ''): ?><p class="verdict invalid"><?= h($loginError) ?></p><?php endif; ?>
    <button type="submit">Log in</button>
  </form>
<?php
    pageEnd();
    exit;
}

$labels = ['correct' => 'Correct', 'wrong' => 'Not yet', 'error' => 'Error', 'invalid' => 'Cannot check'];
$marks = ['correct' => '✓', 'wrong' => '✗', 'error' => '!', 'invalid' => '?'];
$id = preg_replace('/[^a-z0-9]/', '', (string) ($_REQUEST['s'] ?? ''));
$q = max(1, min($count, (int) ($_REQUEST['q'] ?? 1)));

// One exam day at a time (?day=YYYY-MM-DD): today, or the last day with submissions.
examMigrateLegacy($set);
$days = examDays($set);
$today = date('Y-m-d');
$day = examDay((string) ($_REQUEST['day'] ?? (isset($days[$today]) || !$days ? $today : array_key_first($days))));
$self = $base . '&day=' . $day;

// ---------------------------------------------------------------------------
// Teacher actions (POST, then back to the page they came from).
// ---------------------------------------------------------------------------
if ($isPost && isset($_POST['op'])) {
    $op = (string) $_POST['op'];
    $back = $id !== '' ? $self . '&s=' . $id . '&q=' . $q : $self;
    if ($id !== '' && examLoad($set, $id) !== null) {
        if ($op === 'points' && is_numeric($_POST['points'] ?? null)) {
            $max = (float) $exam['exercises'][$q - 1]['points'];
            $points = max(0.0, min($max, round((float) $_POST['points'] * 2) / 2));
            examSetOverride($set, $id, $q, $points, trim((string) ($_POST['note'] ?? '')));
        } elseif ($op === 'clear-points') {
            examSetOverride($set, $id, $q, null, '');
        } elseif ($op === 'delete') {
            examDelete($set, $id);
            $back = $self . '&deleted=' . $id;
        } elseif ($op === 'allow-login') {
            examUpdate($set, $id, fn(array $d) => ['tokenHash' => null] + $d);
        } elseif ($op === 'reopen') {
            examUpdate($set, $id, fn(array $d) => ['submitted' => null] + $d);
        } elseif ($op === 'restore') {
            $version = examLog($set, $id)[(int) ($_POST['v'] ?? -1)] ?? null;
            if ($version !== null && $version['q'] === $q) {
                examSaveText($set, $id, $q, (string) $version['sql'], 'restore');
            }
        }
    } elseif ($op === 'exam-password') {
        examSaveSettings($set, ['password' => trim((string) ($_POST['exam_password'] ?? ''))] + examSettings($set));
        $back .= '&saved=1';
    } elseif ($op === 'submit-all') {
        // End of the exam: hand in every open exam as if the student pressed Submit.
        $slot = acquireDbSlot($config['DB_SLOTS'], 10000);
        $pdo = null;
        $connectError = null;
        if ($slot !== null) {
            schemaOrConnect($config, true, $pdo, $connectError);
        }
        $done = null;             // null: no database, nothing submitted
        if ($pdo !== null) {
            $done = 0;
            foreach (examAllStudents($set) as $s) {
                if (($s['submitted'] ?? null) === null) {
                    examFinalize($set, $s['id'], $exam, $pdo);
                    $done++;
                }
            }
        }
        $pdo = null;
        releaseDbSlot($slot);
        $back .= $done === null ? '&busy=1' : '&submitted=' . $done;
    }
    header('Location: ' . $back, true, 303);
    exit;
}

// ---------------------------------------------------------------------------
// CSV export: one row per student, points, status and answer per assignment.
// ---------------------------------------------------------------------------
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="exam-' . $set . '-' . $day . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");      // BOM: Excel then reads UTF-8 names correctly
    $head = ['Student number', 'E-mail', 'Last name', 'First name', 'Points', 'Max', 'Submitted', 'Last activity'];
    foreach ($exam['exercises'] as $ex) {
        array_push($head, "Q{$ex['number']} points", "Q{$ex['number']} status", "Q{$ex['number']} checks", "Q{$ex['number']} answer", "Q{$ex['number']} note");
    }
    fputcsv($out, $head, ';');
    foreach (examAllStudents($set) as $s) {
        [$total, $max] = examTotal($s, $exam);
        $row = [$s['id'], $s['email'] ?? '', $s['lastName'] ?? '', $s['firstName'] ?? $s['name'], examNum($total), $max, $s['submitted'] ?? '', $s['last'] ?? ''];
        foreach ($exam['exercises'] as $ex) {
            $a = $s['answers'][$ex['number']] ?? null;
            array_push($row, examNum(examPoints($s, $ex)['points']), $a['status'] ?? '', $a['attempts'] ?? 0, $a['sql'] ?? '', $a['override']['note'] ?? '');
        }
        fputcsv($out, $row, ';');
    }
    exit;
}

// ---------------------------------------------------------------------------
// Detail: one student, one assignment. The answer runs again (the database
// is read-only, so the result is what the student got).
// ---------------------------------------------------------------------------
if ($id !== '') {
    $student = examLoad($set, $id);
    if ($student === null) {
        notFound('Student');
    }
    $exercise = $exam['exercises'][$q - 1];
    $answer = $student['answers'][$q] ?? null;
    $sql = (string) ($answer['sql'] ?? '');
    $points = examPoints($student, $exercise);
    [$total, $max] = examTotal($student, $exam);

    $resultsHtml = '';
    if (trim($sql) !== '' && $exercise['type'] === 'erd') {
        // A design assignment: the student's drawing next to the model design, no database.
        $check = checkAnswer(null, $exercise, $sql);
        $resultsHtml = '<div class="verdict ' . h($check['status']) . '"><strong>' . $labels[$check['status']] . '</strong> ' . h($check['message']) . '</div>'
            . '<div class="result"><div class="head"><span>Student\'s design</span></div>' . renderErdPreview($sql) . '</div>'
            . '<div class="result"><div class="head"><span>Model design</span></div>' . renderErdPreview($exercise['solution']) . '</div>';
    } elseif (trim($sql) !== '') {
        $slot = acquireDbSlot($config['DB_SLOTS']);
        if ($slot === null) {
            $resultsHtml = busyNotice();
        } else {
            $pdo = null;
            $connectError = null;
            schemaOrConnect($config, true, $pdo, $connectError);
            if ($pdo === null) {
                $resultsHtml = renderResults([], $connectError);
            } else {
                $check = checkExercise($pdo, $exercise, $sql);
                $resultsHtml = '<div class="verdict ' . h($check['status']) . '"><strong>' . $labels[$check['status']] . '</strong> ' . h($check['message']) . '</div>';
                if ($check['student'] !== null) {
                    $resultsHtml .= '<div class="result"><div class="head"><span>Student\'s result</span><span>'
                        . count($check['student']['rows']) . ' rows</span></div>' . renderTable($check['student'], CHECK_MAX_ROWS) . '</div>';
                }
                if ($check['expected'] !== null) {
                    $resultsHtml .= '<div class="result"><div class="head"><span>Expected result</span><span>'
                        . count($check['expected']['rows']) . ' rows</span></div>' . renderTable($check['expected'], CHECK_MAX_ROWS) . '</div>';
                }
            }
            $pdo = null;
            releaseDbSlot($slot);
        }
    }

    pageStart($student['name'] . ' · Q' . $q, 'exam', null, false, 'exam-admin');
    ?>
  <div class="breadcrumb"><a href="<?= h($self) ?>">Exam results <?= h(date('d/m/Y', strtotime($day))) ?></a> › <?= h($student['name']) ?></div>
  <div class="admin-student">
    <div>
      <h1><?= h($student['name']) ?> <span class="hint"><?= h($student['email'] ?? '') ?></span></h1>
      <p class="hint">
        Started <?= examTime($student['started'] ?? null) ?> · last activity <?= examTime($student['last'] ?? null) ?> ·
        <?= ($student['submitted'] ?? null) ? 'submitted ' . examTime($student['submitted']) : 'not submitted' ?> ·
        <strong><?= examNum($total) ?> / <?= $max ?></strong> points
      </p>
    </div>
    <?php if ($student['submitted'] ?? null): ?>
      <form method="post"><input type="hidden" name="s" value="<?= h($id) ?>"><input type="hidden" name="q" value="<?= $q ?>">
        <button type="submit" name="op" value="reopen" class="secondary" title="The student can change answers and submit again">Reopen exam</button></form>
    <?php endif; ?>
    <?php if ($student['tokenHash'] ?? null): ?>
      <form method="post"><input type="hidden" name="s" value="<?= h($id) ?>"><input type="hidden" name="q" value="<?= $q ?>">
        <button type="submit" name="op" value="allow-login" class="secondary" title="Needed when the student must continue in another browser or on another computer">Allow a new login</button></form>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm(<?= h(json_encode('Remove the submission of ' . $student['name'] . ' (' . $id . ')? All answers, checks and points are removed from the results. The student can log in again and starts over.')) ?>);">
      <input type="hidden" name="s" value="<?= h($id) ?>">
      <button type="submit" name="op" value="delete" class="danger" title="Remove this student from the results">Remove submission</button></form>
  </div>

  <nav class="admin-qnav" aria-label="Assignments">
    <?php foreach ($exam['exercises'] as $ex): $st = $student['answers'][$ex['number']]['status'] ?? 'none'; $p = examPoints($student, $ex); ?>
      <a href="<?= h($self) ?>&amp;s=<?= h($id) ?>&amp;q=<?= $ex['number'] ?>" class="mark-<?= h($st) ?><?= $ex['number'] === $q ? ' current' : '' ?>">Q<?= $ex['number'] ?> <?= $marks[$st] ?? '–' ?> <small><?= examNum($p['points']) ?><?= $p['override'] ? '*' : '' ?></small></a>
    <?php endforeach; ?>
  </nav>
  <h2><?= $q ?>. <?= h($exercise['title']) ?></h2>
  <div class="task"><?= $exercise['task'] ?></div>

  <form method="post" class="admin-points">
    <input type="hidden" name="s" value="<?= h($id) ?>"><input type="hidden" name="q" value="<?= $q ?>">
    <label>Points
      <input type="number" name="points" min="0" max="<?= $points['max'] ?>" step="0.5" value="<?= examNum($points['points']) ?>" required> / <?= $points['max'] ?>
    </label>
    <label class="grow">Note <input type="text" name="note" maxlength="200" value="<?= h($answer['override']['note'] ?? '') ?>" placeholder="optional, e.g. right idea, wrong column"></label>
    <button type="submit" name="op" value="points">Save points</button>
    <?php if ($points['override']): ?>
      <button type="submit" name="op" value="clear-points" class="secondary" formnovalidate>Back to automatic (<?= examNum($points['auto']) ?>)</button>
      <span class="hint">Set by you at <?= examTime($answer['override']['at']) ?></span>
    <?php else: ?>
      <span class="hint">Automatic: <?= $points['max'] ?> when the last Check is correct, otherwise 0</span>
    <?php endif; ?>
  </form>

  <?php if (trim($sql) === ''): ?>
    <p class="lead">No answer.</p>
  <?php else: ?>
    <h3>Answer <span class="hint">saved <?= examTime($answer['savedAt'] ?? null) ?><?= examEditedAfterCheck($answer) ? ' · changed after the last Check, not checked by the student' : '' ?></span></h3>
    <pre><code><?= h($sql) ?></code></pre>
    <?= $resultsHtml ?>
  <?php endif; ?>

  <?php $tries = array_reverse(array_filter($student['history'] ?? [], fn($t) => $t['q'] === $q)); ?>
  <?php if ($tries): ?>
    <h3>Every Check of this assignment</h3>
    <table class="admin-table">
      <thead><tr><th>Time</th><th>Verdict</th><th>Query</th></tr></thead>
      <tbody>
      <?php foreach ($tries as $t): ?>
        <tr>
          <td><?= examTime($t['at']) ?><?= !empty($t['final']) ? '<br><span class="hint">at submit</span>' : '' ?></td>
          <td class="mark-<?= h($t['status']) ?>"><?= $marks[$t['status']] ?> <?= h($t['message']) ?></td>
          <td><pre><code><?= h($t['sql']) ?></code></pre></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php $versions = array_reverse(array_filter(examLog($set, $id), fn($v) => $v['q'] === $q), true); ?>
  <?php if ($versions): ?>
    <details class="help">
      <summary>Saved versions of this assignment (<?= count($versions) ?>): every text the editor had, newest first</summary>
      <p class="hint">Restore puts that text back as the student's answer; the verdict follows at the next Check (or at submit, for an open exam).</p>
      <table class="admin-table">
        <thead><tr><th>Time</th><th>By</th><th>Query</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($versions as $v => $version): ?>
          <tr>
            <td><?= examTime($version['at']) ?></td>
            <td><?= h($version['why']) ?></td>
            <td><?= trim($version['sql']) === '' ? '<span class="hint">(empty)</span>' : '<pre><code>' . h($version['sql']) . '</code></pre>' ?></td>
            <td><form method="post"><input type="hidden" name="s" value="<?= h($id) ?>"><input type="hidden" name="q" value="<?= $q ?>"><input type="hidden" name="v" value="<?= $v ?>">
              <button type="submit" name="op" value="restore" class="secondary">Restore</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </details>
  <?php endif; ?>

  <details class="help">
    <summary>Reference solution</summary>
    <pre><code><?= h($exercise['solution']) ?></code></pre>
  </details>
<?php
    pageEnd();
    exit;
}

// ---------------------------------------------------------------------------
// Overview: every student × every assignment.
// ---------------------------------------------------------------------------
$students = examAllStudents($set);
$open = count(array_filter($students, fn($s) => ($s['submitted'] ?? null) === null));
$refresh = isset($_GET['refresh']);
$tasks = [];
foreach ($exam['exercises'] as $ex) {
    $tasks[$ex['number']] = ['title' => $ex['number'] . '. ' . $ex['title'], 'task' => $ex['task'], 'max' => $ex['points']];
}
pageStart('Exam results', 'exam', null, false, 'exam-admin');
?>
  <?php if ($refresh): ?><meta http-equiv="refresh" content="15"><?php endif; ?>
  <div class="admin-head">
    <h1><?= h($exam['title']) ?> · <?= h(date('d/m/Y', strtotime($day))) ?></h1>
    <span class="hint"><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?>, <?= $open ?> not submitted · <?= date('H:i:s') ?></span>
    <a href="<?= h($self) ?><?= $refresh ? '' : '&amp;refresh=1' ?>"><?= $refresh ? 'Stop auto-refresh' : 'Auto-refresh (15 s)' ?></a>
    <a href="<?= h($self) ?>&amp;csv=1">Download CSV</a>
    <?php if ($open > 0): ?>
      <form method="post" onsubmit="return confirm('Submit the exam of all <?= $open ?> students who did not submit yet? Their editor texts are checked and become their answers.');">
        <button type="submit" name="op" value="submit-all" class="secondary">Submit all open exams</button></form>
    <?php endif; ?>
    <form method="post"><button type="submit" name="logout" value="1" class="link">Log out</button></form>
  </div>
  <?php if (count($sets) > 1): ?>
  <nav class="admin-days" aria-label="Exams">
    <span class="hint">Exam:</span>
    <?php foreach ($sets as $name): ?>
      <a href="exam-admin.php?set=<?= h($name) ?>"<?= $name === $set ? ' class="current" aria-current="page"' : '' ?>><?= h(loadExamSet($name)['title']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <nav class="admin-days" aria-label="Exam days">
    <span class="hint">Day:</span>
    <?php foreach (isset($days[$today]) ? $days : [$today => 0] + $days as $d => $n): ?>
      <a href="<?= h($base) ?>&amp;day=<?= h($d) ?>"<?= $d === $day ? ' class="current" aria-current="page"' : '' ?>><?= h(date('D d/m/Y', strtotime($d))) ?> <small><?= $n ?></small></a>
    <?php endforeach; ?>
  </nav>
  <form method="post" class="admin-password">
    <label for="exam_password">Exam password</label>
    <input type="text" id="exam_password" name="exam_password" maxlength="40" autocomplete="off" spellcheck="false"
           value="<?= h(examSettings($set)['password']) ?>" placeholder="none: exam closed">
    <button type="submit" name="op" value="exam-password" class="secondary">Save</button>
    <span class="hint">Needed to log in, not by students who are already in. Empty = nobody can log in (exam closed). Same for every day.</span>
  </form>
  <?php if (isset($_GET['saved'])): ?><p class="verdict correct">Exam password saved.</p><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><p class="verdict correct">Submission of <?= h(preg_replace('/[^a-z0-9]/', '', (string) $_GET['deleted'])) ?> removed (kept in exam-data/deleted/).</p><?php endif; ?>
  <?php if (isset($_GET['submitted'])): ?><p class="verdict correct"><?= (int) $_GET['submitted'] ?> exam(s) submitted.</p><?php endif; ?>
  <?php if (isset($_GET['busy'])): ?><p class="verdict error">The database was busy, nothing submitted. Try again.</p><?php endif; ?>
  <p class="hint">Last Check per assignment: ✓ correct · ✗ not yet · ! MySQL error · ? cannot check · – not checked · ✎ changed after the last Check. Number = points (* = set by you). Hover a cell for the assignment and the answer, click it for the result.</p>

  <table class="admin-table admin-overview">
    <thead>
      <tr>
        <th>Student</th>
        <?php foreach ($exam['exercises'] as $ex): ?><th title="<?= h($ex['title']) ?>">Q<?= $ex['number'] ?></th><?php endforeach; ?>
        <th>Points</th><th>Submitted</th><th>Last activity</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$students): ?>
      <tr><td colspan="<?= $count + 4 ?>" class="null">Nobody has logged in yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($students as $s): [$total, $max] = examTotal($s, $exam); ?>
      <tr>
        <td><a href="<?= h($self) ?>&amp;s=<?= h($s['id']) ?>&amp;q=1"><?= h(($s['lastName'] ?? '') . ', ' . ($s['firstName'] ?? $s['name'])) ?></a><br><span class="hint"><?= h($s['id']) ?></span></td>
        <?php foreach ($exam['exercises'] as $ex):
            $a = $s['answers'][$ex['number']] ?? null;
            $st = $a['status'] ?? 'none';
            $p = examPoints($s, $ex);
            $edited = examEditedAfterCheck($a);
            $info = ($labels[$st] ?? 'Not checked') . (isset($a['message']) && $st !== 'correct' ? ': ' . $a['message'] : '')
                . ($edited ? ' (changed after the last Check)' : '')
                . ' · ' . examNum($p['points']) . '/' . $p['max'] . ' points' . ($p['override'] ? ' set by you' . (($a['override']['note'] ?? '') !== '' ? ': ' . $a['override']['note'] : '') : '');
        ?>
          <td class="cell mark-<?= h($st) ?><?= $p['override'] ? ' override' : '' ?>" data-q="<?= $ex['number'] ?>" data-sql="<?= h($a['sql'] ?? '') ?>" data-info="<?= h($info) ?>">
            <a href="<?= h($self) ?>&amp;s=<?= h($s['id']) ?>&amp;q=<?= $ex['number'] ?>"><?= $marks[$st] ?? '–' ?><?= $edited ? '✎' : '' ?><small><?= examNum($p['points']) ?><?= $p['override'] ? '*' : '' ?></small></a>
          </td>
        <?php endforeach; ?>
        <td class="num"><strong><?= examNum($total) ?></strong>/<?= $max ?></td>
        <td><?= ($s['submitted'] ?? null) ? examTime($s['submitted']) : '<span class="hint">open</span>' ?></td>
        <td><?= examTime($s['last'] ?? null) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($students): $n = count($students); ?>
      <tfoot>
        <tr>
          <th>Average <span class="hint">(<?= $n ?> student<?= $n === 1 ? '' : 's' ?>)</span></th>
          <?php foreach ($exam['exercises'] as $ex):
              $sum = array_sum(array_map(fn($s) => examPoints($s, $ex)['points'], $students));
              $right = count(array_filter($students, fn($s) => ($s['answers'][$ex['number']]['status'] ?? null) === 'correct'));
          ?>
            <th class="num" title="Average points; share of students whose last Check is correct"><?= examNum(round($sum / $n, 1)) ?><br><span class="hint"><?= round($right / $n * 100) ?>% ✓</span></th>
          <?php endforeach; ?>
          <th class="num"><?= examNum(round(array_sum(array_map(fn($s) => examTotal($s, $exam)[0], $students)) / $n, 1)) ?>/<?= examTotal($students[0], $exam)[1] ?></th>
          <th colspan="2"></th>
        </tr>
      </tfoot>
    <?php endif; ?>
  </table>
  <script type="application/json" id="exam-tasks"><?= json_encode($tasks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php
pageEnd(null, 'run', false, ['exam-admin.js']);
