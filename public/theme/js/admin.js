/* Shared admin shell (sidebar + topbar) for the AdminPanel1 theme.
   Usage in each page:
     <div id="sidebar-mount"></div> ... <div id="topbar-mount"></div>
     <script src="assets/app.js"></script>
     <script>AdminShell.mount('orders', 'Orders');</script>
*/
const AdminShell = (function () {
    const ic = {
        dashboard: '<path d="M4 13h6v8H4zM14 3h6v18h-6zM4 3h6v6H4z"/>',
        orders: '<circle cx="6" cy="19" r="2"/><circle cx="17" cy="19" r="2"/><path d="M17 17h-11v-14h-2M6 5l14 1l-1 7h-13"/>',
        products: '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z M12 12l8-4.5M12 12v9M12 12L4 7.5"/>',
        categories: '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
        banners: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18"/>',
        features: '<path d="M12 3l2.5 5.5L20 9l-4 4l1 6l-5-3l-5 3l1-6l-4-4l5.5-.5z"/>',
        customers: '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 3.13a4 4 0 0 1 0 7.75"/>',
        settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    };

    const svg = (p) => `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;

    const groups = [
        { title: 'Main', items: [
            { key: 'dashboard', label: 'Dashboard', href: 'index.html' },
            { key: 'orders', label: 'Orders', href: 'orders.html', badge: '3' },
        ]},
        { title: 'Catalog', items: [
            { key: 'products', label: 'Products', href: 'products.html' },
            { key: 'categories', label: 'Categories', href: 'categories.html' },
            { key: 'banners', label: 'Banners', href: 'banners.html' },
            { key: 'features', label: 'Features', href: 'features.html' },
        ]},
        { title: 'People', items: [
            { key: 'customers', label: 'Customers', href: 'customers.html' },
        ]},
        { title: 'System', items: [
            { key: 'settings', label: 'Settings', href: 'settings.html' },
        ]},
    ];

    function sidebarHTML(active) {
        let nav = '';
        for (const g of groups) {
            nav += `<p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2 mt-5 first:mt-0">${g.title}</p>`;
            for (const it of g.items) {
                const on = it.key === active;
                const cls = on ? 'bg-primary/10 text-primary font-medium' : 'text-gray-600 hover:bg-gray-100';
                const badge = it.badge ? `<span class="ml-auto bg-amber-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">${it.badge}</span>` : '';
                nav += `<a href="${it.href}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 ${cls}">${svg(ic[it.key])}<span>${it.label}</span>${badge}</a>`;
            }
        }
        return `<aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">
            <div class="h-16 flex items-center gap-2 px-6 border-b border-gray-200">
                <span class="h-9 w-9 rounded-lg bg-primary text-white flex items-center justify-center font-bold">A</span>
                <span class="font-bold text-lg">Admin Panel</span>
            </div>
            <nav class="flex-1 overflow-y-auto py-4 px-3 text-sm">${nav}</nav>
            <div class="border-t border-gray-200 p-4 flex items-center gap-3">
                <img src="https://ui-avatars.com/api/?name=Admin&background=0aad0a&color=fff" class="h-9 w-9 rounded-full" alt="">
                <div class="min-w-0"><p class="text-sm font-medium truncate">Admin</p><p class="text-xs text-gray-400 truncate">admin@example.com</p></div>
            </div>
        </aside>`;
    }

    function topbarHTML(title) {
        return `<header class="h-16 bg-white border-b border-gray-200 flex items-center gap-4 px-4 lg:px-6 sticky top-0 z-20">
            <button id="menuBtn" class="lg:hidden text-gray-600"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <h1 class="text-lg font-semibold">${title}</h1>
            <div class="ml-auto flex items-center gap-3">
                <div class="relative hidden sm:block">
                    <input type="text" placeholder="Search…" class="w-56 bg-gray-100 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <svg class="absolute left-3 top-2.5 text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                </div>
                <button class="relative h-10 w-10 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-600"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg><span class="absolute top-2 right-2 h-2 w-2 bg-amber-500 rounded-full"></span></button>
                <img src="https://ui-avatars.com/api/?name=Admin&background=0aad0a&color=fff" class="h-9 w-9 rounded-full" alt="">
            </div>
        </header>`;
    }

    function mount(active, title) {
        const sm = document.getElementById('sidebar-mount');
        if (sm) sm.outerHTML = sidebarHTML(active);
        const tm = document.getElementById('topbar-mount');
        if (tm) tm.outerHTML = topbarHTML(title);

        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('backdrop');
        const btn = document.getElementById('menuBtn');
        if (btn && sb && bd) {
            btn.addEventListener('click', () => { sb.classList.remove('-translate-x-full'); bd.classList.remove('hidden'); });
            bd.addEventListener('click', () => { sb.classList.add('-translate-x-full'); bd.classList.add('hidden'); });
        }
    }

    return { mount };
})();
