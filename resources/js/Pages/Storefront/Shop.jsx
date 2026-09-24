import { useEffect, useRef, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, router } from '@inertiajs/react';
import ProductCard from '@/Components/Storefront/ProductCard';

export default function ShopPage({ 
  products, 
  activeCategory, 
  categories, 
  allProductsCount, 
  brands, 
  variantFilters = [],
  sort, 
  minRating, 
  q, 
  priceCeiling,
  app,
  seo
}) {
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [loadedProducts, setLoadedProducts] = useState(products);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';

  const maxPrice = Math.max(1000, parseInt(priceCeiling || 100000));
  
  // Get query params from current URL
  const urlParams = new URLSearchParams(window.location.search);
  const minVal = urlParams.get('min') || '';
  const maxVal = urlParams.get('max') || '';
  const activeBrand = urlParams.get('brand') || '';
  const onSale = urlParams.get('on_sale') === '1' || urlParams.get('flash') === '1';
  const inStock = urlParams.get('in_stock') === '1';
  const outOfStock = urlParams.get('out_of_stock') === '1';
  const isFeaturedView = urlParams.get('featured') === '1';
  const isNewView = urlParams.get('new') === '1';
  const isBestView = urlParams.get('best') === '1';
  const activeCollection = isFeaturedView
    ? 'Featured'
    : isNewView
      ? 'New Arrivals'
      : isBestView
        ? 'Best Sellers'
        : '';
  const isCollectionView = Boolean(activeCollection);
  const title = activeCategory
    ? activeCategory.name
    : q ? `Search: ${q}` : activeCollection || 'Shop All Products';

  const formAction = activeCategory ? `/category/${activeCategory.slug}` : '/shop';

  // Keep the accumulated list only while the customer stays in the same
  // catalogue view. A new search, filter, sort, or category starts over with
  // the server's first page.
  const listingParams = new URLSearchParams(window.location.search);
  listingParams.delete('page');
  const listingKey = `${window.location.pathname}?${listingParams.toString()}`;
  const listingKeyRef = useRef(listingKey);

  useEffect(() => {
    if (listingKeyRef.current === listingKey) return;

    listingKeyRef.current = listingKey;
    setLoadedProducts(products);
    setIsLoadingMore(false);
  }, [listingKey, products]);

  const loadMoreProducts = () => {
    if (isLoadingMore || !loadedProducts.next_page_url) return;

    setIsLoadingMore(true);
    router.get(loadedProducts.next_page_url, {}, {
      only: ['products'],
      preserveState: true,
      preserveScroll: true,
      // Loading another batch should not turn the address into ?page=2. The
      // current filtered catalogue remains shareable and reload-safe.
      preserveUrl: true,
      onSuccess: (page) => {
        const nextProducts = page.props.products;
        if (!nextProducts) return;

        setLoadedProducts(currentProducts => {
          const existingIds = new Set(currentProducts.data.map(product => product.id));

          return {
            ...nextProducts,
            data: [
              ...currentProducts.data,
              ...nextProducts.data.filter(product => !existingIds.has(product.id)),
            ],
          };
        });
      },
      onFinish: () => setIsLoadingMore(false),
    });
  };

  const loadMoreButton = loadedProducts.next_page_url && (
    <div className="mt-[45px] flex justify-center">
      <button
        type="button"
        onClick={loadMoreProducts}
        disabled={isLoadingMore}
        className="inline-flex h-[46px] min-w-[179px] items-center justify-center rounded-[23px] bg-[#e6e6e6] px-[15px] text-[15px] font-medium uppercase leading-[1.466667] text-[#333] transition-colors duration-300 hover:bg-[#222] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#222] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
      >
        {isLoadingMore ? 'LOADING...' : 'LOAD MORE'}
      </button>
    </div>
  );

  const productGridClasses = {
    2: 'grid-cols-2 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2',
    3: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3',
    4: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5',
    6: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6',
  };
  const configuredProductPerRow = Number(app?.settings?.[isTemplateOne ? 'template_1_product_per_row' : 'template_2_product_per_row'] || 5);
  const productGridClass = productGridClasses[configuredProductPerRow] || productGridClasses[5];
  const collectionHiddenInputs = (
    <>
      {isFeaturedView && <input type="hidden" name="featured" value="1" />}
      {isNewView && <input type="hidden" name="new" value="1" />}
      {isBestView && <input type="hidden" name="best" value="1" />}
    </>
  );
  const activeVariantValues = (type) => urlParams.getAll(`variants[${type}][]`);
  const activeFilterCount = [
    'min',
    'max',
    'brand',
    'on_sale',
    'in_stock',
    'out_of_stock',
    'min_rating',
  ].filter(key => urlParams.get(key)).length + [...urlParams.keys()]
    .filter(key => key.startsWith('variants[')).length;
  const hasActiveFilters = activeFilterCount > 0;

  const handleFilterSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams();
    
    formData.forEach((value, key) => {
      if (value !== '') {
        params.append(key, value);
      }
    });

    router.get(`${formAction}?${params.toString()}`, {}, { preserveState: true });
    setIsFilterOpen(false);
  };

  const handleSortChange = (e) => {
    const newSort = e.target.value;
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.delete('page');
    currentParams.set('sort', newSort || 'popular');
    
    router.get(`${formAction}?${currentParams.toString()}`, {}, { preserveState: true });
  };

  const buildFilterUrl = (key, value) => {
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.delete('page');
    if (value === null) {
      currentParams.delete(key);
    } else {
      currentParams.set(key, value);
    }
    return `${formAction}?${currentParams.toString()}`;
  };

  if (isTemplateOne) {
    return (
      <StorefrontLayout title={title} app={app} categories={categories}>
        <Head title={seo?.title || title} />

        <main className="storefront-shop-page template-1-shop template-1-container min-w-0">
          <div className="template-1-shop-toolbar">
            <div className="template-1-shop-actions">
              <button type="button" onClick={() => setIsFilterOpen(open => !open)} className={isFilterOpen ? 'is-active' : ''}>
                <svg aria-hidden="true" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                Filter
                {hasActiveFilters && <span className="template-1-filter-count">{activeFilterCount}</span>}
              </button>
              {hasActiveFilters && <Link href={formAction} className="template-1-clear-filters">Clear filters</Link>}
            </div>
            <div className="template-1-shop-nav-row">
              <div className="template-1-filter-row" role="tablist" aria-label="Shop categories">
                <Link href="/shop" className={!activeCategory && !q && !isCollectionView ? 'is-active' : ''}>All Products</Link>
                <Link href="/shop?featured=1" className={isFeaturedView ? 'is-active' : ''}>Featured</Link>
                <Link href="/shop?new=1" className={isNewView ? 'is-active' : ''}>New Arrivals</Link>
                <Link href="/shop?best=1" className={isBestView ? 'is-active' : ''}>Best Sellers</Link>
              </div>
              <label className="template-1-shop-sort">
                <span>Order by</span>
                <select value={sort || 'popular'} onChange={handleSortChange} aria-label="Order products">
                  <option value="popular">Popular</option>
                  <option value="newest">Newest First</option>
                  <option value="price_low">Price: Low to High</option>
                  <option value="price_high">Price: High to Low</option>
                  <option value="rating">Top Rated</option>
                </select>
              </label>
            </div>
          </div>

          {isFilterOpen && (
            <>
              <button
                type="button"
                className="template-1-filter-backdrop"
                onClick={() => setIsFilterOpen(false)}
                aria-label="Close filters"
              />
              <form onSubmit={handleFilterSubmit} className="template-1-filter-panel template-1-filter-drawer" role="dialog" aria-modal="true" aria-label="Product filters">
                <div className="template-1-filter-drawer-header">
                  <h2>Filters</h2>
                  <button type="button" onClick={() => setIsFilterOpen(false)} aria-label="Close filters">
                    <svg aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 6l12 12M18 6 6 18" /></svg>
                  </button>
                </div>
              {minRating && <input type="hidden" name="min_rating" value={minRating} />}
              {q && <input type="hidden" name="q" value={q} />}
              {collectionHiddenInputs}

              <div className="template-1-filter-categories">
                <h3>Categories</h3>
                <div className="template-1-filter-category-list">
                  <Link href="/shop" className={!activeCategory && !isCollectionView ? 'is-active' : ''}>
                    <span>All Products</span>
                    <span>{allProductsCount || categories.reduce((acc, cat) => acc + cat.products_count, 0)}</span>
                  </Link>
                  {categories.map(cat => (
                    <Link key={cat.id} href={`/category/${cat.slug}`} className={activeCategory?.id === cat.id ? 'is-active' : ''}>
                      <span>{cat.name}</span>
                      <span>{cat.products_count}</span>
                    </Link>
                  ))}
                </div>
              </div>

              <div className="flex-1 min-w-[200px]">
                <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Price</h3>
                <div className="flex items-center gap-2">
                  <div className="flex items-center flex-1 border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#717fe0] focus-within:ring-1 focus-within:ring-[#717fe0] transition-all bg-white">
                    <span className="pl-3 text-xs text-gray-400 font-medium">৳</span>
                    <input type="number" name="min" min="0" max={maxPrice} defaultValue={minVal} placeholder="Min" className="w-full min-w-0 px-2 py-2 text-sm focus:outline-none bg-transparent" />
                  </div>
                  <span className="text-gray-400 shrink-0">–</span>
                  <div className="flex items-center flex-1 border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#717fe0] focus-within:ring-1 focus-within:ring-[#717fe0] transition-all bg-white">
                    <span className="pl-3 text-xs text-gray-400 font-medium">৳</span>
                    <input type="number" name="max" min="0" max={maxPrice} defaultValue={maxVal} placeholder="Max" className="w-full min-w-0 px-2 py-2 text-sm focus:outline-none bg-transparent" />
                  </div>
                </div>
              </div>

              {brands?.length > 0 && (
                <div className="flex-1 min-w-[200px]">
                  <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Brand</h3>
                  <select name="brand" defaultValue={activeBrand} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#717fe0] focus:ring-1 focus:ring-[#717fe0] bg-white">
                    <option value="">All brands</option>
                    {brands.map(brand => <option key={brand} value={brand}>{brand}</option>)}
                  </select>
                </div>
              )}

              <div className="flex-1 min-w-[200px]">
                <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Availability</h3>
                <div className="space-y-3">
                  <label className="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="on_sale" value="1" defaultChecked={onSale} className="w-4 h-4 text-[#717fe0] border-gray-300 rounded focus:ring-[#717fe0]" />
                    <span className="text-gray-600 group-hover:text-[#717fe0]">On Sale</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="in_stock" value="1" defaultChecked={inStock} className="w-4 h-4 text-[#717fe0] border-gray-300 rounded focus:ring-[#717fe0]" />
                    <span className="text-gray-600 group-hover:text-[#717fe0]">In Stock</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="out_of_stock" value="1" defaultChecked={outOfStock} className="w-4 h-4 text-[#717fe0] border-gray-300 rounded focus:ring-[#717fe0]" />
                    <span className="text-gray-600 group-hover:text-[#717fe0]">Out of Stock</span>
                  </label>
                </div>
              </div>

              {variantFilters?.length > 0 && variantFilters.map(group => (
                <div key={group.type} className="flex-1 min-w-[200px]">
                  <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">{group.type}</h3>
                  <div className="space-y-3 max-h-40 overflow-y-auto pr-1">
                    {group.options.map(option => (
                      <label key={option.value} className="flex items-center justify-between gap-2 cursor-pointer group">
                        <span className="flex items-center gap-2 min-w-0">
                          <input
                            type="checkbox"
                            name={`variants[${group.type}][]`}
                            value={option.value}
                            defaultChecked={activeVariantValues(group.type).includes(option.value)}
                            className="w-4 h-4 text-[#717fe0] border-gray-300 rounded focus:ring-[#717fe0]"
                          />
                          <span className="text-gray-600 group-hover:text-[#717fe0] truncate">{option.value}</span>
                        </span>
                        <span className="text-xs text-gray-400">{option.products_count}</span>
                      </label>
                    ))}
                  </div>
                </div>
              ))}

              <div className="template-1-filter-buttons">
                <Link href={formAction}>Clear</Link>
                <button type="submit">Apply Filter</button>
              </div>
              </form>
            </>
          )}

          <p className="template-1-result-count">{loadedProducts.total} products found</p>

          {loadedProducts.data.length === 0 ? (
            <div className="template-1-empty">
              <h2>No products found</h2>
              <p>Try adjusting your filters or search term.</p>
              <Link href="/shop">Clear all filters</Link>
            </div>
          ) : (
            <>
              <div className={`grid ${productGridClass} gap-7 sm:gap-8 lg:gap-x-7 lg:gap-y-12`}>
                {loadedProducts.data.map(product => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>

              {loadMoreButton}
            </>
          )}
        </main>
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title={seo?.title || title} />
      
      <main className="storefront-shop-page max-w-[1440px] mx-auto px-4 sm:px-5 py-5 sm:py-6">
        <div className="flex flex-col lg:flex-row gap-5 lg:gap-6">
          
          {/* Overlay for mobile filter */}
          {isFilterOpen && (
            <div 
              className="fixed inset-0 bg-black/50 z-[70] lg:hidden"
              onClick={() => setIsFilterOpen(false)}
            />
          )}

          {/* ===== FILTER SIDEBAR ===== */}
          <aside className={`fixed inset-y-0 left-0 z-[80] w-[300px] max-w-[90%] bg-white p-4 shadow-2xl transition-transform duration-300 overflow-y-auto lg:static lg:z-auto lg:w-[270px] lg:shrink-0 lg:translate-x-0 lg:overflow-visible lg:p-0 lg:shadow-none lg:bg-transparent lg:transition-none ${isFilterOpen ? 'translate-x-0' : '-translate-x-full'}`}>
            <form onSubmit={handleFilterSubmit} className="bg-white rounded-xl border border-gray-100 p-5 space-y-6 lg:sticky lg:top-24">
              
              {minRating && <input type="hidden" name="min_rating" value={minRating} />}
              {q && <input type="hidden" name="q" value={q} />}
              {collectionHiddenInputs}
              
              <div className="flex items-center justify-between lg:hidden border-b border-gray-100 pb-3 -mt-1">
                <span className="font-extrabold text-gray-900 text-lg">Filters</span>
                <button type="button" onClick={() => setIsFilterOpen(false)} className="grid h-8 w-8 place-items-center rounded-md bg-gray-50 text-gray-500 hover:bg-gray-100">
                  <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
              </div>

              {/* Categories */}
              <div>
                <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Categories</h3>
                <ul className="space-y-2 text-sm">
                  <li>
                    <Link href="/shop" className={`flex items-center justify-between group ${!activeCategory && !isCollectionView ? 'text-[#f15a24] font-bold' : 'text-gray-600 hover:text-[#f15a24]'}`}>
                      <span className="truncate">All Products</span>
                      <span className={`text-xs px-2 py-0.5 rounded-full ${!activeCategory && !isCollectionView ? 'bg-[#f15a24]/10 text-[#f15a24]' : 'bg-gray-100 text-gray-500 group-hover:bg-[#f15a24]/10 group-hover:text-[#f15a24]'}`}>
                        {allProductsCount || categories.reduce((acc, cat) => acc + cat.products_count, 0)}
                      </span>
                    </Link>
                  </li>
                  {categories.map((cat) => (
                    <li key={cat.id}>
                      <Link href={`/category/${cat.slug}`} className={`flex items-center justify-between group ${activeCategory?.id === cat.id ? 'text-[#f15a24] font-bold' : 'text-gray-600 hover:text-[#f15a24]'}`}>
                        <span className="truncate">{cat.name}</span>
                        <span className={`text-xs px-2 py-0.5 rounded-full ${activeCategory?.id === cat.id ? 'bg-[#f15a24]/10 text-[#f15a24]' : 'bg-gray-100 text-gray-500 group-hover:bg-[#f15a24]/10 group-hover:text-[#f15a24]'}`}>
                          {cat.products_count}
                        </span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="h-px bg-gray-100"></div>

              {/* Price Range */}
              <div>
                <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Price Range</h3>
                <div className="flex items-center gap-2">
                  <div className="flex items-center flex-1 border border-gray-200 rounded-lg overflow-hidden focus-within:border-[#f15a24] focus-within:ring-1 focus-within:ring-[#f15a24] transition-all">
                    <span className="pl-3 text-xs text-gray-400 font-medium">৳</span>
                    <input type="number" name="min" min="0" max={maxPrice} defaultValue={minVal} placeholder="Min" className="w-full min-w-0 px-2 py-2 text-sm focus:outline-none bg-transparent" />
                  </div>
                  <span className="text-gray-400 shrink-0">–</span>
                  <div className="flex items-center flex-1 border border-gray-200 rounded-lg overflow-hidden focus-within:border-[#f15a24] focus-within:ring-1 focus-within:ring-[#f15a24] transition-all">
                    <span className="pl-3 text-xs text-gray-400 font-medium">৳</span>
                    <input type="number" name="max" min="0" max={maxPrice} defaultValue={maxVal} placeholder="Max" className="w-full min-w-0 px-2 py-2 text-sm focus:outline-none bg-transparent" />
                  </div>
                </div>
              </div>

              <div className="h-px bg-gray-100"></div>

              {/* Average Rating */}
              <div>
                <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Average Rating</h3>
                <ul className="space-y-2.5 text-sm">
                  {[5, 4, 3, 2, 1].map((stars) => {
                    const isActive = minRating == stars;
                    return (
                      <li key={stars}>
                        <Link href={buildFilterUrl('min_rating', stars)} className={`flex items-center gap-2 text-gray-600 hover:text-[#f15a24] ${isActive ? 'font-bold text-[#f15a24]' : ''}`}>
                          <div className={`w-4 h-4 rounded border flex items-center justify-center transition-colors ${isActive ? 'bg-[#f15a24] border-[#f15a24]' : 'border-gray-300'}`}>
                            {isActive && <svg className="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="3"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>}
                          </div>
                          <div className="flex items-center gap-0.5">
                            <span className="text-amber-400 tracking-tight leading-none text-base">{'★'.repeat(stars)}</span>
                            <span className="text-gray-200 tracking-tight leading-none text-base">{'★'.repeat(5 - stars)}</span>
                          </div>
                          <span className="text-xs text-gray-500">& Up</span>
                        </Link>
                      </li>
                    );
                  })}
                  {minRating && (
                    <li className="pt-1">
                      <Link href={buildFilterUrl('min_rating', null)} className="text-xs font-semibold text-[#f15a24] hover:underline pl-6">Clear rating</Link>
                    </li>
                  )}
                </ul>
              </div>

              {/* Product Tags (brands) */}
              {brands?.length > 0 && (
                <>
                  <div className="h-px bg-gray-100"></div>
                  <div>
                    <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Brands</h3>
                    <ul className="space-y-2 max-h-48 overflow-y-auto text-sm pr-2 custom-scrollbar">
                      {brands.map((brand) => (
                        <li key={brand}>
                          <label className="flex items-center gap-2.5 cursor-pointer text-gray-600 hover:text-[#f15a24] group">
                            <input type="radio" name="brand" value={brand} defaultChecked={activeBrand === brand} className="w-4 h-4 text-[#f15a24] border-gray-300 focus:ring-[#f15a24]" />
                            <span className="truncate group-hover:font-medium">{brand}</span>
                          </label>
                        </li>
                      ))}
                    </ul>
                  </div>
                </>
              )}

              <div className="h-px bg-gray-100"></div>

              {/* Product Status */}
              <div>
                <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">Availability</h3>
                <ul className="space-y-2.5 text-sm">
                  <li>
                    <label className="flex items-center gap-2.5 cursor-pointer text-gray-600 hover:text-[#f15a24] group">
                      <input type="checkbox" name="on_sale" value="1" defaultChecked={onSale} className="w-4 h-4 rounded text-[#f15a24] border-gray-300 focus:ring-[#f15a24]" />
                      <span className="group-hover:font-medium">On Sale</span>
                    </label>
                  </li>
                  <li>
                    <label className="flex items-center gap-2.5 cursor-pointer text-gray-600 hover:text-[#f15a24] group">
                      <input type="checkbox" name="in_stock" value="1" defaultChecked={inStock} className="w-4 h-4 rounded text-[#f15a24] border-gray-300 focus:ring-[#f15a24]" />
                      <span className="group-hover:font-medium">In Stock</span>
                    </label>
                  </li>
                  <li>
                    <label className="flex items-center gap-2.5 cursor-pointer text-gray-600 hover:text-[#f15a24] group">
                      <input type="checkbox" name="out_of_stock" value="1" defaultChecked={outOfStock} className="w-4 h-4 rounded text-[#f15a24] border-gray-300 focus:ring-[#f15a24]" />
                      <span className="group-hover:font-medium">Out of Stock</span>
                    </label>
                  </li>
                </ul>
              </div>

              {variantFilters?.length > 0 && variantFilters.map(group => (
                <div key={group.type}>
                  <div className="h-px bg-gray-100 mb-6"></div>
                  <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wider">{group.type}</h3>
                  <ul className="space-y-2 max-h-48 overflow-y-auto text-sm pr-2 custom-scrollbar">
                    {group.options.map(option => (
                      <li key={option.value}>
                        <label className="flex items-center justify-between gap-2.5 cursor-pointer text-gray-600 hover:text-[#f15a24] group">
                          <span className="flex items-center gap-2.5 min-w-0">
                            <input
                              type="checkbox"
                              name={`variants[${group.type}][]`}
                              value={option.value}
                              defaultChecked={activeVariantValues(group.type).includes(option.value)}
                              className="w-4 h-4 rounded text-[#f15a24] border-gray-300 focus:ring-[#f15a24]"
                            />
                            <span className="truncate group-hover:font-medium">{option.value}</span>
                          </span>
                          <span className="text-xs text-gray-400">{option.products_count}</span>
                        </label>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}

              <div className="pt-2 space-y-3 sticky bottom-0 bg-white border-t border-gray-50 -mx-5 px-5 pt-4 mt-6">
                <button type="submit" className="w-full rounded-full bg-[#f15a24] text-white text-sm font-bold py-3 hover:bg-[#d94a1a] transition shadow-md shadow-[#f15a24]/20">
                  Apply Filters
                </button>
                <Link href={formAction} className="block w-full text-center text-sm font-semibold text-gray-500 hover:text-gray-900 transition py-1">
                  Reset All
                </Link>
              </div>
            </form>
          </aside>

          {/* ===== RESULTS ===== */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between mb-4 lg:hidden">
              <button type="button" onClick={() => setIsFilterOpen(true)} className="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                <svg className="h-4 w-4 text-[#f15a24]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                Filters
              </button>
              <p className="text-sm text-gray-500"><span className="font-bold text-gray-900">{products.total}</span> items</p>
            </div>

            <div className="bg-white rounded-xl border border-gray-100 p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
              <div>
                <h1 className="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight truncate">
                  {title}
                </h1>
                <p className="text-sm text-gray-500 mt-1 hidden lg:block"><span className="font-bold text-gray-900">{products.total}</span> products found</p>
              </div>
              <div className="shrink-0 w-full sm:w-auto">
                <select 
                  value={sort || 'popular'}
                  onChange={handleSortChange} 
                  className="w-full sm:w-48 border-gray-200 rounded-lg text-sm px-3 py-2 focus:ring-[#f15a24] focus:border-[#f15a24] bg-gray-50 cursor-pointer font-medium text-gray-700"
                >
                  <option value="popular">Sort: Popular</option>
                  <option value="newest">Newest First</option>
                  <option value="price_low">Price: Low to High</option>
                  <option value="price_high">Price: High to Low</option>
                  <option value="rating">Top Rated</option>
                </select>
              </div>
            </div>

            {loadedProducts.data.length === 0 ? (
              <div className="bg-white rounded-xl border border-dashed border-gray-200 p-12 sm:p-20 text-center text-gray-500 flex flex-col items-center">
                <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                  <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" /></svg>
                </div>
                <h3 className="text-lg font-bold text-gray-900 mb-1">No products found</h3>
                <p className="text-sm mb-6">Try adjusting your filters or search term to find what you're looking for.</p>
                <Link href="/shop" className="inline-flex bg-[#f15a24]/10 text-[#f15a24] font-bold text-sm px-6 py-2.5 rounded-full hover:bg-[#f15a24] hover:text-white transition-colors">
                  Clear all filters
                </Link>
              </div>
            ) : (
              <>
                <div className={`grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`}>
                  {loadedProducts.data.map(product => (
                    <ProductCard key={product.id} product={product} />
                  ))}
                </div>

                {loadMoreButton}
              </>
            )}
          </div>
        </div>
      </main>
    </StorefrontLayout>
  );
}
