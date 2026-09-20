import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

// ─── Icon helper ──────────────────────────────────────────────────────────────
function Icon({ d, size = 15, className = '' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={`shrink-0 ${className}`}>
      <path d={d} />
    </svg>
  );
}

// ─── Summary Stat Card ─────────────────────────────────────────────────────────
function SummaryCard({ label, value, color, iconD, href }) {
  return (
    <a href={href} aria-label={`Filter inventory by ${label}`} className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center gap-3 hover:border-orange-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-orange-300 transition-all">
      <div className="p-2.5 rounded-xl shrink-0" style={{ background: `${color}18` }}>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d={iconD} />
        </svg>
      </div>
      <div>
        <p className="text-xl font-bold text-gray-900">{value}</p>
        <p className="text-[11px] text-gray-400 uppercase tracking-wide font-medium">{label}</p>
      </div>
    </a>
  );
}

// ─── Stock Alert Badge ────────────────────────────────────────────────────────
function StockAlert({ qty }) {
  if (qty <= 0)  return <span className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-full bg-red-50 text-red-600 border border-red-200"><span className="w-1.5 h-1.5 rounded-full bg-red-500" />OUT OF STOCK</span>;
  if (qty <= 5)  return <span className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-full bg-amber-50 text-amber-600 border border-amber-200"><span className="w-1.5 h-1.5 rounded-full bg-amber-400" />LOW STOCK</span>;
  return              <span className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-full bg-green-50 text-green-700 border border-green-200"><span className="w-1.5 h-1.5 rounded-full bg-green-500" />IN STOCK</span>;
}

