#!/usr/bin/env node
// Simple Reverb WebSocket checker
// Usage: node scripts/check-reverb.js ws://127.0.0.1:8080/ws/app/<APP_KEY>?protocol=7
// Requires: npm i ws

const WebSocket = require('ws');

const url = process.argv[2];
if (!url) {
  console.error('Usage: node scripts/check-reverb.js <ws://... or wss://...>');
  process.exit(2);
}

const timeout = Number(process.env.CHECK_REVERB_TIMEOUT || 5000);

console.log(`Checking Reverb endpoint: ${url}`);

const ws = new WebSocket(url, { handshakeTimeout: timeout });

let settled = false;

const fail = (msg, code = 1) => {
  if (settled) return;
  settled = true;
  console.error('FAIL:', msg);
  process.exit(code);
};

const succeed = (msg = 'Connected') => {
  if (settled) return;
  settled = true;
  console.log('OK:', msg);
  ws.close(1000, 'ok');
  process.exit(0);
};

ws.on('open', () => {
  succeed('WebSocket handshake succeeded');
});

ws.on('error', (err) => {
  fail(err.message || String(err));
});

// Safety timeout
setTimeout(() => fail('timeout waiting for open (' + timeout + 'ms)'), timeout + 1000);
