import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import api from '../lib/api';
import { useCart } from '../store/cartStore';

export default function Checkout() {
  const { items, total, fetch, clear } = useCart();
  const [paymentToken, setPaymentToken] = useState('mock_success');
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();

  useEffect(() => { fetch(); }, []);

  async function placeOrder() {
    if (items.length === 0) return;
    setSubmitting(true);
    try {
      const { data } = await api.post('/checkout', { payment_method_token: paymentToken });
      toast.success(`Order ${data.data.reference} placed!`);
      clear();
      navigate(`/orders/${data.data.id}`);
    } catch (e) {
      const msg = e?.response?.data?.message ?? 'Checkout failed';
      toast.error(msg);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <h1 className="text-2xl font-bold mb-6">Checkout</h1>

      <div className="card p-6 mb-4">
        <h2 className="font-semibold mb-4">Order summary</h2>
        <div className="space-y-2 mb-4">
          {items.map((i) => (
            <div key={i.product_id} className="flex justify-between text-sm">
              <span>{i.product?.name} × {i.quantity}</span>
              <span>${i.line_total.toFixed(2)}</span>
            </div>
          ))}
        </div>
        <div className="border-t border-slate-200 pt-4 flex justify-between font-bold">
          <span>Total</span>
          <span>${total.toFixed(2)}</span>
        </div>
      </div>

      <div className="card p-6">
        <h2 className="font-semibold mb-4">Payment (mock)</h2>
        <label className="label">Payment token</label>
        <select className="input mb-4" value={paymentToken} onChange={(e) => setPaymentToken(e.target.value)}>
          <option value="mock_success">mock_success — always succeeds</option>
          <option value="mock_random">mock_random — 95% success</option>
          <option value="mock_fail">mock_fail — always fails</option>
        </select>
        <button
          className="btn-primary w-full"
          disabled={submitting || items.length === 0}
          onClick={placeOrder}
        >
          {submitting ? 'Processing…' : `Pay $${total.toFixed(2)}`}
        </button>
      </div>
    </div>
  );
}
