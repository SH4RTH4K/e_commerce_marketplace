import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';
import { posts } from './Blog';

export default function BlogDetailPage({ slug }) {
  const post = posts.find(item => item.slug === slug) || posts[0];

  return (
    <StorefrontLayout>
      <Head title={post.title} />
      <div className="template-1-breadcrumb">
        <Link href="/">Home</Link> <span>&nbsp; / &nbsp;</span><Link href="/blog">Blog</Link> <span>&nbsp; / &nbsp;</span>{post.title}
      </div>
      <main className="template-1-blog-page">
        <div className="template-1-blog-layout">
          <article>
            <div className="template-1-blog-image">
              <img src={`/templates/template-1/images/${post.image}`} alt={post.title} />
              <span className="template-1-blog-date"><strong>{post.day}</strong><span>{post.date}</span></span>
            </div>
            <div className="template-1-blog-meta"><span>By Admin &nbsp; | &nbsp; {post.date} &nbsp; | &nbsp; StreetStyle, Fashion</span><span>8 Comments</span></div>
            <h2>{post.title}</h2>
            <p>{post.text} Explore proportions, textures, and small details that help familiar pieces feel fresh through the season.</p>
            <p>Start with the pieces you already enjoy wearing, then add one considered layer or accessory. The best personal style is practical enough for real life and distinctive enough to feel like yours.</p>
            <div className="template-1-quote">Tags &nbsp; <strong>Streetstyle</strong> &nbsp; <strong>Crafts</strong></div>
            <section style={{ marginTop: '42px' }}>
              <h2>Leave a Comment</h2>
              <p>Your email address will not be published. Required fields are marked *</p>
              <form className="template-1-contact-field" onSubmit={event => event.preventDefault()}>
                <textarea name="comment" placeholder="Comment..." />
                <input name="name" placeholder="Name *" style={{ marginTop: '20px' }} />
                <input name="email" type="email" placeholder="Email *" style={{ marginTop: '20px' }} />
                <button type="submit" className="template-1-primary-button" style={{ marginTop: '20px' }}>Post Comment</button>
              </form>
            </section>
          </article>
          <aside>
            <div className="template-1-sidebar-block" style={{ marginTop: 0 }}>
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
