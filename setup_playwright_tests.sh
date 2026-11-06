#!/usr/bin/env bash
# Creates the Playwright test files under playwright/test/ and the E2E seeder.
# Run from the PESO_CONNECT project root: bash setup_playwright_tests.sh
set -e

mkdir -p playwright/test/helpers
mkdir -p database/seeders

# ───────────────────────── playwright/playwright.config.ts ─────────────────────────
cat > playwright/playwright.config.ts << 'EOF'
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './test',
  timeout: 60_000,
  fullyParallel: false,   // tests share one database, so run them one at a time
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'php artisan serve --env=e2e --host=127.0.0.1 --port=8000',
    cwd: '..',                       // Laravel project root
    url: 'http://127.0.0.1:8000/login',
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
  },
});
EOF

# ───────────────────────── playwright/test/helpers/selectors.ts ─────────────────────────
cat > playwright/test/helpers/selectors.ts << 'EOF'
import { Locator, Page } from '@playwright/test';

// ── ASSUMPTIONS: adjust these to match your Blade files. ──────────────
export const wm = (prop: string) =>
  `[wire\\:model="${prop}"], [wire\\:model\\.live="${prop}"]`;

export const SEL = {
  email: 'input[name="email"]',
  password: 'input[name="password"]',
  submit: 'button[type="submit"]',
  scanButton: /Scan Active Applicants/,
  reviewButton: /^Review$/,
  retainButton: /Retain Both/,
  deleteNewButton: /Delete New Record/,
  backToQueue: /Back to Queue/,
  save: /^Save$/,
  cancel: /^Cancel$/,
  edit: /^Edit$/,
  delete: /^Delete$/,
  deactivate: /^Deactivate$/,
  view: /^View$/,
};

export const rowFor = (page: Page, text: string): Locator =>
  page.locator('tbody tr', { hasText: text });

export const modal = (page: Page): Locator => page.locator('.fixed.inset-0');

export const badge = (row: Locator, label: string): Locator =>
  row.locator('span', { hasText: new RegExp(`^${label}$`) });

export const searchBox = (page: Page): Locator =>
  page.locator('input[placeholder*="earch"]');
EOF

