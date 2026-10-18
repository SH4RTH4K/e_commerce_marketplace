import AdminLayout from '@/Layouts/AdminLayout';
import { Head, usePage } from '@inertiajs/react';

export default function DropshippingSubpage({ title, description, children, supplier: contextSupplier }) {
  const { props = {}, url = '' } = usePage();
  const supplierId = contextSupplier !== undefined ? contextSupplier?.id : props.selected_supplier_id || props.filters?.supplier_id;
  const suppliers = props.suppliers || props.sync_suppliers || [];
  const supplier = contextSupplier !== undefined ? contextSupplier : suppliers.find(item => Number(item.id) === Number(supplierId));
  const href = path => `${path}${supplierId ? `?supplier_id=${supplierId}` : ''}`;
  return (
    <>
      <Head title={`${title} · Dropshipping`} />
      <AdminLayout title="">
        <div className="space-y-5">
          <div className="rounded-2xl border border-gray-100 bg-white p-3 shadow-sm sm:p-4">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm"><a href={href('/admin/dropshipping')} className="font-semibold text-gray-700 hover:text-orange-600">← Dropshipping overview</a>{supplier && <span className="rounded-lg bg-orange-50 px-3 py-1 font-semibold text-orange-800">{supplier.name}</span>}</div>
            <nav aria-label="Dropshipping workflow" className="mt-3 flex gap-2 overflow-x-auto pb-1">{[['Connection', '/admin/dropshipping/settings'], ['Prices', '/admin/dropshipping/pricing'], ['Categories', '/admin/dropshipping/categories'], ['Supplier products', '/admin/dropshipping/products'], ['Imported products', '/admin/dropshipping/imported']].map(([label, path]) => <a key={path} href={href(path)} aria-current={url.split('?')[0] === path ? 'page' : undefined} className={`shrink-0 rounded-lg px-3 py-2 text-xs font-semibold ${url.split('?')[0] === path ? 'bg-orange-500 text-white' : 'bg-gray-50 text-gray-600 hover:bg-orange-50'}`}>{label}</a>)}</nav>
          </div>
          <div>
            <h1 className="text-xl font-bold text-gray-900">{title}</h1>
            <p className="text-sm text-gray-400 mt-0.5">{description}</p>
          </div>
          {children}
        </div>
      </AdminLayout>
    </>
  );
}
