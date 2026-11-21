// check-colors.mjs — fails the build if an app uses a color of its own.
// Why: CLAUDE.md step 8 requires the WCAG AA contrast check for UI changes. Until the Phase 2
// theme brings the token-pair contrast gate, the apps may use only system colors that browsers
// keep readable in light and dark mode (phase-1.md §6.5 E6). The check is an allow-list: in a
// property that takes a color, every word must be an allowed system color or a known keyword,
// so a color it has never heard of fails too, and so does a color computed in code, which it
// can't see. It errs on the side of failing: text that merely looks like a color declaration
// (a custom property holding a label, "Background: #105" inside a script string) fails too.
// It does not see colors behind computed keys (`{ [key]: 'red' }`), CSS escapes, images, or
// the browser frame's `theme-color`.
// How it connects: run as `pnpm check:colors` in the CI `contrast` job and locally; checks
// the .vue, .html, .svg, .css, .ts, .js and .mjs files git tracks under apps/*/nuxt.
// Tested by check-colors.test.mjs.

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ALLOWED_COLORS = [
  'Canvas',
  'CanvasText',
  'LinkText',
  'VisitedText',
  'ButtonFace',
  'ButtonText',
  'ButtonBorder',
  'Field',
  'FieldText',
];
// Words that may appear in a color-taking property without being a color.
const KEYWORDS = `currentcolor transparent inherit initial unset revert none auto
solid dashed dotted double groove ridge inset outset hidden thin medium thick
underline overline line-through wavy dot circle sesame filled open
no-repeat repeat repeat-x repeat-y space round cover contain center top bottom left right
fixed scroll local border-box padding-box content-box text to`.split(/\s+/);
const ALLOWED_WORDS = new Set([...ALLOWED_COLORS.map((c) => c.toLowerCase()), ...KEYWORDS]);
// Functions whose arguments are checked like the rest of the value. Any other function
// (rgb(), gradients, color-mix(), drop-shadow(), v-bind(), …) fails; url() is skipped.
const ALLOWED_FUNCTIONS = new Set(['var', 'calc', 'min', 'max', 'clamp']);
// Properties that take a color: custom and preprocessor properties, every *color, these
// names, and these names followed by "-…" (border-inline-start, mask-image, …).
const COLOR_NAMES = new Set(['fill', 'stroke', 'box-shadow', 'text-shadow', 'filter']);
const COLOR_FAMILIES = [
  'background',
  'border',
  'outline',
  'text-decoration',
  'text-emphasis',
  'column-rule',
  'mask',
];
// SVG and old HTML attributes that take a color.
const COLOR_ATTRIBUTES = new Set([
  'fill',
  'stroke',
  'color',
  'bgcolor',
  'stop-color',
  'flood-color',
  'lighting-color',
]);
// Put in place of `${…}` in a template literal: it is computed, so it fails as a color.
const COMPUTED = 'computed-in-code';

const HINT =
  `use only the allowed system colors (${ALLOWED_COLORS.join(', ')}), currentColor, ` +
  'transparent or inherit (phase-1.md §6.5 E6)';

/** Does this property take a color? CSS names are matched as written, in any case. */
function takesColor(property) {
  const p = property.toLowerCase();
  return (
    p.startsWith('--') ||
    p.startsWith('$') ||
    p.startsWith('-webkit-text-') ||
    p.endsWith('color') ||
    COLOR_NAMES.has(p) ||
    COLOR_FAMILIES.some((family) => p === family || p.startsWith(`${family}-`))
  );
}

/** A key written in code (backgroundColor, 'border-color') as its CSS property name. */
const cssName = (key) => (key.startsWith('--') ? key : key.replace(/[A-Z]/g, (c) => `-${c}`));

