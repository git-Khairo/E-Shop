import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../lib/api';

export default function OrderDetail() {
  const { id } = useParams();
  const [order, setOrder] = useState(null);

  useEffect(() => {
    api.get(`/orders/${id}`).then(({ data }) => setOrder(data.data));
  }, [id]);

  if (!order) return <div className="max-w-3xl mx-auto px-4 py-8">Loading…</div>;

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <Link to="/orders" className="text-brand-600 text-sm">← All orders</Link>
      <h1 className="text-2xl font-bold mt-2 mb-6">Order {order.reference}</h1>

      <div className="card p-6 mb-4">
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div><span className="text-slate-500">Status:</span> <strong>{order.status}</strong></div>
          <div><span className="text-slate-500">Payment:</span> <strong>{order.payment_status}</strong></div>
          <div><span className="text-slate-500">Placed:</span> {new Date(order.created_at).toLocaleString()}</div>
          <div><span className="text-slate-500">Total:</span> <strong>${order.total.toFixed(2)}</strong></div>
        </div>
      </div>

      <div className="card divide-y divide-slate-200">
        {order.items?.map((item, i) => (
          <div key={i} className="p-4 flex justify-between">
            <div>
              <div className="font-semibold">{item.name}</div>
              <div className="text-sm text-slate-500">{item.quantity} × ${item.unit_price.toFixed(2)}</div>
            </div>
            <div className="font-semibold">${item.line_total.toFixed(2)}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
