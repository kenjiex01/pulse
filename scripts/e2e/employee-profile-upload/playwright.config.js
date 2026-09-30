import { defineConfig } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const demo = process.env.PULSE_E2E_DEMO !== '0';
const authFile = path.join(__dirname, '.auth-state.json');

export default defineConfig({
  testDir: '.',
  timeout: 180_000,
  retries: 0,
  workers: 1,
  globalSetup: './global-setup.js',
  reporter: [['list']],
  outputDir: path.join(__dirname, 'test-results'),
  use: {
    baseURL: process.env.PULSE_E2E_BASE_URL ?? 'http://127.0.0.1:8000',
    headless: true,
    storageState: authFile,
    video: 'on',
    trace: 'off',
    screenshot: 'off',
    viewport: { width: 1440, height: 900 },
    actionTimeout: 30_000,
    launchOptions: {
      slowMo: demo ? 350 : 0,
    },
  },
});
