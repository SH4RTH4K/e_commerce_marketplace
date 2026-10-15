export function seoPageTitle(title, siteName, defaultTitle = '') {
  const brand = String(siteName || 'Shop').trim();
  const cleanTitle = String(title || '').trim();
  if (!cleanTitle || cleanTitle.toLowerCase() === 'home') return defaultTitle || brand;
  const escapedBrand = brand.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+');
  const hasBrand = new RegExp(`(^|[\\s|–—:,-])${escapedBrand}($|[\\s|–—:,-])`, 'iu').test(cleanTitle);
  return hasBrand ? cleanTitle : `${cleanTitle} — ${brand}`;
}
