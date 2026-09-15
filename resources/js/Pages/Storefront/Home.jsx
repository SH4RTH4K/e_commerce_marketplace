import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import ProductCard from '@/Components/Storefront/ProductCard';
import { imageUrl } from '@/lib/utils';
import CategoryIcon from '@/Components/Storefront/CategoryIcon';

function hexToRgba(hex, opacity) {
  const value = String(hex || '').replace('#', '');
  if (!/^[0-9a-f]{6}$/i.test(value)) return `rgba(31, 36, 48, ${opacity})`;
  const number = parseInt(value, 16);
  return `rgba(${(number >> 16) & 255}, ${(number >> 8) & 255}, ${number & 255}, ${opacity})`;
}

export default function HomePage({ 
  heroBanners, 
  middleBanners,
  features, 
  featuredCategories, 
  coupons, 
  flashProducts, 
  trending, 
  bestSellers, 
  newArrivals,
  homeOverviewHasMore = {},
  app 
}) {
  const ctaDefault = app?.settings?.default_cta_text || 'Shop now';
  const viewMore = app?.settings?.home_view_more_label || 'View all';
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';
  const defaultTemplateOneSlides = [
    {
      id: 'template-1-slide-01',
      title: 'NEW SEASON',
      subtitle: 'Women Collection 2018',
      image: '/templates/template-1/images/slide-01.jpg',
      link: '/shop',
    },
    {
      id: 'template-1-slide-02',
      title: 'Jackets & Coats',
      subtitle: 'Men New-Season',
      image: '/templates/template-1/images/slide-02.jpg',
      link: '/shop',
    },
    {
      id: 'template-1-slide-03',
      title: 'New arrivals',
      subtitle: 'Men Collection 2018',
      image: '/templates/template-1/images/slide-03.jpg',
      link: '/shop',
    },
  ];
  const heroOverlayColor = app?.settings?.template_1_hero_overlay_color || '#ffffff';
  const heroOverlayOpacity = Math.max(0, Math.min(80, Number(app?.settings?.template_1_hero_overlay_opacity ?? 28))) / 100;
  const heroTextBackgroundColor = app?.settings?.template_1_hero_text_background_color || '#1f2430';
  const heroTextBackgroundOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_1_hero_text_background_opacity ?? 88))) / 100;
  const heroTextPosition = ['left', 'center', 'right'].includes(app?.settings?.template_1_hero_text_position)
    ? app.settings.template_1_hero_text_position
    : 'left';
  const categoryTitleColor = app?.settings?.template_1_category_title_color || '#ffffff';
  const categorySecondaryColor = app?.settings?.template_1_category_secondary_color || '#f5f7ff';
  const categoryOverlayColor = app?.settings?.template_1_category_overlay_color || '#1f2430';
  const categoryOverlayOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_1_category_overlay_opacity ?? 58))) / 100;
  const categoryHoverOverlayOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_1_category_hover_overlay_opacity ?? 82))) / 100;
  const categoryTitleSize = ['16px', '18px', '20px', '22px', '24px', '28px', '30px', '32px', '36px', '40px', '48px'].includes(app?.settings?.template_1_category_title_size)
    ? app.settings.template_1_category_title_size
    : '28px';
  const categoryTitleWeight = ['400', '500', '600', '700', '800', '900'].includes(String(app?.settings?.template_1_category_title_weight))
    ? String(app.settings.template_1_category_title_weight)
    : '700';
  const categoryTitleStyle = app?.settings?.template_1_category_title_style === 'italic' ? 'italic' : 'normal';
  const categoryTitleTransform = ['none', 'capitalize', 'uppercase', 'lowercase'].includes(app?.settings?.template_1_category_title_transform)
    ? app.settings.template_1_category_title_transform
    : 'none';
  const categoryTextAlign = ['left', 'center', 'right'].includes(app?.settings?.template_1_category_text_align)
    ? app.settings.template_1_category_text_align
    : 'left';
  const displayHeroBanners = isTemplateOne
    ? (heroBanners?.length > 0
      ? heroBanners.map((banner) => ({
          ...banner,
          button: banner.button || banner.button_text,
          link: banner.link || banner.link_url,
        }))
      : defaultTemplateOneSlides)
    : (heroBanners || []);
  const resolveHeroImage = (path, fallback) => path?.startsWith('/templates/') ? path : imageUrl(path, fallback);
  const templateOneBanners = featuredCategories?.length > 0 
    ? featuredCategories.map(cat => ({
        title: cat.name,
        subtitle: cat.description ? cat.description.substring(0, 25) : 'Shop Now',
        image: cat.image ? imageUrl(cat.image) : null,
        href: `/category/${cat.slug}`
      }))
    : [
        { title: 'Women', subtitle: 'Spring 2018', image: '/templates/template-1/images/banner-01.jpg', href: '/shop' },
        { title: 'Men', subtitle: 'Spring 2018', image: '/templates/template-1/images/banner-02.jpg', href: '/shop' },
        { title: 'Accessories', subtitle: 'New Trend', image: '/templates/template-1/images/banner-03.jpg', href: '/shop' },
      ];
  // Resolve dynamic grid class for template-1 banners
  const catPerRow = parseInt(app?.settings?.template_1_category_per_row || '3');
  let bannerGridCols = 'lg:grid-cols-3';
  if (catPerRow === 2) bannerGridCols = 'lg:grid-cols-2';
  if (catPerRow === 3) bannerGridCols = 'lg:grid-cols-3';
  if (catPerRow === 4) bannerGridCols = 'lg:grid-cols-4';
  if (catPerRow === 5) bannerGridCols = 'lg:grid-cols-5';

  const productGridClasses = {
    2: 'grid-cols-2 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2',
    3: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3',
    4: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5',
    6: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6',
  };
  const configuredProductPerRow = Number(app?.settings?.[isTemplateOne ? 'template_1_product_per_row' : 'template_2_product_per_row'] || 5);
  const productGridClass = productGridClasses[configuredProductPerRow] || productGridClasses[5];

  const uniqueProducts = (products) => products.filter((product, index, list) => list.findIndex(item => item.id === product.id) === index);
  const overviewProductsByTab = {
    all: uniqueProducts([
      ...(trending || []),
      ...(newArrivals || []),
      ...(bestSellers || []),
    ]),
    featured: trending || [],
    new: newArrivals || [],
    best: bestSellers || [],
  };
  const overviewCounts = {
    all: Math.max(1, Math.min(48, Number(app?.settings?.template_1_overview_all_count ?? 16))),
    featured: Math.max(1, Math.min(48, Number(app?.settings?.template_1_overview_featured_count ?? 12))),
    new: Math.max(1, Math.min(48, Number(app?.settings?.template_1_overview_new_count ?? 12))),
    best: Math.max(1, Math.min(48, Number(app?.settings?.template_1_overview_best_count ?? 12))),
  };

  const heroSliderRef = useRef(null);
  const catSliderRef = useRef(null);
  const heroCount = displayHeroBanners?.length || 0;
  const [activeHeroIndex, setActiveHeroIndex] = useState(0);
  const [activeOverview, setActiveOverview] = useState('all');
  const [overviewProductLists, setOverviewProductLists] = useState(() => Object.fromEntries(
    Object.entries(overviewProductsByTab).map(([tab, products]) => [
      tab,
      uniqueProducts(products).slice(0, overviewCounts[tab] || 16),
    ]),
  ));
  const [overviewHasMore, setOverviewHasMore] = useState(homeOverviewHasMore);
  const [loadingOverviewTab, setLoadingOverviewTab] = useState(null);
  const overviewProducts = overviewProductLists[activeOverview] || [];

  const overviewTabs = [
    ['all', 'All Products'],
    ['featured', 'Featured'],
    ['new', 'New Arrivals'],
    ['best', 'Best Sellers'],
  ];

  const loadMoreOverviewProducts = async (tab) => {
    if (loadingOverviewTab || !overviewHasMore[tab]) return;

    const displayedProducts = overviewProductLists[tab] || [];
    const search = new URLSearchParams({ tab });
    displayedProducts.forEach(product => search.append('exclude[]', product.id));
    setLoadingOverviewTab(tab);

    try {
      const response = await fetch(`/home/products/load-more?${search.toString()}`, {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error('Unable to load more products.');

      const { products, has_more: hasMore } = await response.json();
      setOverviewProductLists(currentLists => {
        const currentProducts = currentLists[tab] || [];
        const currentIds = new Set(currentProducts.map(product => product.id));

        return {
          ...currentLists,
          [tab]: [...currentProducts, ...products.filter(product => !currentIds.has(product.id))],
        };
      });
      setOverviewHasMore(current => ({ ...current, [tab]: Boolean(hasMore && products.length) }));
    } catch (error) {
      // Keep the button available so a temporary network error can be retried.
    } finally {
      setLoadingOverviewTab(null);
    }
  };

  const loadMoreButton = (tab) => overviewHasMore[tab] && (
    <div className="mt-[45px] flex justify-center">
      <button
        type="button"
        onClick={() => loadMoreOverviewProducts(tab)}
        disabled={loadingOverviewTab !== null}
        className="inline-flex h-[46px] min-w-[179px] items-center justify-center rounded-[23px] bg-[#e6e6e6] px-[15px] text-[15px] font-medium uppercase leading-[1.466667] text-[#333] transition-colors duration-300 hover:bg-[#222] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#222] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
      >
        {loadingOverviewTab === tab ? 'LOADING...' : 'LOAD MORE'}
      </button>
    </div>
  );

  const scrollToHeroSlide = (index) => {
    if (heroCount === 0) return;

    const targetIndex = ((index % heroCount) + heroCount) % heroCount;
    if (isTemplateOne) {
      setActiveHeroIndex(targetIndex);
      return;
    }

    const slider = heroSliderRef.current;
    if (!slider) return;
    slider.scrollTo({
      left: targetIndex * slider.clientWidth,
      behavior: 'smooth',
    });
    setActiveHeroIndex(targetIndex);
  };

  const moveHeroSlide = (direction) => {
    if (isTemplateOne) {
      setActiveHeroIndex(current => (current + direction + heroCount) % heroCount);
      return;
    }

    const slider = heroSliderRef.current;
    const visibleIndex = slider?.clientWidth
      ? Math.round(slider.scrollLeft / slider.clientWidth)
      : activeHeroIndex;

    scrollToHeroSlide(visibleIndex + direction);
  };

  useEffect(() => {
    setActiveHeroIndex(0);
    heroSliderRef.current?.scrollTo({ left: 0, behavior: 'auto' });
  }, [heroCount]);

  useEffect(() => {
    const slider = heroSliderRef.current;
    if (isTemplateOne || !slider || heroCount < 2) return undefined;

    const updateActiveSlide = () => {
      if (!slider.clientWidth) return;
      const index = Math.round(slider.scrollLeft / slider.clientWidth);
      setActiveHeroIndex(Math.max(0, Math.min(index, heroCount - 1)));
    };

    slider.addEventListener('scroll', updateActiveSlide, { passive: true });
    return () => slider.removeEventListener('scroll', updateActiveSlide);
  }, [heroCount, isTemplateOne]);

  useEffect(() => {
    if (!isTemplateOne || heroCount < 2) return undefined;

    const heroInterval = setInterval(() => {
      setActiveHeroIndex(current => (current + 1) % heroCount);
    }, 6000);

    return () => clearInterval(heroInterval);
  }, [heroCount, isTemplateOne]);

  useEffect(() => {
    if (isTemplateOne) return undefined;

    const heroInterval = setInterval(() => {
      const slider = heroSliderRef.current;
      if (slider && heroCount > 1 && slider.clientWidth) {
        const currentIndex = Math.round(slider.scrollLeft / slider.clientWidth);
        const nextIndex = (currentIndex + 1) % heroCount;
        slider.scrollTo({ left: nextIndex * slider.clientWidth, behavior: 'smooth' });
        setActiveHeroIndex(nextIndex);
      }
    }, 4000);

    const catInterval = setInterval(() => {
      if (catSliderRef.current && featuredCategories?.length > 0) {
        const { scrollLeft, scrollWidth, clientWidth } = catSliderRef.current;
        if (scrollLeft + clientWidth >= scrollWidth - 10) {
          catSliderRef.current.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          catSliderRef.current.scrollBy({ left: 200, behavior: 'smooth' });
        }
      }
    }, 3000);

    return () => {
      clearInterval(heroInterval);
      clearInterval(catInterval);
    };
  }, [heroCount, featuredCategories, isTemplateOne]);
  
  // Basic coupon styles array
  const couponStyles = [
    { bg: 'bg-[#f15a24]', btn: 'bg-white text-[#f15a24]' },
    { bg: 'bg-[#1f2430]', btn: 'bg-[#f15a24] text-white' },
    { bg: 'bg-rose-500', btn: 'bg-white text-rose-600' },
    { bg: 'bg-emerald-600', btn: 'bg-white text-emerald-700' },
  ];

  if (isTemplateOne) {
    return (
      <StorefrontLayout>
        <Head title="" />

        <section
          className={`template-1-hero storefront-hero-section template-1-hero-position-${heroTextPosition}`}
          style={{
            '--template-1-hero-overlay-color': heroOverlayColor,
            '--template-1-hero-overlay-opacity': heroOverlayOpacity,
            '--template-1-hero-text-background-color': heroTextBackgroundColor,
            '--template-1-hero-text-background-opacity': heroTextBackgroundOpacity,
          }}
        >
          <div className="template-1-hero-track">
            {displayHeroBanners.map((banner, index) => (
              <div
                key={banner.id || index}
                className={`template-1-hero-slide ${activeHeroIndex === index ? 'is-active' : ''}`}
                style={{ backgroundImage: `url(${resolveHeroImage(banner.image, banner.title)})` }}
                aria-hidden={activeHeroIndex !== index}
              >
                <div className="template-1-container template-1-hero-content">
                  <div className="template-1-hero-copy">
                    <p>{banner.subtitle}</p>
                    <h1>{banner.title}</h1>
                    <Link href={banner.link || '/shop'}>{banner.button || 'Shop Now'}</Link>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {heroCount > 1 && (
            <>
              <button type="button" onClick={() => moveHeroSlide(-1)} className="template-1-hero-arrow template-1-hero-prev" aria-label="Previous banner">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 5-7 7 7 7" /></svg>
              </button>
              <button type="button" onClick={() => moveHeroSlide(1)} className="template-1-hero-arrow template-1-hero-next" aria-label="Next banner">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.5 5 7 7-7 7" /></svg>
              </button>
            </>
          )}
        </section>

        <section
          className={`template-1-container storefront-section py-12 md:py-20 grid gap-[30px] grid-cols-1 sm:grid-cols-2 ${bannerGridCols}`}
          style={{
            '--template-1-category-overlay-background': hexToRgba(categoryOverlayColor, categoryOverlayOpacity),
            '--template-1-category-hover-overlay-background': hexToRgba(categoryOverlayColor, categoryHoverOverlayOpacity),
            '--template-1-category-title-color': categoryTitleColor,
            '--template-1-category-secondary-color': categorySecondaryColor,
            '--template-1-category-title-size': categoryTitleSize,
            '--template-1-category-title-weight': categoryTitleWeight,
            '--template-1-category-title-style': categoryTitleStyle,
            '--template-1-category-title-transform': categoryTitleTransform,
            '--template-1-category-text-align': categoryTextAlign,
            '--template-1-category-text-shadow': app?.settings?.template_1_category_text_shadow === false ? 'none' : '0 2px 8px rgb(0 0 0 / .32)',
          }}
        >
          {templateOneBanners.map(banner => (
            <Link key={banner.title} href={banner.href} className="template-1-banner-card">
              {banner.image ? (
                <img src={banner.image} alt={banner.title} />
              ) : (
                <div className="w-full aspect-[370/248] bg-white"></div>
              )}
              <span className="template-1-banner-overlay">
                <span>
                  <strong>{banner.title}</strong>
                  <em>{banner.subtitle}</em>
                </span>
                <b>Shop Now</b>
              </span>
            </Link>
          ))}
        </section>

        <section className="template-1-products storefront-section template-1-container">
          <div className="template-1-section-head">
            <h2>Product Overview</h2>
            <Link href="/shop">View all</Link>
          </div>

          <div className="template-1-filter-row template-1-overview-tabs" role="tablist" aria-label="Product overview views">
            {overviewTabs.map(([tab, label]) => (
              <button
                key={tab}
                type="button"
                role="tab"
                aria-selected={activeOverview === tab}
                className={`template-1-overview-tab ${activeOverview === tab ? 'is-active' : ''}`}
                onClick={() => setActiveOverview(tab)}
              >
                {label}
              </button>
            ))}
          </div>

          <div className={`grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`}>
            {overviewProducts.map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          {loadMoreButton(activeOverview)}
        </section>
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title="" />
      
      {/* Hero Section */}
      <section className="storefront-hero-section mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 pt-6 min-w-0">
        <div className={`flex flex-col gap-4 items-stretch`}>
          
          {/* Hero Slider Area */}
          <div className="grid grid-cols-1 lg:grid-cols-[2.5fr_1fr] gap-4 w-full">
            {/* Left Slider Container */}
            <div className="relative rounded-xl overflow-hidden min-h-[250px] sm:min-h-[400px] bg-gray-100 group">
              {/* Scrollable Area */}
              <div id="hero-slider" ref={heroSliderRef} className="absolute inset-0 flex snap-x snap-mandatory overflow-x-auto no-scrollbar scroll-smooth">
                 {displayHeroBanners?.length > 0 ? (
                   displayHeroBanners.map((banner, index) => (
                     <div key={index} className="relative w-full shrink-0 snap-center h-full flex flex-col justify-center">
                       {banner.image ? (
                         <img src={resolveHeroImage(banner.image, banner.title)} className="absolute inset-0 w-full h-full object-cover" alt={banner.title} />
                       ) : (
                         <div className="absolute inset-0 bg-gradient-to-r from-[#f15a24] to-[#f37c4f] mix-blend-overlay opacity-90"></div>
                       )}
                       <div className={`relative z-10 p-6 sm:p-12 max-w-lg ${isTemplateOne ? 'text-[#222] drop-shadow-none' : 'text-white drop-shadow-md hidden'}`}>
                          {isTemplateOne && banner.subtitle && <p className="text-xl md:text-2xl mb-3 font-light">{banner.subtitle}</p>}
                          {isTemplateOne && banner.title && <h1 className="text-4xl md:text-6xl mb-8">{banner.title}</h1>}
                          {isTemplateOne && <Link href={banner.link || '/shop'} className="inline-flex items-center justify-center rounded-full bg-[#717fe0] px-8 py-3 text-sm font-semibold uppercase text-white hover:bg-[#222] transition-colors">Shop Now</Link>}
                       </div>
                     </div>
                   ))
                 ) : (
                   <div className="relative w-full h-full flex items-center justify-center p-12 text-center bg-gray-200 text-gray-500 shrink-0">
                     <div>
                       <h1 className="text-3xl sm:text-4xl font-extrabold mb-4">Welcome to {app?.name || 'our store'}</h1>
                       <p className="mb-6 opacity-90">Discover our amazing products</p>
                       <Link href="/shop" className="inline-block bg-[#f15a24] text-white px-8 py-3 rounded-full font-bold">Shop Now</Link>
                     </div>
                   </div>
                 )}
              </div>
               
              {/* Arrow Controls */}
              {heroCount > 1 && (
                <>
                  <button type="button" onClick={() => moveHeroSlide(-1)} aria-label="Previous banner" aria-controls="hero-slider" className="absolute left-4 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 bg-white shadow flex items-center justify-center rounded-sm text-[#f15a24] opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity z-20">
                    <svg className="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7"/></svg>
                  </button>
                  <button type="button" onClick={() => moveHeroSlide(1)} aria-label="Next banner" aria-controls="hero-slider" className="absolute right-4 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 bg-white shadow flex items-center justify-center rounded-sm text-[#f15a24] opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity z-20">
                    <svg className="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
                  </button>
                  
                  {/* Dots */}
                  <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20">
                    {displayHeroBanners.map((banner, index) => (
                      <button
                        key={banner.id || index}
                        type="button"
                        onClick={() => scrollToHeroSlide(index)}
                        aria-label={`Show banner ${index + 1}`}
                        aria-controls="hero-slider"
                        aria-current={activeHeroIndex === index ? 'true' : undefined}
                        className={`h-3 w-3 rounded-full border border-white/30 transition-all focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-transparent ${activeHeroIndex === index ? 'bg-[#f15a24] scale-110' : 'bg-white/70 hover:bg-white'}`}
                      />
                    ))}
                  </div>
                </>
              )}
            </div>

            {/* Right Static Banner */}
            <div className="hidden lg:flex relative rounded-xl overflow-hidden min-h-[400px] bg-orange-50 group">
              {displayHeroBanners?.length > 1 ? (
                 <img src={resolveHeroImage(displayHeroBanners[1].image, displayHeroBanners[1].title)} className="absolute inset-0 w-full h-full object-cover" alt="Offer" />
              ) : (
                <div className="w-full h-full flex flex-col items-center justify-center text-center p-6 bg-gradient-to-br from-orange-100 to-orange-50">
                   <div className="w-20 h-20 bg-orange-200 rounded-full flex items-center justify-center mb-4">
                     <svg className="w-10 h-10 text-orange-500" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                   </div>
                   <h3 className="text-2xl font-bold text-gray-800 mb-2">Special Offer</h3>
                   <p className="text-gray-600 font-medium">Get 10% off on all items</p>
                </div>
              )}
            </div>
          </div>

          {/* Features Bottom Row */}
          {features?.length > 0 && (
            <div className="hidden xl:flex items-center justify-between gap-4 mt-2">
              {features.slice(0, 4).map((feature) => (
                <div key={feature.id} className="rounded-xl bg-white border border-gray-100 p-4 flex items-center gap-3 flex-1">
                  <div className="grid h-10 w-10 place-items-center rounded-full bg-[#f15a24]/10 text-[#f15a24] shrink-0">
                     {feature.icon ? (
                       <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path d={feature.icon}/></svg>
                     ) : (
                       <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                     )}
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-bold leading-tight truncate text-gray-800">{feature.title}</p>
                    {feature.subtitle && <p className="text-xs text-gray-500 truncate mt-0.5">{feature.subtitle}</p>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Mobile Features */}
        {features?.length > 0 && (
          <div className="grid grid-cols-2 gap-3 xl:hidden mt-4">
            {features.slice(0, 4).map((feature) => (
              <div key={feature.id} className="rounded-xl bg-white border border-gray-100 p-3 flex items-center gap-2.5">
                <div className="grid h-9 w-9 place-items-center rounded-full bg-[#f15a24]/10 text-[#f15a24] shrink-0">
                   {feature.icon ? (
                     <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path d={feature.icon}/></svg>
                   ) : (
                     <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                   )}
                </div>
                <div className="min-w-0">
                  <p className="text-[13px] font-bold leading-snug truncate text-gray-800">{feature.title}</p>
                  {feature.subtitle && <p className="text-[11px] text-gray-500 truncate">{feature.subtitle}</p>}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Categories Grid */}
      {featuredCategories?.length > 0 && (
        <section className="storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 mt-2">
          <div className="flex items-center justify-center mb-8">
            <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Featured Categories</h2>
          </div>
          
          <div className="relative group">
            {/* Scrollable Container */}
            <div ref={catSliderRef} className="flex overflow-x-auto snap-x snap-mandatory gap-4 sm:gap-6 pb-4 no-scrollbar scroll-smooth">
              {featuredCategories.map((cat) => (
                <Link key={cat.id} href={`/category/${cat.slug}`} className="group/item flex flex-col items-center text-center shrink-0 w-24 sm:w-32 snap-start">
                  <div className="w-24 h-24 sm:w-32 sm:h-32 rounded-[24px] bg-gradient-to-br from-[#fff7ed] via-white to-[#eef2ff] text-[#717fe0] shadow-sm border border-gray-100 overflow-hidden group-hover/item:shadow-lg group-hover/item:border-[#f15a24]/30 transition-all duration-300 group-hover/item:-translate-y-1 flex items-center justify-center p-3">
                    {cat.image ? (
                      <img src={imageUrl(cat.image)} alt={cat.name} className="w-full h-full object-contain" />
                    ) : (
                      <CategoryIcon icon={cat.icon} name={cat.name} className="h-12 w-12 sm:h-16 sm:w-16 transition-transform duration-300 group-hover/item:scale-110" />
                    )}
                    {/* Legacy image/emoji fallback retained for migration reference.
                    {cat.image ? (
                       <img src={imageUrl(cat.image)} alt={cat.name} className="w-full h-full object-contain" />
                    ) : (
                       <div className="w-full h-full flex items-center justify-center text-3xl sm:text-4xl bg-gray-50 text-gray-300 rounded-xl group-hover/item:text-[#f15a24] transition-colors">
                         {cat.icon ? <span dangerouslySetInnerHTML={{__html: cat.icon}} className="flex items-center justify-center w-10 h-10"/> : '📦'}
                       </div>
                    )} */}
                  </div>
                  <span className="mt-3 text-[13px] sm:text-sm font-bold text-gray-800 group-hover/item:text-[#f15a24] transition-colors line-clamp-2 leading-tight">{cat.name}</span>
                </Link>
              ))}
            </div>
            
            {/* Hover Arrows for Categories */}
            <button 
              onClick={() => catSliderRef.current?.scrollBy({ left: -300, behavior: 'smooth' })}
              className="hidden md:flex absolute -left-4 top-14 -translate-y-1/2 w-10 h-10 rounded-full bg-white shadow-md border border-gray-100 text-[#f15a24] items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-gray-50"
              aria-label="Previous"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button 
              onClick={() => catSliderRef.current?.scrollBy({ left: 300, behavior: 'smooth' })}
              className="hidden md:flex absolute -right-4 top-14 -translate-y-1/2 w-10 h-10 rounded-full bg-white shadow-md border border-gray-100 text-[#f15a24] items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-gray-50"
              aria-label="Next"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </section>
      )}

      {/* Trending / Featured Products */}
      {overviewProductLists.featured?.length > 0 && (
        <section className="storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
          <div className="flex items-end justify-between mb-6 border-b border-gray-100 pb-4">
            <div>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Just For You</h2>
              <p className="text-gray-500 text-sm mt-1">Discover trending products</p>
            </div>
            <Link href="/shop?featured=1" className="text-sm font-bold text-[#f15a24] hover:underline flex items-center gap-1 mb-1">
              {viewMore} <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </Link>
          </div>
          <div className={`grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`}>
            {overviewProductLists.featured.map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          {loadMoreButton('featured')}
        </section>
      )}

      {/* Middle Banners */}
      {middleBanners?.length > 0 && (
        <section className="storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col gap-6">
            {middleBanners.map(banner => (
              <a 
                key={banner.id} 
                href={banner.link || '#'} 
                className="block w-full overflow-hidden rounded-2xl shadow-sm hover:shadow-md transition-shadow group relative bg-gray-100"
              >
                {banner.image ? (
                  <img 
                    src={imageUrl(banner.image)} 
                    alt={banner.title || 'Banner'} 
                    className="w-full h-auto object-cover max-h-[400px] group-hover:scale-[1.02] transition-transform duration-500"
                  />
                ) : (
                  <div className="w-full h-[300px] flex items-center justify-center text-gray-400 bg-gray-200">
                    <span className="font-semibold">Banner Image (Upload via Admin)</span>
                  </div>
                )}
                {/* Fallback text if no image but there's a title/subtitle */}
                {!banner.image && (banner.title || banner.subtitle) && (
                  <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-6 bg-gradient-to-r from-gray-900/60 to-gray-800/60 text-white">
                    {banner.title && <h2 className="text-2xl md:text-4xl font-extrabold mb-2 drop-shadow-md">{banner.title}</h2>}
                    {banner.subtitle && <p className="text-base md:text-lg font-medium drop-shadow-md">{banner.subtitle}</p>}
                  </div>
                )}
              </a>
            ))}
          </div>
        </section>
      )}

      {/* New Arrivals */}
      {overviewProductLists.new?.length > 0 && (
        <section className="storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 bg-gray-50 rounded-3xl my-8">
          <div className="flex items-end justify-between mb-6 pb-2">
            <div>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">New Arrivals</h2>
              <p className="text-gray-500 text-sm mt-1">Fresh off the press</p>
            </div>
            <Link href="/shop?new=1" className="text-sm font-bold text-[#f15a24] hover:underline flex items-center gap-1 mb-1">
              {viewMore} <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </Link>
          </div>
          <div className={`grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`}>
            {overviewProductLists.new.map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          {loadMoreButton('new')}
        </section>
      )}

      {/* Best Sellers */}
      {overviewProductLists.best?.length > 0 && (
        <section className="storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 mb-12">
          <div className="flex items-end justify-between mb-6 border-b border-gray-100 pb-4">
            <div>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Best Sellers</h2>
              <p className="text-gray-500 text-sm mt-1">Our most popular items</p>
            </div>
            <Link href="/shop?best=1" className="text-sm font-bold text-[#f15a24] hover:underline flex items-center gap-1 mb-1">
              {viewMore} <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </Link>
          </div>
          <div className={`grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`}>
            {overviewProductLists.best.map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          {loadMoreButton('best')}
        </section>
      )}

    </StorefrontLayout>
  );
}