/** The problems in one color-taking value: [offset within the value, what was found]. */
function valueProblems(value) {
  const found = [];
  const v = value.replace(/!important/gi, (m) => ' '.repeat(m.length));
  let i = 0;
  while (i < v.length) {
    const c = v[i];
    if (/[\s,/()]/.test(c)) {
      i += 1;
      continue;
    }
    if (c === '"' || c === "'") {
      const end = v.indexOf(c, i + 1);
      i = end === -1 ? v.length : end + 1;
      continue;
    }
    const rest = v.slice(i, i + 200);
    const hex = /^#[0-9a-f]{3,8}\b/i.exec(rest);
    if (hex) {
      found.push([i, hex[0]]);
      i += hex[0].length;
      continue;
    }
    const fn = /^(-?[a-z][\w-]*)\(/i.exec(rest);
    if (fn) {
      const name = fn[1].toLowerCase();
      if (ALLOWED_FUNCTIONS.has(name)) {
        i += fn[0].length; // its arguments are checked like the rest of the value
        continue;
      }
      let depth = 0;
      let j = i + fn[1].length;
      for (; j < v.length; j += 1) {
        if (v[j] === '(') depth += 1;
        else if (v[j] === ')' && --depth === 0) break;
      }
      if (name !== 'url') found.push([i, `${fn[1]}()`]);
      i = j + 1;
      continue;
    }
    const number = /^[+-]?\.?\d[\w.%]*/.exec(rest);
    if (number) {
      i += number[0].length;
      continue;
    }
    const word = /^-{0,2}[a-z][\w-]*/i.exec(rest);
    if (word) {
      if (!word[0].startsWith('--') && !ALLOWED_WORDS.has(word[0].toLowerCase())) {
        found.push([i, word[0]]);
      }
      i += word[0].length;
      continue;
    }
    found.push([i, c]);
    i += 1;
  }
  return found;
}

/** Calls report(offset, property, value) for each declaration in CSS (rules or a list). */
function cssDeclarations(css, report) {
  const text = css.replace(/\/\*[\s\S]*?\*\//g, (c) => c.replace(/[^\n]/g, ' '));
  let start = 0;
  let depth = 0;
  let quote = null;
  const flush = (end) => {
    const statement = text.slice(start, end);
    const colon = statement.indexOf(':');
    if (colon === -1) return;
    const property = statement.slice(0, colon).trim();
    if (!/^(--|\$)?[A-Za-z-][\w-]*$/.test(property)) return;
    const raw = statement.slice(colon + 1);
    report(start + colon + 1 + (raw.length - raw.trimStart().length), property, raw.trim());
  };
  for (let i = 0; i < text.length; i += 1) {
    const c = text[i];
    if (quote) {
      if (c === quote) quote = null;
    } else if (c === '"' || c === "'") {
      quote = c;
    } else if (c === '(') {
      depth += 1;
    } else if (c === ')') {
      depth = Math.max(0, depth - 1);
    } else if (depth === 0 && (c === ';' || c === '}')) {
      flush(i);
      start = i + 1;
    } else if (depth === 0 && c === '{') {
      start = i + 1; // what came before was a selector or an at-rule
    }
  }
  flush(text.length);
}

function checkCss(css, offset, add) {
  cssDeclarations(css, (at, property, value) => {
    if (!takesColor(property)) return;
    for (const [where, what] of valueProblems(value)) {
      add(offset + at + where, what, `${property}: ${value}`);
    }
  });
}

/**
 * Reads code once, without regular expressions that could backtrack: returns the code with
 * comments blanked, and its string literals (offset of the opening quote, text, and whether
 * it is a template literal with `${…}` parts, which are replaced by COMPUTED).
 */
function scanCode(code) {
  const chars = [...code];
  const strings = [];
  let i = 0;
  while (i < chars.length) {
    const c = chars[i];
    const next = chars[i + 1];
    if (c === '/' && next === '/') {
      while (i < chars.length && chars[i] !== '\n') chars[i++] = ' ';
    } else if (c === '/' && next === '*') {
      while (i < chars.length && !(chars[i] === '*' && chars[i + 1] === '/')) {
        if (chars[i] !== '\n') chars[i] = ' ';
        i += 1;
      }
      if (i < chars.length) chars[i] = chars[i + 1] = ' ';
      i += 2;
    } else if (c === '"' || c === "'" || c === '`') {
      const start = i;
      let text = '';
      i += 1;
      while (i < chars.length && chars[i] !== c && (c === '`' || chars[i] !== '\n')) {
        if (chars[i] === '\\') {
          text += chars[i + 1] ?? '';
          i += 2;
        } else if (c === '`' && chars[i] === '$' && chars[i + 1] === '{') {
          let depth = 0;
          for (; i < chars.length; i += 1) {
            if (chars[i] === '{') depth += 1;
            else if (chars[i] === '}' && --depth === 0) break;
          }
          text += COMPUTED;
          i += 1;
        } else {
          text += chars[i++];
        }
      }
      strings.push({ start, end: i, text });
      i += 1;
    } else {
      i += 1;
    }
  }
  return { masked: chars.join(''), strings };
}

// A value in code that is not a color: a number, a boolean, a function, or a type
// (`color: string`, `filter: Filter)`).
const NOT_A_COLOR = [
  /^-?\.?\d/,
  /^(true|false)\b/,
  /^(\([^()]*\)|[\w$]+)\s*=>/,
  /^function\b/,
  /^(string|number|boolean|null|undefined|unknown|never)\b/,
  /^[A-Z][a-z]\w*(\[\])?\s*([|;,)}]|$)/,
];

