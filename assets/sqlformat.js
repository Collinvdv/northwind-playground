/*
 * Small SQL formatter for the course editor (MySQL SELECT statements).
 *
 * Style: every clause keyword (SELECT, FROM, WHERE, GROUP BY, HAVING,
 * ORDER BY, LIMIT) is a line of its own at the statement's indent and its
 * contents follow on the next line(s), indented one step. JOINs belong to
 * FROM and are indented with it; AND / OR start a new line:
 *
 *   SELECT
 *     c.CompanyName,
 *     COUNT(*) AS Orders
 *   FROM
 *     Customers AS c
 *     JOIN Orders AS o ON o.CustomerID = c.CustomerID
 *   WHERE
 *     c.Country = 'Germany'
 *     AND o.OrderDate >= '1997-01-01'
 *   GROUP BY
 *     c.CompanyName
 *   ORDER BY
 *     Orders DESC
 *   LIMIT
 *     5;
 *
 * Subqueries open on "(" and are indented one more step. Keywords are written in capitals,
 * identifiers, strings and comments are left as they are. Nothing is ever
 * dropped: formatting is only whitespace and keyword case.
 *
 * Exposed as window.formatSql(text) (and module.exports in Node for tests).
 */
(function (root) {
  'use strict';

  var INDENT = '  ';

  // Keywords that start a new line at the statement's indent.
  var CLAUSES = {
    'SELECT': 1, 'FROM': 1, 'WHERE': 1, 'GROUP BY': 1, 'HAVING': 1, 'ORDER BY': 1,
    'LIMIT': 1, 'UNION': 1, 'UNION ALL': 1, 'UNION DISTINCT': 1, 'WITH': 1, 'WITH RECURSIVE': 1,
    'JOIN': 1, 'INNER JOIN': 1, 'LEFT JOIN': 1, 'LEFT OUTER JOIN': 1, 'RIGHT JOIN': 1,
    'RIGHT OUTER JOIN': 1, 'FULL JOIN': 1, 'FULL OUTER JOIN': 1, 'CROSS JOIN': 1,
    'NATURAL JOIN': 1, 'STRAIGHT_JOIN': 1, 'WINDOW': 1
  };

  // Multi-word keywords, longest first.
  var COMPOUNDS = [
    ['LEFT', 'OUTER', 'JOIN'], ['RIGHT', 'OUTER', 'JOIN'], ['FULL', 'OUTER', 'JOIN'],
    ['GROUP', 'BY'], ['ORDER', 'BY'], ['PARTITION', 'BY'], ['UNION', 'ALL'], ['UNION', 'DISTINCT'],
    ['WITH', 'RECURSIVE'], ['INNER', 'JOIN'], ['LEFT', 'JOIN'], ['RIGHT', 'JOIN'],
    ['FULL', 'JOIN'], ['CROSS', 'JOIN'], ['NATURAL', 'JOIN']
  ];

  // Reserved words written in capitals. Words not in this list (identifiers,
  // function names) keep their case; a "(" right after them is a call.
  var KEYWORDS = (
    'SELECT FROM WHERE GROUP BY HAVING ORDER LIMIT OFFSET AS ON AND OR NOT IN IS NULL LIKE ' +
    'BETWEEN EXISTS DISTINCT DISTINCTROW ALL ANY SOME CASE WHEN THEN ELSE END ASC DESC ' +
    'JOIN INNER LEFT RIGHT OUTER FULL CROSS NATURAL STRAIGHT_JOIN USING UNION WITH RECURSIVE ' +
    'TRUE FALSE UNKNOWN DIV MOD XOR REGEXP RLIKE ESCAPE INTERVAL OVER PARTITION WINDOW ROWS ' +
    'RANGE PRECEDING FOLLOWING UNBOUNDED CURRENT ROW COLLATE BINARY DESCRIBE DESC EXPLAIN SHOW ' +
    'TABLES COLUMNS DATABASES CREATE TABLE VIEW INSERT INTO VALUES UPDATE SET DELETE'
  ).split(' ').reduce(function (m, k) { m[k] = 1; return m; }, {});

  // Function names written in capitals when followed by "(".
  var FUNCTIONS = (
    'COUNT SUM AVG MIN MAX ROUND FLOOR CEILING CEIL ABS TRUNCATE MOD POWER POW SQRT FORMAT ' +
    'UPPER LOWER UCASE LCASE CONCAT CONCAT_WS LENGTH CHAR_LENGTH SUBSTRING SUBSTR MID REPLACE ' +
    'TRIM LTRIM RTRIM LPAD RPAD INSTR LOCATE REVERSE REPEAT SPACE STRCMP ' +
    'YEAR MONTH DAY DAYNAME MONTHNAME DAYOFMONTH DAYOFWEEK DAYOFYEAR WEEK WEEKDAY QUARTER HOUR MINUTE SECOND ' +
    'DATEDIFF TIMESTAMPDIFF DATE_ADD DATE_SUB ADDDATE SUBDATE DATE_FORMAT STR_TO_DATE LAST_DAY DATE TIME ' +
    'NOW CURDATE CURTIME CURRENT_DATE CURRENT_TIMESTAMP ' +
    'COALESCE IFNULL NULLIF IF GREATEST LEAST GROUP_CONCAT CAST CONVERT ' +
    'ROW_NUMBER RANK DENSE_RANK LAG LEAD NTILE FIRST_VALUE LAST_VALUE'
  ).split(' ').reduce(function (m, k) { m[k] = 1; return m; }, {});

  // Keywords that take a "(" with a space (not a function call).
  var SPACE_PAREN = {
    'IN': 1, 'EXISTS': 1, 'AND': 1, 'OR': 1, 'NOT': 1, 'ON': 1, 'WHERE': 1, 'SELECT': 1, 'FROM': 1,
    'VALUES': 1, 'AS': 1, 'ANY': 1, 'ALL': 1, 'SOME': 1, 'THEN': 1, 'ELSE': 1, 'WHEN': 1,
    'BETWEEN': 1, 'JOIN': 1, 'UNION': 1, 'HAVING': 1, 'BY': 1, 'GROUP BY': 1, 'ORDER BY': 1,
    'PARTITION BY': 1, 'UNION ALL': 1, 'OVER': 1, 'USING': 1, 'LIKE': 1, 'INTERVAL': 1, 'CASE': 1,
    'DISTINCT': 1, 'LIMIT': 1, 'RETURNING': 1
  };

  var TOKEN = new RegExp(
    '(\\s+)|' +                                           // 1 whitespace
    '(--[^\\n]*|#[^\\n]*)|' +                             // 2 line comment
    '(\\/\\*[\\s\\S]*?(?:\\*\\/|$))|' +                   // 3 block comment
    "('(?:[^'\\\\]|\\\\.|'')*'?)|" +                      // 4 single-quoted
    '("(?:[^"\\\\]|\\\\.|"")*"?)|' +                      // 5 double-quoted
    '(`[^`]*`?)|' +                                       // 6 backticks
    '([(),;])|' +                                         // 7 punctuation
    '(<=>|<=|>=|<>|!=|:=|\\|\\||&&|<<|>>|[=<>+\\-*/%^~!|&])|' + // 8 operators
    '(\\d+\\.?\\d*(?:[eE][+-]?\\d+)?)|' +                 // 9 number
    '([A-Za-z_@$][\\w$]*(?:\\.(?:[A-Za-z_][\\w$]*|\\*|`[^`]*`))*|\\.[A-Za-z_*][\\w$]*)|' + // 10 word, maybe dotted
    '(.)', 'g');                                          // 11 anything else

  function tokenize(sql) {
    var tokens = [];
    var m;
    var sawNewline = true;      // a line comment after a line break stays on its own line
    TOKEN.lastIndex = 0;
    while ((m = TOKEN.exec(sql)) !== null) {
      if (m[1]) { if (m[1].indexOf('\n') >= 0) sawNewline = true; continue; }
      if (m[2]) tokens.push({ t: 'lc', v: m[2].replace(/\s+$/, ''), nl: sawNewline });
      sawNewline = false;
      if (m[2]) continue;
      else if (m[3]) tokens.push({ t: 'bc', v: m[3] });
      else if (m[4] || m[5] || m[6]) tokens.push({ t: 'str', v: m[0] });
      else if (m[7]) tokens.push({ t: m[7], v: m[7] });
      else if (m[8]) tokens.push({ t: 'op', v: m[8] });
      else if (m[9]) tokens.push({ t: 'num', v: m[9] });
      else if (m[10]) tokens.push({ t: 'word', v: m[10], u: m[10].toUpperCase() });
      else tokens.push({ t: 'other', v: m[11] });
    }
    // Merge compound keywords.
    var out = [];
    for (var i = 0; i < tokens.length; i++) {
      var tok = tokens[i];
      var merged = false;
      if (tok.t === 'word') {
        for (var c = 0; c < COMPOUNDS.length && !merged; c++) {
          var parts = COMPOUNDS[c];
          var ok = true;
          for (var p = 0; p < parts.length; p++) {
            var t = tokens[i + p];
            if (!t || t.t !== 'word' || t.u !== parts[p]) { ok = false; break; }
          }
          if (ok) {
            out.push({ t: 'word', v: parts.join(' '), u: parts.join(' '), kw: true });
            i += parts.length - 1;
            merged = true;
          }
        }
      }
      if (!merged) {
        if (tok.t === 'word' && KEYWORDS[tok.u] && tok.v.indexOf('.') < 0) {
          tok.kw = true;
          tok.v = tok.u;
        } else if (tok.t === 'word' && FUNCTIONS[tok.u] && tokens[i + 1] && tokens[i + 1].t === '(') {
          tok.v = tok.u;
        }
        out.push(tok);
      }
    }
    return out;
  }

  function formatSql(sql) {
    var tokens = tokenize(String(sql));
    var lines = [];
    var line = '';
    var lineIndent = 0;
    var prev = null;          // previous token on the current line (null at line start)
    var glue = false;         // no space before the next token (unary minus)

    // One context per statement / subquery.
    function newCtx(indent) {
      return { indent: indent, clause: null, pending: false, inWith: false, between: 0, cases: [] };
    }
    var ctx = newCtx(0);
    var stack = [];           // open parens: {sub: bool, ctx (outer), indent (outer line indent)}

    function flush() {
      if (line.replace(/\s+$/, '') !== '') lines.push(line.replace(/\s+$/, ''));
    }
    function newline(indent) {
      flush();
      lineIndent = indent;
      line = repeat(INDENT, indent);
      prev = null;
      glue = false;
    }
    function repeat(s, n) { return new Array(Math.max(0, n) + 1).join(s); }
    // Start a line at the given indent: reuse the current line when it is still empty.
    function breakTo(indent) {
      if (prev === null) { lineIndent = indent; line = repeat(INDENT, indent); glue = false; }
      else newline(indent);
    }
    function atLineStart() { return prev === null; }

    function put(tok) {
      if (!atLineStart()) {
        var space = true;
        if (tok.t === ',' || tok.t === ';' || tok.t === ')') space = false;
        else if (prev.t === '(') space = false;
        else if (tok.t === 'word' && tok.v.charAt(0) === '.') space = false;
        else if (tok.t === '(' && prev.t === 'word' && !(prev.kw && SPACE_PAREN[prev.u]) && !(prev.kw && CLAUSES[prev.u])) space = false;
        else if (glue) space = false;
        if (space) line += ' ';
      }
      line += tok.v;
      prev = tok;
      // Unary minus/plus: glue the number that follows.
      glue = tok.t === 'op' && (tok.v === '-' || tok.v === '+') && (!prevBefore || prevBefore.t === '(' || prevBefore.t === ',' || prevBefore.t === 'op' || (prevBefore.kw));
    }
    var prevBefore = null;

    for (var i = 0; i < tokens.length; i++) {
      var tok = tokens[i];
      var top = stack.length ? stack[stack.length - 1] : null;
      var inExpr = top !== null && !top.sub;   // inside ( ... ) that is not a subquery: no line breaks
      prevBefore = prev;

      if (tok.t === 'lc') {                       // line comment: end of the current line, or its own line
        if (tok.nl && !atLineStart()) newline(lineIndent);
        if (atLineStart()) put(tok); else { line += ' ' + tok.v; }
        newline(lineIndent);
        continue;
      }
      if (tok.t === 'bc') { put(tok); continue; }

      if (tok.t === ';') {
        put(tok);
        ctx = newCtx(0);
        stack = [];
        newline(0);
        lines.push('');
        continue;
      }

      // Contents of a clause start on the next line, indented (DISTINCT stays with SELECT).
      if (ctx.pending && !inExpr && !(tok.kw && (tok.u === 'DISTINCT' || tok.u === 'ALL' || tok.u === 'DISTINCTROW'))) {
        ctx.pending = false;
        breakTo(ctx.indent + 1);
      }

      if (tok.t === '(') {
        var next = tokens[i + 1];
        var isSub = next && next.kw && (next.u === 'SELECT' || next.u === 'WITH' || next.u === 'WITH RECURSIVE');
        put(tok);
        if (isSub && !inExpr) {
          stack.push({ sub: true, ctx: ctx, indent: lineIndent });
          ctx = newCtx(lineIndent + 1);
          newline(ctx.indent);
        } else {
          stack.push({ sub: false, ctx: ctx, indent: lineIndent });
        }
        continue;
      }
      if (tok.t === ')') {
        if (top) {
          stack.pop();
          if (top.sub) {
            ctx = top.ctx;
            newline(top.indent);
          }
        }
        put(tok);
        continue;
      }

      if (tok.t === ',') {
        put(tok);
        if (!inExpr && ctx.clause === 'SELECT' && !ctx.cases.length) newline(ctx.indent + 1);
        else if (!inExpr && ctx.inWith) newline(ctx.indent);
        continue;
      }

      if (tok.kw && !inExpr) {
        if (CLAUSES[tok.u]) {
          var isJoin = /JOIN$/.test(tok.u);
          var isWith = tok.u === 'WITH' || tok.u === 'WITH RECURSIVE';
          var isUnion = tok.u.indexOf('UNION') === 0;
          ctx.between = 0;
          ctx.pending = false;
          if (tok.u === 'SELECT') ctx.inWith = false; else if (isWith) ctx.inWith = true;
          breakTo(isJoin ? ctx.indent + 1 : ctx.indent);
          put(tok);
          ctx.clause = isJoin ? 'FROM' : tok.u;
          ctx.pending = !isJoin && !isWith && !isUnion;
          continue;
        }
        if (tok.u === 'CASE') {
          ctx.cases.push(lineIndent);
          put(tok);
          continue;
        }
        if (ctx.cases.length && (tok.u === 'WHEN' || tok.u === 'ELSE')) {
          breakTo(ctx.cases[ctx.cases.length - 1] + 1);
          put(tok);
          continue;
        }
        if (ctx.cases.length && tok.u === 'END') {
          breakTo(ctx.cases.pop());
          put(tok);
          continue;
        }
        if (tok.u === 'BETWEEN') { ctx.between++; put(tok); continue; }
        if (tok.u === 'AND' && ctx.between > 0) { ctx.between--; put(tok); continue; }
        if ((tok.u === 'AND' || tok.u === 'OR') && !ctx.cases.length && ctx.clause !== 'SELECT') {
          breakTo(ctx.indent + 1);
          put(tok);
          continue;
        }
      }

      put(tok);
    }
    flush();
    // Trim trailing blank lines, keep one newline at the end.
    while (lines.length && lines[lines.length - 1] === '') lines.pop();
    return lines.join('\n') + (lines.length ? '\n' : '');
  }

  root.formatSql = formatSql;
})(typeof module !== 'undefined' && module.exports ? module.exports : window);
