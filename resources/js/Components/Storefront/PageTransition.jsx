import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function findCategory(categories, slug) {
  for (const category of Object.values(categories || {})) {
    if (category.slug === slug) return category;
    const match = findCategory(category.children, slug);
    if (match) return match;
  }
  return null;
}

export function CategoryLoadingSkeleton({ name, columns = 5 }) {
  const columnCount = [2, 3, 4, 5, 6].includes(Number(columns)) ? Number(columns) : 5;
  return <section className="storefront-category-loading" aria-label={`${name} products loading`}
    style={{ '--storefront-loading-columns': columnCount, '--storefront-loading-tablet-columns': Math.min(4, columnCount) }}>
    <div className="storefront-loading-breadcrumb" aria-hidden="true">Shop <span>/</span> {name}</div>
    <div className="storefront-loading-heading">
      <h1>{name}</h1>
      <span className="storefront-loading-caption" aria-hidden="true"><span className="storefront-loading-spinner" />Loading products</span>
    </div>
    <div className="storefront-loading-layout" aria-hidden="true">
      <aside className="storefront-loading-filters">
        <div className="storefront-skeleton storefront-skeleton-label" />
        {Array.from({ length: 5 }, (_, index) => <div key={index} className="storefront-skeleton storefront-skeleton-filter" />)}
        <div className="storefront-skeleton storefront-skeleton-label" />
        <div className="storefront-skeleton storefront-skeleton-filter" />
      </aside>
      <div>
        <div className="storefront-loading-toolbar"><div className="storefront-skeleton" /><div className="storefront-skeleton" /></div>
        <div className="storefront-loading-grid">
          {Array.from({ length: columnCount * 2 }, (_, index) => <div key={index} className="storefront-loading-card">
            <div className="storefront-skeleton storefront-skeleton-image" />
            <div className="storefront-loading-card-details">
              <div className="storefront-skeleton storefront-skeleton-name" />
              <div className="storefront-skeleton storefront-skeleton-price" />
            </div>
          </div>)}
        </div>
      </div>
    </div>
  </section>;
}

export default function PageTransition({ categories, columns, onNavigate, children }) {
  const [pending, setPending] = useState(null);
  const activeVisit = useRef(null);
  const context = useRef({ categories, onNavigate });
  context.current = { categories, onNavigate };

  useEffect(() => {
    let mounted = true;
    const clearPending = visitId => {
      if (!mounted || !activeVisit.current || (visitId != null && visitId !== activeVisit.current.id)) return;
      activeVisit.current = null;
      setPending(null);
    };
    const removeBefore = router.on('before', event => {
      const visit = event.detail.visit;
      // Prefetch emits its own lifecycle events. Only an actual customer visit
      // may replace the visible content, including visits using a cached page.
      if (event.defaultPrevented || visit.prefetch || visit.async) return;
      // Background pagination and form submissions keep their own local feedback.
      if (visit.method !== 'get' || visit.only?.length || visit.except?.length || visit.reset?.length) {
        clearPending();
        return;
      }
      const target = new URL(visit.url, window.location.origin);
      const slug = target.pathname.match(/^\/category\/([^/]+)\/?$/)?.[1];
      const categoryChange = Boolean(slug && target.pathname !== window.location.pathname);
      let decodedSlug = slug;
      try { decodedSlug = decodeURIComponent(slug || ''); } catch { /* A malformed URL still gets a generic loading state. */ }
      const name = categoryChange ? findCategory(context.current.categories, decodedSlug)?.name || 'Products' : null;
      activeVisit.current = { id: visit.id, url: target.href };
      setPending({ categoryChange, name });
      context.current.onNavigate?.();

      // A consumed in-flight prefetch has no start/finish events for this
      // consumer. Its rejected promise also covers cancellation and failures
      // that would otherwise leave a category skeleton displayed forever.
      const prefetch = router.getPrefetching?.(visit.url, visit);
      prefetch?.response?.then(undefined, () => clearPending(visit.id));

      // Another before listener may cancel after this listener has run.
      Promise.resolve().then(() => {
        if (event.defaultPrevented) clearPending(visit.id);
      });
    });
    const removeFinish = router.on('finish', event => {
      clearPending(event.detail.visit.id);
    });
    const removeNavigate = router.on('navigate', event => clearPending(event.detail.visitId));
    const removeSuccess = router.on('success', event => clearPending(event.detail.visitId));
    const removeError = router.on('error', event => clearPending(event.detail.visitId));
    const removeHttpException = router.on('httpException', () => clearPending());
    const removeNetworkError = router.on('networkError', event => {
      const failedUrl = event.detail.error?.url;
      if (!failedUrl || new URL(failedUrl, window.location.origin).href === activeVisit.current?.url) clearPending();
    });
    return () => {
      mounted = false;
      removeBefore(); removeFinish(); removeNavigate(); removeSuccess(); removeError(); removeHttpException(); removeNetworkError();
    };
  }, []);

  return <div className="storefront-page-transition">
    <div role="status" aria-live="polite" aria-atomic="true" className={pending && !pending.categoryChange ? 'storefront-navigation-status' : 'sr-only'}>
      {pending && <><span className="storefront-loading-spinner" aria-hidden="true" />{pending.categoryChange ? `Loading ${pending.name} products` : 'Updating page…'}</>}
    </div>
    <div aria-busy={Boolean(pending)}>
      {pending?.categoryChange ? <CategoryLoadingSkeleton name={pending.name} columns={columns} /> : children}
    </div>
  </div>;
}
