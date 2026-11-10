import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

const requiredFiles = [
  'api/index.php',
  'includes/auth.php',
  'includes/database.php',
  'farm_journal_api.php',
  'database/upgrade_account_safety.sql',
  'database/upgrade_farm_journal.sql',
  'database/upgrade_farm_enterprises.sql',
];

const config = JSON.parse(readFileSync('vercel.json', 'utf8'));
if (config.functions?.['api/index.php']?.runtime !== 'vercel-php@0.9.0') {
  throw new Error('Vercel PHP runtime is missing from vercel.json.');
}

const missingFiles = requiredFiles.filter((path) => !existsSync(path));
if (missingFiles.length) {
  throw new Error(`Build preflight is missing required files: ${missingFiles.join(', ')}`);
}

if (process.env.VERCEL_ENV === 'production') {
  if (!process.env.DATABASE_URL) throw new Error('Vercel production build is missing DATABASE_URL; refusing to deploy before the database migration.');
  const migration = spawnSync(process.execPath, ['scripts/apply_neon_schema.mjs'], { stdio: 'inherit', env: process.env });
  if (migration.error) throw migration.error;
  if (migration.status !== 0) throw new Error('Production database migration failed; deployment stopped.');
}

const outputPath = resolve('public');
if (outputPath !== resolve(process.cwd(), 'public')) throw new Error('Unexpected Vercel output directory.');
rmSync(outputPath, { recursive: true, force: true });
mkdirSync('public/assets/css', { recursive: true });
mkdirSync('public/assets/js', { recursive: true });
cpSync('assets/css', 'public/assets/css', { recursive: true, force: true });
cpSync('assets/js', 'public/assets/js', { recursive: true, force: true });
cpSync('Images', 'public/Images', { recursive: true, force: true });
for (const path of ['agrisense-logo.png', 'farm-journal-ui.html', 'manifest.webmanifest']) {
  cpSync(`assets/${path}`, `public/assets/${path}`, { force: true });
}
cpSync('farm_journal_offline.html', 'public/farm_journal_offline.html', { force: true });
cpSync('service-worker.js', 'public/service-worker.js', { force: true });

console.log('agriSense deployment preflight passed.');
