import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';

export default function AboutPage() {
  return (
    <StorefrontLayout>
      <Head title="About" />
      <div className="template-1-page-title"><h1>About</h1></div>
      <main className="template-1-about-page">
        <div className="template-1-about-layout">
          <article className="template-1-about-copy">
            <h2>Our Story</h2>
            <p>
              We believe good design should make everyday shopping feel simple, personal, and enjoyable. Our marketplace brings together useful products, thoughtful details, and dependable delivery in one welcoming store.
            </p>
            <p>
              From the first product discovery to the moment an order arrives, we focus on clear information, fair prices, and helpful support. Every collection is selected to make modern living a little more expressive.
            </p>
            <p>
              Have a question? Visit our <Link href="/contact">contact page</Link> and our team will be happy to help.
            </p>
          </article>
          <div className="template-1-about-image">
            <img src="/templates/template-1/images/about-01.jpg" alt="Our store" />
          </div>
        </div>

        <div className="template-1-about-layout" style={{ marginTop: '90px' }}>
          <div className="template-1-about-image">
            <img src="/templates/template-1/images/about-02.jpg" alt="Our mission" />
          </div>
          <article>
            <h2>Our Mission</h2>
            <p>
              Our mission is to make trustworthy shopping accessible without losing the warmth of a local store. We keep the experience clear, useful, and centered on the people who use it.
            </p>
            <blockquote className="template-1-quote">
              Creativity is just connecting things. When you ask creative people how they did something, they feel a little guilty because they did not really do it, they just saw something.
              <br /><strong>- Steve Jobs</strong>
            </blockquote>
          </article>
        </div>
      </main>
    </StorefrontLayout>
  );
}
