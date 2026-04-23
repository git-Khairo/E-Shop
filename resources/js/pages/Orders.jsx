import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';
import { useAuth } from '../store/authStore';

export default function Orders() {
  const { user } = useAuth();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/orders').then(({ data }) => setOrders(data.data)).finally(() => setLoading(false));
  }, []);

  return (
    <>
      <section className="bg-slate-50 border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-12">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Account</span>
          <div className="flex items-end justify-between flex-wrap gap-4 mt-2">
            <div>
              <h1 className="heading-serif text-4xl text-slate-900">
                Hello, {user?.username ?? 'customer'}
              </h1>
              <p className="text-slate-600 mt-1">Here's a history of your orders.</p>
            </div>
            <div className="flex gap-2">
              <Link to="/cart" className="btn-ghost">View cart</Link>
              <Link to="/products" className="btn-primary">Continue shopping</Link>
            </div>
          </div>
        </div>
      </section>

      <div className="max-w-5xl mx-auto px-4 py-12">
        {loading ? (
          <div className="card p-12 text-center text-slate-500">Loading…</div>
        ) : orders.length === 0 ? (
          <div className="card p-16 text-center">
            <p className="mb-6 text-slate-500">You haven't placed any orders yet.</p>
            <Link className="btn-primary" to="/products">Browse products</Link>
          </div>
        ) : (
          <div className="card divide-y divide-slate-200">
            {orders.map((o) => (
              <Link key={o.id} to={`/orders/${o.id}`} className="block p-5 hover:bg-slate-50 transition">
                <div className="flex items-center justify-between gap-4 flex-wrap">
                  <div>
                    <div className="text-xs tracking-wide uppercase text-slate-400">Order</div>
                    <div className="font-mono font-semibold text-slate-900">{o.reference}</div>
                    <div className="text-sm text-slate-500 mt-1">
                      Placed {new Date(o.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })}
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-lg font-semibold text-slate-900">${o.total.toFixed(2)}</div>
                    <StatusBadge status={o.payment_status} />
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </>
  );
}

function StatusBadge({ status }) {
  const map = {
    paid:    'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
    failed:  'bg-rose-100 text-rose-700',
  };
  const cls = map[status] ?? 'bg-slate-100 text-slate-600';
  return <span className={`chip mt-1.5 ${cls} capitalize`}>{status}</span>;
}
