import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

/* ─── Type badge ─────────────────────────────────────────────────── */
function TypeBadge({ hasVariants }) {
  return hasVariants
    ? <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-indigo-50 text-indigo-600 border border-indigo-100">VARIABLE</span>
    : <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-gray-50 text-gray-500 border border-gray-100">SIMPLE</span>;
}

/* ─── Status pill ─────────────────────────────────────────────────── */
function StatusPill({ published, onClick }) {
  return (
    <button onClick={onClick}
      className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full transition-colors ${
        published ? 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200' : 'bg-gray-50 text-gray-500 hover:bg-gray-100 border border-gray-200'
      }`}>
      <span className={`h-1.5 w-1.5 rounded-full ${published ? 'bg-green-500' : 'bg-gray-400'}`} />
      {published ? 'Published' : 'Draft'}
    </button>
  );
}

/* ─── Main page ──────────────────────────────────────────────────── */
export default function ProductsIndex({ products, categories, q, category, status }) {
  const { auth } = usePage().props;
  const user = auth?.user;
  const canDelete = user?.is_super_admin || user?.role === 'admin' || (user?.permissions || []).includes('products.delete');

  const [search, setSearch]           = useState(q || '');
  const [catFilter, setCatFilter]     = useState(category || '');
  const [statusFilter, setStatusFilter] = useState(status || '');
  const [selected, setSelected]       = useState([]);
  const [bulkAction, setBulkAction]   = useState('');

  const handleFilter = (overrides = {}) => {
    router.get('/admin/products', { q: search, category: catFilter, status: statusFilter, ...overrides }, { preserveState: true });
  };

  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const toggleAll    = (e) => setSelected(e.target.checked ? products.data?.map(p => p.id) : []);

  const handleBulk = () => {
    if (!bulkAction || selected.length === 0) return;
    if (bulkAction === 'delete') {
      window.showConfirm(`Delete ${selected.length} product(s)?`, () => {
        router.post('/admin/products/bulk', { ids: selected, bulk_action: 'delete' }, {
          onSuccess: () => { setSelected([]); setBulkAction(''); }
        });
      });
      return;
    }
    router.post('/admin/products/bulk', { ids: selected, bulk_action: bulkAction }, {
      onSuccess: () => { setSelected([]); setBulkAction(''); }
    });
  };

  const handleToggle = (productId) => router.patch(`/admin/products/${productId}/toggle`);
  const handleDelete = (product) => {
    window.showConfirm(`Delete "${product.name}" permanently?`, () => router.delete(`/admin/products/${product.id}`));
  };

  return (
    <>
      <Head title="Product Catalog" />
      <AdminLayout title="">
        <div className="space-y-5">

          {/* ── Header ── */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Product Catalog</h1>
              <p className="text-sm text-gray-400 mt-0.5">
                {products.total != null ? `${products.total} products` : 'Manage your products'}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <a href="/admin/inventory"
                className="px-3.5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl transition-colors flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                Inventory
              </a>
              <a href="/admin/products/create"
                className="px-3.5 py-2 text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm shadow-orange-200">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M12 5v14M5 12h14"/>
                </svg>
                Add Product
              </a>
            </div>
          </div>

          {/* ── Table card ── */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            {/* Filters */}
            <form onSubmit={e => { e.preventDefault(); handleFilter(); }} className="p-4 border-b border-gray-50">
              <div className="flex flex-wrap gap-2.5 items-center">
                <div className="relative flex-1 min-w-[220px]">
                  <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                  </svg>
                  <input value={search} onChange={e => setSearch(e.target.value)}
                    placeholder="Search by name or SKU…"
                    className="w-full border border-gray-200 rounded-xl pl-9 pr-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent" />
                </div>
                <select value={statusFilter}
                  onChange={e => { setStatusFilter(e.target.value); handleFilter({ status: e.target.value }); }}
                  className="border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white text-gray-600 focus:outline-none">
                  <option value="">All Status</option>
                  <option value="active">Published</option>
                  <option value="inactive">Draft</option>
                </select>
                <select value={catFilter}
                  onChange={e => { setCatFilter(e.target.value); handleFilter({ category: e.target.value }); }}
                  className="border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white text-gray-600 focus:outline-none">
                  <option value="">All Categories</option>
                  {(categories || []).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <button type="submit"
                  className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors">
                  Search
                </button>
                {(search || catFilter || statusFilter) && (
                  <button type="button"
                    onClick={() => { setSearch(''); setCatFilter(''); setStatusFilter(''); handleFilter({ q: '', category: '', status: '' }); }}
                    className="px-3.5 py-2 text-sm text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                    Clear
                  </button>
                )}
              </div>
            </form>

            {/* Bulk action bar */}
            {selected.length > 0 && (
              <div className="px-5 py-3 border-b border-gray-50 bg-orange-50 flex flex-wrap items-center gap-3">
                <span className="text-sm font-semibold text-orange-700">{selected.length} selected</span>
                <select value={bulkAction} onChange={e => setBulkAction(e.target.value)}
                  className="border border-orange-200 rounded-xl px-3 py-1.5 text-sm bg-white focus:outline-none">
                  <option value="">Choose action…</option>
                  <option value="activate">Publish</option>
                  <option value="deactivate">Set to Draft</option>
                  <option value="delete">Delete</option>
                </select>
                <button onClick={handleBulk} disabled={!bulkAction}
                  className="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors">
                  Apply
                </button>
                <button onClick={() => setSelected([])} className="text-sm text-gray-500 hover:text-gray-700 ml-auto">
                  Clear selection
                </button>
              </div>
            )}

            {/* Table */}
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/60">
                    <th className="px-5 py-3 w-10">
                      <input type="checkbox" onChange={toggleAll}
                        checked={selected.length === products.data?.length && products.data?.length > 0}
                        className="h-4 w-4 accent-orange-500 rounded" />
                    </th>
                    {['PRODUCT DETAILS', 'TYPE', 'PRICE INFO', 'STOCK', 'STATUS', 'ACTIONS'].map(h => (
                      <th key={h} className={`px-5 py-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-left ${h === 'ACTIONS' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(products.data || []).length === 0 ? (
                    <tr>
                      <td colSpan="7" className="px-5 py-16 text-center">
                        <div className="flex flex-col items-center gap-3 text-gray-400">
                          <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" className="opacity-30">
                            <path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/>
                          </svg>
                          <p className="text-sm font-medium">No products found</p>
                          <a href="/admin/products/create" className="text-xs text-orange-500 hover:text-orange-600 font-medium">Add your first product →</a>
                        </div>
                      </td>
                    </tr>
                  ) : (products.data || []).map(product => (
                    <tr key={product.id}
                      className={`hover:bg-orange-50/20 transition-colors ${selected.includes(product.id) ? 'bg-orange-50/40' : ''}`}>
                      <td className="px-5 py-3.5">
                        <input type="checkbox" checked={selected.includes(product.id)} onChange={() => toggleSelect(product.id)}
                          className="h-4 w-4 accent-orange-500 rounded" />
                      </td>

                      {/* Product details */}
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          {product.primary_image
                            ? <img
                                src={product.primary_image.startsWith('http') ? product.primary_image : `/${product.primary_image}`}
                                alt="" className="h-11 w-11 rounded-xl object-cover shrink-0 border border-gray-100" />
                            : <div className="h-11 w-11 rounded-xl bg-gray-50 border border-gray-100 shrink-0 flex items-center justify-center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="2">
                                  <rect x="3" y="3" width="18" height="18" rx="2"/>
                                  <circle cx="8.5" cy="8.5" r="1.5"/>
                                  <path d="M21 15l-5-5L5 21"/>
                                </svg>
                              </div>
                          }
                          <div className="min-w-0">
                            <p className="font-semibold text-gray-800 leading-tight truncate max-w-[200px]">{product.name}</p>
                            {product.sku && <p className="text-[11px] text-gray-400 mt-0.5">SKU: {product.sku}</p>}
                            {product.category?.name && <p className="text-[11px] text-gray-400">{product.category.name}</p>}
                          </div>
                        </div>
                      </td>

                      {/* Type */}
                      <td className="px-5 py-3.5">
                        <TypeBadge hasVariants={product.has_variants} />
                      </td>

                      {/* Price */}
                      <td className="px-5 py-3.5">
                        <p className="font-bold text-gray-900">৳{Number(product.regular_price).toLocaleString()}</p>
                        {product.sale_price > 0 && (
                          <p className="text-xs text-orange-500 font-medium">৳{Number(product.sale_price).toLocaleString()} sale</p>
                        )}
                      </td>

                      {/* Stock */}
                      <td className="px-5 py-3.5">
                        <span className={`font-semibold text-sm ${
                          product.stock_quantity === 0 ? 'text-red-500' :
                          product.stock_quantity <= 5 ? 'text-amber-500' : 'text-gray-800'
                        }`}>
                          {product.stock_quantity}
                        </span>
                        {product.stock_quantity === 0 && <p className="text-[10px] text-red-400 font-medium">OUT OF STOCK</p>}
                        {product.stock_quantity > 0 && product.stock_quantity <= 5 && <p className="text-[10px] text-amber-400 font-medium">LOW STOCK</p>}
                      </td>

                      {/* Status */}
                      <td className="px-5 py-3.5">
                        <StatusPill published={product.is_published} onClick={() => handleToggle(product.id)} />
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/products/${product.id}/edit`}
                            className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold transition-colors">
                            Edit
                          </a>
                          {canDelete && (
                            <button onClick={() => handleDelete(product)}
                              className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-semibold transition-colors">
                              Delete
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {products.links && products.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {products.links.map((link, i) => (
                  link.url ? (
                    <button key={i} onClick={() => router.get(link.url)}
                      className={`min-w-[36px] h-9 px-3 rounded-xl text-sm font-medium transition-colors ${link.active ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                      dangerouslySetInnerHTML={{ __html: link.label }} />
                  ) : (
                    <span key={i} className="min-w-[36px] h-9 px-3 flex items-center justify-center text-sm text-gray-300"
                      dangerouslySetInnerHTML={{ __html: link.label }} />
                  )
                ))}
              </div>
            )}
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
