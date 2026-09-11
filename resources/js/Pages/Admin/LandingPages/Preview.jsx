import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

/**
 * Design preview is rendered as a Blade view (not Inertia) at:
 *   GET /admin/landing-pages/designs/{design}/preview
 *
 * This Inertia page is a fallback shown if the route accidentally
 * hits this component. It provides a helpful redirect back.
 */
export default function PreviewPage({ design }) {
  return (
    <>
      <Head title="Landing Page Preview" />
      <AdminLayout title="Landing Page Preview">
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center max-w-lg mx-auto space-y-4">
          <div className="w-16 h-16 bg-orange-50 rounded-2xl flex items-center justify-center mx-auto">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f15a24" strokeWidth="2">
              <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
            </svg>
          </div>
          <h2 className="text-lg font-semibold text-gray-900">Design Preview</h2>
          <p className="text-sm text-gray-500">
            Landing page design previews open in a new browser tab as a live Blade-rendered page.
            {design && ` You selected the "${design}" design.`}
          </p>
          <div className="flex gap-3 justify-center pt-2">
            <a
              href={design ? `/admin/landing-pages/designs/${design}/preview` : '#'}
              target="_blank"
              rel="noopener"
              className="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors"
            >
              Open Live Preview
            </a>
            <button
              onClick={() => router.get('/admin/landing-pages/create')}
              className="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors"
            >
              ← Back to Designs
            </button>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}