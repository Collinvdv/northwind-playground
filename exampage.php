<?php
/**
 * Graded exam, shared by every exam page (exam-groupby.php, exam-innerjoin.php):
 * the page sets $set (the name of a file in exam-sets/) and requires this file.
 *
 * The student logs in with first name, last name and school e-mail, then
 * solves the assignments of exam-sets/<set>.php. Check says correct / not yet
 * with the reason and the student's own result, never the expected result.
 * The editor text is autosaved (assets/exam.js), every Check is stored, and
 * "Submit exam" checks what is left and locks the exam (see examlib.php).
 * The teacher follows along on exam-admin.php?set=<set>.
 *
 * Like exam.php, the header has no links: the exam page is the only URL students need.
 *   POST first_name, last_name, email[, exam_password]   log in
 *   POST logout=1                       log out
 *   POST action=check|run, sql          as on exercise.php (?q= picks the assignment)
 *   POST action=save, sql               autosave, answers JSON
 *   POST action=submit[, sql]           final submit
 */

declare(strict_types=1);

// Not a page of its own: only an exam page that names its exam set may include it.
if (!isset($set) || !is_string($set)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found.\n");
}

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/examlib.php';

$examUrl = basename((string) $_SERVER['SCRIPT_NAME']);   // the exam page that included this file
$exam = loadExamSet($set);
examMigrateLegacy($set);
$count = count($exam['exercises']);
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = (string) ($_POST['action'] ?? 'check');
// Never serve this page from a cache: an old copy of the editor would be autosaved over newer answers.
header('Cache-Control: no-store');

