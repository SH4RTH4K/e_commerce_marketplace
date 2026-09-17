import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { fileToBase64 } from '@/lib/utils';
import RichTextEditor from '@/Components/Admin/RichTextEditor';

function FormSection({ title, children, action, className = "" }) {
  return (
    <div className={`bg-white rounded-2xl shadow-sm border border-gray-100 p-5 ${className}`}>
      {title && (
        <div className="flex items-center justify-between mb-5 pb-3 border-b border-gray-50">
          <h3 className="font-semibold text-gray-900">{title}</h3>
          {action}
        </div>
      )}
      {children}
    </div>
  );
}

function Field({ label, error, children, required, hint }) {
  return (
    <div>
      <div className="flex items-center justify-between mb-1.5">
        <label className="block text-sm font-medium text-gray-700">
          {label}{required && <span className="text-red-400 ml-0.5">*</span>}
        </label>
        {hint && <span className="text-[11px] text-gray-400 font-normal">{hint}</span>}
      </div>
      {children}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";
const checkboxClass = "h-4 w-4 accent-orange-500 rounded";

const COLOR_PRESETS = [
  { name: 'Black', hex: '#000000' },
  { name: 'White', hex: '#ffffff' },
  { name: 'Red', hex: '#e11d48' },
  { name: 'Blue', hex: '#2563eb' },
  { name: 'Navy', hex: '#1e3a8a' },
  { name: 'Green', hex: '#16a34a' },
  { name: 'Emerald', hex: '#059669' },
  { name: 'Pink', hex: '#ec4899' },
  { name: 'Purple', hex: '#7c3aed' },
  { name: 'Yellow', hex: '#eab308' },
  { name: 'Orange', hex: '#f97316' },
  { name: 'Gold', hex: '#d97706' },
  { name: 'Brown', hex: '#78350f' },
  { name: 'Grey', hex: '#64748b' },
];

const SIZE_PRESETS = ['S', 'M', 'L', 'XL', 'XXL', '3XL', '30', '32', '34', '36', '38', '40', '42'];

export default function ProductForm({ product, categories }) {
  const isEdit = !!product.id;
  const [submitting, setSubmitting] = useState(false);

  const existingVariants = product.variants || [];
  const existingCustomGroups = product.custom_variant_groups || [];

  const [productType, setProductType] = useState(
    existingVariants.length > 0 || existingCustomGroups.length > 0 ? 'variable' : 'simple'
  );

  const { data, setData, processing, errors } = useForm({
    category_id: product.category_id || '',
    name: product.name || '',
    slug: product.slug || '',
    sku: product.sku || '',
    brand: product.brand || '',
    short_description: product.short_description || '',
    description: product.description || '',
    regular_price: product.regular_price && parseFloat(product.regular_price) !== 0 ? product.regular_price : '',
    sale_price: product.sale_price && parseFloat(product.sale_price) !== 0 ? product.sale_price : '',
    stock_quantity: product.stock_quantity && parseInt(product.stock_quantity) !== 0 ? product.stock_quantity : '',
    unit: product.unit || '',
    is_published: product.is_published ?? true,
    is_featured: product.is_featured ?? false,
    is_new_arrival: product.is_new_arrival ?? false,
    is_best_seller: product.is_best_seller ?? false,
    is_flash_sale: product.is_flash_sale ?? false,
    is_free_shipping: product.is_free_shipping ?? false,
    meta_title: product.meta_title || '',
    meta_description: product.meta_description || '',
    meta_keywords: product.meta_keywords || '',
    images: [],
  });

  const [colors, setColors] = useState(
    existingVariants.filter(v => v.type === 'Color').map(v => ({
      id: v.id,
      value: v.value || '',
      color_code: v.color_code || '#000000',
      price_delta: v.price_delta || 0,
      stock: v.stock || 0,
      image_path: v.image_path || '',
      image_file: null,
      image_preview: v.image_path ? (v.image_path.startsWith('http') ? v.image_path : `/${v.image_path}`) : '',
    }))
  );

  const [sizes, setSizes] = useState(
    existingVariants.filter(v => v.type === 'Size').map(v => ({
      id: v.id,
      value: v.value || '',
      price_delta: v.price_delta || 0,
      stock: v.stock || 0,
      image_path: v.image_path || '',
      image_file: null,
      image_preview: v.image_path ? (v.image_path.startsWith('http') ? v.image_path : `/${v.image_path}`) : '',
    }))
  );

  const [customGroups, setCustomGroups] = useState(
    existingCustomGroups.map(g => ({
      name: g.name || '',
      options: (g.options || []).map(opt => ({
        id: opt.id,
        value: opt.value || '',
        price_delta: opt.price_delta || 0,
        stock: opt.stock || 0,
        image_path: opt.image_path || '',
        image_file: null,
        image_preview: opt.image_path ? (opt.image_path.startsWith('http') ? opt.image_path : `/${opt.image_path}`) : '',
      })),
    }))
  );

  const [specs, setSpecs] = useState(Array.isArray(product.specifications) ? product.specifications : []);

  const submit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const formData = new FormData();
      Object.entries(data).forEach(([k, v]) => {
        if (k === 'images') return;
        if (typeof v === 'boolean') formData.append(k, v ? '1' : '0');
        else formData.append(k, v ?? '');
      });

      formData.append('product_type', productType);

      // Encode product gallery images to base64
      if (data.images && data.images.length > 0) {
        const imageFiles = Array.from(data.images);
        for (let i = 0; i < imageFiles.length; i++) {
          const file = imageFiles[i];
          const b64 = await fileToBase64(file);
          formData.append('images_b64[]', b64);
          formData.append('images_name[]', file.name || `image_${i}.jpg`);
        }
      }

      if (productType === 'variable') {
        // Color variants
        for (let i = 0; i < colors.length; i++) {
          const c = colors[i];
          formData.append(`color_variants[${i}][value]`, c.value || '');
          formData.append(`color_variants[${i}][color_code]`, c.color_code || '');
          formData.append(`color_variants[${i}][price_delta]`, c.price_delta || 0);
          formData.append(`color_variants[${i}][stock]`, c.stock || 0);
          if (c.image_file) {
            const b64 = await fileToBase64(c.image_file);
            formData.append(`color_variants[${i}][image_b64]`, b64);
            formData.append(`color_variants[${i}][image_name]`, c.image_file.name || `color_${i}.jpg`);
          } else if (c.image_path) {
            formData.append(`color_variants[${i}][image_path]`, c.image_path);
          }
        }

        // Size variants
        for (let i = 0; i < sizes.length; i++) {
          const s = sizes[i];
          formData.append(`size_variants[${i}][value]`, s.value || '');
          formData.append(`size_variants[${i}][price_delta]`, s.price_delta || 0);
          formData.append(`size_variants[${i}][stock]`, s.stock || 0);
          if (s.image_file) {
            const b64 = await fileToBase64(s.image_file);
            formData.append(`size_variants[${i}][image_b64]`, b64);
            formData.append(`size_variants[${i}][image_name]`, s.image_file.name || `size_${i}.jpg`);
          } else if (s.image_path) {
            formData.append(`size_variants[${i}][image_path]`, s.image_path);
          }
        }

        // Custom variant groups
        for (let g = 0; g < customGroups.length; g++) {
          const grp = customGroups[g];
          formData.append(`custom_variant_groups[${g}][name]`, grp.name || '');
          const opts = grp.options || [];
          for (let o = 0; o < opts.length; o++) {
            const opt = opts[o];
            formData.append(`custom_variant_groups[${g}][options][${o}][value]`, opt.value || '');
            formData.append(`custom_variant_groups[${g}][options][${o}][price_delta]`, opt.price_delta || 0);
            formData.append(`custom_variant_groups[${g}][options][${o}][stock]`, opt.stock || 0);
            if (opt.image_file) {
              const b64 = await fileToBase64(opt.image_file);
              formData.append(`custom_variant_groups[${g}][options][${o}][image_b64]`, b64);
              formData.append(`custom_variant_groups[${g}][options][${o}][image_name]`, opt.image_file.name || `custom_${g}_${o}.jpg`);
            } else if (opt.image_path) {
              formData.append(`custom_variant_groups[${g}][options][${o}][image_path]`, opt.image_path);
            }
          }
        }
      }

      // specs
      specs.forEach((s, i) => {
        formData.append(`spec_labels[${i}]`, s.label || '');
        formData.append(`spec_values[${i}]`, s.value || '');
      });

      const options = {
        forceFormData: true,
        onFinish: () => setSubmitting(false),
        onError: () => setSubmitting(false),
      };

      if (isEdit) {
        formData.append('_method', 'PUT');
        router.post(`/admin/products/${product.id}`, formData, options);
      } else {
        router.post('/admin/products', formData, options);
      }
    } catch (err) {
      setSubmitting(false);
      console.error(err);
    }
  };

  // Color Variant Handlers
  const addColor = (presetName = '', presetHex = '#000000') => {
    // Prevent duplicate preset colors
    if (presetName && colors.some(c => c.value.toLowerCase() === presetName.toLowerCase())) return;
    setColors(prev => [
      ...prev,
      { value: presetName, color_code: presetHex, price_delta: 0, stock: 0, image_path: '', image_file: null, image_preview: '' }
    ]);
  };
  const removeColor = (i) => setColors(prev => prev.filter((_, idx) => idx !== i));
  const updateColor = (i, field, val) => setColors(prev => prev.map((c, idx) => idx === i ? { ...c, [field]: val } : c));
  const handleColorImageChange = (i, file) => {
    if (!file) return;
    const preview = URL.createObjectURL(file);
    setColors(prev => prev.map((c, idx) => idx === i ? { ...c, image_file: file, image_preview: preview } : c));
  };
  const removeColorImage = (i) => {
    setColors(prev => prev.map((c, idx) => idx === i ? { ...c, image_path: '', image_file: null, image_preview: '' } : c));
  };

  // Size Variant Handlers
  const addSize = (val = '') => {
    // Prevent duplicate preset sizes
    if (val && sizes.some(s => s.value.toLowerCase() === val.toLowerCase())) return;
    setSizes(prev => [
      ...prev,
      { value: val, price_delta: 0, stock: 0, image_path: '', image_file: null, image_preview: '' }
    ]);
  };
  const removeSize = (i) => setSizes(prev => prev.filter((_, idx) => idx !== i));
  const updateSize = (i, field, val) => setSizes(prev => prev.map((s, idx) => idx === i ? { ...s, [field]: val } : s));
  const handleSizeImageChange = (i, file) => {
    if (!file) return;
    const preview = URL.createObjectURL(file);
    setSizes(prev => prev.map((s, idx) => idx === i ? { ...s, image_file: file, image_preview: preview } : s));
  };
  const removeSizeImage = (i) => {
    setSizes(prev => prev.map((s, idx) => idx === i ? { ...s, image_path: '', image_file: null, image_preview: '' } : s));
  };

  // Custom Variant Group Handlers
  const addCustomGroup = () => {
    setCustomGroups(prev => [
      ...prev,
      { name: '', options: [{ value: '', price_delta: 0, stock: 0, image_path: '', image_file: null, image_preview: '' }] }
    ]);
  };
  const removeCustomGroup = (gIdx) => setCustomGroups(prev => prev.filter((_, idx) => idx !== gIdx));
  const updateCustomGroupName = (gIdx, name) => setCustomGroups(prev => prev.map((g, idx) => idx === gIdx ? { ...g, name } : g));

  const addCustomOption = (gIdx) => {
    setCustomGroups(prev => prev.map((g, idx) => idx === gIdx ? {
      ...g,
      options: [...g.options, { value: '', price_delta: 0, stock: 0, image_path: '', image_file: null, image_preview: '' }]
    } : g));
  };
  const removeCustomOption = (gIdx, optIdx) => {
    setCustomGroups(prev => prev.map((g, idx) => idx === gIdx ? {
      ...g,
      options: g.options.filter((_, i) => i !== optIdx)
    } : g));
  };
  const updateCustomOption = (gIdx, optIdx, field, val) => {
    setCustomGroups(prev => prev.map((g, idx) => idx === gIdx ? {
      ...g,
      options: g.options.map((opt, i) => i === optIdx ? { ...opt, [field]: val } : opt)
    } : g));
  };
  const handleCustomOptionImageChange = (gIdx, optIdx, file) => {
    if (!file) return;
    const preview = URL.createObjectURL(file);
    setCustomGroups(prev => prev.map((g, idx) => idx === gIdx ? {
      ...g,
      options: g.options.map((opt, i) => i === optIdx ? { ...opt, image_file: file, image_preview: preview } : opt)
    } : g));
  };
  const removeCustomOptionImage = (gIdx, optIdx) => {
    setCustomGroups(prev => prev.map((g, idx) => idx === gIdx ? {
      ...g,
      options: g.options.map((opt, i) => i === optIdx ? { ...opt, image_path: '', image_file: null, image_preview: '' } : opt)
    } : g));
  };

  // Specs Handlers
  const addSpec = () => setSpecs(prev => [...prev, { label: '', value: '' }]);
  const removeSpec = (i) => setSpecs(prev => prev.filter((_, idx) => idx !== i));
  const updateSpec = (i, field, val) => setSpecs(prev => prev.map((s, idx) => idx === i ? { ...s, [field]: val } : s));

  const handleDeleteImage = (imageId) => {
    window.showConfirm('Remove this image?', () => { router.delete(`/admin/products/${product.id}/images/${imageId}`, { preserveScroll: true }); });
  };

  return (
    <>
      <Head title={isEdit ? `Edit: ${product.name}` : 'Add Product'} />
      <AdminLayout title={isEdit ? `Edit Product` : 'Add Product'}>
        <form onSubmit={submit} className="max-w-5xl space-y-5">
          {/* Back */}
          <a href="/admin/products" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Products
          </a>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div className="lg:col-span-2 space-y-5">
              
              {/* 1. Basic info */}
              <FormSection title="Basic Information">
                <div className="space-y-4">
                  <Field label="Product Name" required error={errors.name}>
                    <input value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} required placeholder="e.g. Premium Cotton Casual Shirt" />
                  </Field>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label="Slug (URL)" error={errors.slug} hint="Automatically shortened. Existing links keep working after changes.">
                      <input value={data.slug} onChange={e => setData('slug', e.target.value)} className={inputClass} placeholder="auto-generated" />
                    </Field>
                    <Field label="SKU" error={errors.sku} hint="Stock Keeping Unit / Product Code">
                      <input value={data.sku} onChange={e => setData('sku', e.target.value)} className={inputClass} placeholder="e.g. SHT-BLK-01" />
                    </Field>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label="Brand" error={errors.brand}>
                      <input value={data.brand} onChange={e => setData('brand', e.target.value)} className={inputClass} placeholder="e.g. Nike, Apple, Local" />
                    </Field>
                    <Field label="Category" required error={errors.category_id}>
                      <select value={data.category_id} onChange={e => setData('category_id', e.target.value)} className={inputClass} required>
                        <option value="">Select category…</option>
                        {(categories || []).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                      </select>
                    </Field>
                  </div>
                  <Field label="Short Highlights / Short Description" error={errors.short_description}>
                    <RichTextEditor
                      value={data.short_description}
                      onChange={val => setData('short_description', val)}
                      placeholder="Write short product highlights with bold, bullet points, and formatting..."
                      minHeight="120px"
                    />
                  </Field>
                  <Field label="Full Description" error={errors.description}>
                    <RichTextEditor
                      value={data.description}
                      onChange={val => setData('description', val)}
                      placeholder="Write rich product description with alignment, headings, images, and formatting..."
                      minHeight="220px"
                    />
                  </Field>
                </div>
              </FormSection>

              {/* 2. Product Type & Pricing / Variant Management */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-5">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
                  <div>
                    <h3 className="font-bold text-gray-900 text-base">Product Type & Pricing (প্রোডাক্ট ধরণ ও মূল্য)</h3>
                    <p className="text-xs text-gray-500 mt-0.5">একক প্রোডাক্ট হলে <strong>Simple</strong>, আর সাইজ/কালার ভ্যারিয়েন্ট থাকলে <strong>Variable</strong> সিলেক্ট করুন।</p>
                  </div>
                  <div className="flex items-center bg-gray-100 p-1 rounded-xl shrink-0">
                    <button
                      type="button"
                      onClick={() => setProductType('simple')}
                      className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 ${
                        productType === 'simple'
                          ? 'bg-white text-gray-900 shadow-sm border border-gray-200'
                          : 'text-gray-500 hover:text-gray-900'
                      }`}
                    >
                      <svg className="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                      </svg>
                      <span>Simple Product (একক)</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => setProductType('variable')}
                      className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 ${
                        productType === 'variable'
                          ? 'bg-orange-500 text-white shadow-sm shadow-orange-500/20'
                          : 'text-gray-500 hover:text-gray-900'
                      }`}
                    >
                      <svg className={`w-3.5 h-3.5 shrink-0 ${productType === 'variable' ? 'text-white' : 'text-amber-500'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16m-7 6h7"/>
                      </svg>
                      <span>Variable Product (ভ্যারিয়েন্ট)</span>
                    </button>
                  </div>
                </div>

                {/* ── CASE A: SIMPLE PRODUCT (Show standard single price & stock) ── */}
                {productType === 'simple' ? (
                  <div className="space-y-4 pt-1">
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                      <Field label="Regular Price" required error={errors.regular_price} hint="৳ Base Price">
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={data.regular_price}
                          onChange={e => setData('regular_price', e.target.value)}
                          onFocus={e => e.target.select()}
                          className={inputClass}
                          required
                          placeholder="0.00"
                        />
                      </Field>
                      <Field label="Sale Price" error={errors.sale_price} hint="৳ Discount Price">
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={data.sale_price}
                          onChange={e => setData('sale_price', e.target.value)}
                          onFocus={e => e.target.select()}
                          className={inputClass}
                          placeholder="Optional"
                        />
                      </Field>
                      <Field label="Stock Quantity" required error={errors.stock_quantity} hint="Total Qty">
                        <input
                          type="number"
                          min="0"
                          value={data.stock_quantity}
                          onChange={e => setData('stock_quantity', e.target.value)}
                          onFocus={e => e.target.select()}
                          className={inputClass}
                          required
                          placeholder="0"
                        />
                      </Field>
                      <Field label="Unit" error={errors.unit} hint="e.g. pcs, pair, kg">
                        <input
                          value={data.unit}
                          onChange={e => setData('unit', e.target.value)}
                          className={inputClass}
                          placeholder="pcs, kg, box"
                        />
                      </Field>
                    </div>
                  </div>
                ) : (
                  /* ── CASE B: VARIABLE PRODUCT (Simple Pricing is hidden; Variants have individual prices & stock) ── */
                  <div className="space-y-6 pt-1">
                    {/* Optional Catalog Base Price (used as default/starting price in catalog) */}
                    <div className="p-4 bg-orange-50/40 border border-orange-100 rounded-2xl space-y-3">
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <span className="text-xs font-bold text-gray-900">Base / Starting Price (প্রারম্ভিক মূল্য)</span>
                        <span className="text-[11px] text-gray-500">প্রতিটি ভ্যারিয়েন্টে আলাদা দাম দিতে পারেন, এখানে বেস প্রাইজ নির্ধারণ করতে পারেন</span>
                      </div>
                      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div>
                          <label className="block text-[11px] font-semibold text-gray-600 mb-1">Base Regular Price (৳)</label>
                          <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.regular_price}
                            onChange={e => setData('regular_price', e.target.value)}
                            onFocus={e => e.target.select()}
                            className={inputClass}
                            placeholder="0.00"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-gray-600 mb-1">Base Sale Price (৳, optional)</label>
                          <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.sale_price}
                            onChange={e => setData('sale_price', e.target.value)}
                            onFocus={e => e.target.select()}
                            className={inputClass}
                            placeholder="Optional"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-semibold text-gray-600 mb-1">Unit (একক)</label>
                          <input
                            value={data.unit}
                            onChange={e => setData('unit', e.target.value)}
                            className={inputClass}
                            placeholder="pcs, pair, kg"
                          />
                        </div>
                      </div>
                    </div>

                    {/* 1. Color Variants */}
                    <div className="border border-gray-100 rounded-2xl p-4 bg-gray-50/40 space-y-3">
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                          <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                            Color Variants (কালার অপশন)
                          </h4>
                          <p className="text-[11px] text-gray-500">কালার পিকার দিয়ে কালার সিলেক্ট করুন এবং প্রতিটি কালারের আলাদা ছবি, দাম ও স্টক যুক্ত করুন।</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => addColor()}
                          className="px-3 py-1.5 bg-white border border-gray-200 hover:border-orange-400 text-gray-700 text-xs font-semibold rounded-xl transition-all cursor-pointer shadow-sm self-start sm:self-auto"
                        >
                          + Add Color
                        </button>
                      </div>

                      {/* Quick preset color chips */}
                      <div className="flex items-center gap-1.5 flex-wrap pt-1">
                        <span className="text-[10.5px] text-gray-400 font-medium">Quick Add:</span>
                        {COLOR_PRESETS.map(p => (
                          <button
                            key={p.name}
                            type="button"
                            onClick={() => addColor(p.name, p.hex)}
                            className="inline-flex items-center gap-1 px-2 py-0.5 bg-white border border-gray-200 hover:border-orange-400 rounded-lg text-[10.5px] font-semibold text-gray-700 transition-colors shadow-2xs cursor-pointer"
                          >
                            <span className="w-2.5 h-2.5 rounded-full border border-gray-300 shrink-0" style={{ backgroundColor: p.hex }}></span>
                            {p.name}
                          </button>
                        ))}
                      </div>

                      {/* Colors list */}
                      <div className="space-y-2 pt-2">
                        {colors.map((c, i) => (
                          <div key={i} className="flex flex-wrap sm:flex-nowrap gap-2.5 items-center p-2.5 bg-white rounded-xl border border-gray-200 shadow-2xs">
                            {/* Color Picker & Swatch */}
                            <div className="flex items-center gap-1.5 shrink-0" title="Click to pick color">
                              <input
                                type="color"
                                value={c.color_code || '#000000'}
                                onChange={e => updateColor(i, 'color_code', e.target.value)}
                                className="w-9 h-9 p-0 rounded-lg border border-gray-200 cursor-pointer overflow-hidden"
                              />
                            </div>

                            {/* Color Image Uploader / Preview */}
                            <div className="relative shrink-0">
                              {c.image_preview ? (
                                <div className="relative group w-9 h-9 rounded-lg overflow-hidden border border-orange-200 bg-white">
                                  <img src={c.image_preview} alt="Color" className="w-full h-full object-cover" />
                                  <button
                                    type="button"
                                    onClick={() => removeColorImage(i)}
                                    className="absolute inset-0 bg-red-600/80 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center text-xs font-bold transition-opacity cursor-pointer"
                                    title="Remove image"
                                  >
                                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                  </button>
                                </div>
                              ) : (
                                <label className="w-9 h-9 rounded-lg border-2 border-dashed border-gray-200 hover:border-orange-400 bg-white flex flex-col items-center justify-center cursor-pointer transition-colors" title="Upload image for this color">
                                  <svg className="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                  <span className="text-[7.5px] text-gray-400 font-bold uppercase">Img</span>
                                  <input type="file" accept="image/*" className="hidden" onChange={e => handleColorImageChange(i, e.target.files[0])} />
                                </label>
                              )}
                            </div>

                            {/* Color Name Input */}
                            <div className="flex-1 min-w-[130px]">
                              <input
                                value={c.value}
                                onChange={e => updateColor(i, 'value', e.target.value)}
                                placeholder="Color name (e.g. Royal Blue)"
                                className="w-full border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-300"
                              />
                            </div>

                            {/* Extra Price */}
                            <div className="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5 w-28" title="Extra price for this color">
                              <span className="text-[11px] font-bold text-orange-500">+৳</span>
                              <input
                                type="number"
                                value={c.price_delta}
                                onChange={e => updateColor(i, 'price_delta', e.target.value)}
                                placeholder="0"
                                className="w-full text-xs font-semibold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none"
                              />
                            </div>

                            {/* Stock */}
                            <div className="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5 w-22" title="Stock for this color">
                              <span className="text-[10px] text-gray-400 font-medium">Qty:</span>
                              <input
                                type="number"
                                min="0"
                                value={c.stock}
                                onChange={e => updateColor(i, 'stock', e.target.value)}
                                placeholder="0"
                                className="w-full text-xs font-semibold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none"
                              />
                            </div>

                            {/* Delete */}
                            <button
                              type="button"
                              onClick={() => removeColor(i)}
                              className="h-8 w-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors shrink-0 cursor-pointer"
                              title="Delete color"
                            >
                              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* 2. Size Variants */}
                    <div className="border border-gray-100 rounded-2xl p-4 bg-gray-50/40 space-y-3">
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                          <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                            Size Variants (সাইজ অপশন)
                          </h4>
                          <p className="text-[11px] text-gray-500">প্রোডাক্টের সাইজ (যেমন M, L, XL, 32, 42) যুক্ত করুন।</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => addSize()}
                          className="px-3 py-1.5 bg-white border border-gray-200 hover:border-orange-400 text-gray-700 text-xs font-semibold rounded-xl transition-all cursor-pointer shadow-sm self-start sm:self-auto"
                        >
                          + Add Size
                        </button>
                      </div>

                      {/* Quick preset sizes */}
                      <div className="flex items-center gap-1.5 flex-wrap pt-1">
                        <span className="text-[10.5px] text-gray-400 font-medium">Quick Add:</span>
                        {SIZE_PRESETS.map(sz => (
                          <button
                            key={sz}
                            type="button"
                            onClick={() => addSize(sz)}
                            className="px-2 py-0.5 bg-white border border-gray-200 hover:border-orange-400 rounded-lg text-[10.5px] font-semibold text-gray-700 transition-colors shadow-2xs cursor-pointer"
                          >
                            {sz}
                          </button>
                        ))}
                      </div>

                      {/* Sizes list */}
                      <div className="space-y-2 pt-2">
                        {sizes.map((s, i) => (
                          <div key={i} className="flex flex-wrap sm:flex-nowrap gap-2.5 items-center p-2.5 bg-white rounded-xl border border-gray-200 shadow-2xs">
                            {/* Size Image Uploader / Preview */}
                            <div className="relative shrink-0">
                              {s.image_preview ? (
                                <div className="relative group w-9 h-9 rounded-lg overflow-hidden border border-orange-200 bg-white">
                                  <img src={s.image_preview} alt="Size" className="w-full h-full object-cover" />
                                  <button
                                    type="button"
                                    onClick={() => removeSizeImage(i)}
                                    className="absolute inset-0 bg-red-600/80 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center text-xs font-bold transition-opacity cursor-pointer"
                                    title="Remove image"
                                  >
                                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                  </button>
                                </div>
                              ) : (
                                <label className="w-9 h-9 rounded-lg border-2 border-dashed border-gray-200 hover:border-orange-400 bg-white flex flex-col items-center justify-center cursor-pointer transition-colors" title="Upload image for this size">
                                  <svg className="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                  <span className="text-[7.5px] text-gray-400 font-bold uppercase">Img</span>
                                  <input type="file" accept="image/*" className="hidden" onChange={e => handleSizeImageChange(i, e.target.files[0])} />
                                </label>
                              )}
                            </div>

                            {/* Size Value Input */}
                            <div className="flex-1 min-w-[130px]">
                              <input
                                value={s.value}
                                onChange={e => updateSize(i, 'value', e.target.value)}
                                placeholder="Size (e.g. M, XL, 42)"
                                className="w-full border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-orange-300"
                              />
                            </div>

                            {/* Extra Price */}
                            <div className="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5 w-28" title="Extra price for this size">
                              <span className="text-[11px] font-bold text-orange-500">+৳</span>
                              <input
                                type="number"
                                value={s.price_delta}
                                onChange={e => updateSize(i, 'price_delta', e.target.value)}
                                placeholder="0"
                                className="w-full text-xs font-semibold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none"
                              />
                            </div>

                            {/* Stock */}
                            <div className="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5 w-22" title="Stock for this size">
                              <span className="text-[10px] text-gray-400 font-medium">Qty:</span>
                              <input
                                type="number"
                                min="0"
                                value={s.stock}
                                onChange={e => updateSize(i, 'stock', e.target.value)}
                                placeholder="0"
                                className="w-full text-xs font-semibold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none"
                              />
                            </div>

                            {/* Delete */}
                            <button
                              type="button"
                              onClick={() => removeSize(i)}
                              className="h-8 w-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors shrink-0 cursor-pointer"
                              title="Delete size"
                            >
                              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* 3. Custom Variant Groups */}
                    <div className="space-y-4">
                      {customGroups.map((group, gIdx) => (
                        <div key={gIdx} className="border border-purple-100 rounded-2xl p-4 bg-purple-50/30 space-y-3">
                          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-purple-100">
                            <div className="flex items-center gap-2 flex-1 max-w-sm">
                              <span className="w-2.5 h-2.5 rounded-full bg-purple-500 shrink-0"></span>
                              <input
                                value={group.name}
                                onChange={e => updateCustomGroupName(gIdx, e.target.value)}
                                placeholder="Variant Type Name (e.g. Storage, Pack, Material)"
                                className="w-full bg-white border border-purple-200 rounded-xl px-3 py-1.5 text-xs font-bold text-purple-950 focus:outline-none focus:ring-2 focus:ring-purple-300"
                              />
                            </div>
                            <div className="flex items-center gap-2">
                              <button
                                type="button"
                                onClick={() => addCustomOption(gIdx)}
                                className="px-2.5 py-1 bg-white border border-purple-200 hover:border-purple-400 text-purple-700 text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                              >
                                + Add Option
                              </button>
                              <button
                                type="button"
                                onClick={() => removeCustomGroup(gIdx)}
                                className="text-xs text-red-500 hover:text-red-700 font-semibold px-2 py-1 rounded hover:bg-red-50 transition-colors cursor-pointer"
                              >
                                Delete Group
                              </button>
                            </div>
                          </div>

                          {/* Options */}
                          <div className="space-y-2">
                            {group.options.map((opt, optIdx) => (
                              <div key={optIdx} className="flex flex-wrap sm:flex-nowrap gap-2.5 items-center p-2.5 bg-white rounded-xl border border-gray-200 shadow-2xs">
                                {/* Option Image */}
                                <div className="relative shrink-0">
                                  {opt.image_preview ? (
                                    <div className="relative group w-9 h-9 rounded-lg overflow-hidden border border-purple-200 bg-white">
                                      <img src={opt.image_preview} alt="Option" className="w-full h-full object-cover" />
                                      <button
                                        type="button"
                                        onClick={() => removeCustomOptionImage(gIdx, optIdx)}
                                        className="absolute inset-0 bg-red-600/80 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center text-xs font-bold transition-opacity cursor-pointer"
                                        title="Delete option"
                                      >
                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                      </button>
                                    </div>
                                  ) : (
                                    <label className="w-9 h-9 rounded-lg border-2 border-dashed border-gray-200 hover:border-purple-400 bg-white flex flex-col items-center justify-center cursor-pointer transition-colors" title="Upload image for this option">
                                      <svg className="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                      <span className="text-[7.5px] text-gray-400 font-bold uppercase">Img</span>
                                      <input type="file" accept="image/*" className="hidden" onChange={e => handleCustomOptionImageChange(gIdx, optIdx, e.target.files[0])} />
                                    </label>
                                  )}
                                </div>

                                {/* Option Value */}
                                <div className="flex-1 min-w-[130px]">
                                  <input
                                    value={opt.value}
                                    onChange={e => updateCustomOption(gIdx, optIdx, 'value', e.target.value)}
                                    placeholder="Option Value (e.g. 128GB, Pack of 3)"
                                    className="w-full border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-300"
                                  />
                                </div>

                                {/* Extra Price */}
                                <div className="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5 w-28" title="Extra price for this option">
                                  <span className="text-[11px] font-bold text-orange-500">+৳</span>
                                  <input
                                    type="number"
                                    value={opt.price_delta}
                                    onChange={e => updateCustomOption(gIdx, optIdx, 'price_delta', e.target.value)}
                                    placeholder="0"
                                    className="w-full text-xs font-semibold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none"
                                  />
                                </div>

                                {/* Stock */}
                                <div className="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5 w-22" title="Stock for this option">
                                  <span className="text-[10px] text-gray-400 font-medium">Qty:</span>
                                  <input
                                    type="number"
                                    min="0"
                                    value={opt.stock}
                                    onChange={e => updateCustomOption(gIdx, optIdx, 'stock', e.target.value)}
                                    placeholder="0"
                                    className="w-full text-xs font-semibold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none"
                                  />
                                </div>

                                {/* Delete */}
                                <button
                                  type="button"
                                  onClick={() => removeCustomOption(gIdx, optIdx)}
                                  className="h-8 w-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors shrink-0 cursor-pointer"
                                  title="Delete option"
                                >
                                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                              </div>
                            ))}
                          </div>
                        </div>
                      ))}

                      {/* Add Custom Group Button */}
                      <button
                        type="button"
                        onClick={addCustomGroup}
                        className="w-full py-3 border-2 border-dashed border-purple-200 hover:border-purple-400 text-purple-700 hover:bg-purple-50/50 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer bg-white"
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
                        + Add Custom Variant Type (যেমন: Storage, Pack, Material, Flavour)
                      </button>
                    </div>
                  </div>
                )}
              </div>

              {/* 3. Product Gallery Images */}
              <FormSection title="Product Gallery Images">
                {(product.images || []).length > 0 && (
                  <div className="flex flex-wrap gap-3 mb-4">
                    {product.images.map(img => (
                      <div key={img.id} className="relative group">
                        <img src={img.path.startsWith('http') ? img.path : `/${img.path}`} alt="" className="h-20 w-20 rounded-xl object-cover border border-gray-100" />
                        {img.is_primary && <span className="absolute top-1 left-1 bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">Primary</span>}
                        <button type="button" onClick={() => handleDeleteImage(img.id)}
                          className="absolute top-1 right-1 h-5 w-5 bg-red-500 text-white rounded-full hidden group-hover:flex items-center justify-center text-xs cursor-pointer"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                      </div>
                    ))}
                  </div>
                )}
                <label className="flex items-center gap-3 p-4 border-2 border-dashed border-gray-200 rounded-xl hover:border-orange-300 cursor-pointer transition-colors">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                  <span className="text-sm text-gray-500">Click to upload product gallery images (multiple)</span>
                  <input type="file" multiple accept="image/*" className="hidden" onChange={e => setData('images', e.target.files)} />
                </label>
                {data.images && data.images.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-3">
                    {Array.from(data.images).map((file, i) => (
                      <div key={i} className="text-xs font-medium bg-orange-50 text-orange-600 px-2.5 py-1.5 rounded-lg border border-orange-100 flex items-center gap-1.5 shadow-sm">
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {file.name}
                      </div>
                    ))}
                  </div>
                )}
              </FormSection>

              {/* 4. Specifications */}
              <FormSection title="Specifications (প্রোডাক্টের স্পেসিফিকেশন)">
                <p className="text-xs text-gray-500 mb-3 -mt-2">
                  প্রোডাক্টের স্পেসিফিকেশন যেমন: Material, Warranty, Weight, Dimensions ইত্যাদি যুক্ত করুন। ফাঁকা রাখলে স্টোরফ্রন্টে স্পেসিফিকেশন টেবিল দেখাবে না।
                </p>
                <div className="space-y-2.5">
                  {specs.map((s, i) => (
                    <div key={i} className="flex gap-2 items-center">
                      <input
                        value={s.label}
                        onChange={e => updateSpec(i, 'label', e.target.value)}
                        placeholder="Label (e.g. Material, Warranty, Origin)"
                        className="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                      />
                      <input
                        value={s.value}
                        onChange={e => updateSpec(i, 'value', e.target.value)}
                        placeholder="Value (e.g. 100% Cotton, 1 Year, China)"
                        className="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                      />
                      <button
                        type="button"
                        onClick={() => removeSpec(i)}
                        className="h-8 w-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors shrink-0 cursor-pointer"
                        title="Remove specification"
                      >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                  ))}
                  <button type="button" onClick={addSpec} className="text-sm text-orange-500 hover:text-orange-600 font-medium flex items-center gap-1 cursor-pointer pt-1">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
                    Add Specification
                  </button>
                </div>
              </FormSection>

              {/* 5. SEO */}
              <FormSection title="SEO Meta Tags">
                <div className="space-y-4">
                  <Field label="Meta Title" error={errors.meta_title}><input value={data.meta_title} onChange={e => setData('meta_title', e.target.value)} className={inputClass} placeholder="Custom SEO Title" /></Field>
                  <Field label="Meta Description" error={errors.meta_description}><textarea value={data.meta_description} onChange={e => setData('meta_description', e.target.value)} rows={2} className={inputClass} placeholder="SEO summary for search engines" /></Field>
                  <Field label="Meta Keywords" error={errors.meta_keywords}><input value={data.meta_keywords} onChange={e => setData('meta_keywords', e.target.value)} className={inputClass} placeholder="comma-separated keywords" /></Field>
                </div>
              </FormSection>
            </div>

            {/* Right sidebar */}
            <div className="space-y-5">
              {/* Status */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 className="font-semibold text-gray-900 mb-4 pb-3 border-b border-gray-50">Visibility</h3>
                <div className="space-y-3">
                  {[
                    { key: 'is_published', label: 'Published (visible on store)' },
                    { key: 'is_featured', label: 'Featured / Trending' },
                    { key: 'is_new_arrival', label: 'New Arrival' },
                    { key: 'is_best_seller', label: 'Best Seller' },
                    { key: 'is_flash_sale', label: 'Flash Sale' },
                  ].map(({ key, label }) => (
                    <label key={key} className="flex items-center gap-3 cursor-pointer">
                      <input type="checkbox" checked={!!data[key]} onChange={e => setData(key, e.target.checked)} className={checkboxClass} />
                      <span className="text-sm text-gray-700">{label}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Shipping & Delivery */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 className="font-semibold text-gray-900 mb-4 pb-3 border-b border-gray-50 flex items-center gap-2">
                  <svg className="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>
                  Delivery & Shipping
                </h3>
                <label className="flex items-start gap-3 cursor-pointer p-3 rounded-xl bg-emerald-50/70 border border-emerald-200/80 hover:bg-emerald-50 transition-colors">
                  <input
                    type="checkbox"
                    checked={!!data.is_free_shipping}
                    onChange={e => setData('is_free_shipping', e.target.checked)}
                    className="h-4 w-4 accent-emerald-600 rounded mt-0.5"
                  />
                  <div>
                    <span className="text-sm font-bold text-emerald-950 block">Free Home Delivery</span>
                    <span className="text-xs text-emerald-700 block mt-0.5 leading-relaxed">
                      টিক দিলে এই প্রোডাক্টে কোনো শিপিং চার্জ (ঢাকা / ঢাকার বাইরে) যোগ হবে না এবং ফ্রি ডেলিভারি ব্যানার দেখাবে।
                    </span>
                  </div>
                </label>
              </div>

              {/* Submit */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <button type="submit" disabled={processing || submitting}
                  className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors cursor-pointer">
                  {processing || submitting ? 'Saving…' : (isEdit ? 'Update Product' : 'Create Product')}
                </button>
                {isEdit && (
                  <button type="button" onClick={() => { window.showConfirm(`Delete "${product.name}"?`, () => router.delete(`/admin/products/${product.id}`)); }}
                    className="w-full mt-2 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors cursor-pointer">
                    Delete Product
                  </button>
                )}
              </div>
            </div>
          </div>
        </form>
      </AdminLayout>
    </>
  );
}
