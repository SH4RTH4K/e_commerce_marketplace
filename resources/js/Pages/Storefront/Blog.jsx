import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';

const posts = [
  {
    slug: 'winter-dresses',
    day: '22',
    date: 'Jan 2018',
    image: 'blog-04.jpg',
    title: '8 Inspiring Ways to Wear Dresses in the Winter',
    text: 'Discover simple styling ideas that make winter layers feel comfortable, confident, and distinctly your own.',
  },
  {
    slug: 'mens-holiday-gifts',
    day: '18',
    date: 'Jan 2018',
    image: 'blog-05.jpg',
    title: "The Great Big List of Men's Gifts for the Holidays",
    text: 'A practical guide to finding useful, personal gifts for the people who are hardest to shop for.',
  },
  {
    slug: 'winter-to-spring-trends',
    day: '16',
    date: 'Jan 2018',
    image: 'blog-06.jpg',
    title: '5 Winter-to-Spring Fashion Trends to Try Now',
    text: 'Lighten up your wardrobe with versatile pieces that move easily from cool mornings to brighter afternoons.',
  },
];

export { posts };

export default function BlogPage() {
  return (
    <StorefrontLayout>
      <Head title="Blog" />
      <div className="template-1-page-title"><h1>Blog</h1></div>
      <main className="template-1-blog-page">
        <div className="template-1-blog-layout">
          <section>
            {posts.map(post => (
              <article key={post.slug} className="template-1-blog-post">
                <Link href={`/blog/${post.slug}`} className="template-1-blog-image">
                  <img src={`/templates/template-1/images/${post.image}`} alt={post.title} />
                  <span className="template-1-blog-date"><strong>{post.day}</strong><span>{post.date}</span></span>
                </Link>
                <h2><Link href={`/blog/${post.slug}`}>{post.title}</Link></h2>
                <p>{post.text}</p>
                <div className="template-1-blog-meta">
                  <span>By Admin &nbsp; | &nbsp; StreetStyle, Fashion</span>
                  <Link href={`/blog/${post.slug}`}>Continue Reading &rarr;</Link>
                </div>
              </article>
            ))}
          </section>
          <aside>
            <form action="/shop" method="get" className="template-1-sidebar-search">
              <input name="q" placeholder="Search" aria-label="Search products" />
              <button type="submit" aria-label="Search">&#128269;</button>
            </form>
            <div className="template-1-sidebar-block">
              <h3>Categories</h3>
              {['Fashion', 'Beauty', 'Street Style', 'Life Style', 'DIY & Crafts'].map(item => <a href="/shop" key={item}>{item}</a>)}
            </div>
            <div className="template-1-sidebar-block">
              <h3>Featured Products</h3>
              <a href="/shop">White Shirt With Pleat Detail Back</a>
              <a href="/shop">Converse All Star Hi Black Canvas</a>
              <a href="/shop">Nixon Porter Leather Watch In Tan</a>
            </div>
          </aside>
        </div>
      </main>
    </StorefrontLayout>
  );
}
