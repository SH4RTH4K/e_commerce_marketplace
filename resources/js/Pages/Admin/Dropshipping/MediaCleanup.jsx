import DropshippingSubpage from './Subpage';

export default function MediaCleanup({ supplier_image_links = 0, local_product_images = 0 }) {
  return <DropshippingSubpage title="Media Cleanup" description="Review integration-owned media before cleanup. Merchant-uploaded images are never removed by this module.">
    <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800">Automatic deletion is intentionally unavailable until supplier image ownership and content hashes are established by the verified image pipeline.</div>
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4"><div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5"><p className="text-xs uppercase tracking-wider text-gray-400">Linked supplier records</p><p className="text-3xl font-bold text-gray-900 mt-2">{supplier_image_links}</p></div><div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5"><p className="text-xs uppercase tracking-wider text-gray-400">Local product images</p><p className="text-3xl font-bold text-gray-900 mt-2">{local_product_images}</p></div></div>
  </DropshippingSubpage>;
}
