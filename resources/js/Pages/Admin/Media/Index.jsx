import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { fileToBase64, imageUrl } from '@/lib/utils';

const positionOptions = [
  ['top-left', 'Top - Left'], ['top-center', 'Top - Center'], ['top-right', 'Top - Right'],
  ['center-left', 'Center - Left'], ['center-center', 'Center - Center'], ['center-right', 'Center - Right'],
  ['bottom-left', 'Bottom - Left'], ['bottom-center', 'Bottom - Center'], ['bottom-right', 'Bottom - Right'],
];

/* ─── Image card ─────────────────────────────────────────────────── */
function ImageCard({ image, selectedOrder, onSelect, onDelete, onMoveEarlier, onMoveLater }) {
  const src = imageUrl(image.path, image.alt || image.product?.name);
  const selected = selectedOrder > 0;
  const bannerUsage = image.banner_usage || [];
  const heroUsage = bannerUsage.filter(usage => usage.placement === 'hero');
  const middleUsage = bannerUsage.filter(usage => usage.placement === 'middle');

  const usageBadge = (usage, label, activeClass) => (
    <span className={`${usage.is_active ? activeClass : 'bg-gray-700'} max-w-[165px] truncate rounded-md px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm`}>
      {label} #{usage.position}{!usage.is_active ? ' · HIDDEN' : ''}
    </span>
  );

  return (
    <div
      className={`group relative rounded-2xl overflow-hidden border-2 transition-all cursor-pointer ${
        selected ? 'border-orange-400 ring-2 ring-orange-200' : 'border-transparent hover:border-gray-200'
      }`}
      onClick={() => onSelect(image.id)}
    >
      <div className="aspect-square bg-gray-50">
        <img src={src} alt={image.alt || 'Product image'}
          className="w-full h-full object-cover" loading="lazy" />
      </div>

      {/* Overlay */}
      <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
        <div className="absolute bottom-0 left-0 right-0 p-3">
          {image.product && (
            <p className="text-white text-xs font-medium truncate">{image.product.name}</p>
          )}
          <p className="text-white/60 text-[10px]">{image.created_at}</p>
        </div>
      </div>

      {/* Image and storefront usage badges */}
      <div className="absolute top-2 left-2 flex max-w-[calc(100%-3rem)] flex-col items-start gap-1">
        {image.is_primary && (
          <span className="rounded-md bg-orange-500 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm">PRIMARY</span>
        )}
        {heroUsage.slice(0, 1).map(usage => (
          <span key={usage.id}>{usageBadge(usage, 'IN HERO SLIDER', 'bg-indigo-600')}</span>
        ))}
        {heroUsage.length > 1 && (
          <span className="rounded-md bg-indigo-800 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm">+{heroUsage.length - 1} HERO DUPLICATE</span>
        )}
        {middleUsage.slice(0, 1).map(usage => (
          <span key={usage.id}>{usageBadge(usage, 'IN MIDDLE BANNER', 'bg-emerald-600')}</span>
        ))}
        {middleUsage.length > 1 && (
          <span className="rounded-md bg-emerald-800 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-sm">+{middleUsage.length - 1} MIDDLE DUPLICATE</span>
        )}
      </div>

      {/* Checkbox */}
      <div className={`absolute top-2 right-2 transition-opacity ${selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}`}>
        <div className={`h-5 w-5 rounded-full border-2 flex items-center justify-center ${selected ? 'bg-orange-500 border-orange-500' : 'bg-white border-gray-300'}`}>
          {selected && <span className="text-[10px] leading-none font-extrabold text-white">{selectedOrder}</span>}
        </div>
      </div>

      {/* Slider order controls */}
      {selected && (
        <div className="absolute top-9 right-2 flex overflow-hidden rounded-lg border border-white/80 bg-white shadow-sm">
          <button
            type="button"
            disabled={selectedOrder === 1}
            onClick={e => { e.stopPropagation(); onMoveEarlier(image.id); }}
            className="grid h-6 w-6 place-items-center text-gray-600 hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-300"
            title="Move earlier in slider"
            aria-label="Move earlier in slider"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="m18 15-6-6-6 6" /></svg>
          </button>
          <button
            type="button"
            onClick={e => { e.stopPropagation(); onMoveLater(image.id); }}
            className="grid h-6 w-6 place-items-center border-l border-gray-100 text-gray-600 hover:bg-gray-100"
            title="Move later in slider"
            aria-label="Move later in slider"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="m6 9 6 6 6-6" /></svg>
          </button>
        </div>
      )}

      {/* Copy URL btn */}
      <button
        onClick={e => {
          e.stopPropagation();
          const fullUrl = src.startsWith('http') ? src : window.location.origin + src;
          navigator.clipboard.writeText(fullUrl);
          alert('URL copied to clipboard!');
        }}
        className="absolute bottom-2 left-2 h-7 w-7 rounded-full bg-white hover:bg-gray-100 text-gray-700 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all shadow-lg"
        title="Copy URL"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
          <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
        </svg>
      </button>

      {/* Delete btn */}
      <button
        onClick={e => { e.stopPropagation(); onDelete(image); }}
        className="absolute bottom-2 right-2 h-7 w-7 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all shadow-lg"
        title="Delete"
      >
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
          <path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
        </svg>
      </button>
    </div>
  );
}