# ───────────────────────── playwright/test/helpers/app.ts ─────────────────────────
cat > playwright/test/helpers/app.ts << 'EOF'
import { execFileSync, execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';
import { expect, Page } from '@playwright/test';

// playwright/test/helpers → up three levels = Laravel project root
export const APP_ROOT = path.resolve(__dirname, '..', '..', '..');
export const PASSWORD = 'password';
export const USERS = { staff: 'staff@test.local', noRole: 'norole@test.local' };

// ── ASSUMPTIONS: adjust these to your routes. ─────────────────────────
export const ROUTES = {
  login: '/login',
  duplicates: '/duplicate-review',
  applicants: '/applicants',
  barangays: '/barangays',
  municipalities: '/municipalities',
  skillCategories: '/skill-categories',
  skills: '/skills',
};

const phpStr = (s: string) => s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

function run(code: string): any {
  const out = execFileSync(
    'php',
    ['artisan', 'tinker', '--env=e2e', '--execute', code],
    { cwd: APP_ROOT, encoding: 'utf8' },
  );
  const m = out.match(/E2E_START([\s\S]*?)E2E_END/);
  if (!m) throw new Error(`tinker returned no result:\n${out}`);
  return JSON.parse(m[1]);
}

export function query<T = any>(expr: string): T {
  return run(`$__r = ${expr}; echo 'E2E_START' . json_encode($__r) . 'E2E_END';`);
}

export function exec(code: string): void {
  run(`${code}; echo 'E2E_START' . json_encode(true) . 'E2E_END';`);
}

export function resetDb(): void {
  execFileSync(
    'php',
    ['artisan', 'migrate:fresh', '--seed', '--seeder=E2ESeeder', '--env=e2e', '--force'],
    { cwd: APP_ROOT, stdio: 'pipe' },
  );
}

export function makeApplicant(o: Record<string, unknown> = {}): { id: number; reference_id: string } {
  const json = phpStr(JSON.stringify(o));
  return query(
    `(function () {
       $o = json_decode('${json}', true);
       $bn = $o['barangay_name'] ?? 'San Jose';
       unset($o['barangay_name']);
       $b = \\App\\Models\\Barangay::where('name', $bn)->firstOrFail();
       $a = \\App\\Models\\Applicant::factory()->create(array_merge(
         ['barangay_id' => $b->id, 'is_active' => true, 'status' => 'Pending'], $o));
       return $a->only(['id', 'reference_id']);
     })()`,
  );
}

export function createMatchingPairs(n: number): void {
  exec(`
    $b = \\App\\Models\\Barangay::where('name', 'San Jose')->value('id');
    for ($i = 1; $i <= ${n}; $i++) {
      $bd = '1980-01-' . str_pad($i, 2, '0', STR_PAD_LEFT);
      $c  = '0917' . str_pad($i, 7, '0', STR_PAD_LEFT);
      foreach (['Pair' . $i . 'A', 'Pair' . $i . 'B'] as $ln) {
        \\App\\Models\\Applicant::factory()->create([
          'barangay_id' => $b, 'last_name' => $ln, 'birthdate' => $bd,
          'contact_number' => $c, 'is_active' => true, 'status' => 'Pending',
        ]);
      }
    }`);
}

export function dbRow(table: string, where: Record<string, unknown>): any {
  return query(
    `\\Illuminate\\Support\\Facades\\DB::table('${table}')->where(json_decode('${phpStr(JSON.stringify(where))}', true))->first()`,
  );
}

export function dbCount(table: string, where: Record<string, unknown> = {}): number {
  return query(
    `\\Illuminate\\Support\\Facades\\DB::table('${table}')->where(json_decode('${phpStr(JSON.stringify(where))}', true))->count()`,
  );
}

export function latestRow(table: string): any {
  return query(`\\Illuminate\\Support\\Facades\\DB::table('${table}')->orderByDesc('id')->first()`);
}

export function userId(email: string): number {
  return query(`\\App\\Models\\User::where('email', '${email}')->value('id')`);
}

export async function login(page: Page, email: string, password = PASSWORD): Promise<void> {
  await page.goto(ROUTES.login);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

export async function expectToast(page: Page, text: string | RegExp): Promise<void> {
  await expect(page.getByText(text).first()).toBeVisible({ timeout: 15_000 });
}

export function acceptNextDialog(page: Page): void {
  page.once('dialog', (d) => d.accept());
}
export function dismissNextDialog(page: Page): void {
  page.once('dialog', (d) => d.dismiss());
}

export function phpunitDbName(): string {
  const xml = fs.readFileSync(path.join(APP_ROOT, 'phpunit.xml'), 'utf8');
  const m = xml.match(/name="DB_DATABASE"\s+value="([^"]+)"/);
  return m ? m[1] : '';
}

export function shell(cmd: string): string {
  return execSync(cmd, { cwd: APP_ROOT, encoding: 'utf8', stdio: 'pipe' });
}
EOF

# ───────────────────────── playwright/test/duplicate-detection.spec.ts ─────────────────────────
cat > playwright/test/duplicate-detection.spec.ts << 'EOF'
import { expect, Page, test } from '@playwright/test';
import {
  acceptNextDialog, createMatchingPairs, dbCount, dbRow, dismissNextDialog,
  exec, expectToast, login, makeApplicant, query, resetDb, ROUTES, USERS,
} from './helpers/app';
import { badge, rowFor, SEL, wm } from './helpers/selectors';

type Person = { id: number; reference_id: string };
const PAIR_TOAST = 'Scan complete: 2 active applicant(s) checked, 1 new flag(s).';

let ana: Person;
let ben: Person;

function seedPair() {
  ana = makeApplicant({ first_name: 'Ana', last_name: 'Dela Cruz', birthdate: '1990-05-14', contact_number: '09171234567', sex: 'Female' });
  ben = makeApplicant({ first_name: 'Ben', last_name: 'Delacruz', birthdate: '1990-05-14', contact_number: '+63 917-123-4567', sex: 'Male' });
}

async function openQueue(page: Page) {
  await page.goto(ROUTES.duplicates);
  await expect(page.getByText('Duplicate Review Queue')).toBeVisible();
}

async function runScan(page: Page) {
  acceptNextDialog(page);
  await page.getByRole('button', { name: SEL.scanButton }).click();
}

async function openReview(page: Page, ref: string) {
  await rowFor(page, ref).getByRole('button', { name: SEL.reviewButton }).click();
}

test.describe('Duplicate detection (DD-01 to DD-24)', () => {
  test.beforeEach(() => {
    resetDb();
  });

  test('DD-01 staff can open the Duplicate Review page', async ({ page }) => {
    await login(page, USERS.staff);
    await openQueue(page);
    await expect(page.getByRole('button', { name: SEL.scanButton })).toBeVisible();
    await expect(page.getByText('Pending Flags')).toBeVisible();
  });

  test('DD-02 user without staff role gets 403', async ({ page }) => {
    await login(page, USERS.noRole);
    const res = await page.goto(ROUTES.duplicates);
    expect(res?.status()).toBe(403);
  });

  test('DD-03 3/3 pair creates one flag and flags the newer record', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await expectToast(page, PAIR_TOAST);
    const rows = rowFor(page, ben.reference_id);
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await expect(rows).toContainText('3/3');
    await expect(badge(rows, 'Name')).toHaveCount(1);
    await expect(badge(rows, 'Birthdate')).toHaveCount(1);
    await expect(badge(rows, 'Contact')).toHaveCount(1);

    expect(dbRow('applicants', { id: ben.id }).status).toBe('Flagged');
    expect(dbRow('applicants', { id: ana.id }).status).toBe('Pending');
  });

  test('DD-04 birthdate and contact match without name match creates a 2/3 flag', async ({ page }) => {
    const garcia = makeApplicant({ first_name: 'Rosa', last_name: 'Garcia', birthdate: '1985-02-10', contact_number: '09181112222' });
    const villa = makeApplicant({ first_name: 'Pedro', last_name: 'Villanueva', birthdate: '1985-02-10', contact_number: '09181112222' });

    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    const row = rowFor(page, villa.reference_id);
    await expect(row).toContainText('2/3');
    await expect(badge(row, 'Birthdate')).toHaveCount(1);
    await expect(badge(row, 'Contact')).toHaveCount(1);
    await expect(badge(row, 'Name')).toHaveCount(0);
    expect(garcia.id).toBeTruthy();
  });

  test('DD-05 name-only match does not create a flag', async ({ page }) => {
    const s1 = makeApplicant({ last_name: 'Santos', birthdate: '1988-03-02', contact_number: '09981112222' });
    const s2 = makeApplicant({ last_name: 'Santos', birthdate: '1992-07-01', contact_number: '09175550000' });

    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await expect(rowFor(page, s1.reference_id)).toHaveCount(0);
    await expect(rowFor(page, s2.reference_id)).toHaveCount(0);
  });

  test('DD-06 blank contact numbers never count as a match', async ({ page }) => {
    const r1 = makeApplicant({ last_name: 'Reyes', birthdate: '1975-01-01', contact_number: '' });
    const r2 = makeApplicant({ last_name: 'Reyes', birthdate: '1980-01-01', contact_number: '' });

    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await expect(rowFor(page, r1.reference_id)).toHaveCount(0);
    await expect(rowFor(page, r2.reference_id)).toHaveCount(0);
  });

  test('DD-07 different contact formats are treated as the same number', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await expect(badge(rowFor(page, ben.reference_id), 'Contact')).toHaveCount(1);
  });

  test('DD-08 running the scan twice does not create repeat flags', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);

    await runScan(page);
    await expectToast(page, PAIR_TOAST);

    await runScan(page);
    await expectToast(page, /0 new flag\(s\)/);
    await expect(page.locator('tbody tr')).toHaveCount(1);
  });

  test('DD-09 Retain Both stops the pair from being flagged again', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await openReview(page, ben.reference_id);
    await page.getByRole('button', { name: SEL.retainButton }).click();
    await expectToast(page, 'Flag resolved: Retained Both.');

    await runScan(page);
    await expectToast(page, /0 new flag\(s\)/);
    await expect(rowFor(page, ben.reference_id)).toHaveCount(0);
    expect(dbRow('applicants', { id: ana.id }).status).toBe('Verified');
    expect(dbRow('applicants', { id: ben.id }).status).toBe('Verified');
  });

  test('DD-10 Delete New Record deactivates only the newer record', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await openReview(page, ben.reference_id);
    acceptNextDialog(page);
    await page.getByRole('button', { name: SEL.deleteNewButton }).click();
    await expectToast(page, 'Flag resolved: Deleted.');

    const benRow = dbRow('applicants', { id: ben.id });
    expect(Number(benRow.is_active)).toBe(0);
    expect(benRow.status).toBe('Duplicated');
    expect(Number(dbRow('applicants', { id: ana.id }).is_active)).toBe(1);
    expect(dbCount('applicants')).toBe(2);
  });

  test('DD-11 cancelling the confirm dialog changes nothing', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await openReview(page, ben.reference_id);
    dismissNextDialog(page);
    await page.getByRole('button', { name: SEL.deleteNewButton }).click();

    await expect(page.getByRole('button', { name: SEL.deleteNewButton })).toBeVisible();
    expect(Number(dbRow('applicants', { id: ben.id }).is_active)).toBe(1);
    expect(dbRow('applicants', { id: ben.id }).status).toBe('Flagged');
  });

  test('DD-12 inactive applicants are ignored by the scan', async ({ page }) => {
    const active = makeApplicant({ last_name: 'Mendoza', birthdate: '1990-05-14', contact_number: '09171234567' });
    const inactive = makeApplicant({ last_name: 'Mendoza', birthdate: '1990-05-14', contact_number: '09171234567', is_active: false, status: 'Duplicated' });

    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await expect(rowFor(page, active.reference_id)).toHaveCount(0);
    await expect(rowFor(page, inactive.reference_id)).toHaveCount(0);
  });

  test('DD-13 verified applicant edit is caught and status stays Verified', async ({ page }) => {
    const lito = makeApplicant({ last_name: 'Santos', birthdate: '1988-03-02', contact_number: '09981112222', status: 'Verified' });
    const maria = makeApplicant({ last_name: 'Santos', birthdate: '1992-07-01', contact_number: '09175550000', status: 'Verified' });

    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);
    await expect(rowFor(page, maria.reference_id)).toHaveCount(0);

    exec(`\\App\\Models\\Applicant::find(${maria.id})->update(['birthdate' => '1988-03-02', 'contact_number' => '09981112222'])`);

    await runScan(page);
    const row = rowFor(page, maria.reference_id);
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('3/3');
    expect(dbRow('applicants', { id: maria.id }).status).toBe('Verified');
    expect(lito.id).toBeTruthy();
  });

  test('DD-14 flag badges stay as they were when the flag was created (known limitation)', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    exec(`\\App\\Models\\Applicant::find(${ben.id})->update(['birthdate' => '1999-01-01'])`);
    await page.reload();

    const row = rowFor(page, ben.reference_id);
    await expect(row).toContainText('3/3');
    await expect(badge(row, 'Birthdate')).toHaveCount(1);
  });

  test('DD-15 an existing flag blocks the pair in either order', async ({ page }) => {
    seedPair();
    exec(`\\App\\Models\\DuplicateFlag::factory()->create(['applicant_id_new' => ${ana.id}, 'applicant_id_existing' => ${ben.id}])`);

    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);

    await expect(page.locator('tbody tr', { hasText: ben.reference_id })).toHaveCount(1);
    await expect(page.locator('tbody tr')).toHaveCount(1);
  });

  test('DD-16 second resolver is told the flag was already resolved', async ({ browser }) => {
    seedPair();
    const ctxA = await browser.newContext();
    const ctxB = await browser.newContext();
    const pageA = await ctxA.newPage();
    const pageB = await ctxB.newPage();

    await login(pageA, USERS.staff);
    await login(pageB, USERS.staff);
    await openQueue(pageA);
    await runScan(pageA);
    await openQueue(pageB);

    await openReview(pageA, ben.reference_id);
    await openReview(pageB, ben.reference_id);

    await pageA.getByRole('button', { name: SEL.retainButton }).click();
    await expectToast(pageA, 'Flag resolved: Retained Both.');

    acceptNextDialog(pageB);
    await pageB.getByRole('button', { name: SEL.deleteNewButton }).click();
    await expectToast(pageB, 'This flag was already resolved by someone else.');

    const flag = query(`\\App\\Models\\DuplicateFlag::where('applicant_id_new', ${ben.id})->first()->resolution_status`);
    expect(flag).toBe('Retained Both');

    await ctxA.close();
    await ctxB.close();
  });

  test('DD-17 stale open tab shows a clear message instead of an error page', async ({ browser }) => {
    seedPair();
    const ctxA = await browser.newContext();
    const ctxB = await browser.newContext();
    const pageA = await ctxA.newPage();
    const pageB = await ctxB.newPage();

    await login(pageA, USERS.staff);
    await login(pageB, USERS.staff);
    await openQueue(pageA);
    await runScan(pageA);
    await openReview(pageA, ben.reference_id);

    await openQueue(pageB);
    await openReview(pageB, ben.reference_id);
    await pageB.getByRole('button', { name: SEL.retainButton }).click();
    await expectToast(pageB, 'Flag resolved: Retained Both.');

    await pageA.getByRole('button', { name: SEL.retainButton }).click();
    await expectToast(pageA, 'This flag was already resolved by someone else.');
    await expect(pageA.getByText(/Server Error|500/)).toHaveCount(0);

    await ctxA.close();
    await ctxB.close();
  });

  test('DD-18 resolution notes stop at 1000 characters', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);
    await openReview(page, ben.reference_id);

    const notes = page.locator(wm('resolutionNotes')).first();
    await notes.pressSequentially('a'.repeat(1001));
    const length = await notes.inputValue().then((v) => v.length);
    expect(length).toBe(1000);
  });

  test('DD-19 scan on an empty database reports zero without error', async ({ page }) => {
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);
    await expectToast(page, 'Scan complete: 0 active applicant(s) checked, 0 new flag(s).');
  });

  test('DD-20 many applicants with one birthdate still finish (performance)', async ({ page }) => {
    test.setTimeout(10 * 60_000);
    exec(`
      $b = \\App\\Models\\Barangay::where('name', 'San Jose')->value('id');
      \\App\\Models\\Applicant::factory()->count(300)->create([
        'barangay_id' => $b, 'birthdate' => '2000-01-01', 'is_active' => true, 'status' => 'Pending',
      ]);`);

    await login(page, USERS.staff);
    await openQueue(page);

    const start = Date.now();
    await runScan(page);
    await expect(page.getByText(/Scan complete:/).first()).toBeVisible({ timeout: 300_000 });
    const seconds = (Date.now() - start) / 1000;

    test.info().annotations.push({ type: 'duration', description: `${seconds.toFixed(1)} seconds` });
    console.log(`DD-20 scan took ${seconds.toFixed(1)} seconds`);
    expect(seconds).toBeLessThan(120);
  });

  test('DD-21 scan on 5000 applicants (opt-in with RUN_LARGE_SCAN=1)', async ({ page }) => {
    test.skip(!process.env.RUN_LARGE_SCAN, 'Set RUN_LARGE_SCAN=1 to run this slow test');
    test.setTimeout(30 * 60_000);
    exec(`
      $b = \\App\\Models\\Barangay::where('name', 'San Jose')->value('id');
      \\App\\Models\\Applicant::factory()->count(5000)->create([
        'barangay_id' => $b, 'is_active' => true, 'status' => 'Pending',
      ]);`);

    await login(page, USERS.staff);
    await openQueue(page);
    const start = Date.now();
    await runScan(page);
    await expect(page.getByText(/Scan complete:/).first()).toBeVisible({ timeout: 25 * 60_000 });
    console.log(`DD-21 scan took ${((Date.now() - start) / 1000).toFixed(1)} seconds`);
  });

  test('DD-22 queue shows 15 flags per page and the rest on page 2', async ({ page }) => {
    createMatchingPairs(20);
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);
    await expectToast(page, /20 new flag\(s\)/);

    await expect(page.locator('tbody tr')).toHaveCount(15);
    await page.getByRole('button', { name: '2', exact: true })
      .or(page.getByRole('link', { name: '2', exact: true })).click();
    await expect(page.locator('tbody tr')).toHaveCount(5);
  });

  test.fixme('DD-23 Merged outcome has a button (known gap, decision needed)', async ({ page }) => {
    seedPair();
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);
    await openReview(page, ben.reference_id);
    await expect(page.getByRole('button', { name: /Merge/ })).toHaveCount(1);
  });

  test.fixme('DD-24 scan-created flags are written to the audit log (known gap)', async ({ page }) => {
    seedPair();
    const before = dbCount('audit_logs');
    await login(page, USERS.staff);
    await openQueue(page);
    await runScan(page);
    await expectToast(page, PAIR_TOAST);
    expect(dbCount('audit_logs')).toBeGreaterThan(before);
  });
});
EOF

