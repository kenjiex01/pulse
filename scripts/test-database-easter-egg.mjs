import { chromium } from 'playwright';

const baseUrl = process.env.PULSE_URL ?? 'http://127.0.0.1:8000';
const email = process.env.PULSE_ADMIN_EMAIL ?? 'superadmin@icct.edu.ph';
const password = process.env.PULSE_ADMIN_PASSWORD ?? 'Password123!';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

await page.goto(`${baseUrl}/login`);
await page.fill('input[name="email"]', email);
await page.fill('input[name="password"]', password);
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');

await page.goto(`${baseUrl}/database`);
await page.waitForSelector('[data-database-restore-easter-egg]');

const hasPanel = await page.locator('[data-database-sql-upload]').count();
const hasEgg = await page.locator('[data-database-restore-easter-egg]').count();
console.log('Easter egg root:', hasEgg, 'Upload panel:', hasPanel);

await page.click('[data-database-restore-easter-egg]');

for (let i = 0; i < 4; i += 1) {
    await page.keyboard.press('Period');
    await page.waitForTimeout(250);
}

await page.keyboard.down('Period');
await page.waitForTimeout(5200);
await page.keyboard.up('Period');

const visible = await page.locator('#database-sql-upload-panel').isVisible();
console.log('Panel visible after sequence:', visible);

await browser.close();
process.exit(visible ? 0 : 1);