/* ─── Main page ──────────────────────────────────────────────────── */
export default function MediaIndex({
  images, q, filter = 'all', categories = [], category = '', productStatus = 'all',
  stockOperator = 'any', stockValue = '', perPage = 24, total,
}) {
  const [search, setSearch] = useState(q || '');
  const [activeFilter, setActiveFilter] = useState(filter);
  const [categoryFilter, setCategoryFilter] = useState(category || '');
  const [activeProductStatus, setActiveProductStatus] = useState(productStatus);
  const [activeStockOperator, setActiveStockOperator] = useState(stockOperator);
  const [activeStockValue, setActiveStockValue] = useState(stockValue ?? '');
  const [rowsPerPage, setRowsPerPage] = useState(String(perPage));
  const [selected, setSelected] = useState([]);
  const [textPosition, setTextPosition] = useState('center-left');
  const [imagePosition, setImagePosition] = useState('center-center');
  const [imageOrientation, setImageOrientation] = useState('landscape');

  const visitMedia = (overrides = {}) => router.get('/admin/media', {
    q: search,
    filter: activeFilter,
    category: categoryFilter,
    status: activeProductStatus,
    stock_operator: activeStockOperator,
    stock_value: activeStockValue,
    per_page: rowsPerPage,
    ...overrides,
  }, { preserveState: true });

  const handleSearch = (e) => {
    e.preventDefault();
    visitMedia();
  };

  const clearFilters = () => {
    setSearch('');
    setActiveFilter('all');
    setCategoryFilter('');
    setActiveProductStatus('all');
    setActiveStockOperator('any');
    setActiveStockValue('');
    setRowsPerPage('24');
    router.get('/admin/media', {}, { preserveState: true });
  };

  const handleUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 8 * 1024 * 1024) {
      alert("File is too large. Max limit is 8MB.");
      e.target.value = null;
      return;
    }

    try {
      const b64 = await fileToBase64(file);
      const data = new FormData();
      data.append('image_b64', b64);
      data.append('image_name', file.name || 'media.jpg');

      router.post('/admin/media', data, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { e.target.value = null; },
        onError: (errors) => {
          alert(errors.image || errors.file || 'Failed to upload image.');
          e.target.value = null;
        }
      });
    } catch (err) {
      console.error(err);
      alert('Failed to process image file.');
      e.target.value = null;
    }
  };

  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const selectAll    = () => setSelected(images.data?.map(i => i.id) || []);
  const clearSelect  = () => setSelected([]);
  const moveSelected = (id, direction) => setSelected(prev => {
    const index = prev.indexOf(id);
    const nextIndex = index + direction;
    if (index < 0 || nextIndex < 0 || nextIndex >= prev.length) return prev;

    const next = [...prev];
    [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
    return next;
  });

  const handleDelete = (image) => {
    window.showConfirm('Delete this image? This cannot be undone.', () => {
      router.delete(`/admin/media/${image.id}`);
    });
  };

  const handleBulkDelete = () => {
    if (selected.length === 0) return;
    window.showConfirm(`Delete ${selected.length} image(s)?`, () => {
      selected.forEach(id => router.delete(`/admin/media/${id}`, { preserveScroll: true }));
      setSelected([]);
    });
  };

  const createBanners = (placement) => {
    if (selected.length === 0) return;

    const selectedImages = selected
      .map(id => (images.data || []).find(image => image.id === id))
      .filter(Boolean);
    const eligibleImages = selectedImages.filter(image =>
      !(image.banner_usage || []).some(usage => usage.placement === placement)
    );
    const skippedCount = selectedImages.length - eligibleImages.length;
    const isHero = placement === 'hero';
    const placementLabel = isHero ? 'Hero Slider' : 'Middle Banner';

    if (eligibleImages.length === 0) {
      alert(`All selected images are already in the ${placementLabel}.`);
      return;
    }

    const label = isHero
      ? (eligibleImages.length === 1 ? 'a Hero Slider slide' : `${eligibleImages.length} Hero Slider slides`)
      : (eligibleImages.length === 1 ? 'a Middle Banner' : `${eligibleImages.length} Middle Banners`);
    const skippedMessage = skippedCount > 0
      ? ` ${skippedCount} already-added image${skippedCount === 1 ? '' : 's'} will be skipped.`
      : '';

    window.showConfirm(
      `Create ${label} from the selected media? They will be active and ordered by your selection.${skippedMessage} You can edit their text, style, and links afterward.`,
      () => router.post('/admin/media/banners', {
        image_ids: eligibleImages.map(image => image.id),
        placement,
        style: 'brand',
        text_position: textPosition,
        image_position: imagePosition,
        image_orientation: imageOrientation,
      })
    );
  };

  return (
    <>
      <Head title="Media Manager" />
      <AdminLayout title="">
        <div className="space-y-5">

          {/* ── Header ── */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Media Manager</h1>
              <p className="text-sm text-gray-400 mt-0.5">
                {total?.toLocaleString() || 0} images in your library
              </p>
            </div>
            <div>
              <label className="cursor-pointer px-4 py-2 text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm shadow-orange-200">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                </svg>
                Upload Image (Max 5MB)
                <input type="file" className="hidden" accept="image/*" onChange={handleUpload} />
              </label>
            </div>
          </div>

          {/* ── Toolbar ── */}
          <form onSubmit={handleSearch} className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-end gap-3">
              <label className="min-w-[220px] flex-1">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Search media or products</span>
                <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Name, SKU, or image alt text"
                  className="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-orange-300" />
              </label>
              <label className="min-w-[150px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Category</span>
                <select value={categoryFilter} onChange={e => setCategoryFilter(e.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
                  <option value="">All categories</option>
                  {categories.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </label>
              <label className="min-w-[150px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Status</span>
                <select value={activeProductStatus} onChange={e => setActiveProductStatus(e.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
                  <option value="all">All statuses</option>
                  <option value="published">Published</option>
                  <option value="unpublished">Unpublished</option>
                </select>
              </label>
              <label className="min-w-[135px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Image type</span>
                <select value={activeFilter} onChange={e => setActiveFilter(e.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
                  <option value="all">All images</option>
                  <option value="primary">Primary only</option>
                </select>
              </label>
              <label className="min-w-[160px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Stock comparison</span>
                <select value={activeStockOperator} onChange={e => setActiveStockOperator(e.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
                  <option value="any">Any stock</option>
                  <option value="in_stock">In stock</option>
                  <option value="out_of_stock">Out of stock</option>
                  <option value="gt">Greater than</option>
                  <option value="gte">Greater than or equal</option>
                  <option value="eq">Equal to</option>
                  <option value="lte">Less than or equal</option>
                  <option value="lt">Less than</option>
                </select>
              </label>
              <label className="w-[130px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Stock value</span>
                <input type="number" min="0" value={activeStockValue} onChange={e => setActiveStockValue(e.target.value)} disabled={!['gt', 'gte', 'eq', 'lte', 'lt'].includes(activeStockOperator)} placeholder="e.g. 10"
                  className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-orange-300" />
              </label>
              <label className="w-[120px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Rows per page</span>
                <select value={rowsPerPage} onChange={e => setRowsPerPage(e.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
                  <option value="24">24</option>
                  <option value="48">48</option>
                  <option value="100">100</option>
                </select>
              </label>
              <button type="submit" className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600">Apply filters</button>
              <button type="button" onClick={clearFilters} className="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-200">Clear</button>
            </div>
          </form>

          <div className="flex flex-wrap items-center gap-3">

            <div className="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
              <span className="rounded-md bg-indigo-600 px-2 py-1 text-white">IN HERO SLIDER</span>
              <span className="rounded-md bg-emerald-600 px-2 py-1 text-white">IN MIDDLE BANNER</span>
              <span className="text-gray-400">Badges identify media already used on the homepage.</span>
            </div>

            {selected.length > 0 ? (
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-semibold text-orange-700 bg-orange-50 border border-orange-200 px-3 py-1.5 rounded-xl">
                  {selected.length} selected
                </span>
                {selected.length > 1 && <span className="text-xs text-gray-500">Use the arrows on each image to set slider order.</span>}
                <label className="flex items-center gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/50 px-2.5 py-1.5 text-xs font-medium text-indigo-800">
                  Text
                  <select value={textPosition} onChange={event => setTextPosition(event.target.value)} className="bg-transparent font-semibold outline-none">
                    {positionOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                  </select>
                </label>
                <span className="text-[11px] text-indigo-700">Landscape: choose the Center row. Portrait: choose Top or Bottom.</span>
                <label className="flex items-center gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/50 px-2.5 py-1.5 text-xs font-medium text-indigo-800">
                  Image focus
                  <select value={imagePosition} onChange={event => setImagePosition(event.target.value)} className="bg-transparent font-semibold outline-none">
                    {positionOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                  </select>
                </label>
                <label className="flex items-center gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/50 px-2.5 py-1.5 text-xs font-medium text-indigo-800">
                  Orientation
                  <select value={imageOrientation} onChange={event => setImageOrientation(event.target.value)} className="bg-transparent font-semibold outline-none">
                    <option value="landscape">Landscape / Wide</option>
                    <option value="portrait">Portrait / Tall</option>
                    <option value="square">Square</option>
                  </select>
                </label>
                <button onClick={handleBulkDelete}
                  className="px-3.5 py-2 text-sm font-semibold text-white bg-red-500 hover:bg-red-600 rounded-xl transition-colors flex items-center gap-1.5">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                  </svg>
                  Delete selected
                </button>
                <button onClick={() => createBanners('hero')}
                  className="px-3.5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors">
                  {selected.length === 1 ? 'Make Hero Slide' : 'Make Hero Slider'}
                </button>
                <button onClick={() => createBanners('middle')}
                  className="px-3.5 py-2 text-sm font-semibold text-orange-700 bg-orange-50 hover:bg-orange-100 border border-orange-200 rounded-xl transition-colors">
                  {selected.length === 1 ? 'Make Middle Banner' : 'Make Middle Banners'}
                </button>
                <button onClick={clearSelect}
                  className="px-3.5 py-2 text-sm text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors">
                  Clear
                </button>
              </div>
            ) : (
              <button onClick={selectAll}
                className="px-3.5 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl transition-colors">
                Select all
              </button>
            )}
          </div>

          {/* ── Image grid ── */}
          {(images.data || []).length === 0 ? (
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-24 flex flex-col items-center gap-4 text-gray-400">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" className="opacity-30">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <path d="M21 15l-5-5L5 21"/>
              </svg>
              <p className="text-sm font-medium">No images found in your library</p>
              <p className="text-xs">Images will appear here after products are created with images</p>
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
              {(images.data || []).map(image => (
                <ImageCard key={image.id} image={image}
                  selectedOrder={selected.indexOf(image.id) + 1}
                  onSelect={toggleSelect}
                  onDelete={handleDelete}
                  onMoveEarlier={id => moveSelected(id, -1)}
                  onMoveLater={id => moveSelected(id, 1)} />
              ))}
            </div>
          )}

          {/* ── Pagination ── */}
          {images.links && images.links.length > 3 && (
            <div className="flex flex-wrap gap-1.5 items-center justify-center pt-2">
              {images.links.map((link, i) => (
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
      </AdminLayout>
    </>
  );
}
