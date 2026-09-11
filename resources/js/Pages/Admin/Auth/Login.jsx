import { Head, Link, useForm } from '@inertiajs/react';

export default function AdminLogin({ app }) {
  const { data, setData, post, processing, errors } = useForm({ username: '', password: '' });

  const submit = (e) => {
    e.preventDefault();
    post('/admin/login');
  };

  return (
    <>
      <Head title="Admin Login" />
      <div className="min-h-screen bg-gradient-to-br from-[#1a1d2e] to-[#2d3154] flex items-center justify-center p-4">
        <div className="w-full max-w-md">
          {/* Logo */}
          <div className="text-center mb-8">
            {app?.logo_url
              ? <img src={app.logo_url} alt="" className="h-14 w-14 rounded-2xl object-contain mx-auto mb-4" />
              : <div className="h-14 w-14 rounded-2xl bg-orange-500 flex items-center justify-center mx-auto mb-4"><span className="text-white font-bold text-2xl">S</span></div>
            }
            <h1 className="text-2xl font-bold text-white">{app?.name || 'SHARTHAK'} Admin</h1>
            <p className="text-white/40 text-sm mt-1">Sign in to your admin account</p>
          </div>

          {/* Form card */}
          <div className="bg-white/10 backdrop-blur-sm border border-white/10 rounded-2xl p-8">
            <div className="grid grid-cols-2 gap-1 rounded-xl bg-black/20 p-1 mb-6" aria-label="Login type">
              <span className="rounded-lg bg-orange-500 px-3 py-2 text-center text-sm font-semibold text-white">
                Admin Login
              </span>
              <Link
                href="/login"
                className="rounded-lg px-3 py-2 text-center text-sm font-semibold text-white/60 transition-colors hover:bg-white/10 hover:text-white"
              >
                User Login
              </Link>
            </div>

            <form onSubmit={submit} className="space-y-5">
              <div>
                <label className="block text-sm font-medium text-white/80 mb-2">Username</label>
                <input
                  type="text"
                  value={data.username}
                  onChange={e => setData('username', e.target.value)}
                  autoComplete="username"
                  className="w-full h-11 px-4 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/30 focus:outline-none focus:border-orange-400 focus:bg-white/15 transition text-sm"
                  placeholder="admin"
                  required
                />
                {errors.username && <p className="text-red-300 text-xs mt-1">{errors.username}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-white/80 mb-2">Password</label>
                <input
                  type="password"
                  value={data.password}
                  onChange={e => setData('password', e.target.value)}
                  autoComplete="current-password"
                  className="w-full h-11 px-4 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/30 focus:outline-none focus:border-orange-400 focus:bg-white/15 transition text-sm"
                  placeholder="••••••••"
                  required
                />
                {errors.password && <p className="text-red-300 text-xs mt-1">{errors.password}</p>}
              </div>

              {errors.username && !errors.password && (
                <div className="bg-red-500/20 border border-red-500/30 text-red-200 text-sm px-4 py-3 rounded-xl">
                  {errors.username}
                </div>
              )}

              <button
                type="submit"
                disabled={processing}
                className="w-full h-11 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors text-sm"
              >
                {processing ? 'Signing in…' : 'Sign In'}
              </button>
            </form>
          </div>

          <p className="text-center text-white/30 text-xs mt-6">
            &copy; {new Date().getFullYear()} {app?.name}. Admin Portal.
          </p>
        </div>
      </div>
    </>
  );
}
