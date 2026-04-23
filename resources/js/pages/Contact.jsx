import { useState } from 'react';
import toast from 'react-hot-toast';
import api from '../lib/api';

export default function Contact() {
  const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' });
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    try {
      const { data } = await api.post('/contact', form);
      toast.success(data.message ?? 'Message sent — thank you.');
      setForm({ name: '', email: '', subject: '', message: '' });
    } catch (err) {
      const errors = err?.response?.data?.errors;
      toast.error(errors ? Object.values(errors)[0][0] : 'Could not send.');
    } finally { setBusy(false); }
  }

  return (
    <>
      <section className="bg-slate-50 border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-16 md:py-20 text-center">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">We'd love to hear from you</span>
          <h1 className="heading-serif text-4xl md:text-5xl text-slate-900 mt-3">Get in touch</h1>
          <p className="mt-4 text-slate-600 max-w-2xl mx-auto">
            Questions about an order, a partnership, or the platform itself?
            Send us a note — we reply within 24 hours on business days.
          </p>
        </div>
      </section>

      <section className="max-w-6xl mx-auto px-4 py-16 grid lg:grid-cols-5 gap-10">
        {/* Contact info */}
        <aside className="lg:col-span-2 space-y-8">
          <ContactBlock
            icon={<IconLocation />}
            title="Visit us"
            lines={['123 Commerce Avenue, Suite 400', 'New York, NY 10013']}
          />
          <ContactBlock
            icon={<IconMail />}
            title="Email"
            lines={['General — hello@atelier.local', 'Support — support@atelier.local']}
          />
          <ContactBlock
            icon={<IconPhone />}
            title="Phone"
            lines={['+1 (555) 010-2030', 'Mon – Fri · 9:00 – 18:00 ET']}
          />
          <ContactBlock
            icon={<IconGlobe />}
            title="Social"
            lines={['instagram.com/atelier', 'twitter.com/atelier']}
          />
        </aside>

        {/* Form */}
        <form onSubmit={submit} className="lg:col-span-3 card p-7 md:p-9">
          <h2 className="heading-serif text-2xl text-slate-900 mb-6">Send us a message</h2>
          <div className="grid md:grid-cols-2 gap-4">
            <div>
              <label className="label">Name</label>
              <input required className="input" value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </div>
            <div>
              <label className="label">Email</label>
              <input required type="email" className="input" value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })} />
            </div>
          </div>
          <div className="mt-4">
            <label className="label">Subject</label>
            <input className="input" placeholder="How can we help?" value={form.subject}
              onChange={(e) => setForm({ ...form, subject: e.target.value })} />
          </div>
          <div className="mt-4">
            <label className="label">Message</label>
            <textarea required rows={6} className="input resize-none"
              value={form.message}
              onChange={(e) => setForm({ ...form, message: e.target.value })} />
          </div>
          <div className="mt-6 flex items-center justify-between">
            <p className="text-xs text-slate-500">We never share your details. Ever.</p>
            <button disabled={busy} className="btn-primary">
              {busy ? 'Sending…' : 'Send message'}
            </button>
          </div>
        </form>
      </section>
    </>
  );
}

function ContactBlock({ icon, title, lines }) {
  return (
    <div className="flex items-start gap-4">
      <div className="w-11 h-11 rounded-md bg-brand-900 text-white inline-flex items-center justify-center flex-shrink-0">
        {icon}
      </div>
      <div>
        <div className="font-semibold text-slate-900">{title}</div>
        {lines.map((l, i) => (
          <div key={i} className="text-sm text-slate-600">{l}</div>
        ))}
      </div>
    </div>
  );
}

/* ─── icons ─── */
function IconLocation() { return (<svg className="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M10 18a7 7 0 11-.001-13.999A7 7 0 0110 18zm0-9a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" /></svg>); }
function IconMail()     { return (<svg className="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2 4a2 2 0 012-2h12a2 2 0 012 2v1.2L10 11 2 5.2V4z" /><path d="M2 7.5V15a2 2 0 002 2h12a2 2 0 002-2V7.5l-8 5.2-8-5.2z" /></svg>); }
function IconPhone()    { return (<svg className="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2 3a1 1 0 011-1h3.3a1 1 0 011 .75l.75 3a1 1 0 01-.25 1L6.3 8.3a11 11 0 005.4 5.4l1.55-1.5a1 1 0 011-.25l3 .75a1 1 0 01.75 1V17a1 1 0 01-1 1A15 15 0 012 3z" /></svg>); }
function IconGlobe()    { return (<svg className="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 6a2 2 0 114 0v2a2 2 0 11-4 0V6z" clipRule="evenodd" /></svg>); }
