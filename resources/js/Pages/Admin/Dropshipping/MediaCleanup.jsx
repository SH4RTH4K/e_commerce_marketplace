import DropshippingSubpage from './Subpage';

function Stat({ label, value, note, tone = 'slate' }) {
  const tones = {
    slate: 'bg-slate-50 text-slate-900',
    blue: 'bg-blue-50 text-blue-900',
    emerald: 'bg-emerald-50 text-emerald-900',
    amber: 'bg-amber-50 text-amber-900',
  };

  return <div className={`rounded-2xl border border-gray-100 p-5 shadow-sm ${tones[tone]}`}>
    <p className="text-xs uppercase tracking-wider opacity-60">{label}</p>
    <p className="mt-2 text-3xl font-bold">{Number(value || 0).toLocaleString()}</p>
    <p className="mt-1 text-xs opacity-60">{note}</p>
  </div>;
}

export default function MediaCleanup({ audit = {} }) {
  const supplierProducts = Number(audit.supplier_products || 0);
  const supplierImages = Number(audit.supplier_image_references || 0);
  const externalImages = Number(audit.external_image_references || 0);
  const localImages = Number(audit.local_image_references || 0);
  const unlinkedImages = Number(audit.unlinked_image_references || 0);

  return <DropshippingSubpage title="Media Cleanup" description="Review integration-owned media before cleanup. Merchant-uploaded images are never removed by this module.">
    <div className="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
      <strong className="block mb-1">Media audit completed</strong>
      This report separates supplier URL references from local uploaded files. No file is deleted automatically, so merchant-owned media stays protected.
    </div>

    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <Stat label="Imported products" value={supplierProducts} note="Products linked to a supplier" tone="slate" />
      <Stat label="Supplier image references" value={supplierImages} note="Images attached to imported products" tone="blue" />
      <Stat label="External image URLs" value={externalImages} note="Loaded from supplier sources" tone="emerald" />
      <Stat label="Local image references" value={localImages} note="Files that need ownership review" tone={localImages ? 'amber' : 'emerald'} />
    </div>

    <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 className="font-bold text-gray-900">Current result</h2>
        {localImages === 0 ? (
          <p className="mt-3 text-sm text-emerald-700 bg-emerald-50 rounded-xl p-3">There are no local supplier image files to clean. Current imported product images are external URLs.</p>
        ) : (
          <p className="mt-3 text-sm text-amber-800 bg-amber-50 rounded-xl p-3">{localImages.toLocaleString()} local image references require review before any cleanup can be enabled.</p>
        )}
        {unlinkedImages > 0 && <p className="mt-3 text-sm text-gray-500">{unlinkedImages.toLocaleString()} product image references belong to non-dropshipping products and are excluded from cleanup.</p>}
      </section>

      <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 className="font-bold text-gray-900">Standard cleanup process</h2>
        <ol className="mt-3 space-y-3 text-sm text-gray-600">
          <li><strong className="text-gray-900">1. Audit:</strong> identify the product, supplier, image owner, and source type.</li>
          <li><strong className="text-gray-900">2. Review:</strong> confirm the product is no longer linked or the image is no longer used.</li>
          <li><strong className="text-gray-900">3. Remove:</strong> delete only verified integration-owned local files, never merchant uploads.</li>
          <li><strong className="text-gray-900">4. Verify:</strong> reload product pages and confirm no broken image references remain.</li>
        </ol>
      </section>
    </div>
  </DropshippingSubpage>;
}
