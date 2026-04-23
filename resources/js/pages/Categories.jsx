import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';

const palette = [
  { from: 'from-slate-800',     to: 'to-slate-600',     accent: 'bg-accent-500' },
  { from: 'from-brand-800',     to: 'to-brand-600',     accent: 'bg-white/20' },
  { from: 'from-stone-700',     to: 'to-stone-500',     accent: 'bg-accent-500' },
  { from: 'from-zinc-800',      to: 'to-zinc-600',      accent: 'bg-white/20' },
  { from: 'from-neutral-800',   to: 'to-neutral-600',   accent: 'bg-accent-500' },
  { from: 'from-brand-900',     to: 'to-brand-700',     accent: 'bg-white/20' },
];

export default function Categories() {
  const [cats, setCats] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/categories')
      .then(({ data }) => setCats(data.data))
      .finally(() => setLoading(false));
  }, []);

  return (
    <>
      <PageHeader
        eyebrow="Collections"
        title="Shop by category"
        subtitle="Six curated departments. Each piece is chosen for its craft, fit, and longevity."
      />

      <section className="max-w-7xl mx-auto px-4 pb-20">
        {loading ? (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="h-56 rounded-lg bg-slate-100 animate-pulse" />
            ))}
          </div>
        ) : cats.length === 0 ? (
          <EmptyState />
        ) : (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            {cats.map((c, i) => {
              const tone = palette[i % palette.length];
              return (
                <Link
                  key={c.id}
                  to={`/products?category_id=${c.id}`}
                  className={`group relative overflow-hidden rounded-lg h-56 text-white bg-gradient-to-br ${tone.from} ${tone.to} shadow-soft hover:shadow-soft-lg transition`}
                >
                  <div className="absolute inset-0 opacity-[0.08]" style={{
                    backgroundImage: 'radial-gradient(circle at 30% 20%, white 1px, transparent 1px)',
                    backgroundSize: '28px 28px',
                  }} />
                  <div className="relative h-full p-8 flex flex-col justify-between">
                    <div>
                      <span className="text-[11px] tracking-[0.22em] uppercase text-white/70">Department</span>
                      <h3 className="heading-serif text-3xl mt-2">{c.name}</h3>
                    </div>
                    <div className="flex items-end justify-between">
                      <p className="text-sm text-white/80">
                        {c.products_count} {c.products_count === 1 ? 'piece' : 'pieces'}
                      </p>
                      <span className="inline-flex items-center gap-2 text-sm font-medium group-hover:gap-3 transition-all">
                        Browse <span aria-hidden>→</span>
                      </span>
                    </div>
                  </div>
                </Link>
              );
            })}
          </div>
        )}
      </section>
    </>
  );
}

function PageHeader({ eyebrow, title, subtitle }) {
  return (
    <section className="bg-slate-50 border-b border-slate-200">
      <div className="max-w-7xl mx-auto px-4 py-16 md:py-20 text-center">
        <span className="text-xs tracking-[0.22em] uppercase text-brand-700">{eyebrow}</span>
        <h1 className="heading-serif text-4xl md:text-5xl text-slate-900 mt-3">{title}</h1>
        {subtitle && <p className="mt-4 text-slate-600 max-w-2xl mx-auto">{subtitle}</p>}
      </div>
    </section>
  );
}

function EmptyState() {
  return <div className="card p-12 text-center text-slate-500">No categories yet.</div>;
}
