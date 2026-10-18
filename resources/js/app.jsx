import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { trackPageView } from '@/lib/tracking';
import { seoPageTitle } from '@/lib/seoTitle';
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

function getDefaultSeoTitle() {
  return document.querySelector('meta[name="seo-default-title"]')?.getAttribute('content')?.trim() || '';
}

createInertiaApp({
    title: (title) => {
        return seoPageTitle(title, getDynamicSiteName(), getDefaultSeoTitle());
    },
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
        // Hide the server-rendered SEO fallback only after the application mounted.
        // If JavaScript fails, crawlers and visitors still receive useful branded HTML.
        document.documentElement.classList.add('js');
    },
    progress: {
        color: '#f15a24',
    },
});
