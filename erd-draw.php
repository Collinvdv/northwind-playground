<?php
/**
 * Free drawing: the diagram editor of the design exercises (assets/erd-editor.js)
 * without a task and without a check. Nothing is sent to the server: the
 * drawing is kept in this browser (localStorage) and can be saved as a text
 * file, which the "Text" panel of the editor reads back.
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

$sidebar = renderErdHelp()
    . '<h2>Your drawing</h2><div class="erd-help"><p>It is kept in this browser only: it is still here when you come back, '
    . 'but not on another computer. <strong>Save .txt</strong> downloads it; to open such a file again, press <strong>Text</strong> and paste its contents.</p></div>';

pageStart('Draw an ERD', 'draw', $sidebar);
?>
  <h1>Draw an ERD</h1>
  <p class="lead">A free canvas for your own diagrams: a project, an idea, a sketch for class. It is only a drawing; no database is created and nothing is checked.</p>

  <form id="sqlform" class="sqlform erdform" data-free="nw.erd.free" onsubmit="return false">
    <textarea name="sql" id="sql" spellcheck="false" autocapitalize="off" autocomplete="off"
              placeholder="Teams: TeamID int PK, Name varchar&#10;Players: PlayerID int PK, Name varchar, TeamID int > Teams"></textarea>
    <noscript><p class="hint">The drawing canvas needs JavaScript.</p></noscript>
  </form>
<?php
pageEnd(null, 'run', false, ['erd-editor.js']);