/** Autosave answer: {ok, savedAt} or {ok: false, error}. */
function sendSaveJson(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

if ($isPost && isset($_POST['logout'])) {
    examLogout($set);
    header('Location: ' . $examUrl, true, 303);
    exit;
}

$loginError = '';
if ($isPost && isset($_POST['email'])) {
    try {
        $result = examLogin($set, (string) ($_POST['first_name'] ?? ''), (string) ($_POST['last_name'] ?? ''), (string) $_POST['email'], (string) ($_POST['exam_password'] ?? ''));
    } catch (RuntimeException $e) {
        $result = 'Cannot save answers on the server, tell your teacher: ' . $e->getMessage();
    }
    if (is_array($result)) {
        header('Location: ' . $examUrl . '?q=1', true, 303);
        exit;
    }
    $loginError = $result;
}

$student = examCurrentStudent($set);

// ---------------------------------------------------------------------------
// Not logged in: the login form (a fetch from an expired login gets a notice).
// ---------------------------------------------------------------------------
if ($student === null) {
    if ($isPost && $action === 'save') {
        sendSaveJson(['ok' => false, 'error' => 'not logged in, reload the page'], 401);
    }
    if (isFetchRequest()) {
        sendFragment('<div class="verdict invalid"><strong>Not logged in</strong> Reload the page and log in again; your answers are kept.</div>');
    }
    pageStart($exam['title'], 'exam');
    ?>
  <form method="post" class="exam-login">
    <h1><?= h($exam['title']) ?></h1>
    <?= $exam['intro'] ?>
    <div class="row">
      <div>
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" maxlength="<?= EXAM_NAME_MAX ?>" required autofocus autocomplete="given-name"
               value="<?= h((string) ($_POST['first_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" maxlength="<?= EXAM_NAME_MAX ?>" required autocomplete="family-name"
               value="<?= h((string) ($_POST['last_name'] ?? '')) ?>">
      </div>
    </div>
    <label for="email">School e-mail</label>
    <input type="email" id="email" name="email" required autocomplete="email" placeholder="r1234567@student.thomasmore.be"
           pattern="<?= EXAM_EMAIL_HTML_PATTERN ?>" title="r + 7 digits + @student.thomasmore.be, for example r1095457@student.thomasmore.be"
           value="<?= h((string) ($_POST['email'] ?? '')) ?>">
    <label for="exam_password">Exam password</label>
    <input type="password" id="exam_password" name="exam_password" required autocomplete="off" placeholder="given by your teacher in the classroom">
    <?php if ($loginError !== ''): ?><p class="verdict invalid"><?= h($loginError) ?></p><?php endif; ?>
    <button type="submit">Start the exam</button>
    <p class="hint">Your work is saved on the server while you type. Closed the browser by accident? Open this page again in the same browser and continue.</p>
  </form>
<?php
    pageEnd();
    exit;
}

$id = $student['id'];
$submitted = $student['submitted'] ?? null;

// ---------------------------------------------------------------------------
// Submitted: nothing can change any more, show what was handed in.
// ---------------------------------------------------------------------------
if ($submitted !== null) {
    if ($isPost && $action === 'save') {
        sendSaveJson(['ok' => false, 'error' => 'the exam is submitted'], 409);
    }
    if (isFetchRequest()) {
        sendFragment('<div class="verdict invalid"><strong>Submitted</strong> The exam is submitted; reload the page.</div>');
    }
    pageStart($exam['title'] . ' · submitted', 'exam');
    ?>
  <div class="exam-done">
    <h1><?= h($exam['title']) ?></h1>
    <p class="verdict correct"><strong>Submitted</strong> <?= h($student['name']) ?> (<?= h($student['email']) ?>) · <?= date('d/m/Y H:i:s', strtotime($submitted)) ?>. You can close this page.</p>
    <?php foreach ($exam['exercises'] as $ex): $a = $student['answers'][$ex['number']] ?? null; ?>
      <h2><?= $ex['number'] ?>. <?= h($ex['title']) ?></h2>
      <?php if ($a === null || trim((string) ($a['sql'] ?? '')) === ''): ?>
        <p class="hint">No answer.</p>
      <?php else: ?>
        <pre><code><?= h($a['sql']) ?></code></pre>
        <?= $ex['type'] === 'erd' ? renderErdPreview((string) $a['sql']) : '' ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php
    pageEnd();
    exit;
}

$q = max(1, min($count, (int) ($_GET['q'] ?? $_POST['q'] ?? 1)));
$exercise = $exam['exercises'][$q - 1];
// A design assignment ('type' => 'erd'): the student writes an ERD, no database is needed.
$isErd = $exercise['type'] === 'erd';

// Autosave: no MySQL, just the file.
if ($isPost && $action === 'save') {
    try {
        $record = examSaveText($set, $id, $q, answerText($exercise, (string) ($_POST['sql'] ?? '')), 'autosave');
        sendSaveJson(['ok' => true, 'savedAt' => examTime($record['answers'][$q]['savedAt'] ?? null)]);
    } catch (RuntimeException $e) {
        sendSaveJson(['ok' => false, 'error' => 'the server cannot store answers, tell your teacher'], 500);
    }
}

$sqlInput = answerText($exercise, (string) ($isPost ? ($_POST['sql'] ?? '') : ($student['answers'][$q]['sql'] ?? '')));

// Final submit: save the text that is in the editor, check what is left, lock.
if ($isPost && $action === 'submit') {
    if (isset($_POST['sql'])) {
        examSaveText($set, $id, $q, $sqlInput, 'submit');
    }
    $slot = acquireDbSlot($config['DB_SLOTS']);
    $pdo = null;
    $connectError = null;
    if ($slot !== null) {
        schemaOrConnect($config, true, $pdo, $connectError);
    }
    $done = false;
    if ($pdo !== null) {
        examFinalize($set, $id, $exam, $pdo);
        $done = true;
    }
    $pdo = null;
    releaseDbSlot($slot);
    // Busy or no connection: back to the page with a notice, nothing is lost.
    header('Location: ' . $examUrl . '?q=' . $q . ($done ? '' : '&busy=1'), true, 303);
    exit;
}

$action = $action === 'run' ? 'run' : 'check';

// A POST needs MySQL: take a slot first (503 + retry when all are busy).
$slot = $isPost && !$isErd ? acquireDbSlot($config['DB_SLOTS']) : true;
if ($slot === null) {
    sendBusyIfFetch();
}

$pdo = null;
$connectError = null;
$schema = schemaOrConnect($config, $isPost && !$isErd && $slot !== null, $pdo, $connectError);

$resultsHtml = '';
$check = null;
if ($isPost) {
    if ($isErd) {
        // The editor (assets/erd-editor.js) shows the design itself and sends visual=1: the verdict is enough.
        $check = checkAnswer(null, $exercise, $sqlInput);
        $resultsHtml = renderErdCheck($check, empty($_POST['visual']));
    } elseif ($slot === null) {
        $resultsHtml = busyNotice();
    } elseif ($pdo === null) {
        $resultsHtml = renderResults([], $connectError);
    } elseif ($action === 'run') {
        $resultsHtml = renderResults(runScript($pdo, $sqlInput), null);
    } else {
        $check = checkExercise($pdo, $exercise, $sqlInput);
        $resultsHtml = renderCheck($check, false);
    }
    $pdo = null;              // close the connection before rendering the page
    if (!$isErd) {
        releaseDbSlot($slot);
    }

    // Both Run and Check save the editor text; a Check also counts as an attempt.
    try {
        $student = $check !== null && trim($sqlInput) !== ''
            ? examRecordAttempt($set, $id, $q, $sqlInput, $check)
            : examSaveText($set, $id, $q, $sqlInput, $action);
        $a = $student['answers'][$q];
        // Read by assets/exam.js: autosave time, ✓ in the left panel.
        $resultsHtml .= '<div hidden id="exam-state" data-saved-at="' . examTime($a['savedAt']) . '" data-status="' . h($a['status'] ?? '')
            . '" data-correct="' . examScore($student) . '"></div>';
    } catch (RuntimeException $e) {
        $resultsHtml = '<div class="verdict error"><strong>Not saved</strong> Your answer could not be stored, tell your teacher.</div>' . $resultsHtml;
    }
}

if (isFetchRequest()) {
    sendFragment($resultsHtml);
}

// Sidebar: who is logged in, the assignments (checked ones marked), submit, the tables.
$sidebar = '<h2>Student</h2><div class="exam-student"><strong>' . h($student['name']) . '</strong><span>' . h($student['email']) . '</span>'
    . '<form method="post"><button type="submit" name="logout" value="1" class="link">Not you? Log out</button></form></div>'
    . '<h2>Assignments</h2><nav class="exnav" id="exam-nav" aria-label="Assignments">';
foreach ($exam['exercises'] as $ex) {
    $status = $student['answers'][$ex['number']]['status'] ?? null;
    $classes = array_filter([$ex['number'] === $q ? 'current' : '', $status === 'correct' ? 'solved' : ($status !== null ? 'tried' : '')]);
    $sidebar .= '<a href="' . h($examUrl) . '?q=' . $ex['number'] . '" title="' . h($ex['title']) . '"'
        . ($classes ? ' class="' . implode(' ', $classes) . '"' : '')
        . ($ex['number'] === $q ? ' aria-current="page"' : '') . '>' . $ex['number'] . '</a>';
}
$sidebar .= '</nav>'
    . '<form method="post" id="exam-submit" class="exam-submit" action="' . h($examUrl) . '?q=' . $q . '">'
    . '<button type="submit" name="action" value="submit">Submit exam</button>'
    . '<p class="hint">Done? Submit to hand in. Afterwards nothing can be changed.</p></form>';
if ($isErd) {
    $sidebar .= renderErdHelp();
} else {
    $mentioned = mentionedTables($schema, strip_tags($exercise['task']));
    $sidebar .= renderSchemaSidebar($schema, $mentioned, true);
}

$savedAt = $student['answers'][$q]['savedAt'] ?? null;
pageStart('Assignment ' . $q . ': ' . $exercise['title'], 'exam', $sidebar, !$isErd);
?>
  <div class="breadcrumb"><?= h($exam['title']) ?> · Assignment <?= $q ?> of <?= $count ?> · <span id="exam-correct"><?= examScore($student) ?></span> of <?= $count ?> correct</div>
  <h1><?= $q ?>. <?= h($exercise['title']) ?></h1>
  <?php if (isset($_GET['busy'])): ?>
    <div class="verdict error"><strong>Not submitted</strong> The server was busy. Wait a few seconds and press Submit exam again.</div>
  <?php endif; ?>

  <div class="task">
    <?= lessonHtml($exercise['task'], $schema) ?>
  </div>

  <form method="post" id="sqlform" class="sqlform<?= $isErd ? ' erdform' : '' ?>" data-store-key="nw.exam.<?= h($set) ?>.<?= h($id) ?>.<?= $q ?>">
  <?php if ($isErd): ?>
    <textarea name="sql" id="sql" spellcheck="false" autocapitalize="off" autocomplete="off" placeholder="Teams: TeamID PK, Name&#10;Players: PlayerID PK, Name, TeamID &gt; Teams"><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="check">Check</button>
      <span id="autosave" class="autosave<?= $savedAt ? ' ok' : '' ?>" data-saved-at="<?= h(examTime($savedAt)) ?>"><?= $savedAt ? 'Saved ' . examTime($savedAt) : 'Not saved yet' ?></span>
      <span class="hint">Ctrl+Enter / ⌘+Enter checks · your design is saved automatically</span>
    </div>
  <?php else: ?>
    <textarea name="sql" id="sql" spellcheck="false" placeholder="SELECT ..."><?= h($sqlInput) ?></textarea>
    <div class="bar">
      <button type="submit" name="action" value="check">Check</button>
      <button type="submit" name="action" value="run" class="secondary">Run only</button>
      <button type="button" class="secondary editor-only" data-cmd="undo" title="Undo (Ctrl+Z / ⌘+Z)">Undo</button>
      <button type="button" class="secondary editor-only" data-cmd="redo" title="Redo (Ctrl+Y / ⇧⌘+Z)">Redo</button>
      <button type="button" class="secondary editor-only" data-cmd="format" title="Format the query (⇧Ctrl+F / ⇧⌘+F)">Format</button>
      <span id="autosave" class="autosave<?= $savedAt ? ' ok' : '' ?>" data-saved-at="<?= h(examTime($savedAt)) ?>"><?= $savedAt ? 'Saved ' . examTime($savedAt) : 'Not saved yet' ?></span>
      <span class="hint">Ctrl+Enter / ⌘+Enter checks · your text is saved automatically</span>
    </div>
  <?php endif; ?>
  </form>

  <div class="pager">
    <span><?php if ($q > 1): ?><a href="<?= h($examUrl) ?>?q=<?= $q - 1 ?>">← Assignment <?= $q - 1 ?></a><?php endif; ?></span>
    <span><?php if ($q < $count): ?><a href="<?= h($examUrl) ?>?q=<?= $q + 1 ?>">Assignment <?= $q + 1 ?> →</a><?php endif; ?></span>
  </div>

  <div id="results">
    <?= $resultsHtml ?>
  </div>
<?php
$isErd
    ? pageEnd(null, 'check', false, ['exam.js', 'erd-editor.js'])
    : pageEnd(hintTables($schema), 'check', false, ['exam.js']);
