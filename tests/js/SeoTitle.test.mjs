import assert from 'node:assert/strict';
import { test } from 'node:test';
import { seoPageTitle } from '../../resources/js/lib/seoTitle.js';

test('existing SEO branding stays verbatim and unbranded page titles receive the brand once', () => {
  assert.equal(seoPageTitle("Men's Fashion | TAQI LIFE", 'TAQI LIFE'), "Men's Fashion | TAQI LIFE");
  assert.equal(seoPageTitle('Soap Dispenser — Taqi Life', 'TAQI LIFE'), 'Soap Dispenser — Taqi Life');
  assert.equal(seoPageTitle('TAQI LIFE Online Shopping', 'TAQI LIFE'), 'TAQI LIFE Online Shopping');
  assert.equal(seoPageTitle('Customer CRM', 'TAQI LIFE'), 'Customer CRM — TAQI LIFE');
  assert.equal(seoPageTitle('Lifestyle Products', 'Life'), 'Lifestyle Products — Life');
});

test('home, empty titles, changing brands and regex punctuation retain the proper fallback', () => {
  assert.equal(seoPageTitle('Home', 'TAQI LIFE', 'TAQI LIFE Online Shopping'), 'TAQI LIFE Online Shopping');
  assert.equal(seoPageTitle('', 'TAQI LIFE'), 'TAQI LIFE');
  assert.equal(seoPageTitle('Catalog | Shop (BD)+', 'Shop (BD)+'), 'Catalog | Shop (BD)+');
  assert.equal(seoPageTitle('Catalog | Old Brand', 'New Brand'), 'Catalog | Old Brand — New Brand');
});
