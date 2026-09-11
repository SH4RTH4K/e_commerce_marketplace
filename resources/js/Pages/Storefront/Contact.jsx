import { useEffect } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function ContactPage({ phone, email, address, hours, fields }) {
  const { flash } = usePage().props;
  const { app } = usePage().props;
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';
  
  // Initialize form state dynamically
  const initialData = { fields: {} };
  (fields || []).forEach(f => {
    initialData.fields[f.key] = '';
  });
  
  const { data, setData, post, processing, errors, reset } = useForm(initialData);

  const submit = (e) => {
    e.preventDefault();
    post('/contact', {
      preserveScroll: true,
      onSuccess: () => reset(),
    });
  };

  if (isTemplateOne) {
    return (
      <StorefrontLayout>
        <Head title="Contact" />
        <div className="template-1-page-title"><h1>Contact</h1></div>
        <main className="template-1-contact-page">
          <div className="template-1-contact-layout">
            <section className="template-1-contact-panel">
              <h2>Send Us A Message</h2>
              {flash?.status && <p className="template-1-contact-success">{flash.status}</p>}
              <form onSubmit={submit}>
                {(fields || []).map(field => (
                  <div key={field.id} className="template-1-contact-field">
                    {field.type === 'textarea' ? (
                      <textarea
                        required={field.is_required}
                        value={data.fields[field.key] || ''}
                        onChange={e => setData('fields', { ...data.fields, [field.key]: e.target.value })}
                        placeholder={field.label}
                      />
                    ) : (
                      <input
                        type={field.type === 'email' ? 'email' : (field.type === 'tel' ? 'tel' : 'text')}
                        required={field.is_required}
                        value={data.fields[field.key] || ''}
                        onChange={e => setData('fields', { ...data.fields, [field.key]: e.target.value })}
                        placeholder={`${field.label}${field.is_required ? ' *' : ''}`}
                      />
                    )}
                    {errors[`fields.${field.key}`] && <p className="template-1-form-error">{errors[`fields.${field.key}`]}</p>}
                  </div>
                ))}
                <button type="submit" disabled={processing} className="template-1-primary-button">
                  {processing ? 'Sending...' : 'Submit'}
                </button>
              </form>
            </section>
            <section className="template-1-contact-info">
              <div><h3>Address</h3><p>{address || 'Please contact our support team for store details.'}</p></div>
              <div><h3>Lets Talk</h3>{phone ? <a href={`tel:${phone}`}>{phone}</a> : <p>Our support team is ready to help.</p>}</div>
              <div><h3>Sale Support</h3>{email ? <a href={`mailto:${email}`}>{email}</a> : <p>Send us a message and we will reply soon.</p>}</div>
              {hours && <div><h3>Opening Hours</h3><p>{hours}</p></div>}
            </section>
          </div>
        </main>
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title="Contact Us" />
      
      {/* Hero Section */}
      <div className="bg-white border-b border-gray-100 py-16 sm:py-24">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h1 className="text-4xl sm:text-5xl font-black text-gray-900 tracking-tight mb-4">Contact Us</h1>
          <p className="text-lg text-gray-500 max-w-2xl mx-auto">Have a question or need help? We're here for you. Reach out to our team below.</p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid lg:grid-cols-3 gap-12 lg:gap-16">
          
          {/* Contact Info Sidebar */}
          <div className="space-y-8 lg:col-span-1">
            {address && (
              <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-start gap-4">
                <div className="bg-[#f15a24]/10 text-[#f15a24] p-3 rounded-2xl shrink-0">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
                <div>
                  <h3 className="text-lg font-bold text-gray-900 mb-1">Our Store</h3>
                  <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{address}</p>
                </div>
              </div>
            )}
            
            {phone && (
              <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-start gap-4">
                <div className="bg-[#f15a24]/10 text-[#f15a24] p-3 rounded-2xl shrink-0">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                </div>
                <div>
                  <h3 className="text-lg font-bold text-gray-900 mb-1">Phone Number</h3>
                  <a href={`tel:${phone}`} className="text-sm text-gray-600 hover:text-[#f15a24] font-medium transition-colors">{phone}</a>
                </div>
              </div>
            )}
            
            {email && (
              <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-start gap-4">
                <div className="bg-[#f15a24]/10 text-[#f15a24] p-3 rounded-2xl shrink-0">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </div>
                <div>
                  <h3 className="text-lg font-bold text-gray-900 mb-1">Email Address</h3>
                  <a href={`mailto:${email}`} className="text-sm text-gray-600 hover:text-[#f15a24] font-medium transition-colors">{email}</a>
                </div>
              </div>
            )}
            
            {hours && (
              <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-start gap-4">
                <div className="bg-[#f15a24]/10 text-[#f15a24] p-3 rounded-2xl shrink-0">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                  <h3 className="text-lg font-bold text-gray-900 mb-1">Business Hours</h3>
                  <p className="text-sm text-gray-600 whitespace-pre-wrap leading-relaxed">{hours}</p>
                </div>
              </div>
            )}
          </div>

          {/* Contact Form */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-10">
              <h2 className="text-2xl font-black text-gray-900 mb-6">Send us a message</h2>
              
              {flash?.status && (
                <div className="mb-6 bg-green-50 border border-green-100 text-green-700 p-4 rounded-xl text-sm font-bold flex items-center gap-3">
                  <svg className="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  {flash.status}
                </div>
              )}

              <form onSubmit={submit} className="space-y-5">
                {fields?.map(field => (
                  <div key={field.id}>
                    <label className="block text-sm font-bold text-gray-900 mb-2">
                      {field.label} {field.is_required && <span className="text-[#f15a24]">*</span>}
                    </label>
                    {field.type === 'textarea' ? (
                      <textarea
                        required={field.is_required}
                        value={data.fields[field.key] || ''}
                        onChange={e => setData('fields', { ...data.fields, [field.key]: e.target.value })}
                        rows={4}
                        className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium"
                      ></textarea>
                    ) : (
                      <input
                        type={field.type === 'email' ? 'email' : (field.type === 'tel' ? 'tel' : 'text')}
                        required={field.is_required}
                        value={data.fields[field.key] || ''}
                        onChange={e => setData('fields', { ...data.fields, [field.key]: e.target.value })}
                        className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium"
                      />
                    )}
                    {errors[`fields.${field.key}`] && <p className="mt-1 text-xs font-bold text-red-500">{errors[`fields.${field.key}`]}</p>}
                  </div>
                ))}
                
                <button
                  type="submit"
                  disabled={processing}
                  className="w-full md:w-auto px-8 py-3.5 bg-[#f15a24] hover:bg-[#d94a1a] text-white rounded-xl font-bold transition-all disabled:opacity-75 mt-4 shadow-lg shadow-[#f15a24]/30"
                >
                  {processing ? 'Sending...' : 'Send Message'}
                </button>
              </form>
            </div>
          </div>

        </div>
      </div>
    </StorefrontLayout>
  );
}
