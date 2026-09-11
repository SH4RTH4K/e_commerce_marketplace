import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function RegisterPage() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post('/register');
  };

  return (
    <StorefrontLayout title="Register">
      <Head title="Create an Account" />
      
      <main className="max-w-xl mx-auto px-4 py-16 sm:py-24">
        <div className="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100">
          <div className="text-center mb-8">
            <h1 className="text-3xl font-extrabold text-gray-900 mb-2">Create an Account</h1>
            <p className="text-sm font-medium text-gray-500">Join us to manage your orders easily</p>
          </div>

          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Full Name</label>
              <input
                type="text"
                required
                autoFocus
                value={data.name}
                onChange={e => setData('name', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.name 
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50 text-red-900' 
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="John Doe"
              />
              {errors.name && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.name}</p>}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Email Address</label>
              <input
                type="email"
                required
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
            </div>
            
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Phone Number (Optional)</label>
              <input
                type="tel"
                value={data.phone}
                onChange={e => setData('phone', e.target.value)}
                className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all ${
                  errors.phone 
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/50 text-red-900' 
                    : 'border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white'
                }`}
                placeholder="e.g., 01XXXXXXXXX"
              />
              {errors.phone && <p className="mt-1.5 text-xs font-bold text-red-500">{errors.phone}</p>}
            </div>

            <div className="grid sm:grid-cols-2 gap-5">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Password</label>
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

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide">Confirm Password</label>
                <input
                  type="password"
                  required
                  value={data.password_confirmation}
                  onChange={e => setData('password_confirmation', e.target.value)}
                  className={`w-full border rounded-xl px-4 py-3 text-sm focus:ring-4 focus:outline-none transition-all border-gray-200 focus:border-[#f15a24] focus:ring-[#f15a24]/20 bg-gray-50 hover:bg-white`}
                  placeholder="••••••••"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-extrabold text-sm sm:text-base py-3.5 rounded-xl shadow-lg shadow-[#f15a24]/25 transition-all hover:-translate-y-0.5 mt-6 disabled:opacity-70 disabled:hover:translate-y-0"
            >
              {processing ? 'Creating Account...' : 'Create Account'}
            </button>
          </form>

          <div className="mt-8 text-center border-t border-gray-100 pt-6">
            <p className="text-sm font-medium text-gray-500">
              Already have an account?{' '}
              <Link href="/login" className="font-bold text-[#f15a24] hover:text-[#d94a1a]">
                Log in
              </Link>
            </p>
          </div>
        </div>
      </main>
    </StorefrontLayout>
  );
}