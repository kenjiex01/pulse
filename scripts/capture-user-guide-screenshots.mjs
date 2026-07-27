/**
 * Captures Pulse UI screenshots for the user guide.
 * Usage: node scripts/capture-user-guide-screenshots.mjs
 *
 * Skips 17-tardiness-undertime-policy.png (user-provided reference).
 */
import { chromium } from 'playwright';
import { mkdir, access } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT_DIR = path.join(__dirname, '..', 'docs', 'user-guide', 'images');
const BASE_URL = process.env.PULSE_URL ?? 'http://127.0.0.1:8000';
const EMAIL = process.env.PULSE_EMAIL ?? 'superadmin@icct.edu.ph';
const PASSWORD = process.env.PULSE_PASSWORD ?? 'Password123!';
const POLICY_ID = process.env.PULSE_POLICY_ID ?? '1';
const EMPLOYEE_ID = process.env.PULSE_EMPLOYEE_ID ?? '1';

/** @type {Array<{ name: string; path: string; guest?: boolean; wait?: number; skip?: boolean }>} */
const PAGES = [
  { name: '01-login', path: '/login', guest: true, wait: 800 },
  { name: '02-dashboard', path: '/dashboard' },
  { name: '03-employees', path: '/employees' },
  { name: '04-hr-campuses', path: '/hr/campuses' },
  { name: '05-payroll-rate-definitions', path: '/payroll/rate-definitions/rate-groups' },
  { name: '06-payroll-maintenance', path: '/payroll/maintenance-table/income-types' },
  { name: '07-payroll-government-tables', path: '/payroll/government-tables/pag-ibig' },
  { name: '08-payroll-calendar', path: '/payroll/calendar/calendar/daily' },
  { name: '09-payroll-transaction', path: '/payroll/transaction/batches' },
  { name: '10-timekeeping-policy', path: '/timekeeping/policy/policy' },
  { name: '11-time-logs', path: '/timekeeping/time-logs/time-in-out' },
  { name: '12-employee-profile', path: '/timekeeping/employee-profile' },
  { name: '13-employee-load', path: '/timekeeping/employee-load' },
  { name: '14-users', path: '/users' },
  { name: '15-roles', path: '/roles' },
  { name: '16-database', path: '/database' },
  { name: '17-tardiness-undertime-policy', path: `/timekeeping/policy/${POLICY_ID}/tardiness-undertime`, skip: true },

  // HR
  { name: '18-employee-wizard-campus', path: '/employees/create', wait: 700 },
  { name: '19-employee-salary', path: `/employees/${EMPLOYEE_ID}/edit?tab=salary`, wait: 900 },
  { name: '20-employee-upload', path: '/employees?upload=1', wait: 900 },
  { name: '21-employee-filters', path: '/employees?status=active&compliance=pending', wait: 700 },
  { name: '22-hr-designations', path: '/hr/designations?create=1', wait: 900 },
  { name: '23-hr-positions', path: '/hr/positions?create=1', wait: 900 },
  { name: '24-hr-employment-types', path: '/hr/employment-types' },
  { name: '25-hr-colleges', path: '/hr/colleges' },

  // Timekeeping — module tabs
  { name: '26-shift-codes', path: '/timekeeping/policy/shift-codes?create=1', wait: 900 },
  { name: '27-time-capturing-settings', path: '/timekeeping/policy/time-capturing-settings?create=1', wait: 900 },
  { name: '28-holiday-settings', path: '/timekeeping/policy/holiday-settings' },
  { name: '29-policy-create', path: '/timekeeping/policy/policy?create=1', wait: 900 },

  // Timekeeping — policy settings tabs
  { name: '30-policy-overtime', path: `/timekeeping/policy/${POLICY_ID}/overtime`, wait: 700 },
  { name: '31-policy-breaks', path: `/timekeeping/policy/${POLICY_ID}/breaks`, wait: 700 },
  { name: '32-policy-night-differential', path: `/timekeeping/policy/${POLICY_ID}/night-differential`, wait: 700 },
  { name: '33-policy-general', path: `/timekeeping/policy/${POLICY_ID}/general`, wait: 700 },

  // Timekeeping — uploads & tabs
  { name: '34-time-logs-upload', path: '/timekeeping/time-logs/time-in-out?upload=1', wait: 900 },
  { name: '35-time-logs-dtr', path: '/timekeeping/time-logs/timelogs-dtr' },
  { name: '36-employee-profile-setup', path: `/timekeeping/employee-profile?setup_employee=${EMPLOYEE_ID}`, wait: 1000 },
  { name: '37-employee-load-upload', path: '/timekeeping/employee-load?upload=1', wait: 900 },

  // Payroll — rate definition
  { name: '38-rate-group-create', path: '/payroll/rate-definitions/rate-groups/create', wait: 800 },
  { name: '39-nd-rate-groups', path: '/payroll/rate-definitions/nd-rate-groups' },
  { name: '40-day-types', path: '/payroll/rate-definitions/day-types?create=1', wait: 900 },

  // Payroll — maintenance & govt
  { name: '41-deduction-types', path: '/payroll/maintenance-table/deduction-types?create=1', wait: 900 },
  { name: '42-leave-types', path: '/payroll/maintenance-table/leave-types' },
  { name: '43-philhealth', path: '/payroll/government-tables/philhealth' },
  { name: '44-sss', path: '/payroll/government-tables/sss' },
  { name: '45-withholding-tax', path: '/payroll/government-tables/withholding-tax-2023' },

  // Payroll — calendar & transaction
  { name: '46-calendar-semi-monthly', path: '/payroll/calendar/calendar/semi-monthly' },
  { name: '47-payroll-batch-create', path: '/payroll/transaction/batches?create=1', wait: 900 },
  { name: '48-upload-transactions', path: '/payroll/transaction/upload-transactions?create=1', wait: 900 },

  // Administration — create modals
  { name: '49-users-create', path: '/users?create=1', wait: 900 },
  { name: '50-roles-create', path: '/roles?create=1', wait: 900 },
  { name: '52-hr-employee-departments', path: '/hr/employee-departments' },
  { name: '53-loan-types', path: '/payroll/maintenance-table/loan-types' },
];

