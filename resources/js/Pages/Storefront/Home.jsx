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

const POSITION_MAP = {
  'top-left': ['left', 'top'],
  'top-center': ['center', 'top'],
  'top-right': ['right', 'top'],
  'center-left': ['left', 'center'],
  'center-center': ['center', 'center'],
  'center-right': ['right', 'center'],
  'bottom-left': ['left', 'bottom'],
  'bottom-center': ['center', 'bottom'],
  'bottom-right': ['right', 'bottom'],
};

function normalizePosition(value, fallback = 'center-center') {
  const legacy = { left: 'center-left', center: 'center-center', right: 'center-right' };
  const position = legacy[value] || value;
  if (POSITION_MAP[position]) return position;

  return legacy[fallback] || (POSITION_MAP[fallback] ? fallback : 'center-center');
}

function imageFocusPosition(value) {
  const [horizontal, vertical] = POSITION_MAP[normalizePosition(value)];
  return `${horizontal} ${vertical}`;
}

function imageFit(value) {
  return value === 'portrait' || value === 'square' ? 'contain' : 'cover';
}

function needsBlurFill(value) {
  return imageFit(value) === 'contain';
}

function BlurFillImage({ src, alt, orientation, position, className = '', imageClassName = '', hoverScale = false }) {
  const frameRef = useRef(null);
  const [imageRatio, setImageRatio] = useState(null);
  const [frameRatio, setFrameRatio] = useState(null);
  const configuredContain = needsBlurFill(orientation);
  const autoContain = !configuredContain && imageRatio && frameRatio
    ? (frameRatio >= 1.25 && imageRatio < frameRatio * 0.82)
      || (frameRatio < 1.25 && imageRatio > frameRatio * 1.2)
    : false;
  const useBlurFill = configuredContain || autoContain;
  const foregroundFit = useBlurFill ? 'contain' : imageFit(orientation);

  useEffect(() => {
    const frame = frameRef.current;
    if (!frame) return undefined;

    const updateFrameRatio = () => {
      const { width, height } = frame.getBoundingClientRect();
      if (width > 0 && height > 0) {
        setFrameRatio(width / height);
      }
    };

    updateFrameRatio();
    window.addEventListener('resize', updateFrameRatio);

    let resizeObserver;
    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(updateFrameRatio);
      resizeObserver.observe(frame);
    }

    return () => {
      window.removeEventListener('resize', updateFrameRatio);
      resizeObserver?.disconnect();
    };
  }, []);

  return (
    <span ref={frameRef} className={`block overflow-hidden ${className}`}>
      {useBlurFill && (
        <img
          src={src}
          className="absolute -inset-12 z-0 h-[calc(100%+6rem)] w-[calc(100%+6rem)] scale-125 object-cover opacity-45 blur-[44px]"
          aria-hidden="true"
          alt=""
        />
      )}
      {useBlurFill && <span aria-hidden="true" className="absolute inset-0 z-[1] bg-white/35" />}
      <img
        src={src}
        alt={alt}
        className={`absolute inset-0 z-[2] h-full w-full ${hoverScale ? 'transition-transform duration-500 group-hover:scale-[1.02]' : ''} ${imageClassName}`}
        style={{ objectPosition: imageFocusPosition(position), objectFit: foregroundFit }}
        onLoad={event => {
          const { naturalWidth, naturalHeight } = event.currentTarget;
          if (naturalWidth > 0 && naturalHeight > 0) {
            setImageRatio(naturalWidth / naturalHeight);
          }
        }}
      />
    </span>
  );
}

function textPositionStyle(value, fallback) {
  const [horizontal, vertical] = POSITION_MAP[normalizePosition(value, fallback)];
  return {
    alignItems: { left: 'flex-start', center: 'center', right: 'flex-end' }[horizontal],
    justifyContent: { top: 'flex-start', center: 'center', bottom: 'flex-end' }[vertical],
    textAlign: horizontal,
  };
}

function contentPositionClasses(position, fallback = 'center-left') {
  const [horizontal, vertical] = POSITION_MAP[normalizePosition(position, fallback)];
  const horizontalClass = { left: 'items-start text-left', center: 'items-center text-center', right: 'items-end text-right' }[horizontal];
  const verticalClass = { top: 'justify-start', center: 'justify-center', bottom: 'justify-end' }[vertical];
  return `${horizontalClass} ${verticalClass}`;
}

function heroCopyEdgeSpacing(position, fallback = 'center-left') {
  const [horizontal] = POSITION_MAP[normalizePosition(position, fallback)];
  const spacing = {
    left: 'ml-16 mr-5 sm:ml-20 sm:mr-8',
    center: 'mx-16 sm:mx-20',
    right: 'ml-5 mr-16 sm:ml-8 sm:mr-20',
  };

  return spacing[horizontal];
}

function HeroDots({ banners, activeIndex, onSelect, controlsId }) {
  if (!banners || banners.length < 2) return null;

  return (
    <div
      className="hero-dots absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-0.5 rounded-full bg-black/30 px-2 py-1 shadow-sm backdrop-blur-[2px]"
      role="group"
      aria-label={`Hero slider: ${banners.length} slides`}
    >
      {banners.map((banner, index) => {
        const isActive = activeIndex === index;

        return (
          <button
            key={banner.id || index}
            type="button"
            onClick={() => onSelect(index)}
            aria-label={`Show slide ${index + 1} of ${banners.length}${banner.title ? `: ${banner.title}` : ''}`}
            aria-controls={controlsId}
            aria-current={isActive ? 'true' : undefined}
            className="grid h-7 w-7 place-items-center rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
          >
            <span
              aria-hidden="true"
              className={`block h-2.5 w-2.5 rounded-full border border-white shadow transition-all ${isActive ? 'scale-125 bg-[#717fe0]' : 'bg-white/80 hover:bg-white'}`}
            />
          </button>
        );
      })}
    </div>
  );
}

