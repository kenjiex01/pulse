/**
 * Renders Hours Worked upload sample as a spreadsheet-style PNG for the user guide.
 */
import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, '..', 'docs', 'user-guide', 'images', '54-hours-worked-upload-sample.png');

const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f8fafc;
    padding: 32px;
    color: #111827;
  }
  .sheet {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    max-width: 920px;
  }
  .title {
    padding: 14px 18px;
    background: #0f172a;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
  }
  .title span { color: #94a3b8; font-weight: 400; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { border: 1px solid #e5e7eb; padding: 10px 14px; text-align: left; vertical-align: top; }
  tr.row-key td { background: #eff6ff; color: #1d4ed8; font-family: ui-monospace, monospace; font-size: 12px; font-weight: 600; }
  tr.row-label td { background: #f9fafb; font-weight: 600; color: #374151; }
  tr.row-hint td { background: #fffbeb; color: #92400e; font-size: 11px; line-height: 1.45; }
  tr.row-data td { background: #fff; font-family: ui-monospace, monospace; }
  tr.row-data:nth-child(even) td { background: #fafafa; }
  .col-a { width: 22%; }
  .note {
    margin-top: 14px;
    padding: 12px 16px;
    background: #ecfeff;
    border: 1px solid #a5f3fc;
    border-radius: 6px;
    font-size: 12px;
    color: #0e7490;
    max-width: 920px;
  }
</style>
</head>
<body>
  <div class="sheet">
    <div class="title">Hours Worked upload sample <span>— employee 2026-00003</span></div>
    <table>
      <tr class="row-key">
        <td class="col-a">emp_num</td>
        <td>full_name</td>
        <td>day_type</td>
        <td>time_type</td>
        <td>hours</td>
      </tr>
      <tr class="row-label">
        <td>Employee No.</td>
        <td>Full Name</td>
        <td>Day Type Code</td>
        <td>Time Type Code</td>
        <td>No. of Hours</td>
      </tr>
      <tr class="row-hint">
        <td>Accepts all existing Employee No.</td>
        <td>For reference only; not imported.</td>
        <td>Accepts current value(s): LEGL, REGU, RES, RSLG, RSSP, SPCL, SUND</td>
        <td>Accepts current value(s): BP, NDIF, NOT, NSOT, OT, SOT</td>
        <td>Accepts up to 8 digits and 2 decimals</td>
      </tr>
      <tr class="row-data">
        <td>2026-00003</td>
        <td>Maria L. Santos</td>
        <td>REGU</td>
        <td>BP</td>
        <td>40</td>
      </tr>
      <tr class="row-data">
        <td>2026-00003</td>
        <td>Maria L. Santos</td>
        <td>REGU</td>
        <td>OT</td>
        <td>4</td>
      </tr>
      <tr class="row-data">
        <td>2026-00003</td>
        <td>Maria L. Santos</td>
        <td>REGU</td>
        <td>NDIF</td>
        <td>2</td>
      </tr>
    </table>
  </div>
  <p class="note">Save as <strong>.csv</strong> or <strong>.txt</strong> — first 3 rows are template headers; data starts row 4.</p>
</body>
</html>`;

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const page = await browser.newPage({ viewport: { width: 1000, height: 420 }, deviceScaleFactor: 2 });
await page.setContent(html, { waitUntil: 'networkidle' });
await page.screenshot({ path: OUT, fullPage: true });
await browser.close();
console.log('Saved', OUT);
