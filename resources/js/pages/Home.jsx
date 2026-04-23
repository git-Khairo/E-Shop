import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';
import ProductCard from '../components/ProductCard';

export default function Home() {
  return (
    <>
      <Hero />
      <ValueStrip />
      <LatestProducts />
      <EditorialBanner />
      <CategoriesTeaser />
      <Testimonials />
      <Newsletter />
      <Footer />
    </>
  );
}

/* ─────────────────────────────────────────────────────────────── HERO */
function Hero() {
  return (
    <section className="relative overflow-hidden bg-slate-50 border-b border-slate-200">
      <div className="max-w-7xl mx-auto px-4 py-20 md:py-28 grid lg:grid-cols-2 gap-12 items-center">
        <div>
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Spring / Summer 2026</span>
          <h1 className="heading-serif text-5xl md:text-6xl text-slate-900 mt-4 leading-[1.05]">
            Thoughtful essentials,<br/>
            <span className="text-brand-800">built to last.</span>
          </h1>
          <p className="mt-6 text-slate-600 max-w-lg leading-relaxed">
            A collection of modern classics, crafted in small workshops across
            Europe and Japan. Shipped direct — no middlemen, no markup gymnastics.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Link to="/products" className="btn-dark">Shop the collection</Link>
            <Link to="/about" className="btn-ghost">Our story</Link>
          </div>
          <div className="mt-10 flex items-center gap-6 text-xs text-slate-500">
            <div className="flex items-center gap-1.5">
              <span className="text-amber-500">★★★★★</span>
              <span>4.9 / 5 · 2,400+ reviews</span>
            </div>
            <span aria-hidden className="text-slate-300">·</span>
            <span>Free returns within 30 days</span>
          </div>
        </div>
        <div className="relative">
          <div className="aspect-[4/5] overflow-hidden rounded-lg bg-slate-200 shadow-soft-lg">
            <img
              src="https://picsum.photos/seed/atelier-hero/900/1100"
              alt="Spring collection preview"
              className="w-full h-full object-cover"
            />
          </div>
          <div className="hidden md:block absolute -bottom-6 -left-6 bg-white rounded-lg shadow-soft-lg p-5 w-56 ring-1 ring-slate-200">
            <div className="text-[11px] tracking-[0.22em] uppercase text-accent-600">Featured</div>
            <div className="heading-serif text-lg text-slate-900 mt-1">The Italian Sneaker</div>
            <div className="text-xs text-slate-500 mt-1">Handcrafted in Le Marche.</div>
            <div className="text-sm font-semibold text-slate-900 mt-2">$185</div>
          </div>
        </div>
      </div>
    </section>
  );
}