// ─── Inline stock editor ──────────────────────────────────────────────────────
function StockEditor({ product }) {
  const [editing, setEditing] = useState(false);
  const [val, setVal]         = useState(String(product.stock_quantity ?? 0));

  const save = () => {
    router.patch(`/admin/inventory/${product.id}/stock`, { stock_quantity: parseInt(val, 10) }, {
      preserveScroll: true,
      onSuccess: () => setEditing(false),
    });
  };

  if (editing) {
    return (
      <div className="flex items-center gap-1.5">
        <input type="number" value={val} onChange={e => setVal(e.target.value)} min="0"
          className="w-20 border border-orange-300 rounded-lg px-2 py-1 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-orange-300 text-center"
          onKeyDown={e => { if (e.key === 'Enter') save(); if (e.key === 'Escape') setEditing(false); }}
          autoFocus
        />
        <button onClick={save} className="px-2 py-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold rounded-lg transition-colors">✓</button>
        <button onClick={() => setEditing(false)} className="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs rounded-lg transition-colors">✗</button>
      </div>
    );
  }

  return (
    <button onClick={() => setEditing(true)}
      className="text-sm font-bold text-gray-800 hover:text-orange-600 flex items-center gap-1.5 group transition-colors">
      {product.stock_quantity ?? 0}
      <Icon d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" size={11} className="text-gray-300 group-hover:text-orange-400 transition-colors" />
    </button>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function InventoryIndex({ products, categories, q, category, stock_level, visibility, order, summary }) {
  const { props } = usePage();
  const sym = props.app?.currency_symbol || '৳';

  const [search, setSearch]     = useState(q || '');
  const [catFilter, setCatFilter] = useState(category || '');
  const [levelFilter, setLevelFilter] = useState(stock_level || '');
  const [visibilityFilter, setVisibilityFilter] = useState(visibility || '');
  const [orderFilter, setOrderFilter] = useState(order || 'stock_asc');

  const handleFilter = (overrides = {}) => {
    router.get('/admin/inventory', { q: search, category: catFilter, stock_level: levelFilter, visibility: visibilityFilter, order: orderFilter, ...overrides }, { preserveState: true });
  };

  const clearFilters = () => {
    setSearch('');
    setCatFilter('');
    setLevelFilter('');
    setVisibilityFilter('');
    setOrderFilter('stock_asc');
    router.get('/admin/inventory');
  };

  const hasFilters = Boolean(search || catFilter || levelFilter || visibilityFilter || orderFilter !== 'stock_asc');

  const outCount = summary?.out_stock ?? (products.data || []).filter(p => (p.stock_quantity ?? 0) <= 0).length;
  const lowCount = summary?.low_stock ?? (products.data || []).filter(p => (p.stock_quantity ?? 0) > 0 && (p.stock_quantity ?? 0) <= 5).length;

  return (
    <>
      <Head title="Stock & Inventory Master" />
      <AdminLayout title="">
        <div className="space-y-5">

          {/* ── Page Header ── */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Stock &amp; Inventory Master</h1>
              <p className="text-sm text-gray-400 mt-0.5">Monitor, filter, and quick-edit product stock levels.</p>
            </div>
            <a href="/admin/products/create"
              className="px-3.5 py-2 text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm shadow-orange-200 self-start sm:self-auto">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Product
            </a>
          </div>

          {/* ── Summary cards ── */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <SummaryCard label="Total Products" value={(summary?.total ?? 0).toLocaleString()} color="#6366f1" href="/admin/inventory"
              iconD="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            <SummaryCard label="In Stock" value={(summary?.in_stock ?? 0).toLocaleString()} color="#10b981" href="/admin/inventory?stock_level=in"
              iconD="M22 11.08V12a10 10 0 11-5.93-9.14M22 4L12 14.01l-3-3" />
            <SummaryCard label="Low Stock" value={lowCount.toLocaleString()} color="#f59e0b" href="/admin/inventory?stock_level=low"
              iconD="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            <SummaryCard label="Out of Stock" value={outCount.toLocaleString()} color="#ef4444" href="/admin/inventory?stock_level=out"
              iconD="M18 6L6 18M6 6l12 12" />
          </div>

          {/* ── Filter Bar ── */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <form onSubmit={e => { e.preventDefault(); handleFilter(); }}>
              <div className="flex flex-wrap gap-2.5 items-center">
                <div className="relative flex-1 min-w-[200px]">
                  <Icon d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input value={search} onChange={e => setSearch(e.target.value)}
                    placeholder="Search by name, code or SKU..."
                    className="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
                </div>
                <select value={catFilter} onChange={e => setCatFilter(e.target.value)}
                  className="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none bg-white text-gray-600 min-w-[150px]">
                  <option value="">All Categories</option>
                  {(categories || []).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select value={levelFilter} onChange={e => setLevelFilter(e.target.value)}
                  className="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none bg-white text-gray-600 min-w-[160px]">
                  <option value="">All Stock Levels</option>
                  <option value="out">Out of Stock</option>
                  <option value="low">Low Stock (≤5)</option>
                  <option value="in">In Stock (&gt;5)</option>
                </select>
                <select value={visibilityFilter} onChange={e => setVisibilityFilter(e.target.value)}
                  className="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none bg-white text-gray-600 min-w-[145px]">
                  <option value="">All Visibility</option>
                  <option value="published">Published</option>
                  <option value="draft">Draft</option>
                </select>
                <select value={orderFilter} onChange={e => setOrderFilter(e.target.value)}
                  className="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none bg-white text-gray-600 min-w-[175px]">
                  <option value="stock_asc">Stock: Low to High</option>
                  <option value="stock_desc">Stock: High to Low</option>
                  <option value="updated">Recently Updated</option>
                  <option value="name_asc">Name: A to Z</option>
                  <option value="name_desc">Name: Z to A</option>
                  <option value="price_asc">Price: Low to High</option>
                  <option value="price_desc">Price: High to Low</option>
                </select>
                <button type="submit"
                  className="flex items-center gap-1.5 px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-orange-200">
                  <Icon d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" size={13} />
                  Apply
                </button>
                {hasFilters && <button type="button" onClick={clearFilters} className="px-3 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700">Clear</button>}
              </div>
            </form>
          </div>

          {/* ── Table ── */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/70 border-b border-gray-100">
                    {['PRODUCT INFO', 'CATEGORY', 'SKU', 'TYPE', 'BASE PRICE', 'CURRENT STOCK', 'STATUS ALERT', 'ACTIONS'].map(h => (
                      <th key={h} className={`px-4 py-3.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-left ${h === 'ACTIONS' ? 'text-right' : ''}`}>
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(products.data || []).length === 0 ? (
                    <tr>
                      <td colSpan="8" className="px-5 py-16 text-center">
                        <div className="flex flex-col items-center gap-3 text-gray-300">
                          <Icon d="M9 17H5a2 2 0 00-2 2M9 17h6M9 17V5a2 2 0 012-2h2a2 2 0 012 2v12m0 0h4a2 2 0 012 2" size={40} />
                          <p className="text-gray-400 text-sm">No products match your filter.</p>
                        </div>
                      </td>
                    </tr>
                  ) : (products.data || []).map(product => {
                    const isVariable = product.has_variants || product.product_type === 'variable';
                    const qty = product.stock_quantity ?? 0;

                    return (
                      <tr key={product.id} className={`hover:bg-gray-50/50 transition-colors ${qty <= 0 ? 'bg-red-50/20' : qty <= 5 ? 'bg-amber-50/20' : ''}`}>

                        {/* Product Info */}
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            {product.primary_image
                              ? <img src={product.primary_image.startsWith('http') ? product.primary_image : `/${product.primary_image}`}
                                  alt="" className="h-10 w-10 rounded-xl object-cover border border-gray-100 shrink-0" />
                              : <div className="h-10 w-10 rounded-xl bg-gray-100 shrink-0 flex items-center justify-center">
                                  <Icon d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" size={14} className="text-gray-300" />
                                </div>
                            }
                            <div>
                              <p className="font-semibold text-gray-800 text-sm leading-tight">{product.name}</p>
                              {product.is_published
                                ? <span className="text-[10px] text-green-600 font-semibold">● Published</span>
                                : <span className="text-[10px] text-gray-400 font-semibold">○ Draft</span>
                              }
                            </div>
                          </div>
                        </td>

                        {/* Category */}
                        <td className="px-4 py-3.5 text-xs text-gray-500">{product.category?.name || '—'}</td>

                        {/* SKU */}
                        <td className="px-4 py-3.5">
                          <span className="text-xs font-mono text-gray-600 bg-gray-50 border border-gray-100 px-2 py-0.5 rounded-lg">
                            {product.sku || '—'}
                          </span>
                        </td>

                        {/* Type */}
                        <td className="px-4 py-3.5">
                          <span className={`text-[11px] font-bold uppercase px-2 py-0.5 rounded-lg ${isVariable ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500'}`}>
                            {isVariable ? 'VARIABLE' : 'SIMPLE'}
                          </span>
                        </td>

                        {/* Price */}
                        <td className="px-4 py-3.5 font-bold text-gray-900 text-sm">
                          {sym}{Number(product.regular_price).toLocaleString()}
                        </td>

                        {/* Current Stock (editable) */}
                        <td className="px-4 py-3.5">
                          <StockEditor product={product} />
                        </td>

                        {/* Status Alert */}
                        <td className="px-4 py-3.5">
                          <StockAlert qty={qty} />
                        </td>

                        {/* Actions */}
                        <td className="px-4 py-3.5 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <a href={`/admin/products/${product.id}/edit`}
                              className="p-1.5 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition-colors">
                              <Icon d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" size={14} />
                            </a>
                            <a href={`/product/${product.slug || product.id}`} target="_blank" rel="noreferrer"
                              className="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-colors">
                              <Icon d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" size={14} />
                            </a>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {products.links && products.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-between">
                <p className="text-xs text-gray-400">
                  {products.from && `Showing ${products.from}–${products.to} of ${products.total} products`}
                </p>
                <div className="flex gap-1.5">
                  {products.links.map((link, i) => (
                    link.url ? (
                      <button key={i} onClick={() => router.get(link.url)}
                        className={`min-w-[34px] h-8 px-2.5 rounded-xl text-xs font-semibold transition-colors ${link.active ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                        dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                      <span key={i} className="min-w-[34px] h-8 px-2.5 flex items-center justify-center text-xs text-gray-300"
                        dangerouslySetInnerHTML={{ __html: link.label }} />
                    )
                  ))}
                </div>
              </div>
            )}
          </div>

        </div>
      </AdminLayout>
    </>
  );
}
