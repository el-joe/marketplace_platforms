import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  timeout: 30000,
  retries: 0,
  reporter: [['html'], ['list']],

  use: {
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    // ── Admin panel ────────────────────────────────────────────────────────
    {
      name: 'admin-setup',
      testMatch: /admin\/auth\.setup\.ts/,
      use: { baseURL: 'http://admin.noon.loc' },
    },
    {
      name: 'admin',
      testMatch: /admin\/(?!auth\.setup).*\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://admin.noon.loc',
        storageState: 'tests/admin/.auth/admin.json',
      },
      dependencies: ['admin-setup'],
    },

    // ── Vendor (Partner) panel ─────────────────────────────────────────────
    {
      name: 'vendor-setup',
      testMatch: /vendor\/auth\.setup\.ts/,
      use: { baseURL: 'http://partner.noon.loc' },
    },
    {
      name: 'vendor',
      testMatch: /vendor\/(?!auth\.setup).*\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://partner.noon.loc',
        storageState: 'tests/vendor/.auth/vendor.json',
      },
      dependencies: ['vendor-setup'],
    },

    // ── Marketer panel ─────────────────────────────────────────────────────
    {
      name: 'marketer-setup',
      testMatch: /marketer\/auth\.setup\.ts/,
      use: { baseURL: 'http://marketer.noon.loc' },
    },
    {
      name: 'marketer',
      testMatch: /marketer\/(?!auth\.setup).*\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://marketer.noon.loc',
        storageState: 'tests/marketer/.auth/marketer.json',
      },
      dependencies: ['marketer-setup'],
    },

    // ── Carrier (shipping company) panel ──────────────────────────────────────
    {
      name: 'carrier-setup',
      testMatch: /carrier\/auth\.setup\.ts/,
      use: { baseURL: 'http://carrier.noon.loc' },
    },
    {
      name: 'carrier',
      testMatch: /carrier\/(?!auth\.setup).*\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://carrier.noon.loc',
        storageState: 'tests/carrier/.auth/carrier.json',
      },
      dependencies: ['carrier-setup'],
    },

    // ── Delivery panel ─────────────────────────────────────────────────────────
    {
      name: 'delivery-setup',
      testMatch: /delivery\/auth\.setup\.ts/,
      use: { baseURL: 'http://delivery.noon.loc' },
    },
    {
      name: 'delivery',
      testMatch: /delivery\/(?!auth\.setup).*\.spec\.ts/,
      use: {
        ...devices['iPhone 13'],
        baseURL: 'http://delivery.noon.loc',
        storageState: 'tests/delivery/.auth/delivery.json',
        permissions: ['geolocation'],
        geolocation: { latitude: 25.2048, longitude: 55.2708 }, // Dubai — AE-zoned test agent
      },
      dependencies: ['delivery-setup'],
    },
  ],
});