/** Checks script code (.ts, .js, a Vue <script>, a bound attribute) found at `offset`. */
function checkCode(code, offset, add) {
  const { masked, strings } = scanCode(code);
  const stringAt = new Map(strings.map((s) => [s.start, s]));
  // CSS written inside strings, e.g. a head style in nuxt.config.ts
  for (const s of strings) {
    if (s.text.includes(':')) checkCss(s.text, offset + s.start + 1, add);
  }
  // a color-taking property set to `at` in the code
  const checkValue = (property, at) => {
    if (!takesColor(cssName(property))) return;
    const lead = /^\s*/.exec(masked.slice(at, at + 200))[0].length;
    const s = stringAt.get(at + lead);
    if (s !== undefined) {
      for (const [where, what] of valueProblems(s.text)) {
        add(offset + s.start + 1 + where, what, `${property}: ${s.text}`);
      }
      return;
    }
    const expression = /^[^,;}\n]*/.exec(masked.slice(at + lead, at + lead + 200))[0].trim();
    if (NOT_A_COLOR.some((pattern) => pattern.test(expression))) return;
    add(
      offset + at + lead,
      expression,
      `${property}: ${expression} (a color computed in code can't be checked; ` +
        'write an allowed system color as a string)',
    );
  };
  // object properties: { color: 'red' }, { 'background-color': x }
  for (const m of masked.matchAll(/(?<=^|[{,(\s])(['"]?)([A-Za-z$-][\w$-]*)\1[ \t]*:(?!:)/gm)) {
    checkValue(m[2], m.index + m[0].length);
  }
  // el.style.color = …
  for (const m of masked.matchAll(/\.style\.([A-Za-z]+)[ \t]*=(?!=)/g)) {
    checkValue(m[1], m.index + m[0].length);
  }
  // el.style.setProperty('color', …)
  for (const m of masked.matchAll(/\.setProperty\(\s*/g)) {
    const name = stringAt.get(m.index + m[0].length);
    if (name === undefined) continue;
    const comma = /^\s*,/.exec(masked.slice(name.end + 1, name.end + 50));
    if (comma) checkValue(name.text, name.end + 1 + comma[0].length);
  }
}

/** Checks a template (.vue, .html, .svg): <style>, <script>, and style and color attributes. */
function checkMarkup(text, add) {
  const blank = (s) => s.replace(/[^\n]/g, ' ');
  const markup = text.replace(/<!--[\s\S]*?-->/g, blank);
  for (const m of markup.matchAll(/<(style|script)\b([^>]*)>([\s\S]*?)<\/\1>/gi)) {
    const bodyAt = m.index + m[0].indexOf('>') + 1;
    if (m[1].toLowerCase() === 'script') {
      checkCode(m[3], bodyAt, add);
      continue;
    }
    const lang = /\blang\s*=\s*["']([^"']+)["']/.exec(m[2]);
    if (lang && lang[1].toLowerCase() !== 'css') {
      add(
        m.index,
        `lang="${lang[1]}"`,
        'a <style> in another language; write plain CSS so its colors can be checked',
      );
      continue;
    }
    checkCss(m[3], bodyAt, add);
  }
  const outside = markup.replace(/<(style|script)\b[^>]*>[\s\S]*?<\/\1>/gi, blank);
  const attribute = /\s(:|v-bind:)?([\w-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+))/g;
  for (const m of outside.matchAll(attribute)) {
    const value = m[3] ?? m[4] ?? m[5];
    const at = m.index + m[0].length - value.length - (m[5] === undefined ? 1 : 0);
    const bound = m[1] !== undefined;
    const name = m[2].toLowerCase();
    if (name === 'v-bind') {
      checkCode(value, at, add); // v-bind="{ style: …, fill: … }"
    } else if (name === 'style') {
      if (bound) checkCode(value, at, add);
      else checkCss(value, at, add);
    } else if (COLOR_ATTRIBUTES.has(name)) {
      if (bound) {
        checkCode(`${name}: ${value}`, at - name.length - 2, add);
      } else {
        for (const [where, what] of valueProblems(value))
          add(at + where, what, `${name}="${value}"`);
      }
    }
  }
}

export function checkColors(source, file) {
  const text = source.replace(/^﻿/, '');
  const lineStarts = [0];
  for (let i = 0; i < text.length; i += 1) if (text[i] === '\n') lineStarts.push(i + 1);
  const lineAt = (offset) => {
    let low = 0;
    let high = lineStarts.length - 1;
    while (low < high) {
      const mid = (low + high + 1) >> 1;
      if (lineStarts[mid] <= offset) low = mid;
      else high = mid - 1;
    }
    return low + 1;
  };
  const problems = [];
  const seen = new Set();
  const add = (offset, what, context) => {
    const line = lineAt(offset);
    const key = `${line}\u0000${what.toLowerCase()}`;
    if (seen.has(key)) return;
    seen.add(key);
    const where = context.replace(/\s+/g, ' ');
    problems.push({ line, message: `${file}:${line}: "${what}" in ${where}; ${HINT}` });
  };
  if (/\.(vue|html|svg)$/.test(file)) checkMarkup(text, add);
  else if (file.endsWith('.css')) checkCss(text, 0, add);
  else checkCode(text, 0, add);
  return problems.sort((a, b) => a.line - b.line).map((p) => p.message);
}

function main() {
  const root = fileURLToPath(new URL('..', import.meta.url));
  const patterns = ['vue', 'html', 'svg', 'css', 'ts', 'js', 'mjs'].map(
    (ext) => `:(glob)apps/*/nuxt/**/*.${ext}`,
  );
  const files = execFileSync('git', ['ls-files', '-z', '--', ...patterns], {
    cwd: root,
    encoding: 'utf8',
  })
    .split('\0')
    .filter(Boolean);
  if (files.length === 0) {
    console.error('No app files found under apps/*/nuxt, so nothing would be checked.');
    process.exitCode = 1;
    return;
  }
  const problems = files.flatMap((file) =>
    checkColors(readFileSync(join(root, file), 'utf8'), file),
  );
  if (problems.length > 0) {
    console.error(problems.join('\n'));
    console.error(`\n${problems.length} color(s) of the apps' own. See CLAUDE.md step 8.`);
    process.exitCode = 1;
  } else {
    console.log(`No colors of their own in the apps (${files.length} files checked).`);
  }
}

// Run the repo check only when executed directly, not when the tests import it.
if (process.argv[1] === fileURLToPath(import.meta.url)) main();
