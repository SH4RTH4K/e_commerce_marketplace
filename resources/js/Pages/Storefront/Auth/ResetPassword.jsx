import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ResetPasswordPage({ email }) {
  const { data, setData, post, processing, errors } = useForm({
    password: '',
    password_confirmation: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post('/reset-password');
  };

  return (
    <StorefrontLayout title="Reset Password">
      <Head title="Reset Password" />

      <main className="max-w-md mx-auto px-4 py-16 sm:py-24">
        <div className="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100">
          {/* Icon */}
          <div className="flex justify-center mb-6">
            <div className="w-16 h-16 rounded-2xl bg-[#f15a24]/10 flex items-center justify-center">
              <svg className="w-8 h-8 text-[#f15a24]" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>

          <div className="text-center mb-8">
            <h1 className="text-2xl font-extrabold text-gray-900 mb-2">Set New Password</h1>
            {email && (
              <p className="text-sm font-medium text-gray-500">
                Resetting password for <span className="font-bold text-gray-700">{email}</span>
              </p>
            )}
          </div>

          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">
                New Password
              </label>
              <input
                type="password"
                required
                autoFocus
                value={data.password}
                onChange={e => setData('password', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.password
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50'
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="Min. 8 characters"
              />
              {errors.password && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.password}</p>}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">
                Confirm New Password
              </label>
              <input
                type="password"
                required
                value={data.password_confirmation}
                onChange={e => setData('password_confirmation', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.password_confirmation
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50'
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="Repeat your new password"
              />
              {errors.password_confirmation && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.password_confirmation}</p>}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-extrabold text-sm py-3.5 rounded-xl shadow-lg shadow-[#f15a24]/25 transition-all hover:-translate-y-0.5 mt-2 disabled:opacity-70 disabled:hover:translate-y-0"
            >
              {processing ? 'Updating...' : 'Update Password'}
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