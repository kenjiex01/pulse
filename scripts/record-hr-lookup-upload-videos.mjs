import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const baseUrl = process.env.PULSE_URL || 'http://127.0.0.1:8000';
const videoDir = path.resolve('docs/videos/hr-lookup-uploads');
const workDir = path.resolve('storage/app/hr-lookup-upload-videos');

const modules = [
    {
        lookup: 'campuses',
        find: 'PW Upload Campus',
        values: {
            'Campus Code': 'ZZUP',
            'Campus Name': 'PW Upload Campus',
            'Minimum Wage': '610',
            Address: 'Upload Test Address',
            Phone: '09170000000',
            Email: 'zzup@example.com',
            Website: 'https://example.com',
            Active: 'Yes',
        },
    },
    {
        lookup: 'designations',
        find: 'PW Upload Designation',
        values: { 'Designation Name': 'PW Upload Designation', Active: 'Yes' },
    },
    {
        lookup: 'employee-categories',
        find: 'PW Upload Category',
        values: { 'Category Name': 'PW Upload Category', 'Payroll Hours': 'Logs', 'Sort Order': '9', Active: 'Yes' },
    },
    {
        lookup: 'positions',
        find: 'PW Upload Position',
        values: { 'Position Name': 'PW Upload Position', Active: 'Yes' },
    },
    {
        lookup: 'ranks',
        find: 'PW Upload Rank',
        values: { 'Rank Name': 'PW Upload Rank', Active: 'Yes' },
    },
    {
        lookup: 'employment-types',
        find: 'PW Upload Type',
        values: { 'Type Code': 'UPT', 'Type Name': 'PW Upload Type', Description: 'Uploaded employment type', 'Sort Order': '1', Active: 'Yes' },
    },
    {
        lookup: 'employee-departments',
        find: 'PW Upload Department',
        values: { 'Department Code': 'UPD', 'Department Name': 'PW Upload Department', Description: 'Uploaded department', 'Sort Order': '1', Active: 'Yes' },
    },
    {
        lookup: 'colleges',
        find: 'PW Upload College',
        values: { Campus: 'ZZUP', 'College Code': 'UPC', 'College Name': 'PW Upload College', Description: 'Uploaded college', Active: 'Yes' },
    },
    {
        lookup: 'programs',
        find: 'PW Upload Program',
        values: { Campus: 'ZZUP', 'Program Code': 'UPP', 'Program Name': 'PW Upload Program', Active: 'Yes' },
    },
    {
        lookup: 'document-types',
        find: 'PW Upload Document',
        values: { 'Type Code': 'UPDT', 'Type Name': 'PW Upload Document', Description: 'Uploaded document type', Required: 'No', 'Sort Order': '1', Active: 'Yes' },
    },
];

function csvEscape(value) {
    return `"${String(value ?? '').replaceAll('"', '""')}"`;
}

function fillTemplate(templatePath, values) {
    const text = fs.readFileSync(templatePath, 'utf8').replace(/^\uFEFF/, '');
    const headerLine = text.split(/\r?\n/).find((line) => line.trim() !== '') ?? '';
    const headers = headerLine.split(',').map((cell) => cell.trim().replace(/^"|"$/g, ''));
    const row = headers.map((header) => csvEscape(values[header] ?? '')).join(',');
    const filled = path.join(workDir, `${path.basename(templatePath, '.csv')}-filled.csv`);
    fs.writeFileSync(filled, `${text.trimEnd()}\n${row}\n`);

    return filled;
}

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const loginContext = await browser.newContext();
const loginPage = await loginContext.newPage();
await loginPage.goto(`${baseUrl}/login`);
await loginPage.fill('input[name="email"]', 'superadmin@icct.edu.ph');
await loginPage.fill('input[name="password"]', 'Password123!');
await loginPage.click('button[type="submit"]');
await loginPage.waitForURL('**/dashboard');
const statePath = path.join(workDir, 'state.json');
fs.mkdirSync(workDir, { recursive: true });
fs.mkdirSync(videoDir, { recursive: true });
await loginContext.storageState({ path: statePath });
await loginContext.close();

const saved = [];

for (const module of modules) {
    const context = await browser.newContext({
        storageState: statePath,
        acceptDownloads: true,
        recordVideo: { dir: videoDir, size: { width: 1280, height: 800 } },
        viewport: { width: 1280, height: 800 },
    });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);

    try {
        await page.goto(`${baseUrl}/hr/${module.lookup}`);
        await page.getByRole('button', { name: 'Upload' }).click();
        const modal = page.locator(`#hr-lookup-upload-${module.lookup}`);
        await modal.waitFor({ state: 'visible' });
        await page.waitForTimeout(600);

        const downloadPromise = page.waitForEvent('download');
        await modal.locator('[data-hr-lookup-template-download]').click();
        const download = await downloadPromise;
        const templatePath = path.join(workDir, `${module.lookup}-template.csv`);
        await download.saveAs(templatePath);
        await page.waitForTimeout(600);

        const filled = fillTemplate(templatePath, module.values);
        await modal.locator('[data-hr-lookup-upload-file]').setInputFiles(filled);
        await page.waitForTimeout(400);
        await modal.locator('button[type="submit"]').click();
        await page.locator('.bg-green-50').getByText('Uploaded').waitFor();
        await page.locator('[data-live-table-search]').fill(module.find);
        await page.locator('[data-live-table-results]').getByText(module.find).first().waitFor();
        await page.waitForTimeout(1200);
        console.log(`ok ${module.lookup}`);
    } catch (error) {
        console.error(`failed ${module.lookup}`, error);
        await page.screenshot({ path: path.join(videoDir, `${module.lookup}-error.png`) }).catch(() => {});
        throw error;
    } finally {
        const video = page.video();
        await page.close();
        await context.close();
        if (video) {
            const target = path.join(videoDir, `${module.lookup}.webm`);
            await video.saveAs(target);
            saved.push(target);
        }
    }
}

await browser.close();
console.log(saved.join('\n'));
