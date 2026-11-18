// support.ts — where the TS core's tests find the contract's shared files.
// Why: several tests read the contract's cases and fixtures; one place resolves the
// folder, through the declared @chapelcore/contract dependency rather than a relative path.
// How it connects: imported by tests/*.test.ts. scripts/generate-types.ts finds the
// schemas the same way.
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const CONTRACT_ROOT = dirname(
  fileURLToPath(import.meta.resolve('@chapelcore/contract/package.json')),
);

/** Every `*.json` file in one of the contract's folders, parsed, sorted by file name. */
export function readContractJson(folder: string): { name: string; data: unknown }[] {
  const dir = join(CONTRACT_ROOT, folder);
  return readdirSync(dir)
    .filter((file) => file.endsWith('.json'))
    .sort()
    .map((file) => ({
      name: file.slice(0, -'.json'.length),
      data: JSON.parse(readFileSync(join(dir, file), 'utf8')) as unknown,
    }));
}