# ───────────────────────── playwright/test/applicants.spec.ts ─────────────────────────
cat > playwright/test/applicants.spec.ts << 'EOF'
import { expect, test } from '@playwright/test';
import {
  acceptNextDialog, dbCount, dbRow, exec, latestRow, login, makeApplicant,
  resetDb, ROUTES, USERS,
} from './helpers/app';
import { modal, rowFor, SEL, searchBox, wm } from './helpers/selectors';

test.describe('Applicant management (AM-01 to AM-13)', () => {
  test.beforeEach(async ({ page }) => {
    resetDb();
    await login(page, USERS.staff);
  });

  test('AM-01 staff can open the applicant page', async ({ page }) => {
    await page.goto(ROUTES.applicants);
    await expect(page).toHaveURL(/applicants/);
    await expect(page.locator('tbody')).toBeVisible();
  });

  test('AM-02 guest is redirected to login', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await page.goto(ROUTES.applicants);
    await expect(page).toHaveURL(/login/);
    await ctx.close();
  });

  test('AM-03 search by last name returns only matches', async ({ page }) => {
    const santos = makeApplicant({ last_name: 'Santos', first_name: 'Juan' });
    const reyes = makeApplicant({ last_name: 'Reyes', first_name: 'Maria' });

    await page.goto(ROUTES.applicants);
    await searchBox(page).fill('Santos');
    await expect(rowFor(page, santos.reference_id)).toHaveCount(1);
    await expect(rowFor(page, reyes.reference_id)).toHaveCount(0);
  });

  test('AM-04 search by reference ID finds exactly that applicant', async ({ page }) => {
    const ana = makeApplicant({ last_name: 'Cruz' });
    const other = makeApplicant({ last_name: 'Lopez' });

    await page.goto(ROUTES.applicants);
    await searchBox(page).fill(ana.reference_id);
    await expect(rowFor(page, ana.reference_id)).toHaveCount(1);
    await expect(rowFor(page, other.reference_id)).toHaveCount(0);
  });

  test('AM-05 status filter shows only the chosen status', async ({ page }) => {
    const flagged = makeApplicant({ status: 'Flagged' });
    const pending = makeApplicant({ status: 'Pending' });
    const verified = makeApplicant({ status: 'Verified' });

    await page.goto(ROUTES.applicants);
    await page.locator(wm('filterStatus')).first().selectOption('Flagged');
    await expect(rowFor(page, flagged.reference_id)).toHaveCount(1);
    await expect(rowFor(page, pending.reference_id)).toHaveCount(0);
    await expect(rowFor(page, verified.reference_id)).toHaveCount(0);
  });

  test('AM-06 inactive applicants are hidden from the table', async ({ page }) => {
    const active = makeApplicant({});
    const inactive = makeApplicant({ is_active: false, status: 'Duplicated' });

    await page.goto(ROUTES.applicants);
    await expect(rowFor(page, active.reference_id)).toHaveCount(1);
    await expect(rowFor(page, inactive.reference_id)).toHaveCount(0);
  });

  test('AM-07 edit saves the right fields to the right record only', async ({ page }) => {
    const ana = makeApplicant({ last_name: 'Dela Cruz', contact_number: '09171234567' });
    const ben = makeApplicant({ last_name: 'Delacruz', contact_number: '09175550000' });

    await page.goto(ROUTES.applicants);
    await rowFor(page, ana.reference_id).getByRole('button', { name: SEL.edit }).click();
    await modal(page).locator(wm('editData.contact_number')).fill('09289876543');
    await modal(page).locator(wm('editData.status')).selectOption('Verified');
    await modal(page).getByRole('button', { name: SEL.save }).click();

    const anaRow = dbRow('applicants', { id: ana.id });
    expect(anaRow.contact_number).toBe('09289876543');
    expect(anaRow.status).toBe('Verified');
    expect(dbRow('applicants', { id: ben.id }).contact_number).toBe('09175550000');
  });

  test('AM-08 edit writes an APPLICANT_UPDATED audit entry', async ({ page }) => {
    const ana = makeApplicant({});
    await page.goto(ROUTES.applicants);
    await rowFor(page, ana.reference_id).getByRole('button', { name: SEL.edit }).click();
    await modal(page).locator(wm('editData.status')).selectOption('Verified');
    await modal(page).getByRole('button', { name: SEL.save }).click();

    const log = latestRow('audit_logs');
    expect(log.action).toBe('APPLICANT_UPDATED');
    expect(log.model_type).toBe('Applicant');
    expect(log.model_id).toBe(ana.id);
  });

  test('AM-09 empty last name is rejected and the stored name stays', async ({ page }) => {
    const ana = makeApplicant({ last_name: 'Santos' });
    await page.goto(ROUTES.applicants);
    await rowFor(page, ana.reference_id).getByRole('button', { name: SEL.edit }).click();
    await modal(page).locator(wm('editData.last_name')).fill('');
    await modal(page).getByRole('button', { name: SEL.save }).click();

    await expect(modal(page)).toBeVisible();
    expect(dbRow('applicants', { id: ana.id }).last_name).toBe('Santos');
  });

  test('AM-10 deactivate keeps the row and sets is_active to false', async ({ page }) => {
    const ana = makeApplicant({});
    const before = dbCount('applicants');

    await page.goto(ROUTES.applicants);
    acceptNextDialog(page);
    await rowFor(page, ana.reference_id).getByRole('button', { name: SEL.deactivate }).click();

    expect(dbCount('applicants')).toBe(before);
    expect(Number(dbRow('applicants', { id: ana.id }).is_active)).toBe(0);
  });

  test('AM-11 deactivate writes an APPLICANT_DEACTIVATED audit entry', async ({ page }) => {
    const ana = makeApplicant({});
    await page.goto(ROUTES.applicants);
    acceptNextDialog(page);
    await rowFor(page, ana.reference_id).getByRole('button', { name: SEL.deactivate }).click();

    const log = latestRow('audit_logs');
    expect(log.action).toBe('APPLICANT_DEACTIVATED');
    expect(log.model_id).toBe(ana.id);
  });

  test('AM-12 changing the search resets pagination to page 1', async ({ page }) => {
    exec(`\\App\\Models\\Applicant::factory()->count(25)->create([
      'barangay_id' => \\App\\Models\\Barangay::where('name', 'San Jose')->value('id'),
      'is_active' => true, 'status' => 'Pending',
    ])`);

    await page.goto(ROUTES.applicants);
    await page.getByRole('button', { name: '2', exact: true })
      .or(page.getByRole('link', { name: '2', exact: true })).click();

    await searchBox(page).fill('Zzzz-no-match');
    await searchBox(page).fill('');
    await expect(page.locator('tbody tr').first()).toBeVisible();
    await expect(page.getByRole('button', { name: '2', exact: true })
      .or(page.getByRole('link', { name: '2', exact: true })).first()).toBeVisible();
  });

  test.fixme('AM-13 deactivated status matches the decision (decision needed)', async ({ page }) => {
    const ana = makeApplicant({});
    await page.goto(ROUTES.applicants);
    acceptNextDialog(page);
    await rowFor(page, ana.reference_id).getByRole('button', { name: SEL.deactivate }).click();
    expect(dbRow('applicants', { id: ana.id }).status).toBe('Duplicated');
  });
});
EOF

