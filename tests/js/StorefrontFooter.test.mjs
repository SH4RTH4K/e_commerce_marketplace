import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const source = await readFile(new URL('../../resources/js/Layouts/StorefrontLayout.jsx', import.meta.url), 'utf8');

test('storefront footer exposes terms and privacy links in both template branches', () => {
  assert.match(source, /href="\/terms">Terms &amp; Conditions<\/a>/);
  assert.match(source, /href="\/privacy">Privacy Policy<\/a>/);
  assert.match(source, /link\.url === '\/terms'/);
  assert.match(source, /link\.url === '\/privacy'/);
});
