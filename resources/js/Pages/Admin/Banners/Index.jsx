import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { imageUrl } from '@/lib/utils';

const positionOptions = [
  ['top-left', 'Top - Left'], ['top-center', 'Top - Center'], ['top-right', 'Top - Right'],
  ['center-left', 'Center - Left'], ['center-center', 'Center - Center'], ['center-right', 'Center - Right'],
  ['bottom-left', 'Bottom - Left'], ['bottom-center', 'Bottom - Center'], ['bottom-right', 'Bottom - Right'],
];

export default function BannersIndex({ banners, placements }) {
  const [selected, setSelected] = useState([]);
  const [textPosition, setTextPosition] = useState('center-left');
  const [imagePosition, setImagePosition] = useState('center-center');
  const [imageOrientation, setImageOrientation] = useState('landscape');
  const [search, setSearch] = useState('');
  const [placementFilter, setPlacementFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [appliedSearch, setAppliedSearch] = useState('');
  const [appliedPlacement, setAppliedPlacement] = useState('all');
  const [appliedStatus, setAppliedStatus] = useState('all');
  const handleToggle = (id) => router.patch(`/admin/banners/${id}/toggle`);
  const handleDelete = (banner) => {
    window.showConfirm(`Delete "${banner.title || 'this banner'}" permanently?`, () => { router.delete(`/admin/banners/${banner.id}`); });
  };

  const placementLabel = (key) => {
    if (key === 'hero') return 'Hero Slider';
    if (key === 'middle') return 'Middle Banner';
    return placements?.[key] || key;
  };
  const normalizedSearch = appliedSearch.trim().toLowerCase();
  const filteredBanners = (banners || []).filter(banner => {
    const matchesSearch = !normalizedSearch || [
      banner.title,
      banner.subtitle,
      banner.badge,
      banner.button_text,
      banner.link_url,
      placementLabel(banner.placement),
    ].some(value => String(value || '').toLowerCase().includes(normalizedSearch));
    const matchesPlacement = appliedPlacement === 'all' || banner.placement === appliedPlacement;
    const matchesStatus = appliedStatus === 'all'
      || (appliedStatus === 'active' && banner.is_active)
      || (appliedStatus === 'hidden' && !banner.is_active);

    return matchesSearch && matchesPlacement && matchesStatus;
  });
  const filteredIds = filteredBanners.map(banner => banner.id);
  const allFilteredSelected = filteredIds.length > 0 && filteredIds.every(id => selected.includes(id));
  const toggleSelected = (id) => setSelected(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id]);
  const selectAll = () => setSelected(current => [...new Set([...current, ...filteredIds])]);
  const clearFilteredSelection = () => setSelected(current => current.filter(id => !filteredIds.includes(id)));
  const clearSelected = () => setSelected([]);
  const applyFilters = (event) => {
    event.preventDefault();
    setAppliedSearch(search);
    setAppliedPlacement(placementFilter);
    setAppliedStatus(statusFilter);
    clearSelected();
  };
  const resetFilters = () => {
    setSearch('');
    setPlacementFilter('all');
    setStatusFilter('all');
    setAppliedSearch('');
    setAppliedPlacement('all');
    setAppliedStatus('all');
    clearSelected();
  };
  const updateSelectedStatus = (bulkAction) => {
    if (selected.length === 0) return;
    const label = bulkAction === 'activate' ? 'activate' : 'hide';
    window.showConfirm(`${label[0].toUpperCase() + label.slice(1)} ${selected.length} selected banner(s)?`, () => {
      router.patch('/admin/banners/bulk-status', { ids: selected, bulk_action: bulkAction }, {
        preserveScroll: true,
        onSuccess: () => clearSelected(),
      });
    });
  };
  const deleteSelected = () => {
    if (selected.length === 0) return;
    window.showConfirm(`Permanently delete ${selected.length} selected banner(s)? This cannot be undone.`, () => {
      router.delete('/admin/banners/bulk-delete', { data: { ids: selected }, preserveScroll: true, onSuccess: () => clearSelected() });
    });
  };
  const updateSelectedPosition = () => {
    if (selected.length === 0) return;
    window.showConfirm(`Apply these display settings to ${selected.length} selected banner(s)?`, () => {
      router.patch('/admin/banners/bulk-position', {
        ids: selected,
        text_position: textPosition,
        image_position: imagePosition,
        image_orientation: imageOrientation,
      }, {
        preserveScroll: true,
        onSuccess: () => clearSelected(),
      });
    });
  };

  return (
    <>
      <Head title="Banners" />
      <AdminLayout title="Banners">
        <div className="space-y-5">
          <div className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-3">
              <label className="min-w-[220px] flex-1">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Search banners</span>
                <div className="relative">
                  <svg className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                  </svg>
                  <input
                    type="search"
                    value={search}
                    onChange={event => setSearch(event.target.value)}
                    placeholder="Title, subtitle, button, or link"
                    className="w-full rounded-xl border border-gray-200 py-2.5 pl-10 pr-3.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-orange-300"
                  />
                </div>
              </label>
              <label className="min-w-[180px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Placement</span>
                <select value={placementFilter} onChange={event => setPlacementFilter(event.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-orange-300">
                  <option value="all">All placements</option>
                  {Object.keys(placements || {}).map(key => <option key={key} value={key}>{placementLabel(key)}</option>)}
                </select>
              </label>
              <label className="min-w-[160px]">
                <span className="mb-1.5 block text-xs font-semibold text-gray-600">Visibility</span>
                <select value={statusFilter} onChange={event => setStatusFilter(event.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-orange-300">
                  <option value="all">All visibility</option>
                  <option value="active">Active</option>
                  <option value="hidden">Hidden</option>
                </select>
              </label>
              <button type="submit" className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600">Apply filters</button>
              <button type="button" onClick={resetFilters} className="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-200">Clear filters</button>
            </form>
            <p className="mt-3 text-xs text-gray-400">
              Showing <span className="font-semibold text-gray-600">{filteredBanners.length}</span> of {(banners || []).length} banners
            </p>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3">
            {selected.length > 0 ? (
              <div className="flex flex-wrap items-center gap-2">
                <span className="rounded-xl border border-orange-200 bg-orange-50 px-3 py-2 text-sm font-semibold text-orange-700">{selected.length} selected</span>
                <button onClick={() => updateSelectedStatus('activate')} className="rounded-xl bg-green-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700">Set Active</button>
                <button onClick={() => updateSelectedStatus('deactivate')} className="rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">Set Inactive</button>
                <button onClick={deleteSelected} className="rounded-xl bg-red-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700">Delete Selected</button>
                <div className="flex flex-wrap items-center gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/60 p-1.5">
                  <select value={textPosition} onChange={event => setTextPosition(event.target.value)} className="rounded-lg border border-indigo-100 bg-white px-2 py-1.5 text-xs font-semibold text-indigo-900 outline-none">
                    {positionOptions.map(([value, label]) => <option key={value} value={value}>Text: {label}</option>)}
                  </select>
                  <select value={imagePosition} onChange={event => setImagePosition(event.target.value)} className="rounded-lg border border-indigo-100 bg-white px-2 py-1.5 text-xs font-semibold text-indigo-900 outline-none">
                    {positionOptions.map(([value, label]) => <option key={value} value={value}>Focus: {label}</option>)}
                  </select>
                  <select value={imageOrientation} onChange={event => setImageOrientation(event.target.value)} className="rounded-lg border border-indigo-100 bg-white px-2 py-1.5 text-xs font-semibold text-indigo-900 outline-none">
                    <option value="landscape">Landscape</option>
                    <option value="portrait">Portrait</option>
                    <option value="square">Square</option>
                  </select>
                  <button onClick={updateSelectedPosition} className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-indigo-700">Apply Position</button>
                </div>
                <button onClick={clearSelected} className="px-2 text-sm text-gray-500 hover:text-gray-800">Clear</button>
              </div>
            ) : (
              <button onClick={selectAll} disabled={filteredBanners.length === 0} className="rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">Select all shown</button>
            )}
            <a href="/admin/banners/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Banner
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/50">
                    <th className="w-12 px-5 py-3 text-left">
                      <input type="checkbox" checked={allFilteredSelected} onChange={event => event.target.checked ? selectAll() : clearFilteredSelection()} className="h-4 w-4 rounded accent-orange-500" aria-label="Select all shown banners" />
                    </th>
                    {['Banner', 'Placement', 'Style', 'Position', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {filteredBanners.length === 0 ? (
                    <tr><td colSpan="7" className="px-5 py-12 text-center text-gray-400">{(banners || []).length === 0 ? 'No banners found.' : 'No banners match these filters.'}</td></tr>
                  ) : filteredBanners.map(banner => (
                    <tr key={banner.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="px-5 py-3.5">
                        <input type="checkbox" checked={selected.includes(banner.id)} onChange={() => toggleSelected(banner.id)} className="h-4 w-4 rounded accent-orange-500" aria-label={`Select ${banner.title || 'banner'}`} />
                      </td>
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          {banner.image
                            ? <img src={imageUrl(banner.image, banner.title)} alt="" className="h-10 w-20 rounded-lg object-cover shrink-0 border border-gray-100" />
                            : <div className="h-10 w-20 rounded-lg bg-gray-100 shrink-0 flex items-center justify-center text-xs text-gray-400">No image</div>
                          }
                          <div>
                            <p className="font-semibold text-gray-800">{banner.title || '—'}</p>
                            {banner.subtitle && <p className="text-xs text-gray-400 truncate max-w-[200px]">{banner.subtitle}</p>}
                            {banner.product_id && <p className="text-xs text-orange-500 truncate max-w-[200px]">Product link attached</p>}
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-3.5">
                        <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600">{placementLabel(banner.placement)}</span>
                      </td>
                      <td className="px-5 py-3.5 text-gray-500 capitalize">{banner.style}</td>
                      <td className="px-5 py-3.5 text-gray-500">{banner.position ?? 0}</td>
                      <td className="px-5 py-3.5">
                        <button onClick={() => handleToggle(banner.id)}
                          className={`px-2.5 py-1 text-xs font-medium rounded-full transition-colors ${banner.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}>
                          {banner.is_active ? 'Active' : 'Hidden'}
                        </button>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/banners/${banner.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          <button onClick={() => handleDelete(banner)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
