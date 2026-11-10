import { existsSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import pg from 'pg';

const { Client } = pg;
if (existsSync('.env.local')) process.loadEnvFile('.env.local');
const databaseUrl = process.env.DATABASE_URL;
if (!databaseUrl) throw new Error('Set DATABASE_URL to the production Neon connection string.');

const migrations = [
  'database/neon_schema.sql',
  'database/upgrade_account_safety.sql',
  'database/upgrade_farm_journal.sql',
  'database/upgrade_journal_domains.sql',
  'database/upgrade_farm_enterprises.sql',
];

const client = new Client({ connectionString: databaseUrl, ssl: { rejectUnauthorized: false } });
try {
  await client.connect();
  await client.query('BEGIN');
  for (const path of migrations) {
    const sql = await readFile(path, 'utf8');
    const statements = sql.split(/;\s*(?:\r?\n|$)/)
      .map((part) => part.replace(/^\s*--.*$/gm, '').trim())
      .filter(Boolean);
    for (const statement of statements) await client.query(statement);
  }
  await client.query('COMMIT');

  const required = ['users', 'auth_attempts', 'auth_sessions', 'farm_tasks', 'farm_records', 'field_cases', 'animal_records', 'farm_inventory', 'farm_stock_movements', 'farm_journal_preferences', 'field_harvests', 'farm_enterprises'];
  const result = await client.query('SELECT table_name FROM information_schema.tables WHERE table_schema = $1', ['public']);
  const existing = new Set(result.rows.map((row) => row.table_name));
  const missing = required.filter((table) => !existing.has(table));
  if (missing.length) throw new Error(`Migration verification failed for: ${missing.join(', ')}`);
  console.log('Neon schema migrations applied and verified.');
} catch (error) {
  try { await client.query('ROLLBACK'); } catch {}
  throw error;
} finally {
  await client.end().catch(() => {});
}
