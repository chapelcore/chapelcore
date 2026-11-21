// check-colors.test.mjs — unit tests for scripts/check-colors.mjs.
// Why: until the Phase 2 theme brings the WCAG AA contrast gate, this check is what keeps the
// apps readable in light and dark mode (CLAUDE.md step 8, phase-1.md §6.5 E6). A check that
// misses a color would let an unreadable one through, so it is tested itself.
// How it connects: run with Node's built-in test runner (`pnpm test:scripts`) in the CI
// `test-js` job; the check runs over the apps' files in the `contrast` job.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkColors } from './check-colors.mjs';

const lines = (...text) => text.join('\n');
const count = (text, file = 'a.css') => checkColors(text, file).length;

// ── CSS: what passes ────────────────────────────────────────────────────────

test('css: the allowed system colors and keywords pass', () => {
  const text = lines(
    ':root { color-scheme: light dark; }',
    'body { color: CanvasText; background: Canvas; }',
    'a { color: LinkText; } a:visited { color: VisitedText; }',
    'button { color: ButtonText; background-color: ButtonFace; border: 1px solid ButtonBorder; }',
    'input { color: FieldText; background: Field; outline: 2px solid currentColor; }',
    'p { border-color: transparent; color: inherit !important; text-decoration: underline; }',
    'p { box-shadow: 0 0 0 2px CanvasText, inset 0 0 0 1px Canvas; }',
    'p { background: url("red.png") no-repeat Canvas; border-top: var(--line, 1px solid ButtonBorder); }',
  );
  assert.deepEqual(checkColors(text, 'apps/admin/nuxt/app/assets/main.css'), []);
});

test('css: system color names may be written in any case', () => {
  assert.equal(count('p { color: canvastext; background: CANVAS; }'), 0);
});

test('css: comments, selectors, media queries and properties without color pass', () => {
  const text = lines(
    '/* was color: #c00; background: red; */',
    '@media (prefers-color-scheme: dark) { p { color: CanvasText; } }',
    'p { font: 1rem system-ui; transition: color 0.2s, background 0.2s; }',
    'a:visited, button:active { color: VisitedText; }',
  );
  assert.equal(count(text), 0);
});

// ── CSS: what fails ─────────────────────────────────────────────────────────

test('css: hex colors fail in any case, naming the file, the line and the color', () => {
  const text = lines('p {', '  color: #fff;', '  background: #AbCdEf;', '}');
  const problems = checkColors(text, 'apps/web/nuxt/app/x.css');
  assert.equal(problems.length, 2);
  assert.match(problems[0], /^apps\/web\/nuxt\/app\/x\.css:2: .*"#fff"/);
  assert.match(problems[1], /^apps\/web\/nuxt\/app\/x\.css:3: .*"#AbCdEf"/);
});

test('css: color functions and gradients fail, even around allowed colors', () => {
  for (const value of [
    'rgb(0 0 0)',
    'RGBA(0, 0, 0, 0.5)',
    'hsl(120 50% 50%)',
    'hwb(0 0% 0%)',
    'lab(50% 0 0)',
    'lch(50% 0 0)',
    'oklab(0.5 0 0)',
    'oklch(0.5 0.1 120)',
    'color(srgb 1 0 0)',
    'color-mix(in srgb, Canvas, CanvasText)',
    'light-dark(Canvas, CanvasText)',
    'linear-gradient(Canvas, CanvasText)',
  ]) {
    assert.equal(count(`p { background: ${value}; }`), 1, value);
  }
});

test('css: named colors fail, in any case and with !important', () => {
  for (const value of ['red', 'Red', 'rebeccapurple', 'gray', 'white', 'black', 'red !important']) {
    assert.equal(count(`p { color: ${value}; }`), 1, value);
  }
});

test('css: system colors that are not on the allow-list fail', () => {
  // GrayText is about 3.9:1 on white; the others vary by browser or are deprecated.
  for (const value of [
    'GrayText',
    'Mark',
    'Highlight',
    'HighlightText',
    'ActiveText',
    'AccentColor',
    'SelectedItem',
    'Window',
    'WindowText',
    'ThreeDFace',
    'Background',
    '-webkit-link',
  ]) {
    assert.equal(count(`p { color: ${value}; }`), 1, value);
  }
});

