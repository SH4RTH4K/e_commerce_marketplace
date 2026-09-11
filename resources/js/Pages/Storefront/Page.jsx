import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, usePage } from '@inertiajs/react';

export default function PagePage({ title, heading, body }) {
  const { app } = usePage().props;
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';

  if (isTemplateOne) {
    return (
      <StorefrontLayout>
        <Head title={title || heading} />
        <div className="template-1-page-title"><h1>{heading}</h1></div>
        <main className="template-1-legal-page">
          <div dangerouslySetInnerHTML={{ __html: body }} />
        </main>
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title={title || heading} />
      
      {/* Hero Section */}
      <div className="bg-white border-b border-gray-100 py-16 sm:py-24">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h1 className="text-4xl sm:text-5xl font-black text-gray-900 tracking-tight">{heading}</h1>
        </div>
      </div>

      {/* Content Section */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="bg-white p-8 sm:p-12 rounded-3xl border border-gray-100 shadow-sm">
          <div 
            className="text-gray-700 leading-relaxed text-base sm:text-lg
              [&>h1]:text-3xl [&>h1]:font-black [&>h1]:text-gray-900 [&>h1]:mt-10 [&>h1]:mb-4 [&>h1:first-child]:mt-0
              [&>h2]:text-2xl [&>h2]:font-bold [&>h2]:text-gray-900 [&>h2]:mt-8 [&>h2]:mb-4 [&>h2:first-child]:mt-0
              [&>h3]:text-xl [&>h3]:font-bold [&>h3]:text-gray-900 [&>h3]:mt-6 [&>h3]:mb-3 [&>h3:first-child]:mt-0
              [&>p]:mb-6 [&>p:last-child]:mb-0
              [&>ul]:list-disc [&>ul]:pl-5 [&>ul]:mb-6 [&>ul>li]:mb-2
              [&>ol]:list-decimal [&>ol]:pl-5 [&>ol]:mb-6 [&>ol>li]:mb-2
              [&>a]:text-[#f15a24] [&>a]:underline hover:[&>a]:text-[#d94a1a]
              [&>strong]:font-bold [&>strong]:text-gray-900"
            dangerouslySetInnerHTML={{ __html: body }}
          />
        </div>
      </div>
    </StorefrontLayout>
  );
}
