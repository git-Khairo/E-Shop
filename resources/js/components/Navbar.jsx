import { Link, NavLink } from 'react-router-dom';
import { useEffect, useState } from 'react';
import Logo from './Logo';
import CartButton from './CartButton';
import UserMenu from './UserMenu';
import { useAuth } from '../store/authStore';
import { useCart } from '../store/cartStore';

const NAV_LINKS = [
  { to: '/',           label: 'Home',       end: true },
  { to: '/products',   label: 'Shop' },
  { to: '/categories', label: 'Categories' },
  { to: '/about',      label: 'About' },
  { to: '/contact',    label: 'Contact' },
];

export default function Navbar() {
  const { isAuthed } = useAuth();
  const { fetch: fetchCart } = useCart();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => { if (isAuthed()) fetchCart().catch(() => {}); }, [isAuthed()]);

  useEffect(() => {
    function onScroll() { setScrolled(window.scrollY > 4); }
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const linkCls = ({ isActive }) =>
    `relative px-1 py-2 text-sm font-medium transition ${
      isActive
        ? 'text-brand-900 after:absolute after:left-0 after:right-0 after:-bottom-[1px] after:h-[2px] after:bg-brand-900'
        : 'text-slate-600 hover:text-slate-900'
    }`;

  return (
    <header className={`sticky top-0 z-20 bg-white/90 backdrop-blur transition border-b ${scrolled ? 'border-slate-200 shadow-soft' : 'border-transparent'}`}>
      {/* Slim accent bar */}
      <div className="bg-brand-900 text-white text-[11px]">
        <div className="max-w-7xl mx-auto px-4 py-1.5 flex items-center justify-between">
          <span className="tracking-wide">Complimentary shipping on orders over $150</span>
          <span className="hidden sm:inline tracking-wide">Trusted by 12,000+ customers worldwide</span>
        </div>
      </div>

      {/* Main nav */}
      <div className="max-w-7xl mx-auto px-4 h-16 flex items-center gap-6">
        <Logo />

        <nav className="hidden lg:flex items-center gap-7 ml-4">
          {NAV_LINKS.map((l) => (
            <NavLink key={l.to} to={l.to} end={l.end} className={linkCls}>{l.label}</NavLink>
          ))}
        </nav>

        <div className="ml-auto flex items-center gap-1">
          <CartButton />

          {isAuthed() ? (
            <UserMenu />
          ) : (
            <div className="hidden sm:flex items-center gap-2 ml-2">
              <Link className="btn-ghost" to="/login">Sign in</Link>
              <Link className="btn-primary" to="/register">Create account</Link>
            </div>
          )}

          {/* Mobile menu toggle */}
          <button
            type="button"
            className="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-full text-slate-700 hover:bg-slate-100"
            onClick={() => setMobileOpen((o) => !o)}
            aria-label="Open menu"
          >
            <svg className="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fillRule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clipRule="evenodd" />
            </svg>
          </button>
        </div>
      </div>

      {/* Mobile drawer */}
      {mobileOpen && (
        <div className="lg:hidden border-t border-slate-200 bg-white">
          <nav className="flex flex-col px-4 py-3">
            {NAV_LINKS.map((l) => (
              <NavLink
                key={l.to}
                to={l.to}
                end={l.end}
                onClick={() => setMobileOpen(false)}
                className={({ isActive }) =>
                  `py-2.5 text-sm font-medium ${isActive ? 'text-brand-900' : 'text-slate-700'}`
                }
              >
                {l.label}
              </NavLink>
            ))}
            {!isAuthed() && (
              <div className="flex gap-2 pt-3 border-t border-slate-100 mt-2">
                <Link className="btn-ghost flex-1" to="/login" onClick={() => setMobileOpen(false)}>Sign in</Link>
                <Link className="btn-primary flex-1" to="/register" onClick={() => setMobileOpen(false)}>Create</Link>
              </div>
            )}
          </nav>
        </div>
      )}
    </header>
  );
}