test('css: any word that is not an allowed color or a known keyword fails (allow-list)', () => {
  assert.equal(count('p { color: brandblue; }'), 1);
});

test('css: a color after a comma, in a later shadow or in a var() fallback fails', () => {
  assert.equal(count('p { box-shadow: 0 0 1px Canvas, 0 0 2px red; }'), 1);
  assert.equal(count('p { color: var(--x, red); }'), 1);
  assert.equal(count('p { background: url("a.svg") #fff; }'), 1);
});

test('css: a declaration over several lines is checked, reporting the line of the color', () => {
  const text = lines('p {', '  box-shadow:', '    0 0 1px Canvas,', '    0 0 2px red;', '}');
  const problems = checkColors(text, 'a.css');
  assert.equal(problems.length, 1);
  assert.match(problems[0], /^a\.css:4: .*"red"/);
});

test('css: minified CSS is checked', () => {
  assert.equal(count('p{color:red}a{background:#000}'), 2);
});

test('css: colors in shorthands, longhands, filters and custom properties fail', () => {
  const text = lines(
    'p { border: 1px solid gray; border-inline-start: 1px solid gray; }',
    ':root { --accent: #0a0; --text: red; }',
    'p { text-shadow: 0 0 2px black; filter: drop-shadow(0 0 2px CanvasText); }',
    'p { -webkit-text-stroke: 1px red; text-emphasis: dot green; outline-color: blue; }',
  );
  // gray once (one problem per color per line), #0a0, red, black, drop-shadow(), red, green, blue
  assert.equal(count(text), 8);
});

// ── Vue single-file components ──────────────────────────────────────────────

test('vue: a color in a <style> block fails with its line number in the file', () => {
  const sfc = lines(
    '<template>',
    '  <p>Color: red. Background: #105</p>',
    '</template>',
    '',
    '<style scoped>',
    'p {',
    '  color: red;',
    '}',
    '</style>',
  );
  const problems = checkColors(sfc, 'apps/admin/nuxt/app/app.vue');
  assert.equal(problems.length, 1);
  assert.match(problems[0], /^apps\/admin\/nuxt\/app\/app\.vue:7: .*"red"/);
});

test('vue: page text, class names and plain strings in the script pass', () => {
  const sfc = lines(
    '<script setup lang="ts">',
    "const tone = 'red';",
    "const label = 'Gold star: all systems OK (see issue #105)';",
    '</script>',
    '<template>',
    '  <p class="red">Color: red. Background: #105</p>',
    '</template>',
  );
  assert.equal(count(sfc, 'x.vue'), 0);
});

test('vue: style attributes, :style and v-bind:style bindings are checked', () => {
  const sfc = lines(
    '<template>',
    '  <p style="color: navy">a</p>',
    `  <p :style="{ backgroundColor: '#000' }">b</p>`,
    `  <p v-bind:style="{ 'border-color': 'red' }">c</p>`,
    `  <p :style="{ color: 'CanvasText' }">d</p>`,
    '</template>',
  );
  const problems = checkColors(sfc, 'x.vue');
  assert.equal(problems.length, 3);
  assert.match(problems[0], /^x\.vue:2: .*"navy"/);
  assert.match(problems[1], /^x\.vue:3: .*"#000"/);
  assert.match(problems[2], /^x\.vue:4: .*"red"/);
});

test('vue: a color computed in code fails, because it cannot be checked', () => {
  const sfc = lines(
    '<script setup lang="ts">',
    "const style = { color: tone, borderColor: dark ? 'white' : 'black' };",
    '</script>',
    '<template>',
    '  <p :style="{ color: tone }">a</p>',
    '</template>',
    '<style>',
    'p { color: v-bind(tone); }',
    '</style>',
  );
  assert.equal(count(sfc, 'x.vue'), 4);
});

test('vue: color attributes in the template are checked', () => {
  const sfc = lines(
    '<template>',
    '  <svg><path fill="red" stroke="#000" /><path fill="none" stroke="currentColor" /></svg>',
    '  <svg><path :fill="tone" /></svg>',
    '</template>',
  );
  assert.equal(count(sfc, 'x.vue'), 3);
});