# ───────────────────────── playwright/test/geography.spec.ts ─────────────────────────
cat > playwright/test/geography.spec.ts << 'EOF'
import { expect, test } from '@playwright/test';
import { acceptNextDialog, dbCount, exec, login, makeApplicant, resetDb, ROUTES, USERS } from './helpers/app';
import { modal, rowFor, SEL, wm } from './helpers/selectors';

test.describe('Barangay and municipality (GEO-01 to GEO-07)', () => {
  test.beforeEach(async ({ page }) => {
    resetDb();
    await login(page, USERS.staff);
  });

  test('GEO-01 a barangay shows its municipality', async ({ page }) => {
    await page.goto(ROUTES.barangays);
    await page.getByRole('button', { name: /New Barangay/ }).click();
    await modal(page).locator(wm('name')).fill('Mabini');
    await modal(page).locator(wm('municipalityId')).selectOption({ label: 'Pandan' });
    await modal(page).getByRole('button', { name: SEL.save }).click();

    await expect(rowFor(page, 'Mabini')).toContainText('Pandan');
  });

  test('GEO-02 a municipality lists only its own barangays', async ({ page }) => {
    await page.goto(ROUTES.municipalities);
    await rowFor(page, 'Virac').getByRole('button', { name: SEL.view }).click();
    await expect(page.getByText('San Jose')).toBeVisible();
    await expect(page.getByText('Mabini')).toHaveCount(0);
  });

  test('GEO-03 a barangay with applicants cannot be deleted', async ({ page }) => {
    makeApplicant({ barangay_name: 'San Jose' });
    await page.goto(ROUTES.barangays);
    acceptNextDialog(page);
    await rowFor(page, 'San Jose').getByRole('button', { name: SEL.delete }).click();

    await expect(page.getByText(/applicant|still|cannot/i).first()).toBeVisible();
    expect(dbCount('barangays', { name: 'San Jose' })).toBe(1);
  });

  test('GEO-04 an empty barangay can be deleted', async ({ page }) => {
    exec(`\\App\\Models\\Barangay::create(['name' => 'Test Delete', 'municipality_id' => \\App\\Models\\Municipality::where('name', 'Virac')->value('id')])`);
    await page.goto(ROUTES.barangays);
    acceptNextDialog(page);
    await rowFor(page, 'Test Delete').getByRole('button', { name: SEL.delete }).click();

    await expect(rowFor(page, 'Test Delete')).toHaveCount(0);
    expect(dbCount('barangays', { name: 'Test Delete' })).toBe(0);
  });

  test('GEO-05 a municipality with barangays cannot be deleted', async ({ page }) => {
    await page.goto(ROUTES.municipalities);
    acceptNextDialog(page);
    await rowFor(page, 'Virac').getByRole('button', { name: SEL.delete }).click();

    await expect(page.getByText(/barangay|cannot|still/i).first()).toBeVisible();
    expect(dbCount('municipalities', { name: 'Virac' })).toBe(1);
  });

  test('GEO-06 the same barangay name cannot be added twice in one municipality', async ({ page }) => {
    await page.goto(ROUTES.barangays);
    await page.getByRole('button', { name: /New Barangay/ }).click();
    await modal(page).locator(wm('name')).fill('San Jose');
    await modal(page).locator(wm('municipalityId')).selectOption({ label: 'Virac' });
    await modal(page).getByRole('button', { name: SEL.save }).click();

    await page.waitForTimeout(1000);
    expect(dbCount('barangays', { name: 'San Jose' })).toBe(1);
  });

  test('GEO-07 blank required fields are rejected', async ({ page }) => {
    const before = dbCount('barangays');
    await page.goto(ROUTES.barangays);
    await page.getByRole('button', { name: /New Barangay/ }).click();
    await modal(page).getByRole('button', { name: SEL.save }).click();

    await expect(modal(page)).toBeVisible();
    expect(dbCount('barangays')).toBe(before);
  });
});
EOF