/* ──────────────────────────────────────────────────────── VALUE STRIP */
function ValueStrip() {
  const items = [
    { icon: <IconTruck />,   title: 'Complimentary shipping', body: 'On orders over $150' },
    { icon: <IconShield />,  title: 'Lifetime repairs',       body: 'Every piece, guaranteed' },
    { icon: <IconLeaf />,    title: 'Responsibly made',       body: 'Audited workshops' },
    { icon: <IconReturn />,  title: '30-day returns',         body: 'No questions asked' },
  ];
  return (
    <section className="border-b border-slate-200">
      <div className="max-w-7xl mx-auto px-4 grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-200">
        {items.map((it, i) => (
          <div key={i} className="flex items-center gap-4 py-5 px-2 sm:px-6">
            <div className="text-brand-700">{it.icon}</div>
            <div>
              <div className="text-sm font-semibold text-slate-900">{it.title}</div>
              <div className="text-xs text-slate-500">{it.body}</div>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

/* ──────────────────────────────────────────────────── LATEST PRODUCTS */
function LatestProducts() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/products', { params: { sort: 'newest', per_page: 8 } })
      .then(({ data }) => setList(data.data ?? []))
      .finally(() => setLoading(false));
  }, []);

  return (
    <section className="max-w-7xl mx-auto px-4 py-20">
      <div className="flex items-end justify-between mb-10">
        <div>
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Just arrived</span>
          <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">New this week</h2>
        </div>
        <Link to="/products" className="text-sm font-medium text-slate-700 hover:text-slate-900 hidden sm:inline-flex items-center gap-1.5">
          View all <span aria-hidden>→</span>
        </Link>
      </div>

      {loading ? (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="aspect-square bg-slate-100 rounded-md animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
          {list.map((p) => <ProductCard key={p.id} product={p} />)}
        </div>
      )}
    </section>
  );
}

/* ───────────────────────────────────────────────── EDITORIAL BANNER */
function EditorialBanner() {
  return (
    <section className="bg-brand-900 text-white">
      <div className="max-w-7xl mx-auto px-4 py-20 grid md:grid-cols-2 gap-10 items-center">
        <div className="aspect-[5/3] rounded-lg overflow-hidden bg-brand-800">
          <img
            src="https://picsum.photos/seed/atelier-editorial/1000/600"
            alt="Workshop"
            className="w-full h-full object-cover"
            loading="lazy"
          />
        </div>
        <div>
          <span className="text-xs tracking-[0.22em] uppercase text-accent-500">Made in Italy</span>
          <h2 className="heading-serif text-3xl md:text-4xl mt-2">The craft behind the cloth.</h2>
          <p className="mt-4 text-white/75 leading-relaxed">
            Our wool trousers are cut and sewn at a family-run workshop in Biella —
            the same mills that have supplied Savile Row tailors since 1842. We
            send a small team there every season, because details matter.
          </p>
          <Link to="/about" className="btn-accent mt-7">Learn more</Link>
        </div>
      </div>
    </section>
  );
}

/* ───────────────────────────────────────────── CATEGORIES TEASER */
function CategoriesTeaser() {
  const [cats, setCats] = useState([]);
  useEffect(() => {
    api.get('/categories').then(({ data }) => setCats(data.data.slice(0, 4))).catch(() => {});
  }, []);
  if (cats.length === 0) return null;

  return (
    <section className="max-w-7xl mx-auto px-4 py-20">
      <div className="flex items-end justify-between mb-10">
        <div>
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Browse</span>
          <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">Collections</h2>
        </div>
        <Link to="/categories" className="text-sm font-medium text-slate-700 hover:text-slate-900 hidden sm:inline-flex items-center gap-1.5">
          See all categories <span aria-hidden>→</span>
        </Link>
      </div>
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {cats.map((c) => (
          <Link
            key={c.id}
            to={`/products?category_id=${c.id}`}
            className="group relative overflow-hidden rounded-md aspect-[4/5] bg-slate-200"
          >
            <img
              src={`https://picsum.photos/seed/cat-${c.id}/600/750`}
              alt={c.name}
              loading="lazy"
              className="w-full h-full object-cover transition duration-500 group-hover:scale-105"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent" />
            <div className="absolute bottom-5 left-5 right-5 text-white">
              <div className="heading-serif text-2xl">{c.name}</div>
              <div className="text-xs text-white/80 mt-0.5">{c.products_count} pieces</div>
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}

/* ─────────────────────────────────────────────────── TESTIMONIALS */
function Testimonials() {
  const reviews = [
    { quote: 'The fit and finish rival brands at three times the price. My third order in six months.', name: 'Jordan M.',  role: 'Architect, New York' },
    { quote: 'Customer service reached out *before* I noticed a shipping delay. Class act.',             name: 'Priya R.',   role: 'Designer, Toronto' },
    { quote: "I've worn these boots daily for a year. They look better broken in than they did new.",   name: 'Ethan S.',   role: 'Photographer, Berlin' },
  ];
  return (
    <section className="bg-slate-50 border-y border-slate-200">
      <div className="max-w-7xl mx-auto px-4 py-20">
        <div className="text-center mb-14">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Kind words</span>
          <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">What customers say</h2>
        </div>
        <div className="grid md:grid-cols-3 gap-6">
          {reviews.map((r, i) => (
            <figure key={i} className="card p-8 flex flex-col">
              <div className="text-amber-500 text-lg">★★★★★</div>
              <blockquote className="text-slate-700 leading-relaxed mt-4">&ldquo;{r.quote}&rdquo;</blockquote>
              <figcaption className="mt-6 text-sm">
                <div className="font-semibold text-slate-900">{r.name}</div>
                <div className="text-slate-500">{r.role}</div>
              </figcaption>
            </figure>
          ))}
        </div>
      </div>
    </section>
  );
}

/* ─────────────────────────────────────────────────────── NEWSLETTER */
function Newsletter() {
  return (
    <section className="bg-white">
      <div className="max-w-5xl mx-auto px-4 py-20 text-center">
        <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Stay in touch</span>
        <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">
          Seasonal dispatches. No spam.
        </h2>
        <p className="mt-4 text-slate-600 max-w-xl mx-auto">
          New arrivals, workshop stories, and subscriber-only previews — delivered a few times a year.
        </p>
        <form
          className="mt-8 max-w-md mx-auto flex gap-2"
          onSubmit={(e) => e.preventDefault()}
        >
          <input type="email" required placeholder="you@example.com" className="input flex-1" />
          <button className="btn-primary">Subscribe</button>
        </form>
      </div>
    </section>
  );
}

/* ─────────────────────────────────────────────────────── FOOTER */
function Footer() {
  return (
    <footer className="bg-brand-950 text-slate-300">
      <div className="max-w-7xl mx-auto px-4 py-16 grid md:grid-cols-4 gap-10">
        <div>
          <div className="heading-serif text-xl text-white">ATELIER</div>
          <p className="text-sm text-slate-400 mt-3 leading-relaxed">
            Modern essentials, thoughtfully made and honestly priced.
          </p>
        </div>
        <FooterCol title="Shop">
          <Link to="/products" className="footer-link">All products</Link>
          <Link to="/categories" className="footer-link">Categories</Link>
          <Link to="/products?sort=newest" className="footer-link">New arrivals</Link>
        </FooterCol>
        <FooterCol title="Company">
          <Link to="/about" className="footer-link">About</Link>
          <Link to="/contact" className="footer-link">Contact</Link>
          <a className="footer-link" href="#">Press</a>
        </FooterCol>
        <FooterCol title="Help">
          <a className="footer-link" href="#">Shipping & returns</a>
          <a className="footer-link" href="#">Repairs</a>
          <a className="footer-link" href="#">Privacy</a>
        </FooterCol>
      </div>
      <div className="border-t border-white/10">
        <div className="max-w-7xl mx-auto px-4 py-5 text-xs text-slate-500 flex flex-wrap justify-between gap-2">
          <span>© {new Date().getFullYear()} Atelier E-Shop. All rights reserved.</span>
          <span>Built with Laravel · React · Redis</span>
        </div>
      </div>
      <style>{`.footer-link{display:block;font-size:.875rem;color:#cbd5e1;padding:.25rem 0;}.footer-link:hover{color:#fff;}`}</style>
    </footer>
  );
}
function FooterCol({ title, children }) {
  return (
    <div>
      <div className="text-sm font-semibold text-white mb-4">{title}</div>
      <div className="space-y-1">{children}</div>
    </div>
  );
}

/* ─── value-strip icons ─── */
function IconTruck()  { return (<svg className="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z" /><circle cx="7" cy="18" r="1.5" /><circle cx="18" cy="18" r="1.5" /></svg>); }
function IconShield() { return (<svg className="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M12 3l8 3v6c0 4.5-3 8-8 10-5-2-8-5.5-8-10V6l8-3z" /><path d="M9 12l2 2 4-4" /></svg>); }
function IconLeaf()   { return (<svg className="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M20 4c-8 0-14 5-14 12 0 2 1 4 2 4 7 0 12-6 12-14 0-1 0-2 0-2z" /><path d="M6 20c4-6 8-9 14-14" /></svg>); }
function IconReturn() { return (<svg className="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M4 10h11a5 5 0 110 10H9" /><path d="M7 6l-3 4 3 4" /></svg>); }
