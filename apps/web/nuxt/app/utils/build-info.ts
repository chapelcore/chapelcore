// build-info.ts — the version and codename this build was made from.
// Why: version.json is the only place the version is written (CLAUDE.md "Versioning",
// DOMAIN-MODEL universal rule 12); the bundler copies it into the build, so the page never
// hardcodes it.
// How it connects: Nuxt auto-imports `buildInfo` into the app's components; the API's own
// version comes from its health reply.
import versionFile from '../../../../../version.json';

export interface BuildInfo {
  readonly version: string;
  readonly codename: string | null;
}

export const buildInfo: BuildInfo = {
  version: versionFile.version,
  codename: versionFile.codename,
};