# ───────────────────────── playwright/test/skills.spec.ts ─────────────────────────
cat > playwright/test/skills.spec.ts << 'EOF'
import { expect, test, Page } from '@playwright/test';
import { acceptNextDialog, dbCount, dbRow, exec, expectToast, login, query, resetDb, ROUTES, USERS } from './helpers/app';
import { modal, rowFor, SEL, searchBox, wm } from './helpers/selectors';

async function createCategory(page: Page, name: string, pqf = '', description = '') {
  await page.goto(ROUTES.skillCategories);
  await page.getByRole('button', { name: /New Category/ }).click();
  await modal(page).locator(wm('name')).fill(name);
  if (pqf) await modal(page).locator(wm('pqfLevel')).fill(pqf);
  if (description) await modal(page).locator(wm('description')).fill(description);
  await modal(page).getByRole('button', { name: SEL.save }).click();
}

async function createSkill(page: Page, categoryName: string, name: string) {
  await page.goto(ROUTES.skills);
  await page.getByRole('button', { name: /New Skill/ }).click();
  await modal(page).locator(wm('skillCategoryId')).selectOption({ label: categoryName });
  await modal(page).locator(wm('name')).fill(name);
  await modal(page).getByRole('button', { name: SEL.save }).click();
}

test.describe('Skill categories and skills (SK-01 to SK-13)', () => {
  test.beforeEach(async ({ page }) => {
    resetDb();
    await login(page, USERS.staff);
  });

  test('SK-01 create a skill category', async ({ page }) => {
    await createCategory(page, 'Construction', '2', 'Building trades');
    await expectToast(page, 'Category created.');
    const row = rowFor(page, 'Construction');
    await expect(row).toContainText('2');
    await expect(row).toContainText('0');
  });

  test('SK-02 category names must be unique', async ({ page }) => {
    await createCategory(page, 'Construction');
    await createCategory(page, 'Construction');
    await expect(modal(page).getByText(/taken|unique|already/i).first()).toBeVisible();
    expect(dbCount('skill_categories', { name: 'Construction' })).toBe(1);
  });

  test('SK-03 editing a category keeps its own name valid', async ({ page }) => {
    exec(`\\App\\Models\\SkillCategory::create(['name' => 'Construction'])`);
    await page.goto(ROUTES.skillCategories);
    await rowFor(page, 'Construction').getByRole('button', { name: SEL.edit }).click();
    await modal(page).locator(wm('description')).fill('Updated text');
    await modal(page).getByRole('button', { name: SEL.save }).click();

    await expectToast(page, 'Category updated.');
    expect(dbRow('skill_categories', { name: 'Construction' }).description).toBe('Updated text');
  });

  test('SK-04 a category with skills cannot be deleted', async ({ page }) => {
    exec(`
      $c = \\App\\Models\\SkillCategory::create(['name' => 'Construction']);
      \\App\\Models\\Skill::create(['name' => 'Masonry', 'skill_category_id' => $c->id]);`);
    await page.goto(ROUTES.skillCategories);
    acceptNextDialog(page);
    await rowFor(page, 'Construction').getByRole('button', { name: SEL.delete }).click();

    await expectToast(page, /still has 1 skill/);
    expect(dbCount('skill_categories', { name: 'Construction' })).toBe(1);
  });

  test('SK-05 create a skill under a category', async ({ page }) => {
    exec(`\\App\\Models\\SkillCategory::create(['name' => 'Construction'])`);
    await createSkill(page, 'Construction', 'Masonry');
    await expectToast(page, 'Skill created.');
    const row = rowFor(page, 'Masonry');
    await expect(row).toContainText('Construction');
    await expect(row).toContainText('0');
  });

  test('SK-06 a skill requires a category', async ({ page }) => {
    await page.goto(ROUTES.skills);
    await page.getByRole('button', { name: /New Skill/ }).click();
    await modal(page).locator(wm('name')).fill('Welding');
    await modal(page).getByRole('button', { name: SEL.save }).click();

    expect(dbCount('skills', { name: 'Welding' })).toBe(0);
  });

  test('SK-07 same name is blocked within a category but allowed in another', async ({ page }) => {
    exec(`
      \\App\\Models\\SkillCategory::create(['name' => 'Food Service']);
      \\App\\Models\\SkillCategory::create(['name' => 'Construction']);`);

    await createSkill(page, 'Food Service', 'Cooking');
    await createSkill(page, 'Food Service', 'Cooking');
    expect(dbCount('skills', { name: 'Cooking' })).toBe(1);

    await createSkill(page, 'Construction', 'Cooking');
    expect(dbCount('skills', { name: 'Cooking' })).toBe(2);
  });

  test('SK-08 editing a skill can move it to another category', async ({ page }) => {
    exec(`
      $c = \\App\\Models\\SkillCategory::create(['name' => 'Construction']);
      \\App\\Models\\SkillCategory::create(['name' => 'Food Service']);
      \\App\\Models\\Skill::create(['name' => 'Masonry', 'skill_category_id' => $c->id]);`);

    await page.goto(ROUTES.skills);
    await rowFor(page, 'Masonry').getByRole('button', { name: SEL.edit }).click();
    await modal(page).locator(wm('skillCategoryId')).selectOption({ label: 'Food Service' });
    await modal(page).getByRole('button', { name: SEL.save }).click();

    const foodId = query(`\\App\\Models\\SkillCategory::where('name', 'Food Service')->value('id')`);
    expect(dbRow('skills', { name: 'Masonry' }).skill_category_id).toBe(foodId);
  });

  test('SK-09 a skill held by an applicant cannot be deleted', async ({ page }) => {
    exec(`
      $c = \\App\\Models\\SkillCategory::create(['name' => 'Construction']);
      $s = \\App\\Models\\Skill::create(['name' => 'Masonry', 'skill_category_id' => $c->id]);
      $a = \\App\\Models\\Applicant::factory()->create(['barangay_id' => \\App\\Models\\Barangay::where('name', 'San Jose')->value('id'), 'is_active' => true, 'status' => 'Pending']);
      $a->skills()->attach($s->id, ['proficiency_level' => 'Advanced']);`);

    await page.goto(ROUTES.skills);
    acceptNextDialog(page);
    await rowFor(page, 'Masonry').getByRole('button', { name: SEL.delete }).click();

    await expectToast(page, /applicant/);
    expect(dbCount('skills', { name: 'Masonry' })).toBe(1);
  });

  test('SK-10 skill search and category filter', async ({ page }) => {
    exec(`
      $c = \\App\\Models\\SkillCategory::create(['name' => 'Construction']);
      $f = \\App\\Models\\SkillCategory::create(['name' => 'Food Service']);
      \\App\\Models\\Skill::create(['name' => 'Masonry', 'skill_category_id' => $c->id]);
      \\App\\Models\\Skill::create(['name' => 'Cooking', 'skill_category_id' => $f->id]);`);

    await page.goto(ROUTES.skills);
    await searchBox(page).fill('Mas');
    await expect(rowFor(page, 'Masonry')).toHaveCount(1);
    await expect(rowFor(page, 'Cooking')).toHaveCount(0);

    await searchBox(page).fill('');
    await page.locator('select').first().selectOption({ label: 'Food Service' });
    await expect(rowFor(page, 'Cooking')).toHaveCount(1);
    await expect(rowFor(page, 'Masonry')).toHaveCount(0);

    await page.locator('select').first().selectOption({ label: 'All categories' });
    await expect(rowFor(page, 'Masonry')).toHaveCount(1);
  });

  test.fixme('SK-11 applicant category filter returns the right residents (not built yet)', async ({ page }) => {
    exec(`
      $c = \\App\\Models\\SkillCategory::create(['name' => 'Construction']);
      $s = \\App\\Models\\Skill::create(['name' => 'Masonry', 'skill_category_id' => $c->id]);
      $a = \\App\\Models\\Applicant::factory()->create(['barangay_id' => \\App\\Models\\Barangay::where('name', 'San Jose')->value('id'), 'is_active' => true, 'status' => 'Pending', 'last_name' => 'Cruz']);
      $a->skills()->attach($s->id, ['proficiency_level' => 'Advanced']);`);
    await page.goto(ROUTES.applicants);
    await page.locator(wm('filterCategory')).first().selectOption({ label: 'Construction' });
    await expect(page.getByText('Cruz')).toBeVisible();
  });

  test('SK-12 proficiency level is saved with the skill', async ({ page }) => {
    void page;
    exec(`
      $c = \\App\\Models\\SkillCategory::create(['name' => 'Construction']);
      $s = \\App\\Models\\Skill::create(['name' => 'Masonry', 'skill_category_id' => $c->id]);
      $a = \\App\\Models\\Applicant::factory()->create(['barangay_id' => \\App\\Models\\Barangay::where('name', 'San Jose')->value('id'), 'is_active' => true, 'status' => 'Pending']);
      $a->skills()->attach($s->id, ['proficiency_level' => 'Advanced']);`);

    const level = query(`\\Illuminate\\Support\\Facades\\DB::table('applicant_skill')->value('proficiency_level')`);
    expect(level).toBe('Advanced');
  });

  test('SK-13 users without the staff role cannot open skill pages', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, USERS.noRole);

    expect((await page.goto(ROUTES.skills))?.status()).toBe(403);
    expect((await page.goto(ROUTES.skillCategories))?.status()).toBe(403);
    await ctx.close();
  });
});
EOF

