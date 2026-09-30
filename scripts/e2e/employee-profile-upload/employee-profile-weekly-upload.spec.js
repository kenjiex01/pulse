import fs from 'fs';
import os from 'os';
import path from 'path';
import { test, expect } from '@playwright/test';

const demoPauseMs = process.env.PULSE_E2E_DEMO === '0' ? 0 : 2200;

async function demoPause(page) {
  if (demoPauseMs > 0) {
    await page.waitForTimeout(demoPauseMs);
  }
}

test('Employee Profile upload — template download and upload preview (demo video)', async ({ page }) => {
  test.setTimeout(180_000);

  await page.goto('/timekeeping/employee-profile');
  await expect(page.getByRole('heading', { name: 'Employee Profile' })).toBeVisible();
  await demoPause(page);

  const uploadBtn = page.locator('[data-modal-open="employee-profile-upload-modal"]');
  await expect(uploadBtn).toBeVisible();
  await uploadBtn.scrollIntoViewIfNeeded();
  await uploadBtn.click();

  const uploadModal = page.locator('#employee-profile-upload-modal');
  await expect(uploadModal.getByRole('heading', { name: 'Upload Employee Setup' })).toBeVisible();
  await expect(uploadModal.getByText('shift columns', { exact: false }).first()).toBeVisible();
  await demoPause(page);

  const downloadPromise = page.waitForEvent('download');
  await uploadModal.getByRole('link', { name: 'Download Template' }).click();
  const download = await downloadPromise;

  const downloadPath = path.join(os.tmpdir(), `employee-profile-setup-${Date.now()}.csv`);
  await download.saveAs(downloadPath);

  let csv = fs.readFileSync(downloadPath, 'utf8');
  const lines = csv.trim().split('\n');
  expect(lines[0]).toContain('shift_mon');
  expect(lines[0]).toContain('shift_sat');
  expect(lines[1]).toContain('Mon Shift Code');
  expect(lines[1]).toContain('Default Shift Code');

  // Demo: upload one employee row that already has required setup (not the whole template).
  const aliases = lines[0].split(',');
  const idxHoliday = aliases.indexOf('holiday_group_code');
  const idxPolicy = aliases.indexOf('policy_name');
  const idxShift = aliases.indexOf('shift_code');
  const demoRows = lines.slice(3).filter((line) => {
    const cells = line.split(',');
    return (
      (cells[idxHoliday] ?? '').trim() !== ''
      && (cells[idxPolicy] ?? '').trim() !== ''
      && (cells[idxShift] ?? '').trim() !== ''
    );
  });
  expect(demoRows.length).toBeGreaterThan(0);
  csv = [lines[0], lines[1], lines[2], demoRows[0]].join('\n') + '\n';
  fs.writeFileSync(downloadPath, csv, 'utf8');
  await demoPause(page);

  await uploadModal.locator('input[name="upload_file"]').setInputFiles(downloadPath);
  await demoPause(page);

  await uploadModal.getByRole('button', { name: 'Upload' }).click();

  const previewModal = page.locator('#employee-profile-upload-preview-modal');
  await expect(previewModal.getByRole('heading', { name: 'Upload Preview' })).toBeVisible({ timeout: 90_000 });
  await expect(previewModal.getByText('Valid / Invalid Rows')).toBeVisible();
  await expect(previewModal.getByText('Valid Records Preview')).toBeVisible();
  await previewModal.locator('table').first().scrollIntoViewIfNeeded();
  await demoPause(page);
  await demoPause(page);

  fs.unlinkSync(downloadPath);
});
