import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

/* ─── Nav structure ──────────────────────────────────────────────── */
const navGroups = [
  {
    label: 'OVERVIEW',
    items: [
      {
        key: 'dashboard', label: 'Dashboard', href: '/admin', pattern: /^\/admin$/,
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
      },
      {
        key: 'analytics', label: 'Analytics & Reports', href: '/admin/analytics', pattern: /^\/admin\/analytics/,
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
      },
    ],
  },
  {
    label: 'STORE & PRODUCTS',
    collapsible: [
      {
        key: 'products-group', label: 'Products & Stock', defaultOpen: true,
        icon: 'M12 3l8 4.5v9L12 21l-8-4.5v-9z',
        children: [
          { key: 'products',     label: 'All Products',        href: '/admin/products',        pattern: /^\/admin\/products$/, perm: 'products.view' },
          { key: 'product-add',  label: 'Add New Product',     href: '/admin/products/create', pattern: /^\/admin\/products\/create/, perm: 'products.create' },
          { key: 'inventory',    label: 'Inventory / Stock',   href: '/admin/inventory',       pattern: /^\/admin\/inventory/, perm: 'products.view' },
          { key: 'categories',   label: 'Categories',          href: '/admin/categories',      pattern: /^\/admin\/categories/, perm: 'catalog.manage' },
        ],
      },
    ],
  },
  {
    label: 'SALES',
    collapsible: [
      {
        key: 'orders-group', label: 'Orders', defaultOpen: false,
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        badge: 'pending_orders',
        children: [
          { key: 'orders',       label: 'All Orders',       href: '/admin/orders',              pattern: /^\/admin\/orders$/, badge: 'pending_orders', perm: 'orders.view' },
          { key: 'order-create', label: 'Create Order',     href: '/admin/orders/create',       pattern: /^\/admin\/orders\/create/, perm: 'orders.view' },
          { key: 'abandoned',    label: 'Abandoned Carts',  href: '/admin/abandoned-checkouts', pattern: /^\/admin\/abandoned-checkouts/, perm: 'customers.manage' },
        ],
      },
      {
        key: 'crm-group', label: 'Customer CRM', defaultOpen: false,
        icon: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
        children: [
          { key: 'customers', label: 'All Customers', href: '/admin/customers', pattern: /^\/admin\/customers/, perm: 'customers.manage' },
          { key: 'reviews',   label: 'Customer Reviews',href: '/admin/reviews', pattern: /^\/admin\/reviews/, badge: 'pending_reviews', perm: 'reviews.manage' },
        ],
      },
    ],
  },
  {
    label: 'STOREFRONT UI',
    collapsible: [
      {
        key: 'storefront-ui-group', label: 'Storefront & Design', defaultOpen: true,
        icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z',
        children: [
          { key: 'menu-ordering',   label: 'Navbar & Menu Ordering', href: '/admin/categories/ordering', pattern: /^\/admin\/categories\/ordering/, perm: 'catalog.manage' },
          { key: 'banners',         label: 'Banners & Sliders',   href: '/admin/banners',            pattern: /^\/admin\/banners/, perm: 'catalog.manage' },
          { key: 'landing',         label: 'Landing Pages',       href: '/admin/landing-pages',      pattern: /^\/admin\/landing-pages/, perm: 'marketing.manage' },
          { key: 'flash-sale',      label: 'Flash Sale UI',       href: '/admin/flash-sale',         pattern: /^\/admin\/flash-sale/, perm: 'marketing.manage' },
          { key: 'popup-notif',     label: 'Popup & Announcement',href: '/admin/popup-notification', pattern: /^\/admin\/popup-notification/, perm: 'marketing.manage' },
          { key: 'features',        label: 'Features & Badges',   href: '/admin/features',           pattern: /^\/admin\/features/, perm: 'catalog.manage' },
          { key: 'product-button',  label: 'Quick Order & CTA Buttons', href: '/admin/product-button', pattern: /^\/admin\/product-button/, perm: 'settings.manage' },
          { key: 'contact-fields',  label: 'Contact Form Fields', href: '/admin/contact-fields',     pattern: /^\/admin\/contact-fields/, perm: 'settings.manage' },
        ],
      },
    ],
  },
  {
    label: 'MARKETING',
    collapsible: [
      {
        key: 'marketing-group', label: 'Marketing & Comms', defaultOpen: false,
        icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
        children: [
          { key: 'coupons',     label: 'Coupons & Discounts', href: '/admin/coupons',  pattern: /^\/admin\/coupons/, perm: 'marketing.manage' },
          { key: 'messages',    label: 'Contact Messages',    href: '/admin/messages', pattern: /^\/admin\/messages/, badge: 'new_messages', perm: 'messages.manage' },
        ],
      },
    ],
  },
  {
    label: 'INTEGRATIONS',
    collapsible: [
      {
        key: 'dropshipping-group', label: 'Dropshipping', defaultOpen: true,
        icon: 'M12 3l8 4.5v9L12 21l-8-4.5v-9z',
        children: [
          { key: 'dropshipping', label: 'Dropshipping', href: '/admin/dropshipping', pattern: /^\/admin\/dropshipping\/?$/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-settings', label: 'API Settings', href: '/admin/dropshipping/settings', pattern: /^\/admin\/dropshipping\/settings/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-pricing', label: 'Pricing Rules', href: '/admin/dropshipping/pricing', pattern: /^\/admin\/dropshipping\/pricing/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-categories', label: 'Category Mapping', href: '/admin/dropshipping/categories', pattern: /^\/admin\/dropshipping\/categories/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-variations', label: 'Variation Mapping', href: '/admin/dropshipping/variations', pattern: /^\/admin\/dropshipping\/variations/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-products', label: 'Supplier Products', href: '/admin/dropshipping/products', pattern: /^\/admin\/dropshipping\/products/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-imported', label: 'Imported Products', href: '/admin/dropshipping/imported', pattern: /^\/admin\/dropshipping\/imported/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-media', label: 'Media Cleanup', href: '/admin/dropshipping/media', pattern: /^\/admin\/dropshipping\/media/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-storage', label: 'Storage Usage', href: '/admin/dropshipping/storage', pattern: /^\/admin\/dropshipping\/storage/, perm: 'dropshipping.manage', feature: 'dropshipping' },
          { key: 'dropshipping-migrations', label: 'Database Migration', href: '/admin/dropshipping/migrations', pattern: /^\/admin\/dropshipping\/migrations/, perm: 'dropshipping.manage', feature: 'dropshipping' },
        ],
      },
    ],
  },
  {
    label: 'SYSTEM',
    collapsible: [
      {
        key: 'media', label: 'Media Manager', href: '/admin/media', pattern: /^\/admin\/media/,
        icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        perm: 'media.manage',
      },
      {
        key: 'system-health', label: 'System Health', href: '/admin/system-health', pattern: /^\/admin\/system-health/,
        icon: 'M4 4h16v16H4zM8 16v-3M12 16V8M16 16v-6',
        perm: 'settings.manage',
      },
      {
        key: 'fake-order-guard-group', label: 'Fraud Guard', defaultOpen: false,
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        children: [
          { key: 'fake-order-guard', label: 'Overview',               href: '/admin/fake-order-guard',                pattern: /^\/admin\/fake-order-guard\/?(?:\?.*)?$/, perm: 'fraud_guard.manage' },
          { key: 'fog-guards',       label: 'Guard Modules',          href: '/admin/fake-order-guard/guards',         pattern: /^\/admin\/fake-order-guard\/guards(?:\?|$)/, perm: 'fraud_guard.manage' },
          { key: 'fog-limits',       label: 'Timers & Limits',        href: '/admin/fake-order-guard/limits',         pattern: /^\/admin\/fake-order-guard\/limits(?:\?|$)/, perm: 'fraud_guard.manage' },
          { key: 'fog-bd-courier',   label: 'BD Courier Integration', href: '/admin/fake-order-guard/bd-courier',     pattern: /^\/admin\/fake-order-guard\/bd-courier(?:\?|$)/, perm: 'fraud_guard.manage' },
          { key: 'fog-orders',       label: 'Blocked-source Orders',  href: '/admin/fake-order-guard/blocked-orders', pattern: /^\/admin\/fake-order-guard\/blocked-orders(?:\?|$)/, perm: 'fraud_guard.manage' },
          { key: 'blocked-ips',       label: 'Blocked IPs',         href: '/admin/blocked-ips',       pattern: /^\/admin\/blocked-ips(?:\/|$)/,       perm: 'fraud_guard.manage' },
          { key: 'blocked-devices',   label: 'Blocked Devices',     href: '/admin/blocked-devices',   pattern: /^\/admin\/blocked-devices(?:\/|$)/,   perm: 'fraud_guard.manage' },
          { key: 'blocked-phones',    label: 'Blocked Phones',      href: '/admin/blocked-phones',    pattern: /^\/admin\/blocked-phones(?:\/|$)/,    perm: 'fraud_guard.manage' },
        ],
      },
      {
        key: 'admins', label: 'Staff & Roles', href: '/admin/admins', pattern: /^\/admin\/admins/,
        icon: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1',
        perm: 'admins.manage',
      },
      {
        key: 'settings', label: 'Settings', href: '/admin/settings', pattern: /^\/admin\/settings/,
        icon: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z',
        perm: 'settings.manage',
      },
    ],
  },
];

