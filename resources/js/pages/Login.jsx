import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { useAuth } from '../store/authStore';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: '', password: '' });
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    try {
      await login(form.email, form.password);
      toast.success('Welcome back!');
      navigate('/products');
    } catch (err) {
      toast.error(err?.response?.data?.message ?? 'Login failed');
    } finally { setBusy(false); }
  }

  function useDemo() {
    setForm({ email: 'customer@eshop.local', password: 'password' });
  }

  return (
    <div className="min-h-[calc(100vh-150px)] bg-slate-50 py-16">
      <div className="max-w-md mx-auto px-4">
        <div className="text-center mb-8">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Welcome back</span>
          <h1 className="heading-serif text-3xl text-slate-900 mt-2">Sign in to your account</h1>
        </div>
        <div className="card p-8">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="label">Email</label>
              <input className="input" type="email" required value={form.email}
                     onChange={(e) => setForm({ ...form, email: e.target.value })} />
            </div>
            <div>
              <label className="label">Password</label>
              <input className="input" type="password" required value={form.password}
                     onChange={(e) => setForm({ ...form, password: e.target.value })} />
            </div>
            <button className="btn-primary w-full" disabled={busy}>
              {busy ? 'Signing in…' : 'Sign in'}
            </button>
          </form>

          <div className="relative my-5">
            <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-slate-200" /></div>
            <div className="relative flex justify-center"><span className="bg-white px-2 text-xs text-slate-400">or</span></div>
          </div>
          <button type="button" onClick={useDemo} className="btn-ghost w-full text-xs">
            Use demo account (customer@eshop.local)
          </button>

          <p className="text-sm text-slate-500 mt-6 text-center">
            Don't have an account? <Link to="/register" className="text-brand-800 font-medium hover:underline">Create one</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