async function login(page) {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  await page.waitForTimeout(600);
}

async function capture(page, { name, path: pagePath, wait = 500 }) {
  const url = `${BASE_URL}${pagePath}`;
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(wait);

  const file = path.join(OUT_DIR, `${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  console.log(`Saved ${file}`);
}

async function captureWizardDetails(page) {
  const name = '51-employee-wizard-details';
  const file = path.join(OUT_DIR, `${name}.png`);

  await page.goto(`${BASE_URL}/employees/create`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);

  const firstCard = page.locator('.campus-card').first();
  await firstCard.click();
  await page.waitForURL(/employees\/create.*step=1/, { timeout: 15000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: file, fullPage: false });
  console.log(`Saved ${file}`);
}

async function main() {
  await mkdir(OUT_DIR, { recursive: true });

  const browser = await chromium.launch({
    headless: true,
    channel: 'chrome',
  });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
  });
  const page = await context.newPage();

  await capture(page, PAGES[0]);
  await login(page);

  for (const entry of PAGES.slice(1)) {
    if (entry.skip) {
      const file = path.join(OUT_DIR, `${entry.name}.png`);
      try {
        await access(file);
        console.log(`Skip ${entry.name} (already exists)`);
      } catch {
        console.log(`Skip ${entry.name} (flagged; file missing — capture manually if needed)`);
      }
      continue;
    }

    try {
      await capture(page, entry);
    } catch (err) {
      console.error(`Failed ${entry.name}: ${err.message}`);
    }
  }

  try {
    await captureWizardDetails(page);
  } catch (err) {
    console.error(`Failed 51-employee-wizard-details: ${err.message}`);
  }

  await browser.close();
  console.log('Done.');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
