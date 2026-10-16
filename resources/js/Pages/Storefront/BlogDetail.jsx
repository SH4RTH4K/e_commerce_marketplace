import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';

export default function BlogDetailPage({ guide, guides = [], seo }) {
  return (
    <StorefrontLayout>
      <Head title={seo?.title} />
      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <nav aria-label="Breadcrumb" className="mb-7 flex flex-wrap gap-2 text-sm text-gray-600">
          <Link href="/">Home</Link><span aria-hidden="true">/</span><Link href="/blog">Shopping guides</Link>
        </nav>
        <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_260px]">
          <article className="min-w-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-9">
            <h1 className="text-2xl font-extrabold leading-tight tracking-tight text-gray-900 sm:text-3xl">{guide.title}</h1>
            <p className="mt-4 text-base leading-relaxed text-gray-600">{guide.summary}</p>
            {guide.sections.map(section => (
              <section key={section.heading} className="mt-8">
                <h2 className="text-lg font-bold text-gray-900">{section.heading}</h2>
                <p className="mt-3 leading-relaxed text-gray-700">{section.text}</p>
              </section>
            ))}
            <div className="mt-8 flex flex-wrap gap-4 border-t border-gray-100 pt-6 text-sm font-semibold text-gray-900">
              <Link href="/shop" className="underline">Browse products</Link>
              <Link href="/shipping" className="underline">Delivery information</Link>
              <Link href="/refund-policy" className="underline">Return policy</Link>
              <Link href="/contact" className="underline">Contact support</Link>
            </div>
          </article>
          <aside className="space-y-4">
            <h2 className="text-lg font-bold text-gray-900">More shopping guides</h2>
            {guides.filter(item => item.title !== guide.title).map(item => <Link key={item.slug} href={`/blog/${item.slug}`} className="block rounded-xl border border-gray-200 bg-white p-4 text-sm font-semibold leading-relaxed text-gray-800 hover:underline">{item.title}</Link>)}
          </aside>
        </div>
      </main>
    </StorefrontLayout>
  );
}
