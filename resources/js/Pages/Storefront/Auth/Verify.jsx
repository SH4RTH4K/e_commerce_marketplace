import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function VerifyPage({ email, purpose }) {
  const { flash } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({ code: '' });

  const submit = (e) => {
    e.preventDefault();
    post('/verify');
  };

  const resend = (e) => {
    e.preventDefault();
    post('/verify/resend');
  };

  const title = purpose === 'reset' ? 'Verify to Reset Password' : 'Verify Your Email';
  const subtitle = purpose === 'reset'
    ? 'Enter the 6-digit code sent to your email to reset your password.'
    : 'Enter the 6-digit code we sent to your email to verify your account.';

  return (
    <StorefrontLayout title={title}>
      <Head title={title} />

      <main className="max-w-md mx-auto px-4 py-16 sm:py-24">
        <div className="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100">
          {/* Icon */}
          <div className="flex justify-center mb-6">
            <div className="w-16 h-16 rounded-2xl bg-[#f15a24]/10 flex items-center justify-center">
              <svg className="w-8 h-8 text-[#f15a24]" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
              </svg>
            </div>
          </div>

          <div className="text-center mb-6">
            <h1 className="text-2xl font-extrabold text-gray-900 mb-2">{title}</h1>
            <p className="text-sm font-medium text-gray-500">{subtitle}</p>
            {email && (
              <p className="mt-2 text-sm font-bold text-gray-700 bg-gray-50 rounded-lg px-3 py-1.5 inline-block border border-gray-100">
                {email}
              </p>
            )}
          </div>

          {flash?.status && (
            <div className="mb-5 bg-green-50 border border-green-200 text-green-700 text-sm font-semibold px-4 py-3 rounded-xl flex items-center gap-2">
              <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              {flash.status}
            </div>
          )}

          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">
                Verification Code
              </label>
              <input
                type="text"
                inputMode="numeric"
                maxLength={6}
                required
                autoFocus
                value={data.code}
                onChange={e => setData('code', e.target.value.replace(/\D/g, ''))}
                className={`w-full border rounded-xl px-4 py-3 text-center text-2xl font-bold tracking-[0.5em] focus:ring-4 focus:outline-none transition-all ${
                  errors.code
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50'
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="000000"
              />
              {errors.code && <p className="mt-1.5 text-xs font-bold text-red-500 text-center">{errors.code}</p>}
            </div>

            <button
              type="submit"
              disabled={processing || data.code.length < 6}
              className="w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-extrabold text-sm py-3.5 rounded-xl shadow-lg shadow-[#f15a24]/25 transition-all hover:-translate-y-0.5 disabled:opacity-70 disabled:hover:translate-y-0"
            >
              {processing ? 'Verifying...' : 'Verify Code'}
            </button>
          </form>

          <div className="mt-6 text-center space-y-3">
            <p className="text-sm text-gray-500">
              Didn't receive the code?{' '}
              <button
                onClick={resend}
                disabled={processing}
                className="font-bold text-[#f15a24] hover:text-[#d94a1a] transition-colors disabled:opacity-50"
              >
                Resend
              </button>
            </p>
            <Link href="/login" className="inline-flex items-center gap-1.5 text-sm font-bold text-gray-400 hover:text-gray-600 transition-colors">
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