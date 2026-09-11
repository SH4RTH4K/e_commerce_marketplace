import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';
import { useRef, useState, useEffect } from 'react';

function Status({ value }) {
  const styles = { success: 'bg-green-50 text-green-700', failed: 'bg-red-50 text-red-700', active: 'bg-green-50 text-green-700', disabled: 'bg-gray-100 text-gray-600' };
  return <span className={`inline-flex px-2 py-1 rounded-full text-[11px] font-bold ${styles[value] || 'bg-gray-100 text-gray-600'}`}>{String(value || 'unknown').replaceAll('_', ' ')}</span>;
}

function SearchableSelect({ name, options, placeholder }) {
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedId, setSelectedId] = useState('');
  const [newCategoryName, setNewCategoryName] = useState('');
  const wrapperRef = useRef(null);

  useEffect(() => {
    function handleClickOutside(event) {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const filteredOptions = options.filter(opt => opt.name.toLowerCase().includes(search.toLowerCase()));
  const selectedOption = options.find(opt => opt.id.toString() === selectedId.toString());
  const exactMatch = options.some(opt => opt.name.toLowerCase() === search.trim().toLowerCase());

  let displayText = placeholder;
  if (selectedOption) displayText = selectedOption.name;
  if (newCategoryName) displayText = `New: ${newCategoryName}`;

  return (
    <div className="relative min-w-[160px] text-left" ref={wrapperRef}>
      <input type="hidden" name={name} value={selectedId} />
      <input type="hidden" name="new_category_name" value={newCategoryName} />
      
      <div 
        className="border border-gray-200 rounded-lg px-2 py-1.5 text-xs bg-white cursor-pointer flex justify-between items-center h-[30px]"
        onClick={() => setIsOpen(!isOpen)}
      >
        <span className={selectedOption || newCategoryName ? "text-gray-900 truncate max-w-[130px]" : "text-gray-400"}>
          {displayText}
        </span>
        <svg className="w-3 h-3 text-gray-400 shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></svg>
      </div>

      {isOpen && (
        <div className="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 flex flex-col">
          <div className="p-2 border-b border-gray-100">
            <input
              type="text"
              className="w-full px-2 py-1 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Search or type new..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              onClick={e => e.stopPropagation()}
              autoFocus
            />
          </div>
          <ul className="overflow-y-auto py-1">
            {filteredOptions.length > 0 ? filteredOptions.map(opt => (
              <li 
                key={opt.id}
                className={`px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 ${selectedId.toString() === opt.id.toString() ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'}`}
                onClick={() => {
                  setSelectedId(opt.id);
                  setNewCategoryName('');
                  setSearch('');
                  setIsOpen(false);
                }}
              >
                {opt.name}
              </li>
            )) : null}

            {filteredOptions.length === 0 && search.trim() === '' && (
              <li className="px-3 py-2 text-xs text-gray-500 text-center">No categories exist</li>
            )}
            
            {!exactMatch && search.trim() !== '' && (
              <li 
                className="px-3 py-2 text-xs text-blue-700 bg-blue-50 hover:bg-blue-100 font-medium cursor-pointer flex items-center justify-between"
                onClick={() => {
                  setSelectedId('');
                  setNewCategoryName(search.trim());
                  setSearch('');
                  setIsOpen(false);
                }}
              >
                + Create "{search.trim()}"
              </li>
            )}
          </ul>
        </div>
      )}
    </div>
  );
}

export default function CategoryMapping({ mappings = [], local_categories = [], suppliers = [] }) {
  const mirrored = mappings.length > 0;
  
  return (
    <DropshippingSubpage title="Category Mapping" description="Map supplier categories explicitly to your existing local categories.">
      <div className="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-sm text-blue-800">
        <strong className="block mb-1">How category mapping works</strong>
        First run a successful <strong>Catalog</strong> sync to mirror supplier categories. Then choose a local category for each supplier category below. No local category is created automatically.
      </div>
      
      {local_categories.length === 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800">
          No local store categories exist yet. Create local categories first, then return here to map supplier categories.
        </div>
      )}

      {/* Sync Panel (Always Visible) */}
      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 className="font-bold text-gray-900">Supplier Catalog Sync</h2>
        <p className="text-sm text-gray-500 mt-1">Select a connected supplier below and run Catalog. The queue worker must be running before categories can appear.</p>
        <div className="mt-4 space-y-3">
          {suppliers.length === 0 ? (
            <p className="text-sm text-gray-400">No supplier accounts configured. Open API Settings first.</p>
          ) : (
            suppliers.map(supplier => (
              <div key={supplier.id} className="rounded-xl border border-gray-100 p-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="font-semibold text-gray-800">{supplier.name}</p>
                    <p className="text-xs text-gray-500">
                      {supplier.categories_count || 0} mirrored categories · connection <Status value={supplier.last_connection_status || 'untested'} />
                    </p>
                  </div>
                  <button 
                    disabled={supplier.last_connection_status !== 'success' || !supplier.is_active} 
                    onClick={() => router.post(`/admin/dropshipping/suppliers/${supplier.id}/catalog`, {}, { preserveScroll: true })} 
                    className="px-3 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400 text-xs font-semibold text-white"
                  >
                    Run Catalog Sync
                  </button>
                </div>
                {!supplier.category_endpoint && (
                  <p className="mt-2 text-xs text-amber-700">
                    No category endpoint is configured. If the product profile maps a category key, Catalog will mirror that value automatically.
                  </p>
                )}
              </div>
            ))
          )}
        </div>
      </section>

      {/* Mapping Table */}
      {mirrored && (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden pb-16">
          <div className="px-5 py-4 border-b border-gray-100 flex justify-between">
            <div>
              <h2 className="font-bold text-gray-900">Supplier category mappings</h2>
              <p className="text-xs text-gray-500 mt-1">Choose a local category, then click Map to save. Attach an image if needed.</p>
            </div>
            <span className="text-xs text-gray-400">
              {mappings.filter(mapping => mapping.local_category).length} / {mappings.length} mapped · {local_categories.length} local categories
            </span>
          </div>
          <div className="overflow-x-visible">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400">
                  <th className="px-5 py-3 text-left">Supplier</th>
                  <th className="px-5 py-3 text-left">Supplier category</th>
                  <th className="px-5 py-3 text-center">Image</th>
                  <th className="px-5 py-3 text-left">Local category</th>
                  <th className="px-5 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {mappings.map(mapping => (
                  <tr key={mapping.id}>
                    <td className="px-5 py-4 text-xs text-gray-600 whitespace-nowrap">
                      {mapping.supplier || '—'}
                    </td>
                    <td className="px-5 py-4">
                      <p className="font-semibold text-gray-700">{mapping.name}</p>
                      <p className="text-xs text-gray-400 font-mono">{mapping.key}</p>
                    </td>
                    <td className="px-5 py-4 text-center">
                      <form 
                        onSubmit={event => { 
                          event.preventDefault(); 
                          const data = new FormData(event.currentTarget);
                          if(data.get('image').size > 0) {
                            router.post(`/admin/dropshipping/categories/${mapping.id}/image`, data, { preserveScroll: true, forceFormData: true });
                          }
                        }} 
                        className="flex flex-col items-center gap-2"
                      >
                        {mapping.image ? (
                          <img src={mapping.image} alt="Category" className="w-12 h-12 object-cover rounded-md border border-gray-200" />
                        ) : (
                          <div className="w-12 h-12 bg-gray-100 rounded-md border border-gray-200 flex items-center justify-center text-gray-400 text-xs">No img</div>
                        )}
                        <label className="cursor-pointer text-[10px] font-semibold text-blue-600 hover:text-blue-800">
                          Upload
                          <input 
                            type="file" 
                            name="image" 
                            accept="image/*" 
                            className="hidden" 
                            onChange={(e) => {
                              if(e.target.files.length > 0) e.target.form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
                            }}
                          />
                        </label>
                      </form>
                    </td>
                    <td className="px-5 py-4 text-xs whitespace-nowrap">
                      {mapping.local_category ? (
                        <span className="text-green-700 font-medium">{mapping.local_category}</span>
                      ) : (
                        <span className="text-amber-700">Needs manual mapping</span>
                      )}
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <div className="flex flex-col items-end gap-2">
                        <form 
                          onSubmit={event => { 
                            event.preventDefault(); 
                            router.post(`/admin/dropshipping/categories/${mapping.id}/map`, Object.fromEntries(new FormData(event.currentTarget)), { preserveScroll: true }); 
                          }} 
                          className="flex justify-end gap-2 items-start"
                        >
                          <SearchableSelect 
                            name="category_id" 
                            options={local_categories} 
                            placeholder="Select local category" 
                          />
                          <button className="px-2.5 py-1.5 rounded-lg bg-orange-500 hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400 text-xs font-semibold text-white h-[30px]">
                            Map
                          </button>
                        </form>
                        
                        {!mapping.local_category && (
                          <form onSubmit={event => { 
                            event.preventDefault(); 
                            router.post(`/admin/dropshipping/categories/${mapping.id}/map`, { new_category_name: mapping.name }, { preserveScroll: true }); 
                          }}>
                            <button type="submit" className="text-[10px] text-blue-600 hover:text-blue-800 hover:underline font-medium bg-blue-50 px-2 py-1 rounded">
                              + Auto-create as "{mapping.name}"
                            </button>
                          </form>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </DropshippingSubpage>
  );
}
