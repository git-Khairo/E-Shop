import { Link } from 'react-router-dom';
import { useCart } from '../store/cartStore';

/**
 * Cart button rendered as an icon-only affordance in the top-right of the nav.
 * The numeric badge shows the number of distinct items in the cart.
 */
export default function CartButton() {
  const { items } = useCart();
  const count = items.length;

  return (
    <Link
      to="/cart"
      className="relative inline-flex items-center justify-center w-10 h-10 rounded-full text-slate-700 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-brand-600/40"
      aria-label={`Cart (${count} ${count === 1 ? 'item' : 'items'})`}
    >
      <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M6 6h15l-1.5 9h-12z" />
        <circle cx="9" cy="20" r="1.25" fill="currentColor" />
        <circle cx="18" cy="20" r="1.25" fill="currentColor" />
        <path d="M6 6L5 3H2" />
      </svg>
      {count > 0 && (
        <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-accent-500 text-white text-[10px] font-bold inline-flex items-center justify-center ring-2 ring-white">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  );
}
