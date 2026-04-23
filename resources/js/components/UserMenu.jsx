import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../store/authStore';

/**
 * User avatar + dropdown (Profile / Orders / Logout).
 * Closes on: outside click, Escape, or route navigation.
 */
export default function UserMenu() {
  const { user, logout } = useAuth();
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const navigate = useNavigate();

  useEffect(() => {
    function onDown(e) { if (ref.current && !ref.current.contains(e.target)) setOpen(false); }
    function onEsc(e)  { if (e.key === 'Escape') setOpen(false); }
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onEsc);
    };
  }, []);

  const initials = (user?.username ?? '?').slice(0, 2).toUpperCase();

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="inline-flex items-center gap-2 pl-1 pr-2 py-1 rounded-full text-sm font-medium text-slate-700 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-brand-600/40"
        aria-haspopup="true"
        aria-expanded={open}
      >
        <span className="w-8 h-8 rounded-full bg-brand-800 text-white inline-flex items-center justify-center text-xs font-semibold">
          {initials}
        </span>
        <span className="hidden sm:inline">{user?.username}</span>
        <svg className={`w-4 h-4 text-slate-400 transition ${open ? 'rotate-180' : ''}`} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.24 4.38a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clipRule="evenodd" />
        </svg>
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 mt-2 w-60 origin-top-right rounded-md bg-white shadow-soft-lg ring-1 ring-slate-200 focus:outline-none z-30 py-1.5"
        >
          <div className="px-4 py-3 border-b border-slate-100">
            <p className="text-sm font-medium text-slate-900 truncate">{user?.username}</p>
            <p className="text-xs text-slate-500 truncate">{user?.email}</p>
          </div>

          <MenuLink to="/orders" icon={<IconClipboard />} onNavigate={() => setOpen(false)}>My orders</MenuLink>
          <MenuLink to="/cart"   icon={<IconBag />}       onNavigate={() => setOpen(false)}>My cart</MenuLink>

          <div className="border-t border-slate-100 mt-1 pt-1">
            <button
              role="menuitem"
              className="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
              onClick={async () => {
                await logout();
                setOpen(false);
                navigate('/');
              }}
            >
              <IconLogout />
              <span>Sign out</span>
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function MenuLink({ to, icon, children, onNavigate }) {
  return (
    <Link
      to={to}
      onClick={onNavigate}
      role="menuitem"
      className="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
    >
      <span className="text-slate-500">{icon}</span>
      <span>{children}</span>
    </Link>
  );
}

function IconClipboard() {
  return (
    <svg className="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path d="M9 2a1 1 0 00-1 1H6a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2h-2a1 1 0 00-1-1H9z" />
    </svg>
  );
}
function IconBag() {
  return (
    <svg className="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path d="M5 6V5a5 5 0 1110 0v1h1.5a1 1 0 011 .9l1 12a1 1 0 01-1 1.1H2.5a1 1 0 01-1-1.1l1-12A1 1 0 013.5 6H5zm2 0h6V5a3 3 0 10-6 0v1z" />
    </svg>
  );
}
function IconLogout() {
  return (
    <svg className="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path d="M3 4a1 1 0 011-1h8a1 1 0 110 2H5v10h7a1 1 0 110 2H4a1 1 0 01-1-1V4z" />
      <path d="M13 9l3-3 3 3h-2v4h-2V9h-2z" />
    </svg>
  );
}
