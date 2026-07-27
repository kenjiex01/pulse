/**
 * Expands 3-column field-table rows to 4-column (Field | Ano ito | Para saan | Notes)
 * by splitting combined meaning/notes heuristically.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const file = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'docs', 'user-guide', 'index.html');
let html = readFileSync(file, 'utf8');

// Standardize 3-col headers still using "Meaning"
html = html.replace(
  /<thead><tr><th>Field<\/th><th>Meaning<\/th><th>Notes<\/th><\/tr><\/thead>/g,
  '<thead><tr><th>Field</th><th>Ano ito</th><th>Para saan (Gamit)</th><th>Notes</th></tr></thead>',
);
html = html.replace(
  /<thead><tr><th>Column<\/th><th>Meaning<\/th><th>Notes<\/th><\/tr><\/thead>/g,
  '<thead><tr><th>Column</th><th>Ano ito</th><th>Para saan (Gamit)</th><th>Notes</th></tr></thead>',
);

// Gamit hints keyed by field name (lowercase) — fallback uses second column
const GAMIT = {
  'policy code': 'Unique identifier ng policy group sa employee assignment',
  'policy name': 'Display name sa Employee Profile dropdown',
  description: 'Documentation / label sa lists',
  active: 'Control kung selectable pa ang record',
  'shift code': 'I-assign sa Employee Profile bilang work schedule',
  'time in': 'Base schedule para sa tardiness/OT computation',
  'time out': 'End schedule para sa undertime/OT computation',
  'break minute': 'Expected break duration sa payroll break logic',
  'paid break?': 'Kung counted ang break sa paid hours',
  'device name': 'Piliin sa Time Logs upload Format Type dropdown',
  'employee id (type)': 'Match uploaded punches sa employee record',
  'employee id (column)': 'Parse correct column sa upload file',
  'date (type)': 'Determine which date column meaning to use',
  'date (column)': 'Locate date sa upload file',
  'time in (type / column)': 'Parse time-in punches from file',
  'time out': 'Parse time-out punches from file',
  'same column for time in / time out': 'Biometric punch-per-row parsing',
  'time column': 'Single punch time column sa biometric files',
  'indicator column': 'Distinguish IN vs OUT punches',
  'time in identifier': 'Map punch value to time-in',
  'time out identifier': 'Map punch value to time-out',
  'logout reason': 'Optional reason code from device export',
  'custom fields': 'Extra data columns from device export',
  'holiday code': 'Reference code sa holiday groups at year entries',
  recurring: 'Auto-include sa new holiday years',
  'short description': 'Abbreviated label sa reports',
  date: 'When holiday applies',
  'legal / special': 'Legal vs special holiday pay rules',
  'holiday group code': 'Assign sa Employee Profile Holiday Group',
  'select holidays': 'Define which holidays belong to group',
  year: 'Manage holidays per calendar year',
  holiday: 'Add master holiday to specific year',
  'allow flexi-time': 'Enable flexible arrival window',
  'max. flexi-time (minutes)': 'Cap flexi allowance before counted late',
  'grace period (minutes)': 'Late minutes ignored before penalty',
  'actual late less grace period': 'Reduce counted late by grace',
  'rounding rule': 'Round computed minutes before payroll',
  'leave type': 'Tag leave records at payroll process',
  'from (min)': 'Start of equivalent minute range',
  'to (min)': 'End of equivalent minute range',
  'equivalent (min)': 'Billable minutes for payroll LTDE/deductions',
  'mark as absent': 'Treat range as absence not minutes',
  'treatment of excess hours': 'Drive OT income (OVRT) sa payroll batch',
  'non-regular days': 'OT form rules on rest days/holidays',
  'before time schedule': 'Early arrival OT computation',
  'after time schedule': 'Late stay OT computation',
  'minimum no. of minutes': 'Threshold before OT is billable',
  'special ot start': 'Night/special OT window start',
  'special ot min. minutes': 'Min minutes in special OT window',
  'break computation': 'How break time is derived for payroll',
  'deduct tardiness in breaks': 'Enable break-late LTDE at payroll',
  'compute night differential': 'Enable NDIF income at payroll',
  'start time': 'ND period start for ND hours',
  'end time': 'ND period end for ND hours',
  'enable employee attendance approval': 'Require approval workflow',
  'buffer time for time in (hours)': 'Valid early punch window',
  'buffer time for time out (hours)': 'Valid late punch window',
  'enable employee validation for rest days': 'Enforce weekly rest day cap',
  'maximum no. of rest days per week': 'Max rest days allowed',
  'format type': 'Select column mapping for upload parse',
  'upload file': 'Import data into staging for preview/commit',
  campus: 'Scope DTR parse rules at campus',
  'day checkbox': 'Define employee weekly rest days',
  'paid?': 'Whether rest day is paid',
  'holiday group': 'Which holidays apply to employee',
  'policy group': 'Apply tardiness/OT/break/ND rules',
  'enable cancellation of leaves': 'Allow leave cancellation feature',
  'auto populate attendance': 'Auto-generate attendance from schedule',
  'date from': 'Start of faculty load export period',
  'date to': 'End of faculty load export period',
  'time in': 'Actual class arrival for attendance/payroll',
  remarks: 'User notes on load row',
  comments: 'Additional user notes',
  'verification remarks': 'Verifier notes on load row',
};

html = html.replace(
  /<tr><td>(<strong>[^<]+<\/strong>)<\/td><td>([^<]*(?:<code>[^<]*<\/code>[^<]*)?)<\/td><td>([^<]*)<\/td><\/tr>/g,
  (match, field, ano, notes) => {
    const key = field.replace(/<\/?strong>/g, '').toLowerCase().trim();
    const gamit = GAMIT[key] ?? ano;
    return `<tr><td>${field}</td><td>${ano}</td><td>${gamit}</td><td>${notes}</td></tr>`;
  },
);

// Fix 2-col pre-filled table
html = html.replace(
  /<thead><tr><th>Column<\/th><th>Meaning<\/th><\/tr><\/thead>/g,
  '<thead><tr><th>Column</th><th>Ano ito</th><th>Para saan (Gamit)</th></tr></thead>',
);

writeFileSync(file, html);
console.log('Expanded field tables.');
