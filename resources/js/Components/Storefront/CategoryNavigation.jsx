import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export function categoryChildren(category) {
  return Array.isArray(category?.children) ? category.children : Object.values(category?.children || {});
}

export function categoryHasActive(category, activeId) {
  return activeId != null && (String(category.id) === String(activeId) || categoryChildren(category).some(child => categoryHasActive(child, activeId)));
}

function Chevron({ expanded }) {
  return <svg aria-hidden="true" className={`h-3.5 w-3.5 shrink-0 transition-transform ${expanded ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="m6 9 6 6 6-6" /></svg>;
}

export function CategoryBranchLinks({ categories = [], activeId, level = 0 }) {
  return categories.map(category => <div key={category.id}>
    <Link href={`/category/${category.slug}`} aria-current={String(category.id) === String(activeId) ? 'page' : undefined}
      className="storefront-category-branch-link" style={{ paddingLeft: `${16 + level * 18}px` }}>
      {level > 0 && <span aria-hidden="true" className="mr-2 text-gray-400">↳</span>}{category.name}
    </Link>
    <CategoryBranchLinks categories={categoryChildren(category)} activeId={activeId} level={level + 1} />
  </div>);
}

export function DesktopCategoryItem({ category, activeId, alignRight = false }) {
  const children = categoryChildren(category);
  const active = categoryHasActive(category, activeId);
  const [open, setOpen] = useState(false);
  const itemRef = useRef(null);
  const toggleRef = useRef(null);
  const panelId = `category-branch-${category.id}`;
  useEffect(() => {
    if (!open) return;
    const closeOutside = event => { if (!itemRef.current?.contains(event.target)) setOpen(false); };
    document.addEventListener('pointerdown', closeOutside);
    return () => document.removeEventListener('pointerdown', closeOutside);
  }, [open]);
  return <div ref={itemRef} className="storefront-category-item relative flex min-w-0 items-center"
    onMouseEnter={() => setOpen(true)} onMouseLeave={event => { if (!event.currentTarget.contains(document.activeElement)) setOpen(false); }}
    onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false); }}
    onKeyDown={event => { if (event.key === 'Escape') { setOpen(false); toggleRef.current?.focus(); } }}>
    <div className="storefront-category-trigger" data-active={active ? 'true' : undefined}>
      <Link href={`/category/${category.slug}`} aria-current={active ? 'true' : undefined} className="storefront-category-link">{category.name}</Link>
      {children.length > 0 && <button ref={toggleRef} type="button" className="storefront-category-toggle" aria-label={`Show subcategories of ${category.name}`}
        aria-expanded={open} aria-controls={panelId} onClick={() => setOpen(true)}><Chevron expanded={open} /></button>}
    </div>
    {children.length > 0 && open && <div id={panelId} className={`storefront-category-panel ${alignRight ? 'right-0' : 'left-0'}`}>
      <Link href={`/category/${category.slug}`} className="storefront-category-panel-heading">All {category.name}</Link>
      <CategoryBranchLinks categories={children} activeId={activeId} />
    </div>}
  </div>;
}

export function MobileCategoryLinks({ categories = [], activeId, level = 0 }) {
  return categories.map(category => <MobileCategoryItem key={category.id} category={category} activeId={activeId} level={level} />);
}

function MobileCategoryItem({ category, activeId, level }) {
  const children = categoryChildren(category);
  const active = categoryHasActive(category, activeId);
  const [expanded, setExpanded] = useState(active);
  useEffect(() => { if (active) setExpanded(true); }, [active]);
  const panelId = `mobile-category-branch-${category.id}`;
  return <div>
    <div className={`mx-2 flex items-center rounded-lg ${active ? 'bg-orange-50' : ''}`} style={{ marginLeft: `${8 + level * 16}px` }}>
      <Link href={`/category/${category.slug}`} aria-current={String(category.id) === String(activeId) ? 'page' : undefined}
        className={`flex-1 min-w-0 px-4 py-3 text-sm ${active ? 'font-semibold text-orange-700' : 'text-gray-700'} hover:bg-orange-50 rounded-lg`}>{category.name}</Link>
      {children.length > 0 && <button type="button" className="p-3 text-gray-600" aria-label={`Show subcategories of ${category.name}`}
        aria-expanded={expanded} aria-controls={panelId} onClick={() => setExpanded(value => !value)}><Chevron expanded={expanded} /></button>}
    </div>
    {children.length > 0 && expanded && <div id={panelId}><MobileCategoryLinks categories={children} activeId={activeId} level={level + 1} /></div>}
  </div>;
}

export function CategoryTrail({ path = [], children = [] }) {
  if (!path.length) return null;
  return <div className="storefront-category-trail">
    <nav aria-label="Category breadcrumb" className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
      <Link href="/shop" className="hover:text-orange-700">All Products</Link>
      {path.map((category, index) => <span key={category.id} className="inline-flex items-center gap-2">
        <span aria-hidden="true">/</span><Link href={`/category/${category.slug}`} aria-current={index === path.length - 1 ? 'page' : undefined}
          className={index === path.length - 1 ? 'font-semibold text-gray-900' : 'hover:text-orange-700'}>{category.name}</Link>
      </span>)}
    </nav>
    {children.length > 0 && <div className="mt-3 flex flex-wrap items-center gap-2">
      <span className="mr-1 text-xs font-semibold text-gray-500">Browse subcategories</span>
      {children.map(category => <Link key={category.id} href={`/category/${category.slug}`} className="storefront-category-child-link">{category.name}<span aria-hidden="true"> →</span></Link>)}
    </div>}
  </div>;
}
