import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPasswordPage() {
  const { data, setData, post, processing, errors, wasSuccessful } = useForm({
    email: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post('/forgot-password');
  };

  return (
    <StorefrontLayout title="Forgot Password">
      <Head title="Forgot Password" />

      <main className="max-w-md mx-auto px-4 py-16 sm:py-24">
        <div className="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100">
          {/* Icon */}
          <div className="flex justify-center mb-6">
            <div className="w-16 h-16 rounded-2xl bg-[#f15a24]/10 flex items-center justify-center">
              <svg className="w-8 h-8 text-[#f15a24]" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
              </svg>
            </div>
          </div>

          <div className="text-center mb-8">
            <h1 className="text-2xl font-extrabold text-gray-900 mb-2">Forgot Password?</h1>
            <p className="text-sm font-medium text-gray-500">Enter your email and we'll send you a reset code.</p>
          </div>

          {wasSuccessful && (
            <div className="mb-5 bg-green-50 border border-green-200 text-green-700 text-sm font-semibold px-4 py-3 rounded-xl flex items-center gap-2">
              <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              Reset code sent! Please check your email.
            </div>
          )}

          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">
                Email Address
              </label>
              <input
                type="email"
                required
                autoFocus
                value={data.email}
                onChange={e => setData('email', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.email
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50'
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="you@example.com"
              />
              {errors.email && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.email}</p>}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-extrabold text-sm py-3.5 rounded-xl shadow-lg shadow-[#f15a24]/25 transition-all hover:-translate-y-0.5 disabled:opacity-70 disabled:hover:translate-y-0"
            >
              {processing ? 'Sending...' : 'Send Reset Code'}
            </button>
          </form>

          <div className="mt-6 text-center">
            <Link href="/login" className="inline-flex items-center gap-1.5 text-sm font-bold text-[#f15a24] hover:text-[#d94a1a] transition-colors">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
              </svg>
              Back to Sign In
            </Link>
          </div>
        </div>
      </main>
    </StorefrontLayout>
  );
}