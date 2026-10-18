// PM2 processes for the marketplace (storefront, Reverb WebSocket server, queue worker).
// `args` must be arrays: a single string is passed to php as ONE argument and artisan rejects it.
const APP_DIR = '/var/www/marketplace_platforms';

module.exports = {
  apps: [
    {
      name: 'mp-frontend',
      cwd: `${APP_DIR}/frontend`,
      script: 'node_modules/next/dist/bin/next',
      args: ['start', '-p', '3000', '-H', '127.0.0.1'],
      env: { NODE_ENV: 'production' },
      max_memory_restart: '1G',
      autorestart: true,
    },
    {
      name: 'mp-reverb',
      cwd: `${APP_DIR}/backend`,
      script: 'artisan',
      interpreter: 'php',
      args: ['reverb:start', '--host=127.0.0.1', '--port=8080', '--no-interaction'],
      autorestart: true,
      restart_delay: 3000,
      kill_timeout: 10000,
    },
    {
      name: 'mp-queue',
      cwd: `${APP_DIR}/backend`,
      script: 'artisan',
      interpreter: 'php',
      args: ['queue:work', '--sleep=3', '--tries=3', '--timeout=90', '--max-time=3600'],
      autorestart: true,
      restart_delay: 3000,
      kill_timeout: 100000,
    },
  ],
};
