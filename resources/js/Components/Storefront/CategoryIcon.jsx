const CATEGORY_ICON_PATHS = {
  fashion: (
    <>
      <path d="M8 4l4 2 4-2 4 3-2 4-2-1v10H8V10l-2 1-2-4 4-3Z" />
      <path d="M9 8h6" />
    </>
  ),
  beauty: (
    <>
      <path d="M9 3h6v4H9zM10 7h4v14h-4z" />
      <path d="M8 21h8" />
    </>
  ),
  kids: (
    <>
      <circle cx="12" cy="12" r="8" />
      <circle cx="9" cy="10" r="1" fill="currentColor" stroke="none" />
      <circle cx="15" cy="10" r="1" fill="currentColor" stroke="none" />
      <path d="M9 15c1.6 1.4 4.4 1.4 6 0M5 7 7 4M19 7l-2-3" />
    </>
  ),
  gadgets: (
    <>
      <rect x="7" y="2.5" width="10" height="19" rx="2" />
      <path d="M10 5h4M11 18.5h2M3 10h2M19 10h2M4.5 6.5l1.5 1M19.5 6.5l-1.5 1" />
    </>
  ),
  watch: (
    <>
      <path d="M9 5V2h6v3M9 19v3h6v-3" />
      <rect x="6" y="5" width="12" height="14" rx="4" />
      <path d="M12 9v3l2 1" />
    </>
  ),
  home: (
    <>
      <path d="m3 11 9-7 9 7M5 10v10h14V10M9 20v-6h6v6" />
    </>
  ),
  food: (
    <>
      <path d="M5 3v7M3 3v4a2 2 0 0 0 4 0V3M5 10v11M15 3v18M15 3c3 2 4 5 2 8h-2" />
    </>
  ),
  gift: (
    <>
      <path d="M4 10h16v11H4zM3 7h18v3H3zM12 7v14M12 7H8.5a2 2 0 1 1 0-4C11 3 12 7 12 7ZM12 7h3.5a2 2 0 1 0 0-4C13 3 12 7 12 7Z" />
    </>
  ),
  winter: (
    <>
      <path d="M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9M8 5l4 4 4-4M8 19l4-4 4 4M3 12h5m8 0h5" />
    </>
  ),
  sports: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="m8 5 4 3 4-3M3.5 14l4.5-1 2 5M20.5 14l-4.5-1-2 5M12 8v4l-2 2h4l-2-2" />
    </>
  ),
  automotive: (
    <>
      <path d="m5 11 2-5h10l2 5M3 11h18v7H3zM7 11h10" />
      <circle cx="7" cy="18" r="1.5" />
      <circle cx="17" cy="18" r="1.5" />
    </>
  ),
  books: (
    <>
      <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5zM4 4.5v17M8 6h8M8 10h7" />
    </>
  ),
  other: (
    <>
      <path d="m12 3 1.3 5.7L19 10l-5.7 1.3L12 17l-1.3-5.7L5 10l5.7-1.3L12 3ZM19 16l.6 2.4L22 19l-2.4.6L19 22l-.6-2.4L16 19l2.4-.6L19 16Z" />
    </>
  ),
};

export const CATEGORY_ICON_OPTIONS = [
  { value: 'fashion', label: 'Fashion' },
  { value: 'beauty', label: 'Beauty' },
  { value: 'kids', label: 'Kids & Toys' },
  { value: 'gadgets', label: 'Gadgets & Electronics' },
  { value: 'watch', label: 'Watch & Timepieces' },
  { value: 'home', label: 'Home & Lifestyle' },
  { value: 'food', label: 'Food & Groceries' },
  { value: 'gift', label: 'Customize & Gifts' },
  { value: 'winter', label: 'Winter' },
  { value: 'sports', label: 'Sports' },
  { value: 'automotive', label: 'Automotive' },
  { value: 'books', label: 'Books & Office' },
  { value: 'other', label: 'Other' },
];

function resolveCategoryIcon(icon, name) {
  const explicit = String(icon || '').trim().toLowerCase();
  if (CATEGORY_ICON_PATHS[explicit]) return explicit;

  const label = String(name || '').toLowerCase();
  if (/fashion|clothing|men|women/.test(label)) return 'fashion';
  if (/beauty|health|cosmetic|skin/.test(label)) return 'beauty';
  if (/kid|baby|toy/.test(label)) return 'kids';
  if (/gadget|electronic|phone|laptop|computer/.test(label)) return 'gadgets';
  if (/watch|timepiece/.test(label)) return 'watch';
  if (/home|living|furniture|lifestyle/.test(label)) return 'home';
  if (/food|grocer|grocery|pantry/.test(label)) return 'food';
  if (/gift|custom/.test(label)) return 'gift';
  if (/winter|season/.test(label)) return 'winter';
  if (/sport|fitness|outdoor/.test(label)) return 'sports';
  if (/auto|car|motor/.test(label)) return 'automotive';
  if (/book|office|station/.test(label)) return 'books';

  return 'other';
}

export default function CategoryIcon({ icon, name, className = 'h-12 w-12' }) {
  const resolvedIcon = resolveCategoryIcon(icon, name);

  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {CATEGORY_ICON_PATHS[resolvedIcon]}
    </svg>
  );
}
