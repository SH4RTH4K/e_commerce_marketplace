import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { transformWithOxc } from 'vite';

const filename = new URL('../../resources/js/Components/Storefront/ProductRating.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const context = vm.createContext({ React });
vm.runInContext(`${code}\nglobalThis.Component = ProductRating;`, context);
const render = props => renderToStaticMarkup(React.createElement(context.Component, props));

test('an imported five-star value with no reviews shows no rating stars', () => {
  const html = render({ rating: 5, reviewCount: 0 });
  assert.match(html, /No reviews yet/);
  assert.doesNotMatch(html, /★|out of 5 stars/);
});

test('missing review counts also show the empty state', () => {
  assert.match(render({ rating: 5 }), /No reviews yet/);
});

test('reviewed products retain an accessible rating and review count', () => {
  const html = render({ rating: 4.2, reviewCount: 3 });
  assert.match(html, /4 out of 5 stars from 3 reviews/);
  assert.match(html, /★★★★/);
  assert.doesNotMatch(html, /No reviews yet/);
});
