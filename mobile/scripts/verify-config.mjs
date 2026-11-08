#!/usr/bin/env node
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const active = join(root, 'capacitor.config.json');
const envFile = join(root, '.capacitor-env');

if (!existsSync(active)) {
  console.error('capacitor.config.json missing — run npm run env:test or env:live first.');
  process.exit(1);
}

const cfg = JSON.parse(readFileSync(active, 'utf8'));
const env = existsSync(envFile) ? readFileSync(envFile, 'utf8').trim() : 'unknown';
const url = cfg?.server?.url || '';

console.log(`active_env=${env}`);
console.log(`appId=${cfg.appId}`);
console.log(`appName=${cfg.appName}`);
console.log(`server.url=${url}`);
console.log(`cleartext=${cfg?.server?.cleartext === true ? 'YES (forbidden)' : 'no'}`);

if (cfg?.server?.cleartext === true) {
  console.error('HTTP cleartext is not allowed.');
  process.exit(1);
}

if (env === 'live' && url.includes('generalsys.idigital-cs.com')) {
  console.error('LIVE active config points at TEST — abort.');
  process.exit(1);
}
if (env === 'test' && url.includes('system.genral.net')) {
  console.error('TEST active config points at LIVE — abort.');
  process.exit(1);
}

console.log('verify-config: OK');