/* ─── Icon helper ─────────────────────────────────────────────────── */
function NavIcon({ d, size = 16 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
      {d.split('M').filter(Boolean).map((seg, i) => (
        <path key={i} d={`M${seg}`} />
      ))}
    </svg>
  );
}

/* ─── Chevron ─────────────────────────────────────────────────────── */
function Chevron({ open }) {
  return (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"
      className={`shrink-0 transition-transform duration-200 ${open ? 'rotate-90' : ''}`}>
      <path d="M9 18l6-6-6-6" />
    </svg>
  );
}

/* ─── Collapsible nav item ────────────────────────────────────────── */
function CollapsibleItem({ item, isActive, admin_badges, hasPerm, featureEnabled }) {
  if (!item.children || item.children.length === 0) {
    if (!hasPerm(item.perm)) return null;
    const active = isActive(item.pattern);
    const badge = item.badge && admin_badges ? admin_badges[item.badge] : 0;
    return (
      <a key={item.key} href={item.href}
        className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm mb-0.5 transition-all font-medium
          ${active
            ? 'bg-orange-500 text-white shadow-sm shadow-orange-200'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'}`}
      >
        <NavIcon d={item.icon} />
        <span className="flex-1">{item.label}</span>
        {badge > 0 && (
          <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${active ? 'bg-white text-orange-500' : 'bg-orange-100 text-orange-600'}`}>
            {badge > 99 ? '99+' : badge}
          </span>
        )}
      </a>
    );
  }

  const visibleChildren = item.children.filter(c => featureEnabled(c.feature) && hasPerm(c.perm));
  if (visibleChildren.length === 0) return null;

  const childActive = visibleChildren.some(c => isActive(c.pattern));
  const [open, setOpen] = useState(item.defaultOpen || childActive);

  const badge = item.badge && admin_badges ? admin_badges[item.badge] : 0;

  return (
    <div>
      <button
        onClick={() => setOpen(o => !o)}
        className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all mb-0.5 group
          ${childActive ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'}`}
      >
        <NavIcon d={item.icon} />
        <span className="flex-1 text-left">{item.label}</span>
        {badge > 0 && (
          <span className="text-[10px] font-bold bg-orange-500 text-white px-1.5 py-0.5 rounded-full mr-1">
            {badge > 99 ? '99+' : badge}
          </span>
        )}
        <Chevron open={open} />
      </button>

      <div className={`overflow-hidden transition-all duration-200 ${open ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'}`}>
        <div className="ml-3 pl-3 border-l border-gray-100 space-y-0.5 mb-1">
          {visibleChildren.map(child => {
            const active = isActive(child.pattern);
            const childBadge = child.badge && admin_badges ? admin_badges[child.badge] : 0;
            return (
              <a key={child.key} href={child.href}
                className={`flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-sm transition-all
                  ${active
                    ? 'bg-orange-500 text-white font-semibold shadow-sm shadow-orange-200'
                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'}`}
              >
                <span className="flex-1">{child.label}</span>
                {childBadge > 0 && (
                  <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${active ? 'bg-white text-orange-500' : 'bg-orange-100 text-orange-600'}`}>
                    {childBadge > 99 ? '99+' : childBadge}
                  </span>
                )}
              </a>
            );
          })}
        </div>
      </div>
    </div>
  );
}

/* ─── Main layout ─────────────────────────────────────────────────── */
export default function AdminLayout({ children, title }) {
  const { url, props } = usePage();
  const { auth, app, flash, testing_mode, admin_badges, dropshipping_enabled } = props;
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [confirmModal, setConfirmModal] = useState(null);

  const user = auth?.user;
  const isSuperAdmin = user?.is_super_admin || user?.role === 'admin';
  const userPerms = Array.isArray(user?.permissions) ? user.permissions : [];

  const hasPerm = (perm) => {
    if (isSuperAdmin) return true;
    if (!perm) return true;
    return userPerms.includes(perm);
  };
  const featureEnabled = (feature) => !feature || (feature === 'dropshipping' && dropshipping_enabled);

  useEffect(() => {
    window.showConfirm = (message, onConfirm) => {
      setConfirmModal({ message, onConfirm });
    };
    return () => { delete window.showConfirm; };
  }, []);

  const isActive = (pattern) => pattern.test(url);

  const handleLogout = (e) => {
    e.preventDefault();
    router.post('/admin/logout');
  };

  return (
    <div className="min-h-screen bg-slate-50 flex font-sans">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black/30 z-40 lg:hidden backdrop-blur-sm"
          onClick={() => setSidebarOpen(false)} />
      )}

      {/* ── Sidebar ── */}
      <aside className={`
        fixed lg:sticky lg:top-0 lg:self-start inset-y-0 left-0 z-50
        w-60 h-screen bg-white border-r border-gray-100
        flex flex-col shadow-sm transition-transform duration-200
        ${sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}
      `}>

        {/* Logo / Store header */}
        <div className="h-14 flex items-center gap-2.5 px-4 border-b border-gray-100 shrink-0">
          <a href="/admin" className="flex items-center gap-2.5 min-w-0 flex-1">
            {app?.logo_url
              ? <img src={app.logo_url} alt="" className="h-7 w-7 rounded-lg object-contain shrink-0" />
              : <div className="h-7 w-7 rounded-lg bg-orange-500 flex items-center justify-center shrink-0">
                  <span className="text-white font-bold text-xs">S</span>
                </div>
            }
            <span className="font-bold text-gray-900 text-sm truncate">{app?.name || 'Shopzy'}</span>
          </a>
          <button onClick={() => setSidebarOpen(false)}
            className="lg:hidden text-gray-400 hover:text-gray-600 p-1 rounded-md">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>

        {/* View Site link */}
        <div className="px-3 pt-3 shrink-0">
          <a href="/" target="_blank"
            className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-gray-500 hover:text-orange-600 hover:bg-orange-50 transition-all border border-dashed border-gray-200 hover:border-orange-200">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/>
            </svg>
            View Store
          </a>
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto py-3 px-3 space-y-0 min-h-0">
          {navGroups.map((group) => {
            const visibleDirectItems = group.items ? group.items.filter(item => featureEnabled(item.feature) && hasPerm(item.perm)) : [];
            const visibleCollapsibles = group.collapsible ? group.collapsible.filter(item => {
              if (item.children) return item.children.some(c => featureEnabled(c.feature) && hasPerm(c.perm));
              return hasPerm(item.perm);
            }) : [];

            if (visibleDirectItems.length === 0 && visibleCollapsibles.length === 0) {
              return null;
            }

            return (
              <div key={group.label} className="mb-3">
                <p className="px-3 text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">
                  {group.label}
                </p>

                {/* Direct items */}
                {visibleDirectItems.map(item => {
                  const active = isActive(item.pattern);
                  const badge = item.badge && admin_badges ? admin_badges[item.badge] : 0;
                  return (
                    <a key={item.key} href={item.href}
                      className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm mb-0.5 transition-all font-medium
                        ${active
                          ? 'bg-orange-500 text-white shadow-sm shadow-orange-200'
                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'}`}
                    >
                      <NavIcon d={item.icon} />
                      <span className="flex-1">{item.label}</span>
                      {badge > 0 && (
                        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${active ? 'bg-white text-orange-500' : 'bg-orange-100 text-orange-600'}`}>
                          {badge > 99 ? '99+' : badge}
                        </span>
                      )}
                    </a>
                  );
                })}

                {/* Collapsible items */}
                {visibleCollapsibles.map(item => (
                  <CollapsibleItem
                    key={item.key}
                    item={item}
                    isActive={isActive}
                    admin_badges={admin_badges}
                    hasPerm={hasPerm}
                    featureEnabled={featureEnabled}
                  />
                ))}
              </div>
            );
          })}
        </nav>

        {/* Admin user footer */}
        <div className="border-t border-gray-100 p-3 shrink-0">
          <div className="flex items-center gap-2.5 px-2 py-1.5">
            <img
              src={`https://ui-avatars.com/api/?name=${encodeURIComponent(auth?.user?.name || 'Admin')}&background=f97316&color=fff&size=64`}
              className="h-8 w-8 rounded-full shrink-0" alt=""
            />
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-gray-800 truncate">{auth?.user?.name || 'Admin'}</p>
              <p className="text-[10px] text-gray-400 truncate">{auth?.user?.email}</p>
            </div>
            <button onClick={handleLogout} title="Log out"
              className="h-7 w-7 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
              </svg>
            </button>
          </div>
        </div>
      </aside>

      {/* ── Main content ── */}
      <div className="flex-1 flex flex-col min-w-0">

        {/* Topbar */}
        <header className="h-14 bg-white border-b border-gray-100 flex items-center gap-3 px-4 sm:px-6 shrink-0 sticky top-0 z-30 shadow-sm">
          <button onClick={() => setSidebarOpen(true)}
            className="lg:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M3 12h18M3 6h18M3 18h18"/>
            </svg>
          </button>

          <div className="flex-1">
            {title && (
              <h1 className="text-base font-bold text-gray-800">{title}</h1>
            )}
          </div>

          {/* Right side: admin badge + avatar */}
          <div className="flex items-center gap-3">
            {admin_badges?.pending_orders > 0 && (
              <a href="/admin/orders?status=pending_verification"
                className="hidden sm:flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-full hover:bg-amber-100 transition-colors">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                {admin_badges.pending_orders} pending
              </a>
            )}
            <div className="flex items-center gap-2">
              <img
                src={`https://ui-avatars.com/api/?name=${encodeURIComponent(auth?.user?.name || 'Admin')}&background=f97316&color=fff&size=64`}
                className="h-8 w-8 rounded-full" alt=""
              />
              <span className="hidden md:block text-sm font-medium text-gray-700">{auth?.user?.name}</span>
            </div>
          </div>
        </header>

        {/* Testing mode banner */}
        {testing_mode && (
          <div className="bg-amber-50 border-b border-amber-200 text-amber-800 text-xs px-6 py-2 text-center font-medium flex items-center justify-center gap-2">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Testing mode is ON — write actions are disabled.
          </div>
        )}

        {/* Flash messages */}
        <div className="px-4 sm:px-6 pt-4 space-y-2.5">
          {flash?.status && (
            <div className="bg-green-50 border border-green-200 text-green-700 text-sm font-medium px-4 py-2.5 rounded-xl flex items-center gap-2">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14M22 4L12 14.01l-3-3"/>
              </svg>
              {flash.status}
            </div>
          )}
          {flash?.error && (
            <div className="bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-2.5 rounded-xl flex items-center gap-2">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>
              </svg>
              {flash.error}
            </div>
          )}
          {props.errors && Object.keys(props.errors).length > 0 && (
            <div className="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
              <ul className="list-disc list-inside space-y-1">
                {Object.values(props.errors).flat().map((err, i) => <li key={i}>{err}</li>)}
              </ul>
            </div>
          )}
        </div>

        {/* Page content */}
        <main className="flex-1 p-4 sm:p-6">
          {children}
        </main>
      </div>

      {/* ── Global Confirm Modal ── */}
      {confirmModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <div className="w-11 h-11 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-4">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </div>
            <h3 className="text-lg font-bold text-gray-900 mb-1.5">Confirm Action</h3>
            <p className="text-gray-500 text-sm mb-5">{confirmModal.message}<br/>This action cannot be undone.</p>
            <div className="flex items-center justify-end gap-2.5">
              <button type="button" onClick={() => setConfirmModal(null)}
                className="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                Cancel
              </button>
              <button type="button"
                onClick={() => { confirmModal.onConfirm(); setConfirmModal(null); }}
                className="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors">
                Yes, confirm
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
