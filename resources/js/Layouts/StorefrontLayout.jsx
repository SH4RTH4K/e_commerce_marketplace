import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import CartDrawer from '@/Components/Storefront/CartDrawer';
import VisitorPopupModal from '@/Components/Storefront/VisitorPopupModal';
import ParticleText from '@/Components/Storefront/ParticleText';
import TrueFocus from '@/Components/Storefront/TrueFocus';
import { imageUrl, whatsappNumber } from '@/lib/utils';

function CartIcon({ count = 0, onClick }) {
  return (
    <button type="button" onClick={onClick} className="relative flex flex-col items-center justify-center gap-1 text-gray-700 hover:text-[#f15a24] transition-colors" aria-label="Cart">
      <div className="relative">
        <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h15l-1.5 9h-12L6 6Zm0 0-.7-3H3"/>
          <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
        </svg>
        {count > 0 && (
          <span className="absolute -top-2 -right-2 grid h-4 w-4 place-items-center rounded-full bg-[#f15a24] text-[9px] font-bold text-white">
            {count}
          </span>
        )}
      </div>
      <span className="text-[10px] font-semibold hidden md:block">Cart</span>
    </button>
  );
}

export default function StorefrontLayout({ children, title, description, activeCategory = null }) {
  const { props } = usePage();
  const { auth, app, flash, cartCount = 0, categories = [], hasFlashSale = false, promoText = '', promoLink = '', popup } = props;
  const hasPromoText = typeof promoText === 'string' && promoText.trim() !== '';
  const rawCategories = categories || [];
  const categoryList = Array.isArray(rawCategories)
    ? rawCategories
    : (typeof rawCategories === 'object' && rawCategories !== null ? Object.values(rawCategories) : []);
  const [menuOpen, setMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQ, setSearchQ] = useState('');
  const [cartOpen, setCartOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [chatOpen, setChatOpen] = useState(false);
  const chatRef = useRef(null);

  const chatSettings = app?.settings || {};
  const headerLogoHeight = Math.min(160, Math.max(24, Number(chatSettings.header_logo_height) || 48));
  const headerTitleSize = Math.min(40, Math.max(12, Number(chatSettings.header_title_size) || 18));
  const headerTaglineSize = Math.min(24, Math.max(8, Number(chatSettings.header_tagline_size) || 11));
  const siteName = app?.name || 'SHARTHAK';
  const headerSiteName = /^taqi\s*life$/i.test(siteName) ? 'T\u2009A\u2009Q\u2009I\u2003L\u2009I\u2009F\u2009E' : siteName;
  const focusSiteName = /^taqi\s*life$/i.test(siteName) ? 'TAQI LIFE' : siteName;
  const storefrontTemplate = chatSettings.storefront_template || 'template-2';
  const isTemplateOne = storefrontTemplate === 'template-1';
  const templateOneSiteNameStyle = chatSettings.template_1_site_name_style || 'default';
  const templateOneNavbarMenu = chatSettings.template_1_navbar_menu || 'coza';
  const templateOneShowSearch = chatSettings.template_1_show_search !== false;
  const whatsappDigits = whatsappNumber(chatSettings.whatsapp_number);
  const hasWhatsapp = Boolean(whatsappDigits);
  const hasCall = !!chatSettings.call_number;
  const hasMessenger = !!chatSettings.messenger_page;
  const showChat = chatSettings.chat_enabled !== false && (hasWhatsapp || hasCall || hasMessenger);
  const showCopyright = chatSettings.footer_copyright_enabled !== false;
  const copyrightText = chatSettings.footer_copyright_text?.trim() || `© ${new Date().getFullYear()} ${app?.name || 'Store'}. All rights reserved.`;
  const copyrightUrl = chatSettings.footer_copyright_url?.trim();
  const developerCredit = {
    enabled: chatSettings.footer_developer_enabled === true,
    label: chatSettings.footer_developer_label || 'Developed by',
    name: chatSettings.footer_developer_name?.trim(),
    url: chatSettings.footer_developer_url?.trim(),
  };
  const showDeveloperCredit = developerCredit.enabled && Boolean(developerCredit.name);
  const pageSeo = props.seo || {};
  const defaultSeoTitle = chatSettings.default_meta_title?.trim() || app?.name || 'Store';
  const seoTitle = pageSeo.title?.trim() || defaultSeoTitle;
  const seoDescription = pageSeo.description?.trim() || chatSettings.default_meta_description?.trim();
  const seoKeywords = pageSeo.keywords?.trim() || chatSettings.default_meta_keywords?.trim();
  const seoImage = pageSeo.image
    ? (/^https?:\/\//i.test(pageSeo.image) ? pageSeo.image : imageUrl(pageSeo.image))
    : null;
  const canonicalUrl = pageSeo.canonical || (typeof window !== 'undefined'
    ? `${window.location.origin}${window.location.pathname}`
    : null);
  const typography = chatSettings[`theme_typography_${storefrontTemplate.replace('-', '_')}`] || {};
  const typographyStyle = Object.entries(typography).reduce((styles, [area, values]) => ({
    ...styles,
    [`--theme-${area}-font`]: values.font,
    [`--theme-${area}-color`]: values.color,
    [`--theme-${area}-size`]: values.size,
    [`--theme-${area}-weight`]: values.weight,
    [`--theme-${area}-style`]: values.style,
    [`--theme-${area}-transform`]: values.transform,
  }), {});
  if (chatSettings.template_1_inner_page_banner) {
    typographyStyle['--template-1-page-title-banner'] = `url("${imageUrl(chatSettings.template_1_inner_page_banner)}")`;
  }

  const getMessengerUrl = (page) => {
    if (!page) return '#';
    if (page.startsWith('http')) return page;
    return `https://m.me/${page}`;
  };

  useEffect(() => {
    const handleScroll = () => setIsScrolled(window.scrollY > 20);
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    if (flash?.cart_open) {
      setCartOpen(true);
    }
  }, [flash]);

  useEffect(() => {
    const openCartDrawer = () => setCartOpen(true);
    window.addEventListener('open-cart-drawer', openCartDrawer);
    return () => window.removeEventListener('open-cart-drawer', openCartDrawer);
  }, []);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('desktop-search')?.focus();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const handleSearch = (e) => {
    e.preventDefault();
    window.location.href = `/shop${searchQ ? '?q=' + encodeURIComponent(searchQ) : ''}`;
  };

  const handleLogout = (e) => {
    e.preventDefault();
    router.post('/logout');
  };

  return (
    <>
    <div className="storefront-theme-root" style={typographyStyle}>
      <Head>
        {seoDescription && <meta head-key="description" name="description" content={seoDescription} />}
        {seoKeywords && <meta head-key="keywords" name="keywords" content={seoKeywords} />}
        <meta head-key="og:title" property="og:title" content={seoTitle} />
        {seoDescription && <meta head-key="og:description" property="og:description" content={seoDescription} />}
        <meta head-key="og:type" property="og:type" content={pageSeo.type || 'website'} />
        {canonicalUrl && <meta head-key="og:url" property="og:url" content={canonicalUrl} />}
        {seoImage && <meta head-key="og:image" property="og:image" content={seoImage} />}
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" content={seoTitle} />
        {seoDescription && <meta head-key="twitter:description" name="twitter:description" content={seoDescription} />}
        {seoImage && <meta head-key="twitter:image" name="twitter:image" content={seoImage} />}
        {pageSeo.robots && <meta head-key="robots" name="robots" content={pageSeo.robots} />}
        {canonicalUrl && <link head-key="canonical" rel="canonical" href={canonicalUrl} />}
      </Head>
      {title ? (
        <Head title={title}>{description && <meta name="description" content={description} />}</Head>
      ) : description ? (
        <Head><meta name="description" content={description} /></Head>
      ) : null}

      {/* Top bar */}
      <div className={`storefront-topbar hidden md:block text-xs border-b ${isTemplateOne ? 'template-1-topbar bg-[#222] text-[#b2b2b2] border-[#222]' : 'bg-[#f1f3f5] text-gray-600 border-gray-200'}`}>
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center h-9 gap-4">
          {isTemplateOne ? (
            <>
              {hasPromoText && (
                promoLink
                  ? <a href={promoLink} className="hover:text-[#717fe0] mr-auto font-medium" dangerouslySetInnerHTML={{ __html: promoText }} />
                  : <span className="mr-auto font-medium" dangerouslySetInnerHTML={{ __html: promoText }} />
              )}
              <div className="template-1-topbar-links ml-auto">
                <a href="/contact">Help &amp; FAQs</a>
                <a href={auth?.user ? '/account' : '/login'}>{auth?.user ? 'My Account' : 'Sign In'}</a>
              </div>
            </>
          ) : hasPromoText && (
            promoLink
              ? <a href={promoLink} className="hover:text-[#f15a24] mr-auto font-medium" dangerouslySetInnerHTML={{ __html: promoText }} />
              : <span className="mr-auto font-medium text-gray-700" dangerouslySetInnerHTML={{ __html: promoText }} />
          )}
        </div>
      </div>

      {/* Main header */}
      <header className={`storefront-header-area sticky z-40 transition-all duration-300 ${isTemplateOne ? 'template-1-header' : ''} ${isScrolled ? 'top-0 sm:top-4 px-0 sm:px-4 lg:px-8 mb-4 pointer-events-none' : 'top-0 px-0'}`}>
        <div className={`mx-auto max-w-7xl transition-all duration-300 ${isTemplateOne ? 'template-1-header-inner' : ''} ${isScrolled ? 'bg-white/70 sm:bg-white/60 backdrop-blur-xl sm:border border-white/50 sm:shadow-[0_8px_30px_rgb(0,0,0,0.04)] sm:rounded-[2rem] px-4 md:px-6 pointer-events-auto border-b sm:border-b-0 border-gray-100' : 'bg-white border-b border-gray-100 px-4 sm:px-6 lg:px-8'}`}>
          <div className="flex h-16 md:h-20 items-center gap-4 md:gap-6 lg:gap-10" style={{ minHeight: `${Math.max(64, headerLogoHeight + 16)}px` }}>
            
            {/* Mobile Hamburger */}
            <button onClick={() => setMenuOpen(true)} className="md:hidden p-2 -ml-2 text-gray-800" aria-label="Menu">
              <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" d="M4 7h16M4 12h16M4 17h16"/>
              </svg>
            </button>

            <a href="/" className={`flex items-center gap-2.5 shrink-0 md:mr-4 flex-1 md:flex-none justify-center md:justify-start ${isTemplateOne ? 'template-1-logo-block' : ''}`} aria-label={app?.name || 'Home'}>
              {app?.logo_url
                ? <img src={app.logo_url} alt="" className="w-auto max-w-[180px] object-contain" style={{ height: `${headerLogoHeight}px` }} />
                : null
              }
              <div className="hidden sm:flex min-w-0 flex-col text-left leading-tight">
                {isTemplateOne && templateOneSiteNameStyle === 'particle' ? (
                  <ParticleText
                    text={headerSiteName}
                    particleSize={1.7}
                    density={1}
                    color="#111827"
                    highlightColor="#c2410c"
                    scatter={28}
                    gatherDuration={700}
                    stagger={180}
                    pointerRepel={9}
                    repelRadius={44}
                    idleDrift={0.25}
                    trigger="hover"
                    fontSize={headerTitleSize}
                    fontWeight={900}
                    fontFamily="inherit"
                    glow={false}
                    align="left"
                    className="storefront-site-name-effect"
                    style={{ width: 'clamp(100px, 13vw, 180px)', height: `${Math.max(22, headerTitleSize * 1.35)}px` }}
                  />
                ) : isTemplateOne && templateOneSiteNameStyle === 'focus' ? (
                  <TrueFocus
                    sentence={focusSiteName}
                    blurAmount={0.65}
                    borderColor="#f15a24"
                    glowColor="rgb(241 90 36 / 0.4)"
                    animationDuration={0.55}
                    pauseBetweenAnimations={1.35}
                    fontSize={`${headerTitleSize}px`}
                    fontWeight={900}
                    textColor="#0b1c21"
                  />
                ) : (
                  <span className="truncate font-black tracking-tight text-[#0b1c21]" style={{ fontSize: `${headerTitleSize}px` }}>{siteName}</span>
                )}
                {app?.tagline && <span className="mt-0.5 truncate font-medium text-gray-500" style={{ fontSize: `${headerTaglineSize}px` }}>{app.tagline}</span>}
              </div>
            </a>

            {isTemplateOne && templateOneNavbarMenu === 'coza' && (
              <nav className="template-1-main-menu hidden lg:flex">
                <a href="/" className={typeof window !== 'undefined' && window.location.pathname === '/' ? 'is-active' : ''}>Home</a>
                <div className="template-1-nav-item template-1-nav-dropdown">
                  <a href="/" className={typeof window !== 'undefined' && window.location.pathname === '/' ? 'is-active' : ''}>Home</a>
                  <div className="template-1-nav-panel">
                    <a href="/">Homepage 1</a>
                    <a href="/home-02">Homepage 2</a>
                    <a href="/home-03">Homepage 3</a>
                  </div>
                </div>
                <a href="/shop">Shop</a>
                <a href="/shop?featured=1">Features</a>
                <a href="/track">Track Order</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
                <a href="/contact">Contact</a>
              </nav>
            )}

            {isTemplateOne && templateOneNavbarMenu === 'categories' && categoryList.length > 0 && (
              <nav className="template-1-main-menu hidden lg:flex">
                <a href="/" className={typeof window !== 'undefined' && window.location.pathname === '/' ? 'is-active' : ''}>Home</a>
                {categoryList.map(cat => (
                  <a key={cat.id} href={`/category/${cat.slug}`} className={activeCategory?.id === cat.id ? 'is-active' : ''}>{cat.name}</a>
                ))}
              </nav>
            )}

            {/* Desktop search */}
            <form onSubmit={handleSearch} className={`${isTemplateOne ? (templateOneShowSearch ? 'template-1-header-search hidden md:flex' : 'hidden') : 'hidden md:flex max-w-2xl'} flex-1 min-w-0 group relative`}>
              <div className="flex w-full items-center rounded-full bg-white/80 border border-gray-200/60 focus-within:border-[#f15a24] focus-within:ring-1 focus-within:ring-[#f15a24]/20 overflow-hidden transition-all shadow-sm h-11 pl-4 pr-2">
                <svg className="h-5 w-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="7"/><path strokeLinecap="round" d="m20 20-3-3"/>
                </svg>
                <input id="desktop-search" type="text" value={searchQ} onChange={e => setSearchQ(e.target.value)}
                  placeholder="What are you looking for?"
                  className="flex-1 h-full px-3 text-sm bg-transparent border-none focus:outline-none focus:ring-0 text-gray-800 placeholder-gray-400" />
                
                <div className="hidden lg:flex items-center gap-1 shrink-0 bg-white px-2 py-1.5 rounded-full border border-gray-100 shadow-sm">
                  <kbd className="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold text-gray-500 bg-gray-50 border border-gray-200 rounded">⌘</kbd>
                  <kbd className="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold text-gray-500 bg-gray-50 border border-gray-200 rounded">K</kbd>
                </div>
              </div>
            </form>

            {/* Right icons (Desktop & Mobile adjustments) */}
            <div className={`flex items-center gap-4 md:gap-6 shrink-0 ml-auto md:ml-0 ${isTemplateOne ? 'template-1-header-actions' : ''}`}>
              
              <button onClick={() => setSearchOpen(!searchOpen)} className="md:hidden p-2 text-gray-800" aria-label="Search">
                <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="7"/><path strokeLinecap="round" d="m20 20-3-3"/>
                </svg>
              </button>

              <Link href="/track" className="hidden md:flex flex-col items-center justify-center gap-1 text-gray-700 hover:text-[#f15a24] transition-colors group">
                <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span className="text-[10px] font-semibold">Track Order</span>
              </Link>

              {/* Account dropdown */}
              {auth?.user ? (
                <div className="hidden md:flex items-center gap-3">
                  <Link href="/account" className="flex flex-col items-center justify-center gap-1 text-gray-700 hover:text-[#f15a24] transition-colors" aria-label="My Account">
                    <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                      <circle cx="12" cy="8" r="4"/><path strokeLinecap="round" strokeLinejoin="round" d="M4 21v-1a8 8 0 0116 0v1"/>
                    </svg>
                    <span className="text-[10px] font-semibold">My Account</span>
                  </Link>
                  <button type="button" onClick={handleLogout} className="flex flex-col items-center justify-center gap-1 text-gray-700 hover:text-red-600 transition-colors" aria-label="Logout" title="Logout">
                    <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    <span className="text-[10px] font-semibold">Logout</span>
                  </button>
                  <div className="absolute right-0 top-full pt-2 hidden group-hover:block z-50">
                    <div className="bg-white rounded-xl shadow-xl border border-gray-100 min-w-[180px] py-2">
                      <Link href="/account" className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">My Account</Link>
                      <Link href="/account?tab=orders" className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">My Orders</Link>
                      <hr className="my-1 border-gray-100"/>
                      <button onClick={handleLogout} className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">Logout</button>
                    </div>
                  </div>
                </div>
              ) : (
                <Link href="/login" className="hidden md:flex flex-col items-center justify-center gap-1 text-gray-700 hover:text-[#f15a24] transition-colors">
                  <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/><path strokeLinecap="round" strokeLinejoin="round" d="M4 21v-1a8 8 0 0116 0v1"/>
                  </svg>
                  <span className="text-[10px] font-semibold">Login</span>
                </Link>
              )}

              <Link href="/wishlist" className="hidden md:flex flex-col items-center justify-center gap-1 text-gray-700 hover:text-[#f15a24] transition-colors">
                <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span className="text-[10px] font-semibold">Wishlist</span>
              </Link>

              <CartIcon count={cartCount} onClick={() => setCartOpen(true)} />
            </div>
          </div>
        </div>

        {/* Mobile search drop-down */}
        {searchOpen && (
          <div className="md:hidden border-t border-gray-100 bg-white px-4 py-3 shadow-sm">
            <form onSubmit={handleSearch} className="flex items-center rounded-lg bg-gray-100/80 border border-gray-200 focus-within:border-[#f15a24] focus-within:bg-white overflow-hidden transition-all">
              <input type="text" value={searchQ} onChange={e => setSearchQ(e.target.value)}
                placeholder={`Search in ${app?.name || 'store'}...`}
                className="flex-1 min-w-0 h-10 px-4 text-sm bg-transparent border-none focus:outline-none focus:ring-0" autoFocus />
              <button type="submit" className="shrink-0 h-10 px-4 text-gray-500 hover:text-[#f15a24] flex items-center justify-center">
                <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="7"/><path strokeLinecap="round" d="m20 20-3-3"/>
                </svg>
              </button>
            </form>
          </div>
        )}

      </header>

      {/* Category nav (Dark Green) */}
      {categoryList.length > 0 && !isTemplateOne && (
        <div className={`hidden lg:block ${isTemplateOne ? 'template-1-category-nav bg-white border-b border-gray-100' : 'bg-[#0A2A22]'}`}>
          <div className={`mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-wrap items-center gap-x-3 lg:gap-x-4 xl:gap-x-5 gap-y-1 min-h-[44px] text-[13px] font-semibold ${isTemplateOne ? 'text-[#222]' : 'text-white'}`}>
            {categoryList.map(cat => (
              <div key={cat.id} className="relative group h-full flex items-center shrink-0">
                <a href={`/category/${cat.slug}`}
                  className={`flex items-center gap-1.5 whitespace-nowrap hover:text-[#f15a24] transition-colors py-3 ${activeCategory?.id === cat.id ? 'text-[#f15a24]' : ''}`}>
                  {cat.name}
                  {cat.children && cat.children.length > 0 && (
                    <svg className="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7"/></svg>
                  )}
                </a>
                
                {cat.children && cat.children.length > 0 && (
                  <div className="absolute left-0 top-[100%] hidden group-hover:block z-[999] min-w-[220px] pt-1">
                    <div className="bg-white rounded-xl shadow-xl border border-gray-100 py-2 flex flex-col relative before:absolute before:-top-4 before:left-0 before:w-full before:h-4 before:bg-transparent">
                      {(Array.isArray(cat.children) ? cat.children : Object.values(cat.children)).map(child => (
                        <a key={child.id} href={`/category/${child.slug}`} className="px-4 py-2.5 text-[13px] font-semibold text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">
                          {child.name}
                        </a>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            ))}

            {hasFlashSale && (
              <a href="/shop?flash=1" className="flex items-center gap-1.5 text-white whitespace-nowrap shrink-0 hover:text-[#f15a24] transition-colors py-3">
                ⚡ Offer Zone
              </a>
            )}
          </div>
        </div>
      )}

      {/* Flash status */}
      {flash?.status && (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
          <div className="bg-white border border-green-200 text-green-700 text-sm font-semibold px-4 py-3 rounded-lg shadow-sm flex items-center gap-2">
            <svg className="h-5 w-5 text-green-500" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
            {flash.status}
          </div>
        </div>
      )}

      {/* Mobile menu overlay */}
      {menuOpen && (
        <div className="fixed inset-0 z-[60] lg:hidden">
          <div className="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onClick={() => setMenuOpen(false)} />
          <div className="absolute left-0 top-0 bottom-0 w-[85%] max-w-sm bg-white flex flex-col shadow-2xl overflow-hidden">
            <div className="h-16 flex items-center justify-between px-5 border-b bg-gray-50">
              <div className="flex items-center gap-2 font-black text-xl text-[#f15a24]">
                {app?.logo_url ? <img src={app.logo_url} alt="" className="h-8 w-auto object-contain" /> : (app?.name || 'SHARTHAK')}
              </div>
              <button onClick={() => setMenuOpen(false)} className="p-2 -mr-2 text-gray-500 hover:text-gray-900 rounded-full hover:bg-gray-200 transition-colors">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
            
            <nav className="flex-1 overflow-y-auto py-2">
              <div className="px-4 py-2 text-xs font-bold text-gray-400 uppercase tracking-wider">Main Menu</div>
              <a href="/" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Home</a>
              <a href="/shop" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Shop</a>
              {isTemplateOne && templateOneNavbarMenu === 'coza' && (
                <>
                  <a href="/shop?featured=1" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Features</a>
                  <a href="/blog" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Blog</a>
                  <a href="/about" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">About</a>
                  <a href="/contact" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Contact</a>
                </>
              )}
              
              {categoryList.length > 0 && (
                <>
                  <div className="px-4 py-4 mt-2 text-xs font-bold text-gray-400 uppercase tracking-wider border-t border-gray-100">Categories</div>
                  {categoryList.map(cat => (
                    <a key={cat.id} href={`/category/${cat.slug}`}
                      className="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors rounded-lg mx-2">
                      <span className="w-9 h-9 shrink-0 rounded-xl overflow-hidden bg-gray-100 ring-1 ring-gray-200 flex items-center justify-center">
                        {cat.image ? (
                          <img src={imageUrl(cat.image)} alt={cat.name} className="w-full h-full object-cover" />
                        ) : cat.icon ? (
                          <span className="text-lg">{cat.icon}</span>
                        ) : (
                          <span className="text-lg">📦</span>
                        )}
                      </span>
                      <span>{cat.name}</span>
                    </a>
                  ))}
                </>
              )}

              <div className="px-4 py-4 mt-2 text-xs font-bold text-gray-400 uppercase tracking-wider border-t border-gray-100">Support</div>
              <a href="/contact" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Contact Us</a>
              <a href="/track" className="flex items-center px-6 py-3 text-sm font-medium text-gray-700 hover:text-[#f15a24] hover:bg-orange-50 transition-colors">Track Order</a>
            </nav>

            {!auth?.user ? (
              <div className="p-5 border-t border-gray-100 bg-gray-50">
                <a href="/login" className="flex items-center justify-center w-full py-3 bg-[#f15a24] hover:bg-[#e04708] text-white font-bold rounded-xl transition-colors shadow-sm">Sign In / Register</a>
              </div>
            ) : (
              <div className="p-5 border-t border-gray-100 bg-gray-50 space-y-3">
                <a href="/account" className="flex items-center justify-center w-full py-2.5 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl hover:border-[#f15a24] hover:text-[#f15a24] transition-colors">My Account</a>
                <button onClick={handleLogout} className="flex w-full items-center justify-center py-2.5 bg-red-50 text-red-600 font-bold rounded-xl hover:bg-red-100 transition-colors">Logout</button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Main content - pad bottom on mobile for sticky nav */}
      <main className={`storefront-main-area min-h-screen pb-[70px] md:pb-0 ${isTemplateOne ? 'template-1-storefront' : 'bg-[#f8f9fa]'}`}>
        {children}
      </main>

      {/* Footer */}
      {isTemplateOne ? (
        <footer className="storefront-footer-area template-1-footer">
          <div className="template-1-container">
            <div className="template-1-footer-grid">
              <div>
                <h4>Categories</h4>
                <ul>
                  {categoryList.slice(0, 4).map(cat => (
                    <li key={cat.id}><a href={`/category/${cat.slug}`}>{cat.name}</a></li>
                  ))}
                  <li><a href="/shop">All Products</a></li>
                </ul>
              </div>
              <div>
                <h4>Help</h4>
                <ul>
                  <li><a href="/track">Track Order</a></li>
                  <li><a href="/refund-policy">Returns</a></li>
                  <li><a href="/contact">Contact Us</a></li>
                  <li><a href="/privacy">Privacy Policy</a></li>
                  {chatSettings.shipping_page_enabled !== false && (
                    <li><a href="/shipping">Shipping</a></li>
                  )}
                  <li><a href="/contact">FAQs &amp; Contact</a></li>
                </ul>
              </div>
              <div>
                <h4>Get In Touch</h4>
                <p>{app?.settings?.footer_text || app?.footer_text || 'Any questions? Let us know in store support or contact us online.'}</p>
                <div className="template-1-footer-social">
                  {app?.settings?.facebook_url?.trim() && (
                    <a href={app.settings.facebook_url.trim()} target="_blank" rel="noreferrer" aria-label="Facebook" title="Facebook">
                      <svg className="template-1-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-1.56 19.88v-7.04H7.9v-2.84h2.54V9.84c0-2.51 1.5-3.9 3.78-3.9 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.84h-2.33v7.04A10 10 0 0 0 12 2Z" /></svg>
                    </a>
                  )}
                  {app?.settings?.instagram_url?.trim() && (
                    <a href={app.settings.instagram_url.trim()} target="_blank" rel="noreferrer" aria-label="Instagram" title="Instagram">
                      <svg className="template-1-social-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.4" cy="6.6" r=".8" fill="currentColor" stroke="none" /></svg>
                    </a>
                  )}
                  {app?.settings?.twitter_url?.trim() && (
                    <a href={app.settings.twitter_url.trim()} target="_blank" rel="noreferrer" aria-label="X" title="X">
                      <svg className="template-1-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.22-6.83-5.98 6.83H1.65l7.73-8.84L1.22 2.25h6.83l4.72 6.24 5.47-6.24Zm-1.16 17.52h1.83L7.08 4.12H5.12l11.96 15.65Z" /></svg>
                    </a>
                  )}
                  {app?.settings?.youtube_url?.trim() && (
                    <a href={app.settings.youtube_url.trim()} target="_blank" rel="noreferrer" aria-label="YouTube" title="YouTube">
                      <svg className="template-1-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1C24 15.9 24 12 24 12s0-3.9-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z" /></svg>
                    </a>
                  )}
                </div>
              </div>
              <div>
                <h4>Newsletter</h4>
                <form onSubmit={e => e.preventDefault()} className="template-1-newsletter">
                  <input type="email" placeholder="email@example.com" />
                  <button type="submit">Subscribe</button>
                </form>
              </div>
            </div>
            <div className="template-1-footer-bottom flex flex-wrap items-center justify-between gap-3">
              {showCopyright && (copyrightUrl ? (
                <a href={copyrightUrl} target="_blank" rel="noopener noreferrer" className="hover:text-white hover:underline">{copyrightText}</a>
              ) : <p>{copyrightText}</p>)}
              {showDeveloperCredit && (developerCredit.url ? (
                <a href={developerCredit.url} target="_blank" rel="noopener noreferrer" className="text-sm hover:text-white hover:underline">{developerCredit.label} {developerCredit.name}</a>
              ) : (
                <p className="text-sm">{developerCredit.label} {developerCredit.name}</p>
              ))}
            </div>
          </div>
        </footer>
      ) : (
      <footer className="storefront-footer-area bg-transparent pb-8 pt-4 mb-[60px] md:mb-0">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="bg-white rounded-3xl border border-gray-100 shadow-sm px-6 sm:px-10 py-12 lg:py-16">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">
              <div className="lg:col-span-2">
                <a href="/" className="flex items-center gap-2 mb-5 inline-block">
                  {app?.logo_url
                    ? <img src={app.logo_url} alt="" className="h-10 w-auto object-contain" />
                    : <span className="text-[#f15a24] font-black text-xl">{app?.name}</span>
                  }
                </a>
                <p className="text-gray-500 text-sm leading-relaxed max-w-md">
                  {app?.settings?.footer_text || app?.footer_text || 'Your one-stop marketplace for quality products at great prices. We deliver the best items directly to your doorstep with care.'}
                </p>
              </div>
              <div>
                <h4 className="font-bold text-gray-900 text-base mb-5 uppercase tracking-wider">Quick Links</h4>
                <ul className="space-y-3 text-sm text-gray-500">
                  <li><a href="/shop" className="hover:text-[#f15a24] transition-colors">Shop All</a></li>
                  <li><a href="/contact" className="hover:text-[#f15a24] transition-colors">Contact Us</a></li>
                  <li><a href="/track" className="hover:text-[#f15a24] transition-colors">Track Order</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-bold text-gray-900 text-base mb-5 uppercase tracking-wider">Legal & Policy</h4>
                <ul className="space-y-3 text-sm text-gray-500">
                  <li><a href="/terms" className="hover:text-[#f15a24] transition-colors">Terms & Conditions</a></li>
                  <li><a href="/privacy" className="hover:text-[#f15a24] transition-colors">Privacy Policy</a></li>
                  <li><a href="/refund-policy" className="hover:text-[#f15a24] transition-colors">Refund Policy</a></li>
                </ul>
              </div>
            </div>
            <div className="border-t border-gray-100 mt-12 pt-8 text-center md:text-left flex flex-col md:flex-row items-center justify-between gap-4">
              <p className="text-gray-400 text-sm font-medium">
                &copy; {new Date().getFullYear()} {app?.name || 'SHARTHAK'}. All rights reserved.
              </p>
              <a href="https://sharthak.com/" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 border border-gray-100 bg-white rounded-full px-3 py-1.5 shadow-sm hover:shadow-md hover:border-gray-200 transition-all">
                <span className="text-[11px] font-extrabold text-slate-500 tracking-wider">DESIGNED BY</span>
                <span className="bg-[#f15a24] text-white text-[11px] font-extrabold px-3 py-1 rounded-full tracking-wider">SHARTHAK</span>
              </a>
              {showCopyright && (copyrightUrl ? (
                <a href={copyrightUrl} target="_blank" rel="noopener noreferrer" className="text-gray-400 text-sm font-medium hover:text-[#f15a24] hover:underline">{copyrightText}</a>
              ) : <p className="text-gray-400 text-sm font-medium">{copyrightText}</p>)}
              {showDeveloperCredit && (developerCredit.url ? (
                <a href={developerCredit.url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 border border-gray-100 bg-white rounded-full px-3 py-1.5 shadow-sm hover:shadow-md hover:border-gray-200 transition-all">
                  <span className="text-[11px] font-extrabold text-slate-500 tracking-wider">{developerCredit.label.toUpperCase()}</span>
                  <span className="bg-[#f15a24] text-white text-[11px] font-extrabold px-3 py-1 rounded-full tracking-wider">{developerCredit.name}</span>
                </a>
              ) : (
                <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{developerCredit.label} {developerCredit.name}</p>
              ))}
            </div>
          </div>
        </div>
      </footer>
      )}

      {/* Sticky Bottom Mobile Menu */}
      <div className="md:hidden fixed bottom-0 left-0 right-0 bg-[#f15a24] text-white flex justify-around items-center h-[60px] z-[45] shadow-[0_-4px_10px_rgba(0,0,0,0.1)] pb-safe">
        <a href="/" className="flex flex-col items-center justify-center w-full h-full text-white/90 hover:text-white hover:bg-black/10 transition-colors">
          <svg className="h-5 w-5 mb-1" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          <span className="text-[9px] font-bold tracking-wide">Home</span>
        </a>
        <button onClick={() => setMenuOpen(true)} className="flex flex-col items-center justify-center w-full h-full text-white/90 hover:text-white hover:bg-black/10 transition-colors">
          <svg className="h-5 w-5 mb-1" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
          <span className="text-[9px] font-bold tracking-wide">Menu</span>
        </button>
        <button onClick={() => setCartOpen(true)} className="flex flex-col items-center justify-center w-full h-full text-white/90 hover:text-white hover:bg-black/10 transition-colors relative">
          <div className="relative">
            <svg className="h-5 w-5 mb-1" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            {cartCount > 0 && <span className="absolute -top-1 -right-2 bg-black text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">{cartCount}</span>}
          </div>
          <span className="text-[9px] font-bold tracking-wide">Cart</span>
        </button>
        <button onClick={() => { setSearchOpen(true); window.scrollTo({top:0, behavior:'smooth'}); }} className="flex flex-col items-center justify-center w-full h-full text-white/90 hover:text-white hover:bg-black/10 transition-colors">
          <svg className="h-5 w-5 mb-1" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <span className="text-[9px] font-bold tracking-wide">Search</span>
        </button>
        <a href={auth?.user ? '/account' : '/login'} className="flex flex-col items-center justify-center w-full h-full text-white/90 hover:text-white hover:bg-black/10 transition-colors">
          <svg className="h-5 w-5 mb-1" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <span className="text-[9px] font-bold tracking-wide">Account</span>
        </a>
      </div>

      {/* Cart Drawer */}
      <CartDrawer isOpen={cartOpen} onClose={() => setCartOpen(false)} />

      {/* Floating Live Chat Widget */}
      {showChat && (
        <div ref={chatRef} className="fixed bottom-[80px] right-4 md:bottom-6 md:right-6 z-[999] flex flex-col items-end gap-2">
          {/* Action buttons — shown when chatOpen */}
          <div
            style={{
              display: 'flex',
              flexDirection: 'column',
              gap: '10px',
              alignItems: 'flex-end',
              overflow: 'hidden',
              maxHeight: chatOpen ? '300px' : '0',
              opacity: chatOpen ? 1 : 0,
              transition: 'max-height 0.35s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease',
            }}
          >
            {/* WhatsApp */}
            {hasWhatsapp && (
              <a
                href={`https://wa.me/${whatsappDigits}`}
                target="_blank"
                rel="noopener noreferrer"
                title="Chat on WhatsApp"
                className="group flex items-center gap-2"
              >
                <span className="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">WhatsApp</span>
                <div className="w-12 h-12 rounded-full shadow-lg flex items-center justify-center cursor-pointer transition-transform hover:scale-110 active:scale-95"
                  style={{ background: 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)' }}>
                  <svg viewBox="0 0 32 32" className="w-6 h-6" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 2C8.28 2 2 8.28 2 16c0 2.44.65 4.73 1.78 6.72L2 30l7.52-1.74A13.93 13.93 0 0016 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm0 25.5a11.43 11.43 0 01-5.86-1.62l-.42-.25-4.46 1.03 1.06-4.35-.28-.45A11.47 11.47 0 014.5 16C4.5 9.6 9.6 4.5 16 4.5S27.5 9.6 27.5 16 22.4 27.5 16 27.5zm6.29-8.56c-.34-.17-2.03-1-2.35-1.11-.32-.12-.55-.17-.78.17-.23.34-.9 1.11-1.1 1.34-.2.23-.4.26-.74.09-.34-.17-1.44-.53-2.74-1.69-1.01-.9-1.7-2.02-1.9-2.36-.2-.34-.02-.52.15-.69.15-.15.34-.4.51-.6.17-.2.23-.34.34-.57.12-.23.06-.43-.03-.6-.09-.17-.78-1.88-1.07-2.57-.28-.68-.57-.58-.78-.59h-.66c-.23 0-.6.09-.91.43-.31.34-1.2 1.17-1.2 2.86s1.23 3.32 1.4 3.55c.17.23 2.42 3.7 5.87 5.19.82.35 1.46.56 1.96.72.82.26 1.57.22 2.16.13.66-.1 2.03-.83 2.32-1.63.29-.8.29-1.49.2-1.63-.09-.14-.32-.23-.66-.4z"/>
                  </svg>
                </div>
              </a>
            )}
            {/* Call */}
            {hasCall && (
              <a
                href={`tel:${chatSettings.call_number}`}
                title="Call us"
                className="group flex items-center gap-2"
              >
                <span className="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Call Now</span>
                <div className="w-12 h-12 rounded-full shadow-lg flex items-center justify-center cursor-pointer transition-transform hover:scale-110 active:scale-95"
                  style={{ background: 'linear-gradient(135deg, #34d399 0%, #059669 100%)' }}>
                  <svg viewBox="0 0 24 24" className="w-6 h-6" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V20a1 1 0 01-1 1C9.61 21 3 14.39 3 6.5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.58a1 1 0 01-.25 1.01l-2.2 2.2z"/>
                  </svg>
                </div>
              </a>
            )}
            {/* Messenger */}
            {hasMessenger && (
              <a
                href={getMessengerUrl(chatSettings.messenger_page)}
                target="_blank"
                rel="noopener noreferrer"
                title="Chat on Messenger"
                className="group flex items-center gap-2"
              >
                <span className="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Messenger</span>
                <div className="w-12 h-12 rounded-full shadow-lg flex items-center justify-center cursor-pointer transition-transform hover:scale-110 active:scale-95"
                  style={{ background: 'linear-gradient(135deg, #0084ff 0%, #a033ff 100%)' }}>
                  <svg viewBox="0 0 32 32" className="w-6 h-6" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 2C8.27 2 2 7.8 2 14.93c0 3.9 1.85 7.38 4.76 9.76V30l4.59-2.52A14.86 14.86 0 0016 27.86c7.73 0 14-5.8 14-12.93C30 7.8 23.73 2 16 2zm1.41 17.41l-3.57-3.8-6.97 3.8 7.66-8.13 3.66 3.8 6.88-3.8-7.66 8.13z"/>
                  </svg>
                </div>
              </a>
            )}
          </div>

          {/* Main toggle button */}
          <button
            onClick={() => setChatOpen(o => !o)}
            title={chatOpen ? 'Close' : 'Chat with us'}
            className="w-14 h-14 rounded-full shadow-2xl flex items-center justify-center border-4 border-white"
            style={{
              background: chatOpen
                ? 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'
                : 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)',
              transform: chatOpen ? 'rotate(45deg)' : 'rotate(0deg)',
              transition: 'background 0.3s ease, transform 0.35s cubic-bezier(0.4,0,0.2,1)',
            }}
          >
            {chatOpen ? (
              <svg viewBox="0 0 24 24" className="w-6 h-6" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 18L18 6M6 6l12 12"/>
              </svg>
            ) : (
              <svg viewBox="0 0 24 24" className="w-7 h-7" fill="white" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 2H4C2.9 2 2 2.9 2 4v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/>
              </svg>
            )}
          </button>
        </div>
      )}

      {/* ── Marketing & Notice Visitor Popup ── */}
      <VisitorPopupModal popup={popup} />
    </div>
    </>
  );
}
