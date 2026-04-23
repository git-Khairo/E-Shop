import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { useAuth } from '../store/authStore';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ username: '', email: '', password: '', password_confirmation: '' });
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    try {
      await register(form);
      toast.success('Account created!');
      navigate('/products');
    } catch (err) {
      const errors = err?.response?.data?.errors;
      toast.error(errors ? Object.values(errors)[0][0] : 'Registration failed');
    } finally { setBusy(false); }
  }

  return (
    <div className="min-h-[calc(100vh-150px)] bg-slate-50 py-16">
      <div className="max-w-md mx-auto px-4">
        <div className="text-center mb-8">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Join us</span>
          <h1 className="heading-serif text-3xl text-slate-900 mt-2">Create your account</h1>
        </div>
        <div className="card p-8">
        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="label">Username</label>
            <input className="input" required value={form.username}
              onChange={(e) => setForm({ ...form, username: e.target.value })} />
          </div>
          <div>
            <label className="label">Email</label>
            <input className="input" type="email" required value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </div>
          <div>
            <label className="label">Password</label>
            <input className="input" type="password" required minLength={6} value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })} />
          </div>
          <div>
            <label className="label">Confirm password</label>
            <input className="input" type="password" required value={form.password_confirmation}
              onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
          </div>
          <button className="btn-primary w-full" disabled={busy}>{busy ? 'Creating…' : 'Sign up'}</button>
        </form>
        <p className="text-sm text-slate-500 mt-6 text-center">
          Have an account? <Link to="/login" className="text-brand-800 font-medium hover:underline">Sign in</Link>
        </p>
        </div>
      </div>
    </div>
  );
}
