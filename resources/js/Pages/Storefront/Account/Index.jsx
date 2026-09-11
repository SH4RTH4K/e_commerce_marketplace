import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, usePage, useForm, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { money, imageUrl } from '@/lib/utils';

const Icon = ({ path, className = 'w-5 h-5' }) => (
  <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
    <path strokeLinecap="round" strokeLinejoin="round" d={path} />
  </svg>
);
const ICONS = {
  overview:  'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  orders:    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
  wishlist:  'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
  profile:   'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  password:  'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
  logout:    'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
  cart:      'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
  trash:     'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
  eye:       'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
  bag:       'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
  check:     'M5 13l4 4L19 7',
  phone:     'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
  map:       'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
};

const trackingSteps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

export default function AccountIndex({ user, orders }) {
  const [activeTab, setActiveTab] = useState('overview');
  const [wishlist, setWishlist] = useState([]);

  useEffect(() => {
    const load = () => {
      try { setWishlist(JSON.parse(localStorage.getItem('sharthak_wishlist') || '[]')); }
      catch { setWishlist([]); }
    };
    load();
    window.addEventListener('wishlist-updated', load);
    return () => window.removeEventListener('wishlist-updated', load);
  }, []);

  const removeFromWishlist = (id) => {
    const updated = wishlist.filter(p => p.id !== id);
    localStorage.setItem('sharthak_wishlist', JSON.stringify(updated));
    setWishlist(updated);
    window.dispatchEvent(new Event('wishlist-updated'));
  };

  const addAllToCart = () => {
    wishlist.forEach(p => router.post('/cart/add', { product_id: p.id, qty: 1 }, { preserveScroll: true }));
  };

  const { data: profile, setData: setProfile, put: putProfile, processing: profileProcessing, errors: profileErrors, recentlySuccessful: profileSuccess } = useForm({
    name: user.name || '', phone: user.phone || '', address: user.address || '',
    city: user.city || '', postal_code: user.postal_code || '',
  });
  const { data: pwd, setData: setPwd, put: putPwd, processing: pwdProcessing, errors: pwdErrors, recentlySuccessful: pwdSuccess, reset: resetPwd } = useForm({
    current_password: '', password: '', password_confirmation: '',
  });

  const submitProfile = (e) => { e.preventDefault(); putProfile('/account/profile'); };
  const submitPassword = (e) => { e.preventDefault(); putPwd('/account/password', { preserveScroll: true, onSuccess: () => resetPwd() }); };

  const totalSpent = orders?.reduce((s, o) => s + Number(o.total), 0) || 0;
  const pendingOrders = orders?.filter(o => !['delivered', 'cancelled'].includes(o.status)).length || 0;
  const deliveredOrders = orders?.filter(o => o.status === 'delivered').length || 0;

  const tabs = [
    { id: 'overview',  label: 'Overview',       icon: ICONS.overview },
    { id: 'orders',    label: 'Orders',          icon: ICONS.orders },
    { id: 'wishlist',  label: 'Wishlist',        icon: ICONS.wishlist, badge: wishlist.length },
    { id: 'profile',   label: 'Profile',         icon: ICONS.profile },
    { id: 'password',  label: 'Password',        icon: ICONS.password },
  ];

  const inp = "w-full h-11 px-4 rounded-xl border border-gray-200 focus:border-[#f15a24] focus:ring-2 focus:ring-[#f15a24]/20 outline-none transition-all text-sm";
  const card = "bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6";

  return (
    <StorefrontLayout>
      <Head title="My Account" />

      {/* ── Mobile Top Header ─────────────────────────── */}
      <div className="lg:hidden sticky top-0 z-30 bg-white border-b border-gray-100 shadow-sm px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-[#f15a24] text-white flex items-center justify-center text-lg font-bold shrink-0 shadow">
            {user.name.charAt(0).toUpperCase()}
          </div>
          <div className="flex-1 min-w-0">
            <p className="font-bold text-gray-900 text-sm truncate">{user.name}</p>
            <p className="text-xs text-gray-400 truncate">{user.email}</p>
          </div>
          <Link href="/logout" method="post" as="button" className="p-2 rounded-xl text-red-400 hover:bg-red-50 transition-colors shrink-0">
            <Icon path={ICONS.logout} className="w-5 h-5" />
          </Link>
        </div>
        {/* Mobile stats row */}
        <div className="grid grid-cols-3 gap-2 mt-3">
          {[{l:'Orders',v:orders?.length||0},{l:'Active',v:pendingOrders},{l:'Wishlist',v:wishlist.length}].map(s => (
            <div key={s.l} className="bg-gray-50 rounded-xl py-2 text-center">
              <p className="text-base font-extrabold text-gray-900">{s.v}</p>
              <p className="text-[9px] text-gray-400 font-medium">{s.l}</p>
            </div>
          ))}
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-3 sm:px-4 pt-4 pb-24 lg:pb-10 lg:pt-10">
        <h1 className="hidden lg:block text-3xl font-extrabold text-gray-900 tracking-tight mb-8">My Account</h1>

        <div className="flex flex-col lg:flex-row gap-5 lg:items-start">

          {/* ── Desktop Sidebar ──────────────────────────── */}
          <div className="hidden lg:block w-64 shrink-0 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-6">
            <div className="p-5 bg-gradient-to-br from-[#f15a24]/10 to-orange-50 border-b border-gray-100 flex items-center gap-3">
              <div className="w-13 h-13 w-14 h-14 rounded-full bg-[#f15a24] text-white flex items-center justify-center text-2xl font-bold shadow-md shrink-0">
                {user.name.charAt(0).toUpperCase()}
              </div>
              <div className="min-w-0">
                <p className="font-bold text-gray-900 truncate">{user.name}</p>
                <p className="text-xs text-gray-500 truncate">{user.email}</p>
              </div>
            </div>
            <div className="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
              {[{l:'Orders',v:orders?.length||0},{l:'Active',v:pendingOrders},{l:'Wishlist',v:wishlist.length}].map(s => (
                <div key={s.l} className="py-3 text-center">
                  <p className="text-lg font-extrabold text-gray-900">{s.v}</p>
                  <p className="text-[10px] text-gray-400">{s.l}</p>
                </div>
              ))}
            </div>
            <nav className="flex flex-col p-2 gap-0.5">
              {tabs.map(t => (
                <button key={t.id} onClick={() => setActiveTab(t.id)}
                  className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all ${activeTab === t.id ? 'bg-[#f15a24] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'}`}>
                  <Icon path={t.icon} className="w-4 h-4 shrink-0" />
                  <span className="flex-1 text-left">{t.label}</span>
                  {t.badge > 0 && <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${activeTab === t.id ? 'bg-white/30 text-white' : 'bg-[#f15a24]/10 text-[#f15a24]'}`}>{t.badge}</span>}
                </button>
              ))}
              <hr className="my-1 border-gray-100 mx-2" />
              <Link href="/logout" method="post" as="button" className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-red-500 hover:bg-red-50 transition-colors">
                <Icon path={ICONS.logout} className="w-4 h-4" /> Logout
              </Link>
            </nav>
          </div>

          {/* ── Content ──────────────────────────────────── */}
          <div className="flex-1 w-full min-w-0 space-y-4">

            {/* Section label on mobile */}
            <p className="lg:hidden text-xs font-bold text-gray-400 uppercase tracking-widest">
              {tabs.find(t => t.id === activeTab)?.label}
            </p>

            {/* ── OVERVIEW ──────────────────────────────── */}
            {activeTab === 'overview' && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  {[
                    {label:'Total Orders',value:orders?.length||0,icon:ICONS.bag,bg:'bg-blue-50',text:'text-blue-600'},
                    {label:'Delivered',value:deliveredOrders,icon:ICONS.check,bg:'bg-green-50',text:'text-green-600'},
                    {label:'Active',value:pendingOrders,icon:ICONS.orders,bg:'bg-orange-50',text:'text-[#f15a24]'},
                    {label:'Wishlisted',value:wishlist.length,icon:ICONS.wishlist,bg:'bg-red-50',text:'text-red-500'},
                  ].map(s => (
                    <div key={s.label} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                      <div className={`w-9 h-9 rounded-xl flex items-center justify-center mb-3 ${s.bg} ${s.text}`}>
                        <Icon path={s.icon} className="w-4 h-4" />
                      </div>
                      <p className="text-2xl font-extrabold text-gray-900">{s.value}</p>
                      <p className="text-[11px] text-gray-500 mt-0.5 leading-tight">{s.label}</p>
                    </div>
                  ))}
                </div>

                <div className="bg-gradient-to-br from-[#f15a24] to-orange-400 rounded-2xl p-5 text-white shadow-md shadow-[#f15a24]/20">
                  <p className="text-xs font-medium text-white/80 mb-1">Total Amount Spent</p>
                  <p className="text-3xl font-extrabold tracking-tight">{money(totalSpent)}</p>
                  <p className="text-xs text-white/70 mt-1">Across {orders?.length || 0} orders</p>
                </div>

                <div className={card}>
                  <div className="flex items-center justify-between mb-4">
                    <h2 className="text-sm font-bold text-gray-900">Recent Orders</h2>
                    <button onClick={() => setActiveTab('orders')} className="text-xs font-bold text-[#f15a24]">View all →</button>
                  </div>
                  {orders?.length > 0 ? (
                    <div className="space-y-2">
                      {orders.slice(0, 3).map(o => (
                        <div key={o.id} className="flex items-center gap-2 sm:gap-3 p-3 rounded-xl border border-gray-100">
                          <div className="flex-1 min-w-0">
                            <p className="font-bold text-sm text-gray-900 truncate">#{o.order_number}</p>
                            <p className="text-[10px] text-gray-400">{new Date(o.created_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})}</p>
                          </div>
                          <span className={`px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase shrink-0 ${o.status==='delivered'?'bg-green-100 text-green-700':o.status==='cancelled'?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700'}`}>{o.status}</span>
                          <span className="font-bold text-sm text-gray-900 shrink-0">{money(o.total)}</span>
                          <div className="flex items-center gap-1.5 shrink-0">
                            <a href={`/order/${o.order_number}/invoice`} target="_blank" rel="noreferrer" className="text-gray-400 hover:text-gray-900" title="Invoice">
                              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                              </svg>
                            </a>
                            <Link href={`/account/orders/${o.order_number}`} className="text-gray-400 hover:text-[#f15a24]" title="View Details">
                              <Icon path={ICONS.eye} className="w-4 h-4" />
                            </Link>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-center text-sm text-gray-400 py-4">No orders yet. <Link href="/shop" className="text-[#f15a24] font-bold">Shop now</Link></p>
                  )}
                </div>

                {wishlist.length > 0 && (
                  <div className={card}>
                    <div className="flex items-center justify-between mb-3">
                      <h2 className="text-sm font-bold text-gray-900">Wishlist ({wishlist.length})</h2>
                      <button onClick={() => setActiveTab('wishlist')} className="text-xs font-bold text-[#f15a24]">Manage →</button>
                    </div>
                    <div className="flex gap-3 overflow-x-auto pb-1">
                      {wishlist.slice(0,6).map(p => {
                        const img = p.images?.find(i=>i.is_primary)?.path||p.images?.[0]?.path;
                        return (
                          <Link key={p.id} href={`/product/${p.slug||p.id}`} className="shrink-0 w-14 sm:w-16">
                            <div className="w-14 h-14 sm:w-16 sm:h-16 rounded-xl border border-gray-100 overflow-hidden bg-gray-50 hover:border-[#f15a24]/50 transition-colors">
                              <img src={imageUrl(img,p.name)} alt={p.name} className="w-full h-full object-cover" />
                            </div>
                            <p className="text-[9px] text-gray-500 mt-1 truncate">{p.name}</p>
                          </Link>
                        );
                      })}
                    </div>
                  </div>
                )}

                <div className={card}>
                  <h2 className="text-sm font-bold text-gray-900 mb-3">Account Info</h2>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {[
                      {icon:ICONS.profile,label:'Name',value:user.name},
                      {icon:ICONS.phone,label:'Phone',value:user.phone||'—'},
                      {icon:ICONS.map,label:'City',value:user.city||'—'},
                    ].map(item => (
                      <div key={item.label} className="flex items-center gap-3 p-3 rounded-xl bg-gray-50">
                        <div className="w-8 h-8 rounded-lg bg-white border border-gray-100 flex items-center justify-center shrink-0">
                          <Icon path={item.icon} className="w-4 h-4 text-gray-400" />
                        </div>
                        <div className="min-w-0">
                          <p className="text-[9px] text-gray-400 font-medium">{item.label}</p>
                          <p className="text-sm font-bold text-gray-900 truncate">{item.value}</p>
                        </div>
                      </div>
                    ))}
                    <button onClick={() => setActiveTab('profile')} className="flex items-center justify-center p-3 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-[#f15a24] hover:border-[#f15a24]/50 transition-colors">
                      Edit Profile →
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* ── WISHLIST ──────────────────────────────── */}
            {activeTab === 'wishlist' && (
              <div className={card}>
                <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                  <p className="text-sm text-gray-500 flex-1">{wishlist.length} saved item{wishlist.length !== 1 ? 's' : ''}</p>
                  {wishlist.length > 0 && (
                    <button onClick={addAllToCart} className="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 bg-[#f15a24] text-white text-sm font-bold rounded-xl hover:bg-[#d94b1a] transition-colors">
                      <Icon path={ICONS.cart} className="w-4 h-4" /> Add All to Cart
                    </button>
                  )}
                </div>
                {wishlist.length > 0 ? (
                  <div className="space-y-3">
                    {wishlist.map(p => {
                      const img = p.images?.find(i=>i.is_primary)?.path||p.images?.[0]?.path;
                      const price = p.sale_price || p.regular_price;
                      const hasDiscount = p.sale_price && p.regular_price > p.sale_price;
                      return (
                        <div key={p.id} className="flex gap-3 p-3 rounded-xl border border-gray-100 hover:border-gray-200 transition-colors">
                          <Link href={`/product/${p.slug||p.id}`} className="w-16 h-16 sm:w-20 sm:h-20 shrink-0 rounded-xl overflow-hidden bg-gray-50 border border-gray-100">
                            <img src={imageUrl(img,p.name)} alt={p.name} className="w-full h-full object-cover" />
                          </Link>
                          <div className="flex-1 min-w-0 flex flex-col justify-between">
                            <div>
                              <p className="text-[10px] text-gray-400">{p.category?.name||'Product'}</p>
                              <Link href={`/product/${p.slug||p.id}`} className="text-sm font-bold text-gray-900 hover:text-[#f15a24] line-clamp-2">{p.name}</Link>
                            </div>
                            <div className="flex items-center justify-between mt-1 gap-2 flex-wrap">
                              <div>
                                <span className="font-bold text-sm text-gray-900">{money(price)}</span>
                                {hasDiscount && <span className="ml-1.5 text-xs text-gray-400 line-through">{money(p.regular_price)}</span>}
                              </div>
                              <div className="flex gap-1.5">
                                <button onClick={() => router.post('/cart/add',{product_id:p.id,qty:1},{preserveScroll:true})} className="p-2 rounded-lg bg-[#f15a24]/10 text-[#f15a24] hover:bg-[#f15a24] hover:text-white transition-colors">
                                  <Icon path={ICONS.cart} className="w-3.5 h-3.5" />
                                </button>
                                <button onClick={() => removeFromWishlist(p.id)} className="p-2 rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-colors">
                                  <Icon path={ICONS.trash} className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="text-center py-12">
                    <div className="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-3">
                      <Icon path={ICONS.wishlist} className="w-7 h-7 text-red-300" />
                    </div>
                    <h3 className="text-base font-bold text-gray-900 mb-1">Wishlist is empty</h3>
                    <p className="text-gray-400 text-sm mb-4">Save products you love.</p>
                    <Link href="/shop" className="inline-flex items-center gap-2 px-5 py-2.5 bg-[#f15a24] text-white font-bold rounded-xl hover:bg-[#d94b1a] text-sm">Browse Products</Link>
                  </div>
                )}
              </div>
            )}

            {/* ── ORDERS ─────────────────────────────────── */}
            {activeTab === 'orders' && (
              <div className={card}>
                {orders?.length > 0 ? (
                  <div className="space-y-4">
                    {orders.map(order => {
                      const si = trackingSteps.indexOf(order.status);
                      return (
                        <div key={order.id} className="border border-gray-100 rounded-2xl p-4 hover:border-gray-200 transition-colors">
                          <div className="flex items-start justify-between gap-2 mb-3">
                            <div>
                              <p className="font-bold text-sm text-gray-900">#{order.order_number}</p>
                              <p className="text-[10px] text-gray-400">{new Date(order.created_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})} · {order.items?.length||0} item(s)</p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                              <span className={`px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase ${order.status==='delivered'?'bg-green-100 text-green-700':order.status==='cancelled'?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700'}`}>{order.status}</span>
                              <span className="font-bold text-sm text-gray-900">{money(order.total)}</span>
                            </div>
                          </div>

                          {order.items?.length > 0 && (
                            <div className="flex gap-2 mb-3 overflow-x-auto pb-1">
                              {order.items.slice(0,5).map((item,i) => {
                                const img = item.image || item.product?.images?.find(x=>x.is_primary)?.path || item.product?.images?.[0]?.path;
                                return <div key={i} className="w-10 h-10 sm:w-12 sm:h-12 shrink-0 rounded-lg overflow-hidden border border-gray-100 bg-gray-50"><img src={imageUrl(img,item.product_name||item.product?.name||'Product')} alt="" className="w-full h-full object-cover" /></div>;
                              })}
                              {order.items.length > 5 && <div className="w-10 h-10 sm:w-12 sm:h-12 shrink-0 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500">+{order.items.length-5}</div>}
                            </div>
                          )}

                          {order.status !== 'cancelled' && (
                            <div className="py-4 border-y border-gray-50 my-1">
                              <div className="relative flex justify-between items-center px-2 sm:px-4">
                                <div className="absolute left-2 sm:left-4 right-2 sm:right-4 top-1/2 -translate-y-1/2 h-1 bg-gray-100 rounded-full z-0" />
                                <div className="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 h-1 bg-[#f15a24] rounded-full z-0 transition-all"
                                  style={{width:`calc(${(Math.max(0,si)/(trackingSteps.length-1))*100}% - 16px)`}} />
                                {trackingSteps.map((step,i) => {
                                  const done = i <= si;
                                  return (
                                    <div key={step} className="relative z-10 flex flex-col items-center">
                                      <div className={`w-5 h-5 sm:w-7 sm:h-7 rounded-full flex items-center justify-center border-2 bg-white transition-all ${done?'border-[#f15a24] text-[#f15a24]':'border-gray-200'} ${i===si?'ring-4 ring-[#f15a24]/20':''}`}>
                                        {done ? <Icon path={ICONS.check} className="w-2.5 h-2.5 sm:w-3 sm:h-3" /> : <div className="w-1.5 h-1.5 rounded-full bg-gray-200" />}
                                      </div>
                                      <span className={`text-[7px] sm:text-[9px] font-bold uppercase absolute top-6 sm:top-8 whitespace-nowrap ${done?'text-gray-700':'text-gray-400'}`}>{step}</span>
                                    </div>
                                  );
                                })}
                              </div>
                            </div>
                          )}
                          {order.status === 'cancelled' && <div className="py-3 text-center text-red-400 font-semibold text-xs border-y border-gray-50 my-1">Order cancelled</div>}

                          <div className="flex items-center justify-end gap-2.5 mt-3 pt-2.5 border-t border-gray-50">
                            <a
                              href={`/order/${order.order_number}/invoice`}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                              <svg className="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                              </svg>
                              ইনভয়েস / Invoice
                            </a>
                            <Link href={`/account/orders/${order.order_number}`} className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-orange-50 text-orange-600 text-xs font-bold hover:bg-orange-100 transition-colors">
                              View Details <Icon path={ICONS.eye} className="w-3.5 h-3.5" />
                            </Link>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="text-center py-12">
                    <div className="w-14 h-14 rounded-full bg-orange-50 flex items-center justify-center mx-auto mb-3">
                      <Icon path={ICONS.bag} className="w-7 h-7 text-[#f15a24]/40" />
                    </div>
                    <h3 className="text-base font-bold text-gray-900 mb-1">No orders yet</h3>
                    <p className="text-gray-400 text-sm mb-4">Place your first order today!</p>
                    <Link href="/shop" className="inline-flex items-center gap-2 px-5 py-2.5 bg-[#f15a24] text-white font-bold rounded-xl hover:bg-[#d94b1a] text-sm">Start Shopping</Link>
                  </div>
                )}
              </div>
            )}

            {/* ── PROFILE ───────────────────────────────── */}
            {activeTab === 'profile' && (
              <div className={card}>
                {profileSuccess && (
                  <div className="mb-4 p-3 bg-green-50 text-green-700 rounded-xl text-sm font-bold flex items-center gap-2">
                    <Icon path={ICONS.check} className="w-4 h-4" /> Profile updated!
                  </div>
                )}
                <form onSubmit={submitProfile} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-1.5">Full Name</label>
                      <input type="text" value={profile.name} onChange={e=>setProfile('name',e.target.value)} className={inp} />
                      {profileErrors.name && <p className="mt-1 text-xs text-red-500">{profileErrors.name}</p>}
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-1.5">Email (Read-only)</label>
                      <input type="email" value={user.email} disabled className="w-full h-11 px-4 rounded-xl border border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed text-sm" />
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-sm font-semibold text-gray-700 mb-1.5">Phone Number</label>
                      <input type="text" value={profile.phone} onChange={e=>setProfile('phone',e.target.value)} className={inp} placeholder="+880 1XXX-XXXXXX" />
                      {profileErrors.phone && <p className="mt-1 text-xs text-red-500">{profileErrors.phone}</p>}
                    </div>
                  </div>
                  <div className="pt-2 border-t border-gray-100">
                    <h3 className="text-sm font-bold text-gray-900 mb-3">Shipping Address</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div className="sm:col-span-2">
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Street Address</label>
                        <input type="text" value={profile.address} onChange={e=>setProfile('address',e.target.value)} className={inp} placeholder="House, Road, Area..." />
                      </div>
                      <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">City</label>
                        <input type="text" value={profile.city} onChange={e=>setProfile('city',e.target.value)} className={inp} placeholder="Dhaka" />
                      </div>
                      <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Postal Code</label>
                        <input type="text" value={profile.postal_code} onChange={e=>setProfile('postal_code',e.target.value)} className={inp} placeholder="1212" />
                      </div>
                    </div>
                  </div>
                  <button type="submit" disabled={profileProcessing} className="w-full sm:w-auto h-11 px-8 bg-[#f15a24] hover:bg-[#d94b1a] text-white rounded-xl font-bold transition-colors disabled:opacity-70 flex items-center justify-center gap-2">
                    {profileProcessing ? 'Saving...' : <><Icon path={ICONS.check} className="w-4 h-4" /> Save Profile</>}
                  </button>
                </form>
              </div>
            )}

            {/* ── PASSWORD ──────────────────────────────── */}
            {activeTab === 'password' && (
              <div className={card}>
                <p className="text-sm text-gray-500 mb-4">Use a strong password with at least 8 characters.</p>
                {pwdSuccess && (
                  <div className="mb-4 p-3 bg-green-50 text-green-700 rounded-xl text-sm font-bold flex items-center gap-2">
                    <Icon path={ICONS.check} className="w-4 h-4" /> Password updated!
                  </div>
                )}
                <form onSubmit={submitPassword} className="space-y-4">
                  {[
                    {label:'Current Password',key:'current_password',val:pwd.current_password,err:pwdErrors.current_password},
                    {label:'New Password',key:'password',val:pwd.password,err:pwdErrors.password},
                    {label:'Confirm New Password',key:'password_confirmation',val:pwd.password_confirmation,err:null},
                  ].map(f => (
                    <div key={f.key}>
                      <label className="block text-sm font-semibold text-gray-700 mb-1.5">{f.label}</label>
                      <input type="password" value={f.val} onChange={e=>setPwd(f.key,e.target.value)} className={inp} />
                      {f.err && <p className="mt-1 text-xs text-red-500">{f.err}</p>}
                    </div>
                  ))}
                  <button type="submit" disabled={pwdProcessing} className="w-full sm:w-auto h-11 px-8 bg-[#f15a24] hover:bg-[#d94b1a] text-white rounded-xl font-bold transition-colors disabled:opacity-70">
                    {pwdProcessing ? 'Updating...' : 'Update Password'}
                  </button>
                </form>
              </div>
            )}

          </div>
        </div>
      </div>

      {/* ── Mobile Bottom Tab Bar ──────────────────────── */}
      <div className="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-100 shadow-[0_-2px_16px_rgba(0,0,0,0.07)]"
        style={{paddingBottom:'env(safe-area-inset-bottom)'}}>
        <div className="flex items-center justify-around px-1 py-1.5">
          {tabs.map(t => (
            <button key={t.id} onClick={() => setActiveTab(t.id)}
              className={`flex flex-col items-center gap-0.5 px-2 py-1.5 rounded-xl flex-1 transition-all relative ${activeTab === t.id ? 'text-[#f15a24]' : 'text-gray-400'}`}>
              {t.badge > 0 && (
                <span className="absolute top-0.5 right-1.5 w-4 h-4 bg-[#f15a24] text-white text-[8px] font-bold rounded-full flex items-center justify-center leading-none">
                  {t.badge > 9 ? '9+' : t.badge}
                </span>
              )}
              <Icon path={t.icon} className="w-5 h-5" />
              <span className="text-[9px] font-semibold leading-none">{t.label}</span>
              {activeTab === t.id && <span className="absolute bottom-0 left-1/2 -translate-x-1/2 w-4 h-0.5 bg-[#f15a24] rounded-full" />}
            </button>
          ))}
        </div>
      </div>

    </StorefrontLayout>
  );
}