export default function HomePage({ 
  heroBanners, 
  heroSideBanners,
  middleBanners,
  features, 
  featuredCategories, 
  coupons, 
  flashProducts, 
  trending, 
  bestSellers, 
  newArrivals,
  templateTwoCategorySections = [],
  homeOverviewHasMore = {},
  flashSaleHasMore = false,
  app 
}) {
  const ctaDefault = app?.settings?.default_cta_text || 'Shop now';
  const viewMore = app?.settings?.home_view_more_label || 'View all';
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';
  const isTemplateTwo = app?.settings?.storefront_template === 'template-2';
  const heroOverlayColor = app?.settings?.template_1_hero_overlay_color || '#ffffff';
  const heroOverlayOpacity = Math.max(0, Math.min(80, Number(app?.settings?.template_1_hero_overlay_opacity ?? 0))) / 100;
  const heroTextBackgroundColor = app?.settings?.template_1_hero_text_background_color || '#1f2430';
  const heroTextBackgroundOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_1_hero_text_background_opacity ?? 88))) / 100;
  const heroTextPosition = ['left', 'center', 'right'].includes(app?.settings?.template_1_hero_text_position)
    ? app.settings.template_1_hero_text_position
    : 'left';
  const templateTwoHeroOverlayColor = app?.settings?.template_2_hero_overlay_color || '#ffffff';
  const templateTwoHeroOverlayOpacity = Math.max(0, Math.min(80, Number(app?.settings?.template_2_hero_overlay_opacity ?? 0))) / 100;
  const templateTwoHeroTextBackgroundColor = app?.settings?.template_2_hero_text_background_color || '#1f2430';
  const templateTwoHeroTextBackgroundOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_2_hero_text_background_opacity ?? 35))) / 100;
  const templateTwoHeroTextPosition = ['left', 'center', 'right'].includes(app?.settings?.template_2_hero_text_position)
    ? app.settings.template_2_hero_text_position
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
  const templateTwoCategoryTitleColor = app?.settings?.template_2_category_title_color || '#1f2937';
  const templateTwoCategorySecondaryColor = app?.settings?.template_2_category_secondary_color || '#f2541c';
  const templateTwoCategoryOverlayColor = app?.settings?.template_2_category_overlay_color || '#1f2430';
  const templateTwoCategoryOverlayOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_2_category_overlay_opacity ?? 0))) / 100;
  const templateTwoCategoryHoverOverlayOpacity = Math.max(0, Math.min(100, Number(app?.settings?.template_2_category_hover_overlay_opacity ?? 12))) / 100;
  const templateTwoCategoryTitleSize = ['12px', '13px', '14px', '15px', '16px', '18px', '20px', '22px', '24px'].includes(app?.settings?.template_2_category_title_size)
    ? app.settings.template_2_category_title_size
    : '14px';
  const templateTwoCategoryTitleWeight = ['400', '500', '600', '700', '800', '900'].includes(String(app?.settings?.template_2_category_title_weight))
    ? String(app.settings.template_2_category_title_weight)
    : '700';
  const templateTwoCategoryTitleStyle = app?.settings?.template_2_category_title_style === 'italic' ? 'italic' : 'normal';
  const templateTwoCategoryTitleTransform = ['none', 'capitalize', 'uppercase', 'lowercase'].includes(app?.settings?.template_2_category_title_transform)
    ? app.settings.template_2_category_title_transform
    : 'none';
  const templateTwoCategoryTextAlign = ['left', 'center', 'right'].includes(app?.settings?.template_2_category_text_align)
    ? app.settings.template_2_category_text_align
    : 'center';
  const templateTwoCategoryTextShadow = app?.settings?.template_2_category_text_shadow === true;
  const flashSaleEnabled = app?.settings?.homepage_flash_sale_enabled !== false;
  const flashSaleTitle = app?.settings?.home_hot_deal_title || 'Flash Sale';
  const flashSaleEndsAt = app?.settings?.flash_sale_ends_at || '';
  const displayHeroBanners = isTemplateOne
    ? (heroBanners || []).map((banner) => ({
        ...banner,
        button: banner.button || banner.button_text,
        link: banner.link || banner.link_url,
      }))
    : (heroBanners || []);
  const displayHeroSideBanners = (heroSideBanners || []).map((banner) => ({
    ...banner,
    button: banner.button || banner.button_text,
    link: banner.link || banner.link_url,
  }));
  const hasHeroSideBanner = isTemplateTwo && displayHeroSideBanners.length > 0;
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
  const catPerRow = parseInt(app?.settings?.template_1_category_per_row || '3');
  const categorySlideWidth = {
    2: 'calc(50% - 15px)',
    3: 'calc(33.333333% - 20px)',
    4: 'calc(25% - 22.5px)',
    5: 'calc(20% - 24px)',
  }[catPerRow] || 'calc(33.333333% - 20px)';

  const productGridClasses = {
    2: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4',
    5: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5',
    6: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6',
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
  const heroSideCount = displayHeroSideBanners.length;
  const [activeHeroIndex, setActiveHeroIndex] = useState(0);
  const [activeHeroSideIndex, setActiveHeroSideIndex] = useState(0);
  const [isTemplateTwoCategoryCompact, setIsTemplateTwoCategoryCompact] = useState(false);
  const [activeOverview, setActiveOverview] = useState('all');
  const [overviewProductLists, setOverviewProductLists] = useState(() => Object.fromEntries(
    Object.entries(overviewProductsByTab).map(([tab, products]) => [
      tab,
      uniqueProducts(products).slice(0, overviewCounts[tab] || 16),
    ]),
  ));
  const [overviewHasMore, setOverviewHasMore] = useState(homeOverviewHasMore);
  const [loadingOverviewTab, setLoadingOverviewTab] = useState(null);
  const [categoryProductLists, setCategoryProductLists] = useState(() => Object.fromEntries(
    (templateTwoCategorySections || []).map(section => [section.id, section.products || []]),
  ));
  const [categoryHasMore, setCategoryHasMore] = useState(() => Object.fromEntries(
    (templateTwoCategorySections || []).map(section => [section.id, Boolean(section.has_more)]),
  ));
  const [loadingCategorySection, setLoadingCategorySection] = useState(null);
  const [flashProductList, setFlashProductList] = useState(flashProducts || []);
  const [flashHasMore, setFlashHasMore] = useState(Boolean(flashSaleHasMore));
  const [loadingFlashProducts, setLoadingFlashProducts] = useState(false);
  const [flashNow, setFlashNow] = useState(() => Date.now());
  const overviewProducts = overviewProductLists[activeOverview] || [];

  const overviewTabs = [
    ['all', 'All Products'],
    ['featured', 'Featured'],
    ['new', 'New Arrivals'],
    ['best', 'Best Sellers'],
  ];

  useEffect(() => {
    setCategoryProductLists(Object.fromEntries(
      (templateTwoCategorySections || []).map(section => [section.id, section.products || []]),
    ));
    setCategoryHasMore(Object.fromEntries(
      (templateTwoCategorySections || []).map(section => [section.id, Boolean(section.has_more)]),
    ));
  }, [templateTwoCategorySections]);

  useEffect(() => {
    setFlashProductList(flashProducts || []);
    setFlashHasMore(Boolean(flashSaleHasMore));
  }, [flashProducts, flashSaleHasMore]);

  useEffect(() => {
    if (!flashSaleEndsAt) return undefined;

    const interval = window.setInterval(() => setFlashNow(Date.now()), 1000);
    return () => window.clearInterval(interval);
  }, [flashSaleEndsAt]);

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

  const loadMoreCategoryProducts = async (section) => {
    if (loadingCategorySection || !categoryHasMore[section.id]) return;

    const displayedProducts = categoryProductLists[section.id] || [];
    const search = new URLSearchParams({ category_id: section.id });
    displayedProducts.forEach(product => search.append('exclude[]', product.id));
    setLoadingCategorySection(section.id);

    try {
      const response = await fetch(`/home/category-products/load-more?${search.toString()}`, {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error('Unable to load more category products.');

      const { products, has_more: hasMore } = await response.json();
      setCategoryProductLists(currentLists => {
        const currentProducts = currentLists[section.id] || [];
        const currentIds = new Set(currentProducts.map(product => product.id));

        return {
          ...currentLists,
          [section.id]: [...currentProducts, ...products.filter(product => !currentIds.has(product.id))],
        };
      });
      setCategoryHasMore(current => ({ ...current, [section.id]: Boolean(hasMore && products.length) }));
    } catch (error) {
      // Let the customer retry the same button after a temporary network issue.
    } finally {
      setLoadingCategorySection(null);
    }
  };

  const loadMoreFlashProducts = async () => {
    if (loadingFlashProducts || !flashHasMore) return;

    const search = new URLSearchParams();
    flashProductList.forEach(product => search.append('exclude[]', product.id));
    setLoadingFlashProducts(true);

    try {
      const response = await fetch(`/home/flash-sale/load-more?${search.toString()}`, {
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error('Unable to load more flash sale products.');

      const { products, has_more: hasMore } = await response.json();
      setFlashProductList(currentProducts => {
        const currentIds = new Set(currentProducts.map(product => product.id));
        return [...currentProducts, ...products.filter(product => !currentIds.has(product.id))];
      });
      setFlashHasMore(Boolean(hasMore && products.length));
    } catch (error) {
      // Keep the button available so a temporary network error can be retried.
    } finally {
      setLoadingFlashProducts(false);
    }
  };

  const flashTargetTime = (() => {
    if (!flashSaleEndsAt) return null;

    const target = new Date(String(flashSaleEndsAt).replace(' ', 'T')).getTime();
    return Number.isFinite(target) ? target : null;
  })();
  const flashSaleExpired = flashTargetTime !== null && flashTargetTime <= flashNow;

  const flashRemaining = (() => {
    if (!flashTargetTime || flashSaleExpired) return null;

    const seconds = Math.floor((flashTargetTime - flashNow) / 1000);
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    return { days, hours, minutes, seconds: secs };
  })();

  const FlashSaleSection = ({ template = 'two' } = {}) => {
    if (!flashSaleEnabled || flashSaleExpired || flashProductList.length === 0) return null;

    const isTemplateOneSection = template === 'one';
    const sectionClass = isTemplateOneSection
      ? 'template-1-container storefront-section py-10 md:py-14'
      : 'template-2-flash-sale-section storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8';
    const gridClass = isTemplateOneSection
      ? `grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`
      : `template-2-category-product-grid grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`;

    return (
      <section className={sectionClass}>
        <div className={isTemplateOneSection ? 'mb-7 flex flex-col gap-4 border-b border-gray-100 pb-5 md:flex-row md:items-end md:justify-between' : 'mb-6 flex flex-col gap-4 border-b border-gray-100 pb-4 md:flex-row md:items-end md:justify-between'}>
          <div>
            <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-[#f2541c]/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-[#f2541c]">
              <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
              Limited Offer
            </div>
            <h2 className="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">{flashSaleTitle}</h2>
            <p className="mt-1 text-sm text-gray-500">Grab these deals before the timer runs out.</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {flashRemaining && (
              <div className="flex items-center gap-1.5 rounded-xl bg-[#1f2430] px-3 py-2 text-white shadow-sm">
                {[
                  ['D', flashRemaining.days],
                  ['H', flashRemaining.hours],
                  ['M', flashRemaining.minutes],
                  ['S', flashRemaining.seconds],
                ].map(([label, value]) => (
                  <span key={label} className="grid min-w-10 place-items-center rounded-lg bg-white/10 px-2 py-1">
                    <strong className="font-mono text-sm leading-none">{String(value).padStart(2, '0')}</strong>
                    <em className="mt-0.5 text-[9px] not-italic leading-none text-white/70">{label}</em>
                  </span>
                ))}
              </div>
            )}
            <Link href="/shop?flash=1" className="inline-flex h-10 items-center justify-center rounded-xl bg-[#f2541c] px-4 text-sm font-bold text-white transition-colors hover:bg-[#d6431a]">
              {viewMore}
            </Link>
          </div>
        </div>

        <div className={gridClass}>
          {flashProductList.map(product => (
            <div key={product.id} className="flash-sale-product-card relative">
              <ProductCard product={product} />
              <div className="pointer-events-none absolute inset-x-3 bottom-3 rounded-full bg-gray-100/95 p-1 shadow-sm">
                <div className="h-1.5 rounded-full bg-gray-200">
                  <div className="h-full rounded-full bg-[#f2541c]" style={{ width: `${Math.max(5, Math.min(100, Number(product.flash_sale_progress || 50)))}%` }} />
                </div>
              </div>
            </div>
          ))}
        </div>

        {flashHasMore && (
          <div className="mt-5 flex justify-center">
            <button
              type="button"
              onClick={loadMoreFlashProducts}
              disabled={loadingFlashProducts}
              className="inline-flex h-[42px] min-w-[150px] items-center justify-center rounded-[21px] bg-[#e6e6e6] px-5 text-[13px] font-bold uppercase leading-none text-[#333] transition-colors duration-300 hover:bg-[#222] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#222] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
            >
              {loadingFlashProducts ? 'LOADING...' : 'LOAD MORE'}
            </button>
          </div>
        )}
      </section>
    );
  };

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
    setActiveHeroSideIndex(0);
  }, [heroSideCount]);

  useEffect(() => {
    if (!hasHeroSideBanner || heroSideCount < 2) return undefined;

    const interval = window.setInterval(() => {
      setActiveHeroSideIndex(current => (current + 1) % heroSideCount);
    }, 5000);

    return () => window.clearInterval(interval);
  }, [hasHeroSideBanner, heroSideCount]);

  const moveCategorySlide = (direction) => {
    const slider = catSliderRef.current;
    if (!slider) return;
    if (slider.scrollWidth <= slider.clientWidth + 2) return;

    const atStart = slider.scrollLeft <= 8;
    const atEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 8;
    if (direction < 0 && atStart) {
      slider.scrollTo({ left: slider.scrollWidth, behavior: 'smooth' });
      return;
    }
    if (direction > 0 && atEnd) {
      slider.scrollTo({ left: 0, behavior: 'smooth' });
      return;
    }

    slider.scrollBy({ left: direction * Math.max(240, slider.clientWidth * 0.85), behavior: 'smooth' });
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

    const catInterval = setInterval(() => {
      if (catSliderRef.current && featuredCategories?.length > 0 && !isTemplateTwoCategoryCompact) {
        const { scrollLeft, scrollWidth, clientWidth } = catSliderRef.current;
        if (scrollLeft + clientWidth >= scrollWidth - 10) {
          catSliderRef.current.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          catSliderRef.current.scrollBy({ left: 200, behavior: 'smooth' });
        }
      }
    }, 3000);

    return () => {
      clearInterval(catInterval);
    };
  }, [featuredCategories, isTemplateOne, isTemplateTwoCategoryCompact]);

  useEffect(() => {
    if (isTemplateOne) return undefined;

    const slider = catSliderRef.current;
    if (!slider) return undefined;

    const updateCategoryLayout = () => {
      const firstItem = slider.querySelector('.template-two-category-item');
      const secondItem = firstItem?.nextElementSibling;
      const itemWidth = firstItem?.getBoundingClientRect().width || 0;
      const gap = firstItem && secondItem
        ? Math.max(0, secondItem.getBoundingClientRect().left - firstItem.getBoundingClientRect().right)
        : 0;
      const itemCount = featuredCategories?.length || 0;
      const contentWidth = itemCount > 0 ? (itemWidth * itemCount) + (gap * Math.max(0, itemCount - 1)) : 0;
      setIsTemplateTwoCategoryCompact(contentWidth > 0 && contentWidth <= slider.clientWidth + 2);
    };

    updateCategoryLayout();
    window.addEventListener('resize', updateCategoryLayout);

    let resizeObserver;
    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(updateCategoryLayout);
      resizeObserver.observe(slider);
    }

    return () => {
      window.removeEventListener('resize', updateCategoryLayout);
      resizeObserver?.disconnect();
    };
  }, [featuredCategories, isTemplateOne]);
  
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

        {heroCount > 0 && <section
          className={`template-1-hero storefront-hero-section template-1-hero-position-${heroTextPosition}`}
          style={{
            '--template-1-hero-overlay-color': heroOverlayColor,
            '--template-1-hero-overlay-opacity': heroOverlayOpacity,
            '--template-1-hero-text-background-color': heroTextBackgroundColor,
            '--template-1-hero-text-background-opacity': heroTextBackgroundOpacity,
          }}
        >
          <div id="template-1-hero-slider" className="template-1-hero-track">
            {displayHeroBanners.map((banner, index) => (
              <div
                key={banner.id || index}
                className={`template-1-hero-slide relative overflow-hidden ${activeHeroIndex === index ? 'is-active' : ''}`}
                aria-hidden={activeHeroIndex !== index}
              >
                {banner.image && (
                  <BlurFillImage
                    src={resolveHeroImage(banner.image, banner.title)}
                    alt={banner.title}
                    orientation={banner.image_orientation}
                    position={banner.image_position}
                    className="absolute inset-0 h-full w-full"
                  />
                )}
                <div className="template-1-container template-1-hero-content relative z-30" style={textPositionStyle(banner.text_position, heroTextPosition)}>
                  <div className="template-1-hero-copy" style={{ textAlign: textPositionStyle(banner.text_position, heroTextPosition).textAlign }}>
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
              <HeroDots
                banners={displayHeroBanners}
                activeIndex={activeHeroIndex}
                onSelect={scrollToHeroSlide}
                controlsId="template-1-hero-slider"
              />
            </>
          )}
        </section>}

        {features?.length > 0 && (
          <section className="template-1-container storefront-section pb-2">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {features.slice(0, 4).map(feature => (
                <div key={feature.id} className="flex min-w-0 items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                  <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#717fe0]/10 text-[#717fe0]">
                    {feature.icon ? <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path d={feature.icon}/></svg> : <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>}
                  </div>
                  <div className="min-w-0"><p className="truncate text-sm font-bold text-gray-800">{feature.title}</p>{feature.subtitle && <p className="mt-0.5 truncate text-xs text-gray-500">{feature.subtitle}</p>}</div>
                </div>
              ))}
            </div>
          </section>
        )}

        <section
          className="template-1-container storefront-section py-12 md:py-20"
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
            '--template-1-category-slide-width': categorySlideWidth,
          }}
        >
          <div className="template-1-category-carousel">
            <div
              ref={catSliderRef}
              id="template-1-category-slider"
              className="template-1-category-track no-scrollbar"
            >
              {templateOneBanners.map(banner => (
                <Link key={banner.title} href={banner.href} className="template-1-banner-card template-1-category-slide">
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
            </div>

            {templateOneBanners.length > 1 && (
              <>
                <button
                  type="button"
                  onClick={() => moveCategorySlide(-1)}
                  className="template-1-category-arrow template-1-category-prev"
                  aria-label="Previous categories"
                  aria-controls="template-1-category-slider"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 19-7-7 7-7" /></svg>
                </button>
                <button
                  type="button"
                  onClick={() => moveCategorySlide(1)}
                  className="template-1-category-arrow template-1-category-next"
                  aria-label="Next categories"
                  aria-controls="template-1-category-slider"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg>
                </button>
              </>
            )}
          </div>
        </section>

        {/* Middle Banners — displayed between the category promos and product overview. */}
        {middleBanners?.length > 0 && (
          <section className="template-1-container storefront-section space-y-6 pb-12 md:pb-16">
            {middleBanners.map(banner => (
              <a
                key={banner.id}
                href={banner.link || '/shop'}
                className="group relative block overflow-hidden rounded-2xl bg-[#f2f2f2] shadow-sm transition-shadow hover:shadow-md"
              >
                {banner.image ? (
                      <div className="relative aspect-[2/1] w-full overflow-hidden sm:aspect-[3/1]">
                        <BlurFillImage
                          src={imageUrl(banner.image, banner.title)}
                          alt={banner.title || 'Promotional banner'}
                          orientation={banner.image_orientation}
                          position={banner.image_position}
                          className="absolute inset-0 h-full w-full object-cover"
                          hoverScale
                        />
                      </div>
                ) : (
                  <div className="flex h-56 items-center justify-center bg-gradient-to-r from-[#717fe0] to-[#5967c8] p-6 text-center text-white">
                    <div>
                      {banner.title && <h2 className="text-2xl font-bold md:text-4xl">{banner.title}</h2>}
                      {banner.subtitle && <p className="mt-2 text-sm md:text-lg">{banner.subtitle}</p>}
                    </div>
                  </div>
                )}
                {(banner.title || banner.subtitle || banner.button) && (
                  <div className={`absolute inset-0 flex flex-col justify-center p-5 sm:p-10 ${contentPositionClasses(banner.text_position)}`}>
                    <div className="max-w-xl rounded-xl bg-[#1f2430]/85 px-5 py-4 text-white shadow-lg sm:px-7 sm:py-6">
                      {banner.title && <h2 className="text-xl font-bold sm:text-3xl">{banner.title}</h2>}
                      {banner.subtitle && <p className="mt-2 text-sm text-white/90 sm:text-base">{banner.subtitle}</p>}
                      {banner.button && <span className="mt-4 inline-flex rounded-full bg-[#717fe0] px-5 py-2 text-xs font-bold uppercase tracking-wide text-white">{banner.button}</span>}
                    </div>
                  </div>
                )}
              </a>
            ))}
          </section>
        )}

        <FlashSaleSection template="one" />

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
          <div className={`grid grid-cols-1 gap-4 w-full ${hasHeroSideBanner ? 'lg:grid-cols-[2.5fr_1fr]' : ''}`}>
            {/* Left Slider Container */}
            <div className={`relative rounded-xl overflow-hidden bg-gray-100 group ${isTemplateTwo ? 'min-h-[360px] sm:min-h-[480px]' : 'min-h-[250px] sm:min-h-[400px]'}`}>
              {/* Scrollable Area */}
              <div id="hero-slider" ref={heroSliderRef} className="absolute inset-0 flex snap-x snap-mandatory overflow-hidden scroll-smooth">
                 {displayHeroBanners?.length > 0 ? (
                   displayHeroBanners.map((banner, index) => {
                     const heroImage = resolveHeroImage(banner.image, banner.title);
                     const hasHeroCopy = Boolean(banner.subtitle || banner.button);

                     return (
                     <div key={index} className={`relative w-full shrink-0 snap-center h-full flex flex-col ${contentPositionClasses(banner.text_position, templateTwoHeroTextPosition)}`}>
                       {banner.image ? (
                         <BlurFillImage
                           src={heroImage}
                           alt={banner.title}
                           orientation={banner.image_orientation}
                           position={banner.image_position}
                           className="template-two-hero-image absolute inset-0 h-full w-full object-cover"
                         />
                       ) : (
                         <div className="absolute inset-0 bg-gradient-to-r from-[#f15a24] to-[#f37c4f] mix-blend-overlay opacity-90"></div>
                       )}
                       {isTemplateTwo && templateTwoHeroOverlayOpacity > 0 && <div className="absolute inset-0" style={{ backgroundColor: hexToRgba(templateTwoHeroOverlayColor, templateTwoHeroOverlayOpacity) }} />}
                       {!hasHeroCopy && banner.link && (
                         <Link href={banner.link} className="absolute inset-0 z-[3]" aria-label={banner.title || 'Open banner'} />
                       )}
                       {hasHeroCopy && (
                         <div className={`template-two-hero-copy relative z-10 mt-5 mb-16 max-w-md p-5 sm:mt-8 sm:mb-20 sm:p-7 ${heroCopyEdgeSpacing(banner.text_position, templateTwoHeroTextPosition)} ${isTemplateOne ? 'text-[#222] drop-shadow-none' : isTemplateTwo ? 'rounded-xl border border-white/15 text-white shadow-lg backdrop-blur-sm' : 'text-white drop-shadow-md hidden'}`} style={isTemplateTwo ? { backgroundColor: hexToRgba(templateTwoHeroTextBackgroundColor, templateTwoHeroTextBackgroundOpacity) } : undefined}>
                            {banner.subtitle && <p className="text-xl md:text-2xl mb-3 font-light">{banner.subtitle}</p>}
                            {banner.title && <h1 className="text-4xl md:text-6xl mb-8">{banner.title}</h1>}
                            {banner.button && <Link href={banner.link || '/shop'} className={`inline-flex items-center justify-center px-8 py-3 text-sm font-semibold uppercase text-white transition-colors ${isTemplateOne ? 'rounded-full bg-[#717fe0] hover:bg-[#222]' : 'rounded-lg bg-[#f2541c] hover:bg-[#d6431a]'}`}>{banner.button}</Link>}
                         </div>
                       )}
                     </div>
                     );
                   })
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
                  <button type="button" onClick={() => moveHeroSlide(-1)} aria-label="Previous banner" title="Previous banner" aria-controls="hero-slider" className="absolute left-4 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white/90 bg-[#f2541c] text-white shadow-[0_6px_18px_rgba(0,0,0,0.3)] transition-transform hover:scale-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white md:h-11 md:w-11">
                    <svg className="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7"/></svg>
                  </button>
                  <button type="button" onClick={() => moveHeroSlide(1)} aria-label="Next banner" title="Next banner" aria-controls="hero-slider" className="absolute right-4 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white/90 bg-[#f2541c] text-white shadow-[0_6px_18px_rgba(0,0,0,0.3)] transition-transform hover:scale-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white md:h-11 md:w-11">
                    <svg className="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
                  </button>
                  
                  <HeroDots
                    banners={displayHeroBanners}
                    activeIndex={activeHeroIndex}
                    onSelect={scrollToHeroSlide}
                    controlsId="hero-slider"
                  />
                </>
              )}
            </div>

            {/* Right Promo Banner */}
            {hasHeroSideBanner && (
              <div className="hidden lg:flex relative min-h-[480px] overflow-hidden rounded-xl bg-orange-50">
                {displayHeroSideBanners.map((banner, index) => {
                  const isActive = activeHeroSideIndex === index;
                  const hasSideCopy = Boolean(banner.subtitle || banner.button);

                  return (
                    <div
                      key={banner.id || index}
                      className={`absolute inset-0 block transition-opacity duration-500 ${isActive ? 'z-10 opacity-100' : 'z-0 opacity-0 pointer-events-none'}`}
                      aria-hidden={!isActive}
                    >
                      {banner.link && !banner.button && (
                        <Link href={banner.link} className="absolute inset-0 z-[3]" aria-label={banner.title || 'Open banner'} />
                      )}
                      {banner.image ? (
                        <BlurFillImage
                          src={resolveHeroImage(banner.image, banner.title)}
                          alt={banner.title || 'Promotional banner'}
                          orientation={banner.image_orientation}
                          position={banner.image_position}
                          className="absolute inset-0 h-full w-full object-cover"
                          imageClassName="transition-transform duration-500 hover:scale-[1.02]"
                        />
                      ) : (
                        <div className="absolute inset-0 bg-gradient-to-br from-orange-100 to-orange-50" />
                      )}
                      {hasSideCopy && (
                        <div className={`absolute inset-0 flex flex-col p-6 ${contentPositionClasses(banner.text_position, 'center-center')}`}>
                          <div className="max-w-[88%] rounded-xl bg-[#1f2430]/80 px-5 py-4 text-white shadow-lg backdrop-blur-sm">
                            {banner.title && <h3 className="text-2xl font-bold">{banner.title}</h3>}
                            {banner.subtitle && <p className="mt-2 text-sm font-medium text-white/90">{banner.subtitle}</p>}
                            {banner.button && banner.link && <Link href={banner.link} className="mt-4 inline-flex rounded-lg bg-[#f2541c] px-4 py-2 text-xs font-bold uppercase tracking-wide text-white">{banner.button}</Link>}
                            {banner.button && !banner.link && <span className="mt-4 inline-flex rounded-lg bg-[#f2541c] px-4 py-2 text-xs font-bold uppercase tracking-wide text-white">{banner.button}</span>}
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
                {heroSideCount > 1 && (
                  <div className="absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-black/25 px-2.5 py-1.5 backdrop-blur-sm">
                    {displayHeroSideBanners.map((banner, index) => (
                      <button
                        key={banner.id || index}
                        type="button"
                        onClick={() => setActiveHeroSideIndex(index)}
                        aria-label={`Show side promo ${index + 1} of ${heroSideCount}${banner.title ? `: ${banner.title}` : ''}`}
                        aria-current={activeHeroSideIndex === index ? 'true' : undefined}
                        className={`h-2.5 rounded-full border border-white transition-all ${activeHeroSideIndex === index ? 'w-5 bg-[#f2541c]' : 'w-2.5 bg-white/75 hover:bg-white'}`}
                      />
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Features Bottom Row */}
          {!isTemplateTwo && features?.length > 0 && (
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
        {!isTemplateTwo && features?.length > 0 && (
          <div className="grid grid-cols-2 gap-3 xl:hidden mt-4">
            {features.slice(0, 4).map((feature) => (
                <div key={feature.id} className="rounded-xl bg-white border border-gray-100 p-3 flex items-center gap-2.5 min-h-24">
                <div className="grid h-9 w-9 place-items-center rounded-full bg-[#f15a24]/10 text-[#f15a24] shrink-0">
                   {feature.icon ? (
                     <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path d={feature.icon}/></svg>
                   ) : (
                     <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                   )}
                </div>
                  <div className="min-w-0 flex-1">
                  <p className="text-[13px] font-bold leading-snug text-gray-800 line-clamp-2">{feature.title}</p>
                  {feature.subtitle && <p className="text-[11px] leading-snug text-gray-500 line-clamp-2">{feature.subtitle}</p>}
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
            <h2 className="template-two-featured-category-heading w-full max-w-full px-3 text-center text-[24px] leading-tight sm:text-3xl font-extrabold text-gray-900 tracking-tight break-words">
              <span className="block sm:hidden">Featured<br />Categories</span>
              <span className="hidden sm:inline">Featured Categories</span>
            </h2>
          </div>
          
          <div className={`template-two-category-carousel relative ${isTemplateTwoCategoryCompact ? 'is-compact' : ''}`}>
            {/* Scrollable Container */}
            <div ref={catSliderRef} className="template-two-category-track flex overflow-x-auto snap-x snap-mandatory gap-4 sm:gap-6 pb-4 no-scrollbar scroll-smooth">
              {featuredCategories.map((cat) => (
                <Link key={cat.id} href={`/category/${cat.slug}`} className="template-two-category-item group/item flex flex-col items-center shrink-0 w-24 sm:w-32 snap-start" style={{ textAlign: templateTwoCategoryTextAlign }}>
                  <div className="relative w-24 h-24 sm:w-32 sm:h-32 rounded-[24px] bg-gradient-to-br from-[#fff7ed] via-white to-[#eef2ff] text-[#717fe0] shadow-sm border border-gray-100 overflow-hidden group-hover/item:shadow-lg group-hover/item:border-[#f15a24]/30 transition-all duration-300 group-hover/item:-translate-y-1 flex items-center justify-center p-3">
                    {cat.image ? (
                      <img src={imageUrl(cat.image)} alt={cat.name} className="w-full h-full object-contain" />
                    ) : (
                      <CategoryIcon icon={cat.icon} name={cat.name} className="h-12 w-12 sm:h-16 sm:w-16 transition-transform duration-300 group-hover/item:scale-110" />
                    )}
                    <span aria-hidden="true" className="template-two-category-tile-overlay absolute inset-0 pointer-events-none" style={{ backgroundColor: templateTwoCategoryOverlayColor, '--category-overlay-opacity': templateTwoCategoryOverlayOpacity, '--category-hover-overlay-opacity': templateTwoCategoryHoverOverlayOpacity }} />
                    {/* Legacy image/emoji fallback retained for migration reference.
                    {cat.image ? (
                       <img src={imageUrl(cat.image)} alt={cat.name} className="w-full h-full object-contain" />
                    ) : (
                       <div className="w-full h-full flex items-center justify-center text-3xl sm:text-4xl bg-gray-50 text-gray-300 rounded-xl group-hover/item:text-[#f15a24] transition-colors">
                         {cat.icon ? <span dangerouslySetInnerHTML={{__html: cat.icon}} className="flex items-center justify-center w-10 h-10"/> : '📦'}
                       </div>
                    )} */}
                  </div>
                  <span className="template-two-category-title mt-3 line-clamp-2 leading-tight transition-colors" style={{ color: templateTwoCategoryTitleColor, fontSize: templateTwoCategoryTitleSize, fontWeight: templateTwoCategoryTitleWeight, fontStyle: templateTwoCategoryTitleStyle, textTransform: templateTwoCategoryTitleTransform, textShadow: templateTwoCategoryTextShadow ? '0 1px 2px rgba(0, 0, 0, 0.28)' : 'none', '--category-accent-color': templateTwoCategorySecondaryColor }}>{cat.name}</span>
                </Link>
              ))}
            </div>
            
            {/* Hover Arrows for Categories */}
            <button 
              type="button"
              onClick={() => moveCategorySlide(-1)}
              className="template-two-category-arrow template-two-category-prev hidden md:flex"
              aria-label="Previous"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button 
              type="button"
              onClick={() => moveCategorySlide(1)}
              className="template-two-category-arrow template-two-category-next hidden md:flex"
              aria-label="Next"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </section>
      )}

      <FlashSaleSection template="two" />

      {isTemplateTwo && templateTwoCategorySections.map(section => (
        <section key={section.id} className="template-2-category-section storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
          <div className="flex items-center justify-between gap-4 mb-6 border-b border-gray-100 pb-4">
            <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{section.name}</h2>
            <Link href={`/category/${section.slug}`} className="text-sm font-bold text-[#f15a24] hover:underline flex items-center gap-1">
              {viewMore} <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </Link>
          </div>
          {(() => {
            const sectionProducts = categoryProductLists[section.id] || section.products || [];

            return (
              <>
                <div className={`template-2-category-product-grid grid ${productGridClass} gap-3 sm:gap-4 lg:gap-5`}>
                  {sectionProducts.map(product => (
                    <ProductCard key={product.id} product={product} />
                  ))}
                </div>
                {categoryHasMore[section.id] && (
                  <div className="mt-5 flex justify-center">
                    <button
                      type="button"
                      onClick={() => loadMoreCategoryProducts(section)}
                      disabled={loadingCategorySection !== null}
                      className="inline-flex h-[42px] min-w-[150px] items-center justify-center rounded-[21px] bg-[#e6e6e6] px-5 text-[13px] font-bold uppercase leading-none text-[#333] transition-colors duration-300 hover:bg-[#222] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#222] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
                    >
                      {loadingCategorySection === section.id ? 'LOADING...' : 'LOAD MORE'}
                    </button>
                  </div>
                )}
              </>
            );
          })()}
        </section>
      ))}

      {/* Trending / Featured Products */}
      {!isTemplateTwo && overviewProductLists.featured?.length > 0 && (
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
      {!isTemplateTwo && middleBanners?.length > 0 && (
        <section className="storefront-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col gap-6">
            {middleBanners.map(banner => (
              <a 
                key={banner.id} 
                href={banner.link || '#'} 
                className="block w-full overflow-hidden rounded-2xl shadow-sm hover:shadow-md transition-shadow group relative bg-gray-100"
              >
                {banner.image ? (
                  <div className="relative aspect-[2/1] w-full overflow-hidden sm:aspect-[3/1]">
                    <BlurFillImage
                      src={imageUrl(banner.image, banner.title)}
                      alt={banner.title || 'Banner'}
                      orientation={banner.image_orientation}
                      position={banner.image_position}
                      className="absolute inset-0 h-full w-full object-cover"
                      hoverScale
                    />
                  </div>
                ) : (
                  <div className="w-full h-[300px] flex items-center justify-center text-gray-400 bg-gray-200">
                    <span className="font-semibold">Banner Image (Upload via Admin)</span>
                  </div>
                )}
                {(banner.title || banner.subtitle || banner.button) && (
                  <div className={`absolute inset-0 flex flex-col justify-center p-5 sm:p-10 ${contentPositionClasses(banner.text_position)}`}>
                    <div className="max-w-xl rounded-xl bg-[#1f2430]/85 px-5 py-4 text-white shadow-lg sm:px-7 sm:py-6">
                      {banner.title && <h2 className="text-xl font-bold sm:text-3xl">{banner.title}</h2>}
                      {banner.subtitle && <p className="mt-2 text-sm text-white/90 sm:text-base">{banner.subtitle}</p>}
                      {banner.button && <span className="mt-4 inline-flex rounded-full bg-[#f15a24] px-5 py-2 text-xs font-bold uppercase tracking-wide text-white">{banner.button}</span>}
                    </div>
                  </div>
                )}
              </a>
            ))}
          </div>
        </section>
      )}

      {/* New Arrivals */}
      {!isTemplateTwo && overviewProductLists.new?.length > 0 && (
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
      {!isTemplateTwo && overviewProductLists.best?.length > 0 && (
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
