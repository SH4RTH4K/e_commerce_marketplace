import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function LoginPage() {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const submit = (e) => {
    e.preventDefault();

    // Make the common admin mistake recoverable: the storefront form is
    // email-based, while the admin portal uses the username "admin".
    if (data.email.trim().toLowerCase() === 'admin') {
      window.location.assign('/admin/login');
      return;
    }

    post('/login');
  };

  return (
    <StorefrontLayout title="Login">
      <Head title="Login" />
      
      <main className="max-w-md mx-auto px-4 py-16 sm:py-24">
        <div className="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100">
          <div className="grid grid-cols-2 gap-1 rounded-xl bg-gray-100 p-1 mb-8" aria-label="Login type">
            <span className="rounded-lg bg-[#f15a24] px-3 py-2 text-center text-sm font-bold text-white shadow-sm">
              User Login
            </span>
            <Link
              href="/admin/login"
              className="rounded-lg px-3 py-2 text-center text-sm font-bold text-gray-500 transition-colors hover:bg-white hover:text-gray-900"
            >
              Admin Login
            </Link>
          </div>

          <div className="text-center mb-8">
            <h1 className="text-3xl font-extrabold text-gray-900 mb-2">Welcome Back!</h1>
            <p className="text-sm font-medium text-gray-500">Log in to your account to continue</p>
          </div>

          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Email Address</label>
              <input
                type="text"
                inputMode="email"
                required
                autoFocus
                value={data.email}
                onChange={e => setData('email', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.email 
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50 text-red-900' 
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="you@example.com"
              />
              {errors.email && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.email}</p>}
              <p className="mt-1.5 text-xs text-gray-400">
                Administrator? Use the <Link href="/admin/login" className="font-bold text-[#f15a24] hover:text-[#d94a1a]">Admin Login</Link> with username <code className="font-semibold">admin</code>.
              </p>
            </div>

            <div>
              <div className="flex justify-between items-center mb-1.5">
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wide">Password</label>
                <Link href="/forgot-password" className="text-xs font-bold text-[#f15a24] hover:text-[#d94a1a]">
                  Forgot Password?
                </Link>
              </div>
              <input
                type="password"
                required
                value={data.password}
                onChange={e => setData('password', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.password 
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50 text-red-900' 
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="••••••••"
              />
              {errors.password && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.password}</p>}
            </div>

            <div className="flex items-center">
              <input
                id="remember"
                type="checkbox"
                checked={data.remember}
                onChange={e => setData('remember', e.target.checked)}
                className="w-4 h-4 rounded border-gray-300 text-[#f15a24] focus:ring-[#f15a24]"
              />
              <label htmlFor="remember" className="ml-2 block text-sm font-semibold text-gray-600 select-none cursor-pointer">
                Remember me
              </label>
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-extrabold text-sm sm:text-base py-3.5 rounded-xl shadow-lg shadow-[#f15a24]/25 transition-all hover:-translate-y-0.5 mt-4 disabled:opacity-70 disabled:hover:translate-y-0"
            >
              {processing ? 'Logging in...' : 'Log In'}
            </button>
          </form>

          <div className="mt-8 text-center border-t border-gray-100 pt-6">
            <p className="text-sm font-medium text-gray-500">
              Don't have an account?{' '}
              <Link href="/register" className="font-bold text-[#f15a24] hover:text-[#d94a1a]">
                Sign up
              </Link>
            </p>
          </div>
        </div>
      </main>
    </StorefrontLayout>
  );
}
