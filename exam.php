<?php
/**
 * Exam mode: the playground without navigation. Only the SQL editor, the
 * results and the table list; no links to the course, chapters, hints or
 * solutions. Hand out this URL during an exam.
 */

declare(strict_types=1);

const EXAM_MODE = true;
require __DIR__ . '/playground.php';
