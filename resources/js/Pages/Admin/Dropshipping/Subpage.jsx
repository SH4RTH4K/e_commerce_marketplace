import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

export default function DropshippingSubpage({ title, description, children }) {
  return (
    <>
      <Head title={`${title} · Dropshipping`} />
      <AdminLayout title="">
        <div className="space-y-5">
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
