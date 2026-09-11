import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useRef } from 'react';

export default function PopupNotification({ settings }) {
  const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
    popup_enabled:        settings.popup_enabled || false,
    popup_title:          settings.popup_title || '',
    popup_text:           settings.popup_text || '',
    popup_link:           settings.popup_link || '',
    popup_btn_label:      settings.popup_btn_label || 'Shop Now',
    popup_delay_seconds:  settings.popup_delay_seconds ?? 3,
    popup_frequency:      settings.popup_frequency || 'once_per_session',
    popup_image_file:     null,
    remove_image:         false,
  });

  const [imagePreview, setImagePreview] = useState(settings.popup_image || '');
  const [previewDevice, setPreviewDevice] = useState('desktop'); // desktop | mobile
  const [simulatedOpen, setSimulatedOpen] = useState(true);
  const fileInputRef = useRef(null);

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setData('popup_image_file', file);
      setData('remove_image', false);
      const reader = new FileReader();
      reader.onload = (ev) => setImagePreview(ev.target.result);
      reader.readAsDataURL(file);
    }
  };

  const handleRemoveImage = () => {
    setData('popup_image_file', null);
    setData('remove_image', true);
    setImagePreview('');
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/admin/popup-notification', {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Popup Notification &amp; Notices — Marketing" />
      <AdminLayout title="">
        <div className="space-y-6 max-w-7xl mx-auto pb-12">

          {/* ── Header ── */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Popup Notification &amp; Notices</h1>
              <p className="text-xs text-gray-500 mt-0.5">
                Display engaging promotional popups, announcements, or notices to store visitors with customizable timer delays.
              </p>
            </div>
            {recentlySuccessful && (
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Changes Saved Successfully!
              </span>
            )}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {/* ── Left Column: Form Settings (7 cols) ── */}
            <div className="lg:col-span-7 space-y-5">
              <form onSubmit={handleSubmit} className="space-y-5">

                {/* 1. Main Enable / Disable Card */}
                <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-sm font-bold text-gray-900">Enable Popup Notification</h2>
                      <p className="text-xs text-gray-500 mt-0.5">Toggle whether visitors see this popup on the storefront</p>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={data.popup_enabled}
                        onChange={e => setData('popup_enabled', e.target.checked)}
                        className="sr-only peer"
                      />
                      <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                    </label>
                  </div>
                </div>

                {/* 2. Timer & Trigger Rules */}
                <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
                  <h2 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                    Display Timing &amp; Frequency
                  </h2>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                        Popup Delay Timer (Seconds)
                      </label>
                      <select
                        value={data.popup_delay_seconds}
                        onChange={e => setData('popup_delay_seconds', parseInt(e.target.value, 10))}
                        className="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-orange-300 font-medium"
                      >
                        <option value="0">Immediately on page load (0s)</option>
                        <option value="2">After 2 Seconds</option>
                        <option value="3">After 3 Seconds (Recommended)</option>
                        <option value="5">After 5 Seconds</option>
                        <option value="10">After 10 Seconds</option>
                        <option value="15">After 15 Seconds</option>
                        <option value="30">After 30 Seconds</option>
                        <option value="60">After 1 Minute (60s)</option>
                        <option value="120">After 2 Minutes (120s)</option>
                      </select>
                      <p className="text-[11px] text-gray-400 mt-1">Visitors will see the popup after browsing for this duration.</p>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                        Display Frequency
                      </label>
                      <select
                        value={data.popup_frequency}
                        onChange={e => setData('popup_frequency', e.target.value)}
                        className="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-orange-300 font-medium"
                      >
                        <option value="once_per_session">Once per browser session (Recommended)</option>
                        <option value="every_24h">Once every 24 hours</option>
                        <option value="always">Always show on every page visit</option>
                      </select>
                      <p className="text-[11px] text-gray-400 mt-1">Prevents annoying repeat popups if visitor already closed it.</p>
                    </div>
                  </div>
                </div>

                {/* 3. Notice Content (Title, Body Text) */}
                <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
                  <h2 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Popup Content &amp; Notice
                  </h2>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                      Headline / Notice Title (Optional)
                    </label>
                    <input
                      type="text"
                      value={data.popup_title}
                      onChange={e => setData('popup_title', e.target.value)}
                      placeholder="e.g. 🎉 Special Weekend Offer! or জরুরি নোটিশ"
                      className="w-full text-xs border border-gray-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-300"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                      Message / Description Body (Optional)
                    </label>
                    <textarea
                      rows={4}
                      value={data.popup_text}
                      onChange={e => setData('popup_text', e.target.value)}
                      placeholder="Enter detailed notice, coupon discount details, or promotional message..."
                      className="w-full text-xs border border-gray-200 rounded-xl p-3.5 focus:outline-none focus:ring-2 focus:ring-orange-300"
                    />
                  </div>
                </div>

                {/* 4. Banner / Poster Image Upload */}
                <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
                  <h2 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Popup Banner Image (Optional)
                  </h2>

                  {imagePreview ? (
                    <div className="relative rounded-xl border border-gray-200 overflow-hidden bg-gray-50 max-h-56 flex items-center justify-center p-2">
                      <img src={imagePreview} alt="Popup Preview" className="max-h-52 object-contain rounded-lg shadow-sm" />
                      <button
                        type="button"
                        onClick={handleRemoveImage}
                        className="absolute top-3 right-3 px-2.5 py-1 bg-red-600/90 hover:bg-red-700 text-white rounded-lg text-xs font-semibold shadow-md flex items-center gap-1 transition-colors"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Remove Image
                      </button>
                    </div>
                  ) : (
                    <div
                      onClick={() => fileInputRef.current?.click()}
                      className="border-2 border-dashed border-gray-200 hover:border-orange-400 rounded-2xl p-6 text-center cursor-pointer transition-colors bg-gray-50/50"
                    >
                      <svg className="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.5">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <p className="text-xs font-semibold text-gray-700">Click to upload popup banner image</p>
                      <p className="text-[11px] text-gray-400 mt-0.5">PNG, JPG, WEBP up to 4MB (Recommended size: 600x400px)</p>
                    </div>
                  )}

                  <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleImageChange}
                    accept="image/*"
                    className="hidden"
                  />
                  {errors.popup_image_file && <p className="text-xs text-red-500">{errors.popup_image_file}</p>}
                </div>

                {/* 5. Call To Action (Button & Link) */}
                <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
                  <h2 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    Action Button &amp; Link (Optional)
                  </h2>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                        Button Label
                      </label>
                      <input
                        type="text"
                        value={data.popup_btn_label}
                        onChange={e => setData('popup_btn_label', e.target.value)}
                        placeholder="e.g. Shop Now / অফারটি নিন"
                        className="w-full text-xs border border-gray-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-300"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                        Button Link / URL
                      </label>
                      <input
                        type="text"
                        value={data.popup_link}
                        onChange={e => setData('popup_link', e.target.value)}
                        placeholder="e.g. /shop or https://..."
                        className="w-full text-xs border border-gray-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-300"
                      />
                    </div>
                  </div>
                </div>

                {/* Save Button Bar */}
                <div className="flex items-center justify-end gap-3 pt-2">
                  <button
                    type="submit"
                    disabled={processing}
                    className="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-colors shadow-sm shadow-orange-200 flex items-center gap-2"
                  >
                    {processing ? (
                      <>
                        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Saving Settings…
                      </>
                    ) : (
                      'Save Popup Settings'
                    )}
                  </button>
                </div>

              </form>
            </div>

            {/* ── Right Column: Live Interactive Storefront Preview (5 cols) ── */}
            <div className="lg:col-span-5 space-y-4">
              <div className="sticky top-20 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live Visitor Preview
                  </span>
                  <div className="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
                    <button
                      type="button"
                      onClick={() => setPreviewDevice('desktop')}
                      className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors ${previewDevice === 'desktop' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-800'}`}
                    >
                      Desktop
                    </button>
                    <button
                      type="button"
                      onClick={() => setPreviewDevice('mobile')}
                      className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors ${previewDevice === 'mobile' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-800'}`}
                    >
                      Mobile
                    </button>
                  </div>
                </div>

                {/* Preview Frame */}
                <div className="bg-slate-900/90 rounded-2xl p-4 sm:p-6 min-h-[480px] flex items-center justify-center relative overflow-hidden shadow-inner border border-slate-800">
                  {/* Backdrop blur layer */}
                  <div className="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

                  {simulatedOpen ? (
                    <div className={`relative z-10 bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 transition-all ${
                      previewDevice === 'mobile' ? 'max-w-[290px] w-full' : 'max-w-[380px] w-full'
                    }`}>
                      {/* Close Cross Button */}
                      <button
                        type="button"
                        onClick={() => setSimulatedOpen(false)}
                        className="absolute top-2.5 right-2.5 z-20 w-7 h-7 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center transition-colors text-xs font-bold backdrop-blur-xs"
                        title="Close (Simulate visitor dismiss)"
                      >
                        ✕
                      </button>

                      {/* Image Banner */}
                      {imagePreview && (
                        <div className="w-full bg-gray-100 max-h-48 overflow-hidden flex items-center justify-center">
                          <img
                            src={imagePreview}
                            alt="Popup Preview"
                            className="w-full h-auto object-cover max-h-48"
                          />
                        </div>
                      )}

                      {/* Text Content */}
                      <div className="p-5 text-center space-y-2.5">
                        {data.popup_title ? (
                          <h3 className="text-base font-bold text-gray-900 leading-snug">
                            {data.popup_title}
                          </h3>
                        ) : !imagePreview && (
                          <h3 className="text-base font-bold text-gray-900">
                            Special Announcement
                          </h3>
                        )}

                        {data.popup_text && (
                          <p className="text-xs text-gray-600 leading-relaxed whitespace-pre-line">
                            {data.popup_text}
                          </p>
                        )}

                        {/* Action Button */}
                        {data.popup_btn_label && (
                          <div className="pt-2">
                            <a
                              href={data.popup_link || '#'}
                              onClick={e => e.preventDefault()}
                              className="inline-block w-full py-2.5 px-4 bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs rounded-xl shadow-md transition-colors"
                            >
                              {data.popup_btn_label}
                            </a>
                          </div>
                        )}

                        <p className="text-[10px] text-gray-400 pt-1">
                          (Appears {data.popup_delay_seconds === 0 ? 'immediately' : `after ${data.popup_delay_seconds} seconds`})
                        </p>
                      </div>
                    </div>
                  ) : (
                    <div className="relative z-10 text-center space-y-3">
                      <p className="text-xs text-slate-400">Popup dismissed by visitor.</p>
                      <button
                        type="button"
                        onClick={() => setSimulatedOpen(true)}
                        className="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-orange-600 transition-colors"
                      >
                        Re-open Preview
                      </button>
                    </div>
                  )}
                </div>

                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-800 space-y-1">
                  <p className="font-bold flex items-center gap-1.5 text-amber-900">
                    <svg className="w-4 h-4 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10" />
                      <line x1="12" y1="16" x2="12" y2="12" />
                      <line x1="12" y1="8" x2="12.01" y2="8" />
                    </svg>
                    How it works for visitors:
                  </p>
                  <p className="text-amber-800 leading-relaxed">
                    When enabled, any visitor who arrives at your website will browse for {data.popup_delay_seconds} seconds before this popup smoothly fades in. They can easily click the <strong>(X)</strong> cross button or background to dismiss it.
                  </p>
                </div>

              </div>
            </div>

          </div>

        </div>
      </AdminLayout>
    </>
  );
}
