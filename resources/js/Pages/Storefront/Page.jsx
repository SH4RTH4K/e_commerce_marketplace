import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, usePage } from '@inertiajs/react';

function InlinePolicyText({ text }) {
  return String(text || '').split(/(\*\*[^*]+\*\*)/g).map((part, index) => (
    part.startsWith('**') && part.endsWith('**')
      ? <strong key={index}>{part.slice(2, -2)}</strong>
      : part
  ));
}

function PlainPolicyContent({ body }) {
  const content = String(body || '').replace(/\r/g, '').trim();
  const headingPattern = /(^|\s)(#{1,3})\s+(.+?)(?=(?:\s+#{1,3}\s)|$)/g;
  const blocks = [];
  let cursor = 0;
  let match;

  const addParagraphs = (text, keyPrefix) => {
    text.trim().split(/\n{2,}/).filter(Boolean).forEach((paragraph, index) => {
      blocks.push(<p key={`${keyPrefix}-${index}`}><InlinePolicyText text={paragraph.trim()} /></p>);
    });
  };

  while ((match = headingPattern.exec(content)) !== null) {
    addParagraphs(content.slice(cursor, match.index + match[1].length), `text-${cursor}`);
    const Heading = match[2].length === 1 ? 'h2' : 'h3';
    blocks.push(<Heading key={`heading-${match.index}`}><InlinePolicyText text={match[3].trim()} /></Heading>);
    cursor = headingPattern.lastIndex;
  }

  addParagraphs(content.slice(cursor), `text-${cursor}`);

  return blocks.length ? blocks : <p>{content}</p>;
}

function PolicyContent({ body }) {
  const containsHtml = /<\/?[a-z][^>]*>/i.test(String(body || ''));
  const contentClass = `policy-content text-[15px] leading-8 text-gray-600 sm:text-base
    [&>h2]:mt-10 [&>h2]:border-l-4 [&>h2]:border-[#f15a24] [&>h2]:pl-4 [&>h2]:text-2xl [&>h2]:font-extrabold [&>h2]:leading-tight [&>h2]:text-gray-900 [&>h2:first-child]:mt-0
    [&>h3]:mt-8 [&>h3]:text-lg [&>h3]:font-bold [&>h3]:text-gray-900
    [&>p]:mb-5 [&>p:last-child]:mb-0 [&>strong]:font-bold [&>strong]:text-gray-900
    [&>ul]:mb-5 [&>ul]:list-disc [&>ul]:space-y-2 [&>ul]:pl-6 [&>ol]:mb-5 [&>ol]:list-decimal [&>ol]:space-y-2 [&>ol]:pl-6
    [&>a]:font-semibold [&>a]:text-[#f15a24] [&>a]:underline`;

  return containsHtml
    ? <div className={contentClass} dangerouslySetInnerHTML={{ __html: body }} />
    : <div className={contentClass}><PlainPolicyContent body={body} /></div>;
}

export default function PagePage({ title, heading, body, showPageTitle = true }) {
  const { app } = usePage().props;
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';

  if (isTemplateOne) {
    return (
      <StorefrontLayout>
        <Head title={title || heading} />
        {showPageTitle && <div className="template-1-page-title"><h1>{heading}</h1></div>}
        <main className="template-1-legal-page">
          <article className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-10">
            <PolicyContent body={body} />
          </article>
        </main>
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title={title || heading} />
      
      {showPageTitle && (
        <div className="bg-white border-b border-gray-100 py-16 sm:py-24">
          <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 className="text-4xl sm:text-5xl font-black text-gray-900 tracking-tight">{heading}</h1>
          </div>
        </div>
      )}

      {/* Content Section */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="bg-white p-8 sm:p-12 rounded-3xl border border-gray-100 shadow-sm">
          <PolicyContent body={body} />
        </div>
      </div>
    </StorefrontLayout>
  );
}
