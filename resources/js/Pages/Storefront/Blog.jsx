import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';

export default function BlogPage({ guides = [], seo }) {
  return (
    <StorefrontLayout>
      <Head title={seo?.title} />
      <main className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <header className="mb-8 max-w-2xl">
          <p className="text-sm font-semibold text-gray-600">SHOP WITH CONFIDENCE</p>
          <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-gray-900">Shopping guides</h1>
          <p className="mt-3 leading-relaxed text-gray-600">Helpful checks before you choose a product, place an order or request assistance.</p>
        </header>
        <div className="grid gap-5 md:grid-cols-3">
          {guides.map((guide, index) => (
            <article key={guide.slug} className="flex flex-col rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
              <span className="mb-5 text-sm font-bold text-gray-500">GUIDE {String(index + 1).padStart(2, '0')}</span>
              <h2 className="text-xl font-bold leading-snug text-gray-900"><Link href={`/blog/${guide.slug}`} className="hover:underline">{guide.title}</Link></h2>
              <p className="mb-6 mt-3 text-sm leading-relaxed text-gray-600">{guide.summary}</p>
              <Link href={`/blog/${guide.slug}`} className="mt-auto font-semibold text-gray-900 underline underline-offset-4">Read guide &rarr;</Link>
            </article>
          ))}
        </div>
        <p className="mt-8 text-sm text-gray-600">Need help with a specific product? <Link href="/contact" className="font-semibold text-gray-900 underline">Contact our team</Link> with its name or SKU.</p>
      </main>
    </StorefrontLayout>
  );
}
