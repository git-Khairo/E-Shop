import { Link } from 'react-router-dom';

/**
 * Small brand mark + wordmark. Intentionally restrained (no emoji).
 */
export default function Logo({ className = '' }) {
  return (
    <Link to="/" className={`inline-flex items-center gap-2.5 group ${className}`}>
      <span className="relative inline-flex items-center justify-center w-8 h-8 rounded-md bg-brand-900 text-white">
        <svg viewBox="0 0 24 24" className="w-4 h-4" fill="currentColor" aria-hidden="true">
          <path d="M6 7h12l-1 10a2 2 0 0 1-2 1.9H9a2 2 0 0 1-2-1.9L6 7zm2-1a4 4 0 0 1 8 0h2V6a6 6 0 1 0-12 0v0h2z" />
        </svg>
      </span>
      <span className="flex flex-col leading-none">
        <span className="heading-serif text-lg text-slate-900 group-hover:text-brand-800 transition">ATELIER</span>
        <span className="text-[10px] tracking-[0.18em] text-slate-500 uppercase">E-Shop · Est. 2026</span>
      </span>
    </Link>
  );
}
