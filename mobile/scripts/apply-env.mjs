#!/usr/bin/env node
/**
 * Copy environment Capacitor config → capacitor.config.json
 * TEST and LIVE are explicit; no silent fallback to TEST.
 */
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const env = (process.argv[2] || '').toLowerCase();

if (env !== 'test' && env !== 'live') {
  console.error('Usage: node scripts/apply-env.mjs <test|live>');
  process.exit(1);
}

const src = join(root, 'config', `capacitor.${env}.json`);
const dest = join(root, 'capacitor.config.json');

if (!existsSync(src)) {
  console.error(`Missing config: ${src}`);
  process.exit(1);
}

const cfg = JSON.parse(readFileSync(src, 'utf8'));
const url = cfg?.server?.url || '';
if (env === 'live') {
  if (!url.includes('system.genral.net')) {
    console.error('LIVE config must target https://system.genral.net/ — refusing to apply.');
    process.exit(1);
  }
  if (url.includes('generalsys.idigital-cs.com')) {
    console.error('LIVE config must not reference the TEST domain.');
    process.exit(1);
  }
}
if (env === 'test') {
  if (!url.includes('generalsys.idigital-cs.com')) {
    console.error('TEST config must target generalsys.idigital-cs.com — refusing to apply.');
    process.exit(1);
  }
}

copyFileSync(src, dest);
writeFileSync(join(root, '.capacitor-env'), env + '\n', 'utf8');
console.log(`Applied Capacitor env=${env} → ${dest}`);
console.log(`server.url=${url}`);
console.log(`appId=${cfg.appId}`);
