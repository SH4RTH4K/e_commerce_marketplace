import { useState, useEffect, useRef } from 'react';
import { useForm, router } from '@inertiajs/react';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";

function compressImage(file, maxWidth = 1200, maxHeight = 1200, quality = 0.85) {
  return new Promise((resolve) => {
    if (!file.type || !file.type.startsWith('image/') || file.type === 'image/svg+xml') {
      const reader = new FileReader();
      reader.onload = (e) => resolve(e.target.result);
      reader.readAsDataURL(file);
      return;
    }

    const img = new Image();
    const reader = new FileReader();
    reader.onload = (e) => {
      img.onload = () => {
        let { width, height } = img;
        if (width > maxWidth || height > maxHeight) {
          if (width / height > maxWidth / maxHeight) {
            height = Math.round((height * maxWidth) / width);
            width = maxWidth;
          } else {
            width = Math.round((width * maxHeight) / height);
            height = maxHeight;
          }
        }
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        resolve(canvas.toDataURL('image/jpeg', quality));
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

function Field({ label, children }) {
  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
      {children}
    </div>
  );
}

function ImageUpload({ field, value, onChange }) {
  const fileInput = useRef(null);
  const [tab, setTab] = useState('upload'); // 'upload' | 'url'
  const [preview, setPreview] = useState(null);
  const [compressing, setCompressing] = useState(false);

  useEffect(() => {
    if (value && typeof value === 'string' && value.startsWith('data:image/')) {
      setPreview(value);
    } else if (value && typeof value === 'object' && value instanceof File) {
      const url = URL.createObjectURL(value);
      setPreview(url);
      return () => URL.revokeObjectURL(url);
    } else if (typeof value === 'string' && value) {
      setPreview(value.startsWith('http') || value.startsWith('/') ? value : `/${value}`);
    } else {
      setPreview(null);
    }
  }, [value]);

  const handleFileChange = async (e) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setCompressing(true);
      try {
        const b64 = await compressImage(file);
        setPreview(b64);
        onChange(b64);
      } catch (err) {
        console.error('Image compression error:', err);
      } finally {
        setCompressing(false);
      }
    }
  };

  return (
    <div className="space-y-3 bg-gray-50/70 p-3.5 rounded-2xl border border-gray-200">
      <div className="flex items-center justify-between text-xs">
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setTab('upload')}
            className={`px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer ${tab === 'upload' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
          >
            File Upload
          </button>
          <button
            type="button"
            onClick={() => setTab('url')}
            className={`px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer ${tab === 'url' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
          >
            Image URL
          </button>
        </div>
        {preview && (
          <button
            type="button"
            onClick={() => {
              onChange('');
              setPreview(null);
              if (fileInput.current) fileInput.current.value = '';
            }}
            className="text-red-500 hover:text-red-700 font-semibold text-xs cursor-pointer"
          >
            Remove Image
          </button>
        )}
      </div>

      <div className="flex items-center gap-4">
        {preview ? (
          <img
            src={preview}
            alt=""
            className="w-20 h-20 object-cover rounded-xl border border-gray-200 bg-white shrink-0 shadow-sm"
          />
        ) : (
          <div className="w-20 h-20 bg-gray-100 rounded-xl border border-dashed border-gray-300 flex flex-col items-center justify-center text-[11px] text-gray-400 shrink-0">
            <span>📷</span>
            <span>No Image</span>
          </div>
        )}

        <div className="flex-1">
          {tab === 'upload' ? (
            <div>
              <input
                type="file"
                accept="image/*"
                ref={fileInput}
                onChange={handleFileChange}
                className="hidden"
              />
              <button
                type="button"
                disabled={compressing}
                onClick={() => fileInput.current?.click()}
                className="px-4 py-2 bg-white border border-gray-300 hover:border-orange-400 text-gray-700 text-xs font-semibold rounded-xl transition-colors shadow-sm cursor-pointer disabled:opacity-60"
              >
                {compressing ? 'Processing Image…' : 'Choose Image File'}
              </button>
              <p className="text-[11px] text-gray-500 mt-1.5">Auto-optimized for ultra-fast page load</p>
            </div>
          ) : (
            <div>
              <input
                type="url"
                value={typeof value === 'string' && !value.startsWith('data:') ? value : ''}
                onChange={e => onChange(e.target.value)}
                placeholder="https://images.unsplash.com/photo-..."
                className={`${inputClass} !py-1.5 text-xs`}
              />
              <p className="text-[11px] text-gray-500 mt-1">Paste a direct image web link</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function ArrayBuilder({ field, items, onChange }) {
  const isPackages      = field.type === 'packages_list';
  const isFeatures      = field.type === 'features_list';
  const isBenefitsIcon  = field.type === 'benefits_icon_list';
  const isTestimonials  = field.type === 'testimonials_list';
  const isBenefitsItems = field.type === 'benefits_items_list';

  const getEmptyItem = () => {
    if (isPackages)      return { name: '', price: 699, qty: 1, badge: '' };
    if (isFeatures)      return { title: '', body: '', image: '' };
    if (isBenefitsIcon)  return { icon: 'trending-up', title: '', body: '' };
    if (isTestimonials)  return { name: '', text: '', image: '', rating: 5 };
    if (isBenefitsItems) return { title: '', items: '' };
    return {};
  };

  const currentItems = Array.isArray(items) ? items : [];

  const updateItem = (index, key, val) => {
    const newItems = [...currentItems];
    newItems[index] = { ...newItems[index], [key]: val };
    onChange(newItems);
  };

  const addItem    = () => onChange([...currentItems, getEmptyItem()]);
  const removeItem = (index) => onChange(currentItems.filter((_, i) => i !== index));

  return (
    <div className="space-y-3 bg-gray-50/60 p-3 rounded-2xl border border-gray-100">
      {currentItems.map((item, i) => (
        <div key={i} className="p-3.5 bg-white border border-gray-200 rounded-xl relative shadow-sm">
          <button
            type="button"
            onClick={() => removeItem(i)}
            className="absolute top-2.5 right-2.5 p-1 text-gray-400 hover:text-red-500 bg-white rounded-full transition-colors z-10 shadow-sm border border-gray-100 cursor-pointer"
            title="Delete item"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>

          <div className="space-y-2.5 pr-6">
            {isPackages && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  <div className="sm:col-span-2">
                    <label className="text-[11px] font-bold text-gray-500 uppercase">Package Name</label>
                    <input
                      value={item.name || ''}
                      onChange={e => updateItem(i, 'name', e.target.value)}
                      placeholder="e.g. 1 Set (5 Books)"
                      className={`${inputClass} !py-1.5`}
                    />
                  </div>
                  <div>
                    <label className="text-[11px] font-bold text-gray-500 uppercase">Price (৳)</label>
                    <input
                      type="number"
                      value={item.price ?? ''}
                      onChange={e => updateItem(i, 'price', e.target.value)}
                      placeholder="699"
                      className={`${inputClass} !py-1.5 font-bold text-orange-600`}
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div>
                    <label className="text-[11px] font-bold text-gray-500 uppercase">Quantity (Items count)</label>
                    <input
                      type="number"
                      value={item.qty ?? 1}
                      onChange={e => updateItem(i, 'qty', e.target.value)}
                      placeholder="1"
                      className={`${inputClass} !py-1.5`}
                    />
                  </div>
                  <div>
                    <label className="text-[11px] font-bold text-gray-500 uppercase">Badge Label (optional)</label>
                    <input
                      value={item.badge || ''}
                      onChange={e => updateItem(i, 'badge', e.target.value)}
                      placeholder="e.g. Popular, Best Value"
                      className={`${inputClass} !py-1.5`}
                    />
                  </div>
                </div>
              </>
            )}

            {isFeatures && (
              <>
                <input
                  value={item.title || ''}
                  onChange={e => updateItem(i, 'title', e.target.value)}
                  placeholder="Feature / Book Title"
                  className={`${inputClass} !py-1.5 font-semibold`}
                />
                <textarea
                  value={item.body || ''}
                  onChange={e => updateItem(i, 'body', e.target.value)}
                  placeholder="Short Description"
                  rows={2}
                  className={`${inputClass} !py-1.5`}
                />
                <div>
                  <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Feature / Book Image</label>
                  <ImageUpload
                    field={{ name: `feature_image_${i}` }}
                    value={item.image || ''}
                    onChange={val => updateItem(i, 'image', val)}
                  />
                </div>
              </>
            )}

            {isBenefitsIcon && (
              <>
                <div className="flex gap-2">
                  <input
                    value={item.icon || ''}
                    onChange={e => updateItem(i, 'icon', e.target.value)}
                    placeholder="icon (e.g. trending-up, zap)"
                    className={`${inputClass} !py-1.5 w-28`}
                  />
                  <input
                    value={item.title || ''}
                    onChange={e => updateItem(i, 'title', e.target.value)}
                    placeholder="Benefit Title"
                    className={`${inputClass} !py-1.5 flex-1 font-semibold`}
                  />
                </div>
                <textarea
                  value={item.body || ''}
                  onChange={e => updateItem(i, 'body', e.target.value)}
                  placeholder="Benefit Description"
                  rows={2}
                  className={`${inputClass} !py-1.5`}
                />
              </>
            )}

            {isTestimonials && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <input
                    value={item.name || ''}
                    onChange={e => updateItem(i, 'name', e.target.value)}
                    placeholder="Reviewer Name (e.g. Tanvir Ahmed)"
                    className={`${inputClass} !py-1.5 font-semibold`}
                  />
                  <input
                    value={item.rating ?? 5}
                    type="number"
                    min="1"
                    max="5"
                    onChange={e => updateItem(i, 'rating', Number(e.target.value))}
                    placeholder="Rating (1-5)"
                    className={`${inputClass} !py-1.5`}
                  />
                </div>
                <textarea
                  value={item.text || ''}
                  onChange={e => updateItem(i, 'text', e.target.value)}
                  placeholder="Review / Testimonial text"
                  rows={2}
                  className={`${inputClass} !py-1.5`}
                />
                <div>
                  <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Reviewer Photo</label>
                  <ImageUpload
                    field={{ name: `testi_image_${i}` }}
                    value={item.image || ''}
                    onChange={val => updateItem(i, 'image', val)}
                  />
                </div>
              </>
            )}

            {isBenefitsItems && (
              <>
                <input
                  value={item.title || ''}
                  onChange={e => updateItem(i, 'title', e.target.value)}
                  placeholder="Title"
                  className={`${inputClass} !py-1.5 font-semibold`}
                />
                <textarea
                  value={item.items || ''}
                  onChange={e => updateItem(i, 'items', e.target.value)}
                  placeholder="Items (one per line)"
                  rows={3}
                  className={`${inputClass} !py-1.5`}
                />
              </>
            )}
          </div>
        </div>
      ))}

      <button
        type="button"
        onClick={addItem}
        className="w-full py-2.5 bg-white border border-dashed border-gray-300 hover:border-orange-400 text-gray-700 hover:text-orange-600 text-sm font-semibold rounded-xl transition-colors shadow-sm cursor-pointer"
      >
        + Add New Item
      </button>
    </div>
  );
}

/** Build clean initial form data from the section meta + stored content. */
function buildInitialData(meta, initialContent, sectionKey) {
  const data = { _method: 'put' };

  if (meta.toggle) {
    data.section_visible = initialContent?._sections?.[sectionKey] ?? true;
  }

  (meta.fields || []).forEach(f => {
    if (f.type.startsWith('page_')) return;

    const raw = initialContent?.[f.name];

    if (f.type.endsWith('_list')) {
      data[f.name] = Array.isArray(raw) ? raw : [];
    } else if (f.type === 'number') {
      data[f.name] = raw !== undefined && raw !== null && raw !== '' ? Number(raw) : '';
    } else {
      data[f.name] = raw ?? '';
    }
  });

  return data;
}

export default function SectionEditor({ pageId, sectionKey, meta, initialContent }) {
  const [isOpen,   setIsOpen]   = useState(false);
  const [loading,  setLoading]  = useState(false);
  const [savedMsg, setSavedMsg] = useState('');

  const contentRef = useRef(initialContent);

  const { data, setData, post, reset } = useForm(
    () => buildInitialData(meta, initialContent, sectionKey)
  );

  useEffect(() => {
    if (contentRef.current !== initialContent) {
      contentRef.current = initialContent;
      reset(buildInitialData(meta, initialContent, sectionKey));
    }
  }, [initialContent]);

  const handleSubmit = (e) => {
    if (e && e.preventDefault) e.preventDefault();
    setLoading(true);
    setSavedMsg('');

    post(`/admin/landing-pages/${pageId}/sections/${sectionKey}`, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        setLoading(false);
        setSavedMsg('Saved!');
        setTimeout(() => setSavedMsg(''), 3500);
      },
      onError: (errs) => {
        setLoading(false);
        console.error('Section save error:', errs);
      },
    });
  };

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4 transition-all hover:border-orange-200">
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="w-full px-5 py-4 flex items-center justify-between bg-white hover:bg-gray-50 transition-colors text-left cursor-pointer"
      >
        <h4 className="font-semibold text-gray-900">{meta.label}</h4>
        <div className="flex items-center gap-3">
          {savedMsg && (
            <span className="px-2.5 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-700 animate-pulse">
              ✓ {savedMsg}
            </span>
          )}
          {meta.toggle && (
            <span className={`px-2.5 py-0.5 text-[11px] font-bold rounded-full ${data.section_visible ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
              {data.section_visible ? 'Visible' : 'Hidden'}
            </span>
          )}
          <svg className={`w-5 h-5 text-gray-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </button>

      {isOpen && (
        <form onSubmit={handleSubmit} className="px-5 pb-5 pt-2 border-t border-gray-50">
          {/* Top Quick Save Bar */}
          <div className="sticky top-16 z-20 flex items-center justify-between p-3 bg-orange-50/90 backdrop-blur border border-orange-200 rounded-xl mb-4 shadow-sm">
            <div className="text-xs text-orange-950 font-semibold flex items-center gap-1.5">
              <span>⚡ Editing: <strong className="text-orange-700">{meta.label}</strong></span>
              {savedMsg && <span className="text-green-600 font-bold ml-2">✓ Saved!</span>}
            </div>
            <button
              type="submit"
              disabled={loading}
              className="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-xs font-bold rounded-lg shadow transition-all flex items-center gap-1.5 cursor-pointer hover:shadow-md"
            >
              {loading ? 'Saving…' : '💾 Save This Section'}
            </button>
          </div>

          {meta.toggle && (
            <label className="flex items-center gap-3 cursor-pointer mb-5 p-3 bg-gray-50/70 rounded-xl border border-gray-100">
              <input
                type="checkbox"
                checked={!!data.section_visible}
                onChange={e => setData('section_visible', e.target.checked)}
                className="h-4 w-4 accent-orange-500 rounded cursor-pointer"
              />
              <span className="text-sm font-medium text-gray-700">Show this section on the live page</span>
            </label>
          )}

          <div className="space-y-3">
            {(meta.fields || []).filter(f => !f.type.startsWith('page_')).map(f => (
              <Field key={f.name} label={f.label}>
                {f.type === 'text' && (
                  <input
                    value={data[f.name] ?? ''}
                    onChange={e => setData(f.name, e.target.value)}
                    className={inputClass}
                  />
                )}
                {f.type === 'number' && (
                  <input
                    type="number"
                    value={data[f.name] ?? ''}
                    onChange={e => setData(f.name, e.target.value)}
                    className={inputClass}
                  />
                )}
                {f.type === 'textarea' && (
                  <textarea
                    value={data[f.name] ?? ''}
                    onChange={e => setData(f.name, e.target.value)}
                    rows={3}
                    className={inputClass}
                  />
                )}
                {f.type === 'image' && (
                  <ImageUpload
                    field={f}
                    value={data[f.name]}
                    onChange={val => setData(f.name, val)}
                  />
                )}
                {f.type.endsWith('_list') && (
                  <ArrayBuilder
                    field={f}
                    items={Array.isArray(data[f.name]) ? data[f.name] : []}
                    onChange={val => setData(f.name, val)}
                  />
                )}
              </Field>
            ))}
          </div>

          <div className="mt-5 flex items-center justify-between gap-3 pt-3 border-t border-gray-100">
            <button
              type="button"
              onClick={() => setIsOpen(false)}
              className="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-xl transition-colors cursor-pointer"
            >
              Close
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2 shadow-md hover:shadow-lg cursor-pointer"
            >
              {loading ? (
                <>
                  <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  Saving…
                </>
              ) : '💾 Save Section'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
