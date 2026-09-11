import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { trackPageView } from '@/lib/tracking';
import '../css/app.css';

// Track SPA page views on every Inertia navigation and sync site name
router.on('navigate', (event) => {
  trackPageView(event.detail.page.url);
  const currentSiteName = event.detail?.page?.props?.app?.name;
  if (currentSiteName) {
    const meta = document.querySelector('meta[name="site-name"]');
    if (meta) meta.setAttribute('content', currentSiteName);
  }
});

function getDynamicSiteName() {
  const meta = document.querySelector('meta[name="site-name"]');
  return meta?.getAttribute('content')?.trim() || '';
}

createInertiaApp({
    title: (title) => {
        const siteName = getDynamicSiteName() || 'Shop';
        const cleanTitle = (title || '').trim();
        if (!cleanTitle || cleanTitle.toLowerCase() === 'home') {
            return siteName;
        }
        return `${cleanTitle} — ${siteName}`;
    },
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#f15a24',
    },
});
