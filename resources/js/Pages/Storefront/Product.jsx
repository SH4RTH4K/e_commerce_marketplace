import { useState, useMemo, useEffect, useRef } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import ProductCard from '@/Components/Storefront/ProductCard';
import CodOrderModal from '@/Components/Storefront/CodOrderModal';
import { money, imageUrl, whatsappNumber } from '@/lib/utils';
import { trackViewItem, trackAddToCart } from '@/lib/tracking';

const escapeDescriptionHtml = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#039;');

const formatDescriptionHtml = (value) => {
  const text = String(value || '').trim();

  if (!text) return '<p>No description available.</p>';
  if (/<\/?[a-z][\s\S]*>/i.test(text)) return text;

  return text
    .split(/\r?\n\s*\r?\n/)
    .map(paragraph => paragraph.trim())
    .filter(Boolean)
    .map(paragraph => `<p>${escapeDescriptionHtml(paragraph).replace(/\r?\n/g, '<br />')}</p>`)
    .join('');
};

export default function ProductPage({ product, related, sizes, colors, weights, variantGroups = [], features, reviews, canReview, auth, seo }) {
  const { app } = usePage().props;
  const settings = app?.settings || {};
  const isTemplateOne = settings.storefront_template === 'template-1';

  // Button customization settings
  const isCodEnabled = settings.product_page_cod_enabled !== false && settings.pay_cod_enabled !== false;
  const codText = settings.product_page_cod_text || 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন';
  const codBgColor = settings.product_page_cod_bg_color || '#16a34a';
  const codTextColor = settings.product_page_cod_text_color || '#ffffff';

  const isWhatsappEnabled = settings.product_page_whatsapp_enabled !== false;
  const whatsappText = settings.product_page_whatsapp_text || 'WhatsApp Order';
  const configuredWhatsappNumber = settings.whatsapp_number || settings.product_page_whatsapp_number || '';
  const whatsappDigits = whatsappNumber(configuredWhatsappNumber);
  const whatsappBgColor = settings.product_page_whatsapp_bg_color || '#25D366';
  const whatsappTextColor = settings.product_page_whatsapp_text_color || '#ffffff';

  const isCallEnabled = settings.product_page_call_enabled !== false;
  const callText = settings.product_page_call_text || 'Call For Order';
  const callNumber = settings.product_page_call_number || settings.call_number || settings.contact_phone || '';
  const callBgColor = settings.product_page_call_bg_color || '#294294';
  const callTextColor = settings.product_page_call_text_color || '#ffffff';

  const addCartText = settings.product_page_add_cart_text || 'ADD TO CART';
  const addCartBgColor = settings.product_page_add_cart_bg_color || '#f15a24';
  const addCartTextColor = settings.product_page_add_cart_text_color || '#ffffff';

  const buyText = settings.product_page_buy_text || 'ORDER NOW';
  const buyBgColor = settings.product_page_buy_bg_color || '#0b1c21';
  const buyTextColor = settings.product_page_buy_text_color || '#ffffff';
  const productButtonClass = 'storefront-product-button w-full rounded-xl text-[11px] sm:text-[13px] font-bold py-2.5 sm:py-3 transition-all hover:brightness-95 active:scale-[0.99] flex items-center justify-center gap-1.5 shadow-sm text-center cursor-pointer disabled:cursor-not-allowed disabled:opacity-50';

  const [activeImage, setActiveImage] = useState(product.images?.[0]?.path);
  const [activeTab, setActiveTab] = useState('desc');
  
  // Dynamic variant state
  const [selectedVariants, setSelectedVariants] = useState(() => {
    const init = {};
    if (variantGroups && variantGroups.length > 0) {
      variantGroups.forEach(grp => {
        if (grp.options && grp.options.length > 0) {
          init[grp.name] = grp.options[0].value;
        }
      });
    } else {
      if (colors?.length > 0) init['Color'] = colors[0].value;
      if (sizes?.length > 0) init['Size'] = sizes[0].value;
      if (weights?.length > 0) init['Weight'] = weights[0].value;
    }
    return init;
  });

  const [qty, setQty] = useState(1);
  const [imageError, setImageError] = useState(false);
  const [showQuickOrder, setShowQuickOrder] = useState(false);
  const failedImagePaths = useRef(new Set());

  const selectImage = path => {
    setActiveImage(path);
    setImageError(false);
  };

  const handleMainImageError = () => {
    if (activeImage) failedImagePaths.current.add(activeImage);

    const nextImage = (product.images || []).find(image => image.path && !failedImagePaths.current.has(image.path));
    if (nextImage) {
      setActiveImage(nextImage.path);
      setImageError(false);
      return;
    }

    setImageError(true);
  };

  const getVariantString = () => {
    if (variantGroups && variantGroups.length > 0) {
      return Object.entries(selectedVariants)
        .filter(([_, v]) => Boolean(v))
        .map(([k, v]) => `${k}: ${v}`)
        .join(', ');
    }
    const parts = [];
    if (selectedVariants['Color']) parts.push(`Color: ${selectedVariants['Color']}`);
    if (selectedVariants['Size']) parts.push(`Size: ${selectedVariants['Size']}`);
    if (selectedVariants['Weight']) parts.push(`Weight: ${selectedVariants['Weight']}`);
    return parts.join(', ');
  };

  const quickOrderForm = useForm({
    customer_name: auth?.user?.name || '',
    customer_phone: auth?.user?.phone || '',
    shipping_address: auth?.user?.address || '',
    city: 'Dhaka',
    shipping_zone: 'inside_dhaka',
    payment_method: 'cod',
  });

  const handleQuickOrderSubmit = async (e) => {
    e.preventDefault();
    try {
      const variant = getVariantString();

      const response = await fetch('/cart/buy-now', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        },
        body: JSON.stringify({
          product_id: product.id,
          qty: qty,
          variant: variant || null
        })
      });

      if (!response.ok) {
        throw new Error('Failed to prepare cart for quick order');
      }

      quickOrderForm.post('/checkout', {
        preserveScroll: true,
        onSuccess: () => setShowQuickOrder(false)
      });
    } catch (err) {
      console.error(err);
      alert('Failed to process order. Please try again.');
    }
  };

  // Parse specifications
  const specRows = useMemo(() => {
    if (!product.specifications) return [];
    return product.specifications.map(row => {
      if (typeof row === 'string') {
        if (!row.trim()) return null;
        if (row.includes(':')) {
          const [label, ...val] = row.split(':');
          return { label: label.trim(), value: val.join(':').trim() };
        }
        return { label: 'Detail', value: row.trim() };
      }
      const label = row.label?.trim();
      const value = row.value?.trim();
      if (!label && !value) return null;
      return { label: label || 'Detail', value };
    }).filter(Boolean);
  }, [product.specifications]);

  const bulletSpecs = useMemo(() => {
    return specRows.slice(0, 6).map(row => {
      if (row.label && row.label !== 'Detail' && row.value) return `${row.label}: ${row.value}`;
      return row.label && row.label !== 'Detail' ? row.label : row.value;
    });
  }, [specRows]);

  // Pricing Logic
  const priceData = useMemo(() => {
    let delta = 0;
    
    if (variantGroups && variantGroups.length > 0) {
      variantGroups.forEach(grp => {
        const val = selectedVariants[grp.name];
        if (val) {
          const opt = (grp.options || []).find(x => x.value === val);
          if (opt) delta += parseFloat(opt.price_delta) || 0;
        }
      });
    } else {
      if (selectedVariants['Color']) {
        const c = colors?.find(x => x.value === selectedVariants['Color']);
        if (c) delta += parseFloat(c.price_delta) || 0;
      }
      if (selectedVariants['Size']) {
        const s = sizes?.find(x => x.value === selectedVariants['Size']);
        if (s) delta += parseFloat(s.price_delta) || 0;
      }
      if (selectedVariants['Weight']) {
        const w = weights?.find(x => x.value === selectedVariants['Weight']);
        if (w) delta += parseFloat(w.price_delta) || 0;
      }
    }

    const basePrice = parseFloat(product.sale_price || product.regular_price) || 0;
    const regularPrice = parseFloat(product.regular_price) || 0;
    const onSale = product.sale_price && parseFloat(product.sale_price) < regularPrice;

    return {
      price: Math.max(0, basePrice + delta),
      comparePrice: onSale ? Math.max(0, regularPrice + delta) : null,
      onSale,
      discountPercent: onSale ? Math.round(((regularPrice - basePrice) / regularPrice) * 100) : 0
    };
  }, [product, selectedVariants, variantGroups, colors, sizes, weights]);

  // Review Form
  const reviewForm = useForm({
    author_name: auth?.user?.name || '',
    rating: 5,
    title: '',
    body: ''
  });

  const submitReview = (e) => {
    e.preventDefault();
    reviewForm.post(`/product/${product.slug || product.id}/reviews`, {
      preserveScroll: true,
      onSuccess: () => {
        reviewForm.reset('title', 'body');
        setActiveTab('rev');
      }
    });
  };

  const handleAddToCart = () => {
    const variant = getVariantString();

    trackAddToCart(
      { id: product.id, name: product.name, price: priceData.price, category: product.category?.name },
      qty,
      variant || null
    );

    router.post('/cart/add', {
      product_id: product.id,
      variant: variant || null,
      qty
    }, { preserveScroll: true });
  };

  const handleBuyNow = () => {
    const variant = getVariantString();

    trackAddToCart(
      { id: product.id, name: product.name, price: priceData.price, category: product.category?.name },
      qty,
      variant || null
    );

    router.post('/cart/add', {
      product_id: product.id,
      variant: variant || null,
      qty
    }, { preserveScroll: true });
  };

  const inStock = product.stock_quantity > 0;
  const mainImageUrl = imageUrl(activeImage, product.name);
  const rating = Math.max(0, Math.min(5, Math.round(parseFloat(product.rating) || 0)));

  // Track product view once on mount
  useEffect(() => {
    trackViewItem({
      id: product.id,
      name: product.name,
      price: priceData.price,
      category: product.category?.name,
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [product.id]);

  if (isTemplateOne) {
    const description = product.description || product.short_description || 'No description available.';
    const descriptionMarkup = formatDescriptionHtml(description);
    return (
      <StorefrontLayout>
        <Head title={seo?.title || product.name} />
        <div className="template-1-breadcrumb">
          <Link href="/">Home</Link> <span>&nbsp; / &nbsp;</span>
          {product.category && <><Link href={`/category/${product.category.slug}`}>{product.category.name}</Link> <span>&nbsp; / &nbsp;</span></>}
          {product.name}
        </div>
        <main className="template-1-detail">
          <div className="template-1-detail-grid">
            <div className="template-1-gallery">
              <div className="template-1-gallery-thumbs">
                {(product.images || []).map(image => (
                  <button key={image.id || image.path} type="button" className={activeImage === image.path ? 'is-active' : ''} onClick={() => selectImage(image.path)}>
                    <img src={imageUrl(image.path, product.name)} alt="" />
                  </button>
                ))}
              </div>
              <div className="template-1-gallery-main">
                {mainImageUrl && !imageError ? <img src={mainImageUrl} onError={handleMainImageError} alt={product.name} /> : <span>No image available</span>}
                {mainImageUrl && !imageError && <button type="button" onClick={() => window.open(mainImageUrl, '_blank', 'noopener,noreferrer')} aria-label="View larger image">&#8599;</button>}
              </div>
            </div>

            <div className="template-1-product-info">
              <h1>{product.name}</h1>
              <span className="template-1-product-price">
                {money(priceData.price)}
                {priceData.comparePrice !== null && <del>{money(priceData.comparePrice)}</del>}
              </span>
              <div className="template-1-product-rating" aria-label={`${rating} out of 5 stars`}>{'★'.repeat(rating)}<span>{'★'.repeat(5 - rating)}</span> <small>({product.reviews_count || 0})</small></div>
              <div className="template-1-product-description" dangerouslySetInnerHTML={{ __html: descriptionMarkup }} />

              {(variantGroups || []).map(group => (
                <div className="template-1-variant-row" key={group.name}>
                  <label htmlFor={`template-1-${group.name}`}>{group.name}</label>
                  <select id={`template-1-${group.name}`} value={selectedVariants[group.name] || ''} onChange={event => setSelectedVariants(prev => ({ ...prev, [group.name]: event.target.value }))}>
                    {(group.options || []).map(option => <option key={option.id || option.value} value={option.value}>{option.value}</option>)}
                  </select>
                </div>
              ))}

              <div className="template-1-buy-row">
                <div className="template-1-qty">
                  <button type="button" onClick={() => setQty(value => Math.max(1, value - 1))} aria-label="Decrease quantity">-</button>
                  <output>{qty}</output>
                  <button type="button" onClick={() => setQty(value => value + 1)} aria-label="Increase quantity">+</button>
                </div>
                <div className="template-1-buy-actions">
                  <button
                    type="button"
                    disabled={!inStock}
                    onClick={handleAddToCart}
                    className={`${productButtonClass} template-1-reference-action-button`}
                    style={{
                      backgroundColor: '#717fe0',
                      color: '#ffffff',
                      boxShadow: '0 4px 6px rgba(0, 0, 0, 0.18)'
                    }}
                  >
                    <svg className="w-4 h-4 sm:w-[18px] sm:h-[18px] shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>{addCartText}</span>
                  </button>
                  <button
                    type="button"
                    disabled={!inStock}
                    onClick={handleBuyNow}
                    className={`${productButtonClass} template-1-reference-action-button`}
                    style={{
                      backgroundColor: '#717fe0',
                      color: '#ffffff',
                      boxShadow: '0 4px 6px rgba(0, 0, 0, 0.18)'
                    }}
                  >
                    <svg className="w-4 h-4 sm:w-[18px] sm:h-[18px] shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>{buyText}</span>
                  </button>
                </div>
              </div>
              {!inStock && <p className="template-1-stock-note">Out of stock</p>}
            </div>
          </div>

          <section className="template-1-detail-tabs">
            <div className="template-1-detail-tab-list">
              <button type="button" className={activeTab === 'desc' ? 'is-active' : ''} onClick={() => setActiveTab('desc')}>Description</button>
              <button type="button" className={activeTab === 'info' ? 'is-active' : ''} onClick={() => setActiveTab('info')}>Additional information</button>
              <button type="button" className={activeTab === 'rev' ? 'is-active' : ''} onClick={() => setActiveTab('rev')}>Reviews ({reviews?.total || 0})</button>
            </div>
            {activeTab === 'desc' && <div className="template-1-detail-tab-content template-1-richtext" dangerouslySetInnerHTML={{ __html: descriptionMarkup }} />}
            {activeTab === 'info' && (
              <table className="template-1-spec-table"><tbody>
                {product.brand && <tr><td>Brand</td><td>{product.brand}</td></tr>}
                {product.sku && <tr><td>SKU</td><td>{product.sku}</td></tr>}
                {specRows.map((row, index) => <tr key={index}><td>{row.label}</td><td>{row.value}</td></tr>)}
              </tbody></table>
            )}
            {activeTab === 'rev' && (
              <div className="template-1-review-list">
                {(reviews?.data || []).length === 0 ? <p>No reviews yet. Be the first to review this product.</p> : reviews.data.map(review => (
                  <article key={review.id}>
                    <div className="template-1-product-rating">{'★'.repeat(review.rating)}<span>{'★'.repeat(5 - review.rating)}</span> <small>{review.author_name}</small></div>
                    {review.title && <strong>{review.title}</strong>}
                    <p>{review.body}</p>
                  </article>
                ))}
              </div>
            )}
          </section>

          {related?.length > 0 && <section className="template-1-related"><h2>Related Products</h2><div className="template-1-related-grid">{related.slice(0, 4).map(item => <ProductCard key={item.id} product={item} />)}</div></section>}
        </main>
        <CodOrderModal open={showQuickOrder} onClose={() => setShowQuickOrder(false)} product={product} qty={qty} variant={getVariantString() || null} unitPrice={priceData.price} imageUrl={imageUrl} />
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title={seo?.title || product.name} />
      
      <main className="storefront-product-page max-w-[1440px] mx-auto w-full px-4 sm:px-5 py-6 overflow-x-hidden">
        {/* Breadcrumb */}
        <nav className="text-sm text-gray-500 mb-5 flex flex-wrap items-center gap-2">
          <Link href="/" className="hover:text-[#f15a24] transition-colors">Home</Link>
          <span className="text-gray-300">/</span>
          {product.category && (
            <>
              <Link href={`/category/${product.category.slug}`} className="hover:text-[#f15a24] transition-colors">{product.category.name}</Link>
              <span className="text-gray-300">/</span>
            </>
          )}
          <span className="text-gray-900 font-medium truncate max-w-[200px] sm:max-w-md">{product.name}</span>
        </nav>

        {/* Product Details Grid */}
        <div className="bg-white rounded-2xl border border-gray-100 p-4 sm:p-6 lg:p-8 grid lg:grid-cols-2 gap-8 lg:gap-12 shadow-sm mb-8">
          
          {/* Gallery */}
          <div className="flex flex-col-reverse md:flex-row gap-4 select-none min-w-0">
            
            {product.images?.length > 1 && (
              <div className="flex md:flex-col gap-3 overflow-x-auto md:overflow-y-auto pb-2 md:pb-0 md:pr-2 custom-scrollbar shrink-0 max-h-[500px] w-full md:w-auto min-w-0">
                {product.images.map(img => (
                  <button 
                    key={img.id}
                    type="button" 
                    onClick={() => selectImage(img.path)}
                    className={`relative rounded-xl border-2 shrink-0 overflow-hidden w-20 h-20 bg-white transition-all ${activeImage === img.path ? 'border-[#f15a24]' : 'border-gray-100 hover:border-[#f15a24]/50'}`}
                  >
                    <img 
                      src={imageUrl(img.path, product.name)} 
                      onError={(e) => {
                        e.target.style.display = 'none';
                        e.target.nextElementSibling.style.display = 'flex';
                      }}
                      className="w-full h-full object-contain p-1" 
                      alt="" 
                    />
                    <div className="hidden absolute inset-0 flex items-center justify-center text-gray-300">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                  </button>
                ))}
              </div>
            )}

            <div className="flex-1 relative rounded-2xl border border-gray-100 bg-white aspect-square flex items-center justify-center p-6 overflow-hidden group">
              {priceData.onSale && (
                <span className="absolute top-4 left-4 z-10 bg-[#f15a24] text-white text-xs font-black px-2.5 py-1 rounded-md shadow-sm shadow-[#f15a24]/30">
                  -{priceData.discountPercent}% OFF
                </span>
              )}
              {mainImageUrl && !imageError ? (
                <img 
                  src={mainImageUrl} 
                  onError={handleMainImageError}
                  className="w-full h-full object-contain object-center transform group-hover:scale-105 transition-transform duration-500" 
                  alt={product.name} 
                />
              ) : (
                <div className="w-full h-full flex flex-col items-center justify-center text-gray-300">
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                  <span className="text-sm mt-3 font-medium text-gray-400">No Image Available</span>
                </div>
              )}
            </div>
          </div>

          {/* Info */}
          <div className="flex flex-col min-w-0">
            <div className="mb-2 flex flex-wrap items-center gap-2">
              {inStock ? (
                <span className="inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-xs font-bold px-2.5 py-1 rounded-full border border-green-100">
                  <span className="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                  In Stock
                </span>
              ) : (
                <span className="inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-2.5 py-1 rounded-full border border-red-100">
                  <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                  Out of Stock
                </span>
              )}
              {product.is_free_shipping && (
                <span className="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full border border-emerald-200 shadow-2xs">
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                  Free Delivery (ফ্রি ডেলিভারি)
                </span>
              )}
            </div>
            <h1 className="text-2xl sm:text-[28px] font-semibold text-gray-800 tracking-tight leading-tight mb-2">{product.name}</h1>
            
            <div className="flex flex-wrap items-center gap-4 mb-2">
              <div className="flex items-center gap-1">
                <div className="flex text-amber-400 text-lg">
                  {'★'.repeat(rating)}<span className="text-gray-200">{'★'.repeat(5 - rating)}</span>
                </div>
                <span className="text-sm text-gray-500 ml-1 font-medium">({product.reviews_count || 0})</span>
              </div>
              {product.sku && (
                <>
                  <span className="text-gray-300 hidden sm:inline">|</span>
                  <span className="text-sm text-gray-500">SKU: <span className="font-semibold text-gray-900">{product.sku}</span></span>
                </>
              )}
            </div>

            <div className="flex items-baseline gap-3 mb-5 mt-3">
              <span className="text-3xl font-bold text-[#f15a24]">{money(priceData.price)}</span>
              {priceData.comparePrice !== null && (
                <span className="text-base text-gray-400 font-medium line-through decoration-gray-300">{money(priceData.comparePrice)}</span>
              )}
            </div>
            
            <hr className="border-gray-100 mb-6" />

            {product.short_description && (
              <div 
                className="mt-2 mb-6 text-gray-700 leading-relaxed text-sm prose prose-sm max-w-none [&_p]:mb-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:my-2 [&_strong]:text-gray-900 [&_blockquote]:border-l-4 [&_blockquote]:border-orange-400 [&_blockquote]:pl-3 [&_blockquote]:italic [&_h2]:text-lg [&_h2]:font-bold [&_h3]:text-base [&_h3]:font-bold"
                dangerouslySetInnerHTML={{ __html: product.short_description }}
              />
            )}

            {bulletSpecs.length > 0 && (
              <div className="bg-gray-50/50 rounded-xl p-5 border border-gray-100 mb-8">
                <ul className="text-sm text-gray-600 space-y-2">
                  {bulletSpecs.map((spec, i) => (
                    <li key={i} className="flex gap-2">
                      <span className="text-[#f15a24] mt-0.5">•</span>
                      <span>{spec}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Dynamic Variant Groups (Color with swatch/picker, Size, Custom options) */}
            {variantGroups && variantGroups.length > 0 ? (
              <div className="space-y-5 mt-auto border-t border-gray-100 pt-5">
                {variantGroups.map((grp) => {
                  const currentVal = selectedVariants[grp.name];
                  const isColor = grp.name.toLowerCase() === 'color';

                  return (
                    <div key={grp.name}>
                      <div className="flex items-center justify-between mb-2">
                        <p className="text-sm font-bold text-gray-900">{grp.name}</p>
                        <span className="text-sm text-gray-500 font-medium">{currentVal}</span>
                      </div>
                      <div className="flex flex-wrap gap-2.5">
                        {(grp.options || []).map(opt => {
                          const isSelected = currentVal === opt.value;
                          return (
                            <button
                              key={opt.id || opt.value}
                              type="button"
                              onClick={() => {
                                setSelectedVariants(prev => ({ ...prev, [grp.name]: opt.value }));
                                if (opt.image_path) {
                                  setActiveImage(opt.image_path);
                                }
                              }}
                              className={`px-3.5 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 border cursor-pointer ${
                                isSelected
                                  ? 'bg-[#f15a24] text-white shadow-md shadow-[#f15a24]/20 border-[#f15a24] ring-2 ring-orange-200'
                                  : 'bg-white border-gray-200 text-gray-700 hover:border-[#f15a24] hover:text-[#f15a24]'
                              }`}
                            >
                              {/* Color Swatch Circle for color attributes */}
                              {isColor && opt.color_code && (
                                <span
                                  className="w-3.5 h-3.5 rounded-full border border-black/15 shrink-0 shadow-2xs"
                                  style={{ backgroundColor: opt.color_code }}
                                />
                              )}
                              {/* Thumbnail image if available */}
                              {opt.image_path && (
                                <img
                                  src={imageUrl(opt.image_path, opt.value)}
                                  alt={opt.value}
                                  className="w-5 h-5 rounded-md object-cover border border-white/50 shrink-0"
                                />
                              )}
                              <span>{opt.value}</span>
                              {parseFloat(opt.price_delta) > 0 && (
                                <span className={`text-[11px] font-bold px-1.5 py-0.5 rounded-md ${
                                  isSelected ? 'bg-white/20 text-white' : 'bg-orange-50 text-orange-600'
                                }`}>
                                  +{money(opt.price_delta)}
                                </span>
                              )}
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : null}

              <div className="flex flex-col gap-4 pt-2">
                
                {/* Quantity and Actions */}
                <div className="flex flex-col gap-5 mt-2">
                  <div className="flex items-center gap-4">
                    <span className="text-[15px] font-medium text-gray-800">Quantity:</span>
                    <div className="flex items-center border border-gray-200 rounded-md overflow-hidden bg-white h-[42px] shrink-0 w-32">
                      <button 
                        type="button" 
                        onClick={() => setQty(Math.max(1, qty - 1))}
                        className="w-10 h-full flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors disabled:opacity-50"
                        disabled={!inStock}
                      >−</button>
                      <input
                        type="number"
                        min="1"
                        max="99"
                        value={qty}
                        onChange={(e) => setQty(Math.max(1, Math.min(99, parseInt(e.target.value) || 1)))}
                        className="flex-1 w-full h-full text-center border-0 bg-transparent p-0 font-medium text-gray-900 focus:ring-0"
                        disabled={!inStock}
                      />
                      <button 
                        type="button" 
                        onClick={() => setQty(Math.min(99, qty + 1))}
                        className="w-10 h-full flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors disabled:opacity-50"
                        disabled={!inStock}
                      >+</button>
                    </div>
                  </div>

                  <div className="flex flex-col gap-3">
                    {/* Free Delivery Banner */}
                    {product.is_free_shipping && (
                      <div className="flex items-center gap-2.5 px-3.5 py-2.5 bg-emerald-50/90 border border-emerald-200/80 rounded-xl text-emerald-800 text-xs sm:text-sm font-semibold">
                        <div className="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>
                        </div>
                        <span>এই প্রোডাক্টে রয়েছে <strong>ফ্রি হোম ডেলিভারি</strong> (সারা দেশে কোনো ডেলিভারি চার্জ নেই)!</span>
                      </div>
                    )}

                    {/* COD Quick Order Button — full width, prominent */}
                    {isCodEnabled && (
                      <button
                        type="button"
                        onClick={() => setShowQuickOrder(true)}
                        disabled={!inStock}
                        className="w-full h-[50px] sm:h-[54px] active:scale-[0.98] font-extrabold rounded-xl shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2.5 text-sm sm:text-base cursor-pointer hover:opacity-95"
                        style={{
                          backgroundColor: codBgColor,
                          color: codTextColor,
                          boxShadow: `0 10px 25px -5px ${codBgColor}40`
                        }}
                      >
                        <svg className="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        {codText}
                      </button>
                    )}

                    {/* Dynamic Grid for Actions: Add to Cart, Order Now, WhatsApp, Call */}
                    <div className="grid grid-cols-2 gap-2 sm:gap-3 w-full max-w-full">
                      <button
                        type="button"
                        onClick={handleAddToCart}
                        disabled={!inStock}
                        className="font-semibold rounded-md shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90 h-[40px] sm:h-[46px] flex items-center justify-center gap-1.5 sm:gap-2 px-1 sm:px-2 min-w-0 overflow-hidden cursor-pointer"
                        style={{
                          backgroundColor: addCartBgColor,
                          color: addCartTextColor
                        }}
                      >
                        <svg className="w-4 h-4 sm:w-5 sm:h-5 hidden sm:block shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span className="text-[10px] sm:text-[14px] leading-tight text-center truncate">{addCartText}</span>
                      </button>

                      <button
                        type="button"
                        onClick={handleBuyNow}
                        disabled={!inStock}
                        className="font-semibold rounded-md shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90 h-[40px] sm:h-[46px] flex items-center justify-center px-1 sm:px-2 min-w-0 overflow-hidden cursor-pointer"
                        style={{
                          backgroundColor: buyBgColor,
                          color: buyTextColor
                        }}
                      >
                        <span className="text-[10px] sm:text-[14px] leading-tight text-center truncate">{buyText}</span>
                      </button>

                      {isWhatsappEnabled && whatsappDigits && (
                        <a
                          href={`https://wa.me/${whatsappDigits}?text=${encodeURIComponent(`Hi, I want to order ${product.name}. Link: ${typeof window !== 'undefined' ? window.location.href : ''}`)}`}
                          target="_blank"
                          rel="noreferrer"
                          className={`font-semibold rounded-md shadow-sm transition-all hover:opacity-90 h-[40px] sm:h-[46px] flex items-center justify-center gap-1.5 sm:gap-2 px-1 sm:px-2 min-w-0 overflow-hidden ${!isCallEnabled ? 'col-span-2' : ''}`}
                          style={{
                            backgroundColor: whatsappBgColor,
                            color: whatsappTextColor
                          }}
                        >
                          <svg className="w-3.5 h-3.5 sm:w-5 sm:h-5 hidden sm:block shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                          <span className="text-[10px] sm:text-[14px] leading-tight text-center truncate">{whatsappText}</span>
                        </a>
                      )}

                      {isCallEnabled && (
                        <a
                          href={`tel:${callNumber}`}
                          className={`font-semibold rounded-md shadow-sm transition-all hover:opacity-90 h-[40px] sm:h-[46px] flex items-center justify-center gap-1.5 sm:gap-2 px-1 sm:px-2 min-w-0 overflow-hidden ${!isWhatsappEnabled ? 'col-span-2' : ''}`}
                          style={{
                            backgroundColor: callBgColor,
                            color: callTextColor
                          }}
                        >
                          <svg className="w-4 h-4 sm:w-5 sm:h-5 hidden sm:block shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>
                          <span className="text-[10px] sm:text-[14px] leading-tight text-center truncate">{callText}</span>
                        </a>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        {/* Tabs section */}
        <div className="bg-white rounded-2xl border border-gray-100 p-4 sm:p-6 lg:p-8 shadow-sm mb-8">
          <div className="border-b border-gray-200 flex gap-6 sm:gap-8 overflow-x-auto custom-scrollbar">
            <button 
              type="button" 
              onClick={() => setActiveTab('desc')}
              className={`py-4 -mb-px border-b-2 font-bold whitespace-nowrap transition-colors cursor-pointer ${activeTab === 'desc' ? 'border-[#f15a24] text-[#f15a24]' : 'border-transparent text-gray-500 hover:text-gray-900'}`}
            >
              {specRows.length > 0 ? 'Description & Specs' : 'Description'}
            </button>
            <button 
              type="button" 
              onClick={() => setActiveTab('rev')}
              className={`py-4 -mb-px border-b-2 font-bold whitespace-nowrap transition-colors cursor-pointer ${activeTab === 'rev' ? 'border-[#f15a24] text-[#f15a24]' : 'border-transparent text-gray-500 hover:text-gray-900'}`}
            >
              Reviews ({reviews?.total || 0})
            </button>
          </div>

          <div className="pt-8">
            {activeTab === 'desc' && (
              <div className={specRows.length > 0 ? "grid lg:grid-cols-3 gap-10" : "max-w-4xl"}>
                <div className={specRows.length > 0 ? "lg:col-span-2 min-w-0 break-words" : "w-full min-w-0 break-words"}>
                  <h3 className="text-xl font-extrabold text-gray-900 mb-6">Product Description</h3>
                  <div className="product-description-content text-gray-700 leading-relaxed max-w-none prose prose-slate [&_p]:mb-4 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:my-4 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:my-3 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:my-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-3 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-3 [&_li]:mb-1.5 [&_blockquote]:border-l-4 [&_blockquote]:border-orange-500 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-gray-600 [&_blockquote]:my-4 [&_a]:text-orange-600 [&_a]:underline [&_hr]:my-6 [&_hr]:border-gray-200">
                    <div dangerouslySetInnerHTML={{ __html: formatDescriptionHtml(product.description) }} />
                  </div>
                </div>
                
                {specRows.length > 0 && (
                  <div className="lg:col-span-1 min-w-0 break-words">
                    <div className="bg-gray-50 rounded-xl p-5 border border-gray-100 overflow-hidden">
                      <h3 className="text-lg font-extrabold text-gray-900 mb-4">Specifications</h3>
                      <table className="w-full text-sm">
                        <tbody className="divide-y divide-gray-200">
                          {product.brand && (
                            <tr>
                              <td className="py-3 text-gray-500 font-medium">Brand</td>
                              <td className="py-3 text-gray-900 font-semibold text-right">{product.brand}</td>
                            </tr>
                          )}
                          {product.sku && (
                            <tr>
                              <td className="py-3 text-gray-500 font-medium">SKU</td>
                              <td className="py-3 text-gray-900 font-semibold text-right">{product.sku}</td>
                            </tr>
                          )}
                          {specRows.map((row, i) => (
                            <tr key={i}>
                              <td className="py-3 text-gray-500 font-medium">{row.label}</td>
                              <td className="py-3 text-gray-900 font-semibold text-right">{row.value || '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </div>
            )}

            {activeTab === 'rev' && (
              <div className="grid lg:grid-cols-12 gap-10">
                <div className="lg:col-span-7 space-y-6">
                  {reviews?.data?.length === 0 ? (
                    <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-100">
                      <div className="text-4xl mb-3">⭐</div>
                      <p className="text-gray-900 font-bold">No reviews yet</p>
                      <p className="text-gray-500 text-sm mt-1">Be the first to review this product!</p>
                    </div>
                  ) : (
                    reviews.data.map(review => (
                      <article key={review.id} className="border-b border-gray-100 pb-6 last:border-0 last:pb-0">
                        <div className="flex items-center gap-2 mb-2">
                          <div className="flex text-amber-400 text-sm">
                            {'★'.repeat(review.rating)}<span className="text-gray-200">{'★'.repeat(5 - review.rating)}</span>
                          </div>
                          <span className="text-gray-400 text-xs font-medium">•</span>
                          <span className="text-gray-500 text-xs font-medium">{new Date(review.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                        </div>
                        {review.title && <h3 className="font-bold text-gray-900 mb-1">{review.title}</h3>}
                        <p className="text-gray-600 text-sm whitespace-pre-line leading-relaxed">{review.body}</p>
                        <p className="mt-3 text-xs font-bold text-gray-900">{review.author_name}</p>
                      </article>
                    ))
                  )}
                  
                  {reviews?.last_page > 1 && (
                    <nav className="flex justify-center pt-4">
                      <ul className="flex items-center gap-1">
                        {reviews.links.map((link, idx) => {
                          const isPrevOrNext = link.label.includes('Previous') || link.label.includes('Next');
                          const label = link.label.replace('&laquo; Previous', 'Prev').replace('Next &raquo;', 'Next');
                          
                          if (!link.url) return null;

                          return (
                            <li key={idx}>
                              <button 
                                type="button"
                                onClick={(e) => {
                                  e.preventDefault();
                                  router.get(link.url, {}, { preserveScroll: true });
                                }}
                                className={`px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                                  link.active 
                                    ? 'bg-[#f15a24] text-white' 
                                    : 'bg-white border border-gray-200 text-gray-700 hover:border-[#f15a24] hover:text-[#f15a24]'
                                }`}
                              >
                                {label}
                              </button>
                            </li>
                          );
                        })}
                      </ul>
                    </nav>
                  )}
                </div>

                <div className="lg:col-span-5">
                  <div className="bg-gray-50 rounded-xl p-6 border border-gray-100 sticky top-24">
                    <h3 className="font-extrabold text-gray-900 text-lg mb-1">Write a Review</h3>
                    <p className="text-sm text-gray-500 mb-6">Share your thoughts with other customers</p>
                    
                    {!auth?.user ? (
                      <div className="text-center">
                        <Link href={`/login?redirect=/product/${product.slug}#reviews`} className="block w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-bold px-5 py-3 rounded-xl transition-colors">
                          Sign in to review
                        </Link>
                      </div>
                    ) : !canReview ? (
                      <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-700 font-medium">
                        You have already submitted a review for this product. Thank you!
                      </div>
                    ) : (
                      <form onSubmit={submitReview} className="space-y-4">
                        <div>
                          <label className="block text-sm font-bold text-gray-900 mb-1.5">Your Name <span className="text-gray-400 font-normal">(Optional)</span></label>
                          <input 
                            type="text" 
                            value={reviewForm.data.author_name}
                            onChange={e => reviewForm.setData('author_name', e.target.value)}
                            placeholder={auth?.user?.name || "Your display name"}
                            className="w-full border-gray-200 rounded-lg text-sm focus:ring-[#f15a24] focus:border-[#f15a24] placeholder-gray-400" 
                          />
                          {reviewForm.errors.author_name && (
                            <p className="text-xs text-red-500 mt-1 font-medium">{reviewForm.errors.author_name}</p>
                          )}
                        </div>
                        <div>
                          <label className="block text-sm font-bold text-gray-900 mb-1.5">Rating</label>
                          <select 
                            value={reviewForm.data.rating}
                            onChange={e => reviewForm.setData('rating', e.target.value)}
                            className="w-full border-gray-200 rounded-lg text-sm focus:ring-[#f15a24] focus:border-[#f15a24] bg-white font-medium"
                          >
                            {[5, 4, 3, 2, 1].map(num => (
                              <option key={num} value={num}>{num} Stars</option>
                            ))}
                          </select>
                          {reviewForm.errors.rating && (
                            <p className="text-xs text-red-500 mt-1 font-medium">{reviewForm.errors.rating}</p>
                          )}
                        </div>
                        <div>
                          <label className="block text-sm font-bold text-gray-900 mb-1.5">Title <span className="text-gray-400 font-normal">(Optional)</span></label>
                          <input 
                            type="text" 
                            value={reviewForm.data.title}
                            onChange={e => reviewForm.setData('title', e.target.value)}
                            placeholder="Sum up your experience"
                            className="w-full border-gray-200 rounded-lg text-sm focus:ring-[#f15a24] focus:border-[#f15a24] placeholder-gray-400" 
                          />
                          {reviewForm.errors.title && (
                            <p className="text-xs text-red-500 mt-1 font-medium">{reviewForm.errors.title}</p>
                          )}
                        </div>
                        <div>
                          <label className="block text-sm font-bold text-gray-900 mb-1.5">Review</label>
                          <textarea 
                            rows="4" 
                            required 
                            value={reviewForm.data.body}
                            onChange={e => reviewForm.setData('body', e.target.value)}
                            placeholder="What did you like or dislike?"
                            className="w-full border-gray-200 rounded-lg text-sm focus:ring-[#f15a24] focus:border-[#f15a24] placeholder-gray-400 resize-none"
                          ></textarea>
                          {reviewForm.errors.body && (
                            <p className="text-xs text-red-500 mt-1 font-medium">{reviewForm.errors.body}</p>
                          )}
                        </div>
                        <button 
                          type="submit" 
                          disabled={reviewForm.processing}
                          className="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold py-3 rounded-xl transition-colors disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer"
                        >
                          {reviewForm.processing ? 'Submitting...' : 'Submit Review'}
                        </button>
                      </form>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Related Products */}
        {related?.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-2xl font-extrabold text-gray-900 tracking-tight">You Might Also Like</h2>
              <Link href={`/category/${product.category?.slug}`} className="text-sm font-bold text-[#f15a24] hover:text-[#d94a1a] transition-colors">
                View more <span aria-hidden="true">&rarr;</span>
              </Link>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 lg:gap-5">
              {related.map(rel => (
                <ProductCard key={rel.id} product={rel} />
              ))}
            </div>
          </div>
        )}
      </main>

      <CodOrderModal
        open={showQuickOrder}
        onClose={() => setShowQuickOrder(false)}
        product={product}
        qty={qty}
        variant={getVariantString() || null}
        unitPrice={priceData.price}
        imageUrl={imageUrl}
      />
    </StorefrontLayout>
  );
}
