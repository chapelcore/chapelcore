// types.test.ts — the generated types are up to date and accept every valid fixture.
// Why: the types must never drift from the contract (phase-1.md §6.4). The first two tests
// fail when someone edits a generated file by hand or changes a schema without
// regenerating; the third proves the types accept all the data the contract calls valid;
// the fourth, checked by the compiler, proves they are read-only (universal rule 8).
// How it connects: runs scripts/generate-types.ts in memory and compares with the
// committed files; type-checks the contract's fixtures/valid/ with the TypeScript compiler.
import { mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import ts from 'typescript';
import { describe, expect, it } from 'vitest';
import {
  CONTRACT_FILE,
  FIXTURES_DIR,
  generateTypes,
  readSchemas,
  TYPES_FILE,
} from '../scripts/generate-types.js';
import type { HealthStatus } from '../src/index.js';

const REGENERATE = 'out of date: run `pnpm --filter @chapelcore/core generate`';

describe('generated types', () => {
  it('src/generated/contract.ts matches the schemas', async () => {
    const { contract } = await generateTypes();
    expect(readFileSync(CONTRACT_FILE, 'utf8'), REGENERATE).toBe(contract);
  });

  it('src/types.ts matches the schemas', async () => {
    const { types } = await generateTypes();
    expect(readFileSync(TYPES_FILE, 'utf8'), REGENERATE).toBe(types);
  });

  // The TypeScript compiler takes about half a second here, and much longer on a busy machine.
  it('accept every valid fixture', { timeout: 30_000 }, () => {
    // One TypeScript file with each fixture as a typed constant: `const f0: T.HealthStatus = {…};`
    const lines = [
      `import type * as T from ${JSON.stringify(TYPES_FILE.replace(/\.ts$/, '.js'))};`,
    ];
    let count = 0;
    for (const schema of readSchemas()) {
      const dir = join(FIXTURES_DIR, 'valid', schema.name);
      for (const file of readdirSync(dir)
        .filter((name) => name.endsWith('.json'))
        .sort()) {
        const json = readFileSync(join(dir, file), 'utf8');
        lines.push(
          `// ${schema.name}/${file}`,
          `export const f${count}: T.${schema.title} = ${json};`,
        );
        count += 1;
      }
    }

    const dir = mkdtempSync(join(tmpdir(), 'chapelcore-fixtures-'));
    try {
      const file = join(dir, 'fixtures.ts');
      writeFileSync(file, lines.join('\n'));
      const program = ts.createProgram([file], {
        strict: true,
        exactOptionalPropertyTypes: true,
        noEmit: true,
        module: ts.ModuleKind.NodeNext,
        moduleResolution: ts.ModuleResolutionKind.NodeNext,
        target: ts.ScriptTarget.ES2024,
        skipLibCheck: true,
        types: [],
      });
      const problems = ts
        .getPreEmitDiagnostics(program)
        .map((diagnostic) => ts.flattenDiagnosticMessageText(diagnostic.messageText, '\n'));
      expect(problems).toEqual([]);
      expect(count).toBeGreaterThan(0);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  });

  it('are read-only at every depth', () => {
    // Checked by the compiler (pnpm type-check): each line below must be a type error.
    const change = (health: HealthStatus): void => {
      // @ts-expect-error: fields are read-only
      health.status = 'down';
      // @ts-expect-error: nested fields are read-only
      health.version.codename = null;
      // @ts-expect-error: lists are read-only
      health.checks[0] = { name: 'extra', status: 'ok', message: null };
    };
    expect(change).toBeTypeOf('function');
  });
});