test('vue: a <style> in another language fails, since it cannot be checked', () => {
  const sfc = lines('<style lang="scss">', '$accent: Canvas;', '</style>');
  const problems = checkColors(sfc, 'x.vue');
  assert.equal(problems.length, 1);
  assert.match(problems[0], /^x\.vue:1: .*plain CSS/);
});

// ── TypeScript ──────────────────────────────────────────────────────────────

test('ts: CSS inside a string and color properties of objects are checked; messages pass', () => {
  const ts = lines(
    'export default defineNuxtConfig({',
    "  app: { head: { style: [{ innerHTML: 'body { background: #fafafa }' }] } },",
    '});',
    "const message = 'Gold star: all systems OK (see issue #105)';",
    "const style = { 'background-color': 'red', color: 'CanvasText' };",
  );
  const problems = checkColors(ts, 'apps/web/nuxt/nuxt.config.ts');
  assert.equal(problems.length, 2);
  assert.match(problems[0], /^apps\/web\/nuxt\/nuxt\.config\.ts:2: .*"#fafafa"/);
  assert.match(problems[1], /^apps\/web\/nuxt\/nuxt\.config\.ts:5: .*"red"/);
});

test('a problem says how to fix it', () => {
  const [problem] = checkColors('p { color: red; }', 'a.css');
  assert.match(problem, /system colors/);
  assert.match(problem, /CanvasText/);
});

// ── Added after the code review ─────────────────────────────────────────────

test('attributes in single quotes or without quotes are checked', () => {
  const sfc = lines(
    '<template>',
    "  <p style='color: red'>a</p>",
    "  <svg><rect fill='#f00' /><rect stroke=blue /></svg>",
    '</template>',
  );
  assert.equal(count(sfc, 'x.vue'), 3);
});

test('an SVG file is checked like a template', () => {
  const svg = "<svg xmlns='http://www.w3.org/2000/svg'><path fill='#f00' d='M0 0h1v1z'/></svg>";
  assert.equal(count(svg, 'apps/web/nuxt/public/logo.svg'), 1);
});

test('a property name in capitals is still a property', () => {
  assert.equal(count('p { COLOR: red; BACKGROUND: blue; }'), 2);
});

test('a template literal that computes part of a declaration fails', () => {
  assert.equal(count('const s = `color: ${tone}`;', 'x.ts'), 1);
  const sfc = lines('<template>', '  <p :style="`color: ${tone}`">a</p>', '</template>');
  assert.equal(count(sfc, 'x.vue'), 1);
});

test('style set from code fails: .style.x =, setProperty() and a v-bind object', () => {
  const ts = lines(
    "el.style.color = 'red';",
    "el.style.setProperty('background', '#000');",
    'el.style.backgroundColor = tone;',
  );
  assert.equal(count(ts, 'x.ts'), 3);
  const sfc = lines('<template>', `  <p v-bind="{ style: 'color: red' }">a</p>`, '</template>');
  assert.equal(count(sfc, 'x.vue'), 1);
});

test('code that only looks like a color property passes', () => {
  const ts = lines(
    'interface Look { color: string; background: string | null }',
    'function pick(items: Item[], filter: Filter): void {}',
    'const flags = { masked: true, outlined: false, borderless: true, outlineOffset: 2 };',
    "const secret = { maskedValue: '••••' };",
    "const style = { color: 'CanvasText' as const };",
  );
  assert.deepEqual(checkColors(ts, 'x.ts'), []);
  assert.equal(
    count('<template><!-- style="color: red" was removed --><p /></template>', 'x.vue'),
    0,
  );
});

test('a long line with an unclosed string full of backslashes is still quick', () => {
  const start = Date.now();
  checkColors(
    `const pattern = '${'\\\\'.repeat(40)}x; const style = { color: '${'\\\\'.repeat(40)}x`,
    'x.ts',
  );
  assert.ok(Date.now() - start < 1000, `took ${Date.now() - start} ms`);
});