# ───────────────────────── playwright/test/deployment.spec.ts ─────────────────────────
cat > playwright/test/deployment.spec.ts << 'EOF'
import { expect, test } from '@playwright/test';
import {
  dbCount, exec, expectToast, latestRow, login, phpunitDbName,
  resetDb, ROUTES, shell, USERS, userId,
} from './helpers/app';
import { modal, SEL, wm } from './helpers/selectors';

test.describe('Deployment and other risks (DEP-01 to DEP-10)', () => {
  test('DEP-01 autoload is clean locally', () => {
    const out = shell('composer dump-autoload -o 2>&1');
    expect(out).not.toMatch(/does not comply with psr-4/);
  });

  test('DEP-02 git stores the correct filename case', () => {
    const files = shell('git ls-files app/Livewire');
    expect(files).toContain('GeographicalManagement.php');
    expect(files).not.toContain('Geographicalmanagement.php');
  });

  test.fixme('DEP-03 Render build succeeds (manual: check the Render dashboard)', () => {});

  test('DEP-04 a real staff account can open Duplicate Review', async ({ page }) => {
    const email = process.env.REAL_STAFF_EMAIL;
    const password = process.env.REAL_STAFF_PASSWORD;
    test.skip(!email || !password, 'Set REAL_STAFF_EMAIL and REAL_STAFF_PASSWORD');

    await login(page, email!, password!);
    const res = await page.goto(ROUTES.duplicates);
    expect(res?.status()).not.toBe(403);
  });

  test('DEP-05 tests use a separate database', () => {
    const db = phpunitDbName();
    expect(db).toMatch(/test|memory/i);
  });

  test('DEP-06 fresh migrations run cleanly', () => {
    expect(() => resetDb()).not.toThrow();
  });

  test('DEP-07 wrong password shows a clear message, not a server error', async ({ page }) => {
    resetDb();
    await page.goto(ROUTES.login);
    await page.fill('input[name="email"]', USERS.staff);
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('button[type="submit"]');

    await expect(page.getByText(/do not match our records/i)).toBeVisible();
    await expect(page.getByText(/Server Error|Whoops|500/)).toHaveCount(0);
  });

  test('DEP-08 successful login writes a login_success activity entry', async ({ page }) => {
    resetDb();
    await login(page, USERS.staff);

    const log = latestRow('activity_logs');
    expect(log.event).toBe('login_success');
    expect(log.user_id).toBe(userId(USERS.staff));
  });

  test('DEP-09 expired session fails clearly instead of silently', async ({ page }) => {
    resetDb();
    await login(page, USERS.staff);
    await page.goto(ROUTES.duplicates);

    exec(`\\Illuminate\\Support\\Facades\\DB::table('sessions')->where('user_id', ${userId(USERS.staff)})->delete()`);

    await page.getByRole('button', { name: SEL.scanButton }).click();
    await page.waitForTimeout(3000);

    const onLogin = /login/.test(page.url());
    const message = await page.getByText(/expired|log in again|session|login/i).count();
    expect(onLogin || message > 0).toBe(true);
  });

  test('DEP-10 scan and category save each show a toast', async ({ page }) => {
    resetDb();
    await login(page, USERS.staff);

    await page.goto(ROUTES.duplicates);
    page.once('dialog', (d) => d.accept());
    await page.getByRole('button', { name: SEL.scanButton }).click();
    await expectToast(page, /Scan complete:/);

    await page.goto(ROUTES.skillCategories);
    await page.getByRole('button', { name: /New Category/ }).click();
    await modal(page).locator(wm('name')).fill('Toast Test');
    await modal(page).getByRole('button', { name: SEL.save }).click();
    await expectToast(page, 'Category created.');
    expect(dbCount('skill_categories', { name: 'Toast Test' })).toBe(1);
  });
});
EOF

# ───────────────────────── database/seeders/E2ESeeder.php ─────────────────────────
cat > database/seeders/E2ESeeder.php << 'EOF'
<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class E2ESeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('admin', 'web');

        $staff = User::create([
            'name'     => 'E2E Staff',
            'email'    => 'staff@test.local',
            'password' => Hash::make('password'),
        ]);
        $staff->assignRole('staff');

        User::create([
            'name'     => 'E2E No Role',
            'email'    => 'norole@test.local',
            'password' => Hash::make('password'),
        ]);

        $virac  = Municipality::create(['name' => 'Virac',  'province' => 'Catanduanes']);
        $pandan = Municipality::create(['name' => 'Pandan', 'province' => 'Catanduanes']);

        Barangay::create(['name' => 'San Jose', 'municipality_id' => $virac->id]);
        Barangay::create(['name' => 'Mabini',   'municipality_id' => $pandan->id]);
    }
}
EOF

echo "Done. Files created under playwright/test/ and database/seeders/E2ESeeder.php"