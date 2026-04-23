import { Link } from 'react-router-dom';

export default function About() {
  return (
    <>
      <section className="bg-slate-50 border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-16 md:py-20 text-center">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Our Story</span>
          <h1 className="heading-serif text-4xl md:text-5xl text-slate-900 mt-3">
            Quality, tailored for the modern wardrobe.
          </h1>
          <p className="mt-5 text-slate-600 max-w-2xl mx-auto leading-relaxed">
            Atelier was founded in 2026 with a simple premise — that everyday
            essentials deserve uncompromised craftsmanship, honest pricing, and
            a shopping experience that respects your time.
          </p>
        </div>
      </section>

      {/* Mission + image */}
      <section className="max-w-7xl mx-auto px-4 py-20 grid lg:grid-cols-2 gap-12 items-center">
        <div className="aspect-[4/5] overflow-hidden rounded-lg bg-slate-100">
          <img
            src="https://picsum.photos/seed/atelier-atelier/900/1100"
            alt="Atelier workspace"
            className="w-full h-full object-cover"
            loading="lazy"
          />
        </div>
        <div>
          <span className="text-xs tracking-[0.22em] uppercase text-accent-600">Our mission</span>
          <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">
            Fewer, better pieces.
          </h2>
          <p className="mt-5 text-slate-600 leading-relaxed">
            We partner directly with small mills and workshops across Italy, Portugal
            and Japan. By shortening the supply chain and skipping wholesale markups,
            we offer heirloom-quality goods at prices that feel fair — not markup
            gymnastics.
          </p>
          <p className="mt-4 text-slate-600 leading-relaxed">
            Every item is built to last a decade. If it doesn't — we'll repair or
            replace it. That's our promise.
          </p>
          <Link to="/products" className="btn-dark mt-8">Shop the collection</Link>
        </div>
      </section>

      {/* Values */}
      <section className="bg-slate-50 border-y border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-20">
          <div className="text-center mb-12">
            <span className="text-xs tracking-[0.22em] uppercase text-brand-700">What we value</span>
            <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">The Atelier standard</h2>
          </div>
          <div className="grid md:grid-cols-3 gap-6">
            <ValueCard
              number="01"
              title="Material integrity"
              body="Full-grain leathers, long-staple cottons, Italian wools. We name our mills because we're proud to."
            />
            <ValueCard
              number="02"
              title="Fair-wage production"
              body="We audit every partner workshop annually. No subcontracted labor, no shortcuts, no exceptions."
            />
            <ValueCard
              number="03"
              title="Direct-to-customer pricing"
              body="No middlemen. You pay for the garment — not the billboard or the boutique rent."
            />
            <ValueCard
              number="04"
              title="Lifetime repairs"
              body="Sole re-lasting, leather re-edging, button refits. Send it back and we'll take care of it."
            />
            <ValueCard
              number="05"
              title="Responsible packaging"
              body="Recycled cardboard, soy-based inks, zero plastic fillers. Because small details add up."
            />
            <ValueCard
              number="06"
              title="Customer-first support"
              body="Real humans in New York and Porto, reachable by email within 24 hours — weekends included."
            />
          </div>
        </div>
      </section>

      {/* Numbers */}
      <section className="max-w-7xl mx-auto px-4 py-20">
        <div className="grid sm:grid-cols-2 md:grid-cols-4 gap-8 text-center">
          <Stat number="12,400+" label="Customers worldwide" />
          <Stat number="4.9 / 5" label="Average review score" />
          <Stat number="38" label="Partner workshops" />
          <Stat number="2026" label="Founded" />
        </div>
      </section>

      {/* Team */}
      <section className="bg-slate-50 border-t border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-20">
          <div className="text-center mb-12">
            <span className="text-xs tracking-[0.22em] uppercase text-brand-700">The team</span>
            <h2 className="heading-serif text-3xl md:text-4xl text-slate-900 mt-2">People behind the product</h2>
          </div>
          <div className="grid sm:grid-cols-2 md:grid-cols-4 gap-8">
            <TeamMember name="Elena Moretti"    role="Founder & Creative Director" seed="elena" />
            <TeamMember name="Marcus Dawson"    role="Head of Production"          seed="marcus" />
            <TeamMember name="Anaïs Bellemare"  role="Design Lead"                 seed="anais" />
            <TeamMember name="Tobias Keller"    role="Customer Experience"         seed="tobias" />
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-brand-900 text-white">
        <div className="max-w-5xl mx-auto px-4 py-16 text-center">
          <h2 className="heading-serif text-3xl md:text-4xl">Ready to see it for yourself?</h2>
          <p className="mt-4 text-white/70 max-w-lg mx-auto">
            Browse the collection or talk to our concierge team — we're here to help.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link to="/products" className="btn-accent">Shop the collection</Link>
            <Link to="/contact" className="btn-ghost">Contact us</Link>
          </div>
        </div>
      </section>
    </>
  );
}

function ValueCard({ number, title, body }) {
  return (
    <div className="card p-7">
      <span className="text-xs tracking-[0.22em] text-accent-600">{number}</span>
      <h3 className="heading-serif text-xl text-slate-900 mt-2">{title}</h3>
      <p className="mt-3 text-sm text-slate-600 leading-relaxed">{body}</p>
    </div>
  );
}

function Stat({ number, label }) {
  return (
    <div>
      <div className="heading-serif text-4xl text-brand-800">{number}</div>
      <div className="mt-2 text-sm tracking-wide uppercase text-slate-500">{label}</div>
    </div>
  );
}

function TeamMember({ name, role, seed }) {
  return (
    <div className="text-center">
      <div className="aspect-square overflow-hidden rounded-lg bg-slate-200 mb-3">
        <img
          src={`https://picsum.photos/seed/${seed}/500/500`}
          alt={name}
          className="w-full h-full object-cover grayscale hover:grayscale-0 transition"
          loading="lazy"
        />
      </div>
      <div className="font-medium text-slate-900">{name}</div>
      <div className="text-sm text-slate-500">{role}</div>
    </div>
  );
}
