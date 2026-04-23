import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { useCart } from '../store/cartStore';
import QuantitySelector from '../components/QuantitySelector';

export default function Cart() {
  const { items, total, loading, fetch, remove, update, busy } = useCart();
  const navigate = useNavigate();

  useEffect(() => { fetch(); }, []);

  async function handleQty(productId, nextQty) {
    try {
      await update(productId, nextQty);
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Could not update quantity.';
      toast.error(msg);
    }
  }

  async function handleRemove(productId) {
    try {
      await remove(productId);
      toast.success('Removed from cart');
    } catch {
      toast.error('Could not remove item.');
    }
  }

  return (
    <>
      <section className="bg-slate-50 border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-12 text-center">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">Checkout</span>
          <h1 className="heading-serif text-4xl text-slate-900 mt-2">Your cart</h1>
        </div>
      </section>

      <div className="max-w-5xl mx-auto px-4 py-12">
        {loading && items.length === 0 ? (
          <div className="card p-12 text-center text-slate-500">Loading…</div>
        ) : items.length === 0 ? (
          <div className="card p-16 text-center">
            <div className="text-5xl mb-4 text-slate-300">◻︎</div>
            <p className="text-slate-500 mb-6">Your cart is empty.</p>
            <Link className="btn-primary" to="/products">Start shopping</Link>
          </div>
        ) : (
          <div className="grid lg:grid-cols-3 gap-8">
            <div className="lg:col-span-2">
              <div className="card divide-y divide-slate-200">
                {items.map((i) => {
                  const maxQty = Math.max(1, Math.min(i.product?.stock ?? 99, 99));
                  const isBusy = !!busy[i.product_id];
                  return (
                    <div key={i.product_id} className="p-5 flex items-center gap-4 flex-wrap md:flex-nowrap">
                      <div className="w-20 h-20 bg-slate-100 rounded-md overflow-hidden flex-shrink-0">
                        {i.product?.image
                          ? <img src={i.product.image} alt={i.product.name} className="w-full h-full object-cover" />
                          : <div className="w-full h-full grid place-items-center text-slate-300 text-3xl">◻︎</div>}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-slate-900 truncate">{i.product?.name}</div>
                        <div className="text-sm text-slate-500 mt-1">
                          ${i.unit_price.toFixed(2)} each
                          {i.product?.stock !== undefined && (
                            <span className="ml-2 text-slate-400">· {i.product.stock} available</span>
                          )}
                        </div>
                      </div>

                      <div className="flex items-center gap-3">
                        <QuantitySelector
                          value={i.quantity}
                          onChange={(q) => handleQty(i.product_id, q)}
                          min={1}
                          max={maxQty}
                          disabled={isBusy}
                          size="sm"
                        />
                        <div className="w-24 text-right font-semibold text-slate-900">
                          ${i.line_total.toFixed(2)}
                        </div>
                        <button
                          className="text-sm text-slate-500 hover:text-rose-600 ml-2"
                          onClick={() => handleRemove(i.product_id)}
                          disabled={isBusy}
                          aria-label="Remove from cart"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            <aside className="lg:col-span-1">
              <div className="card p-6 sticky top-24">
                <h2 className="font-semibold text-slate-900 mb-4">Order summary</h2>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between text-slate-600">
                    <span>Subtotal</span>
                    <span>${total.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between text-slate-600">
                    <span>Shipping</span>
                    <span>Calculated at checkout</span>
                  </div>
                </div>
                <div className="border-t border-slate-200 mt-4 pt-4 flex justify-between font-semibold text-slate-900">
                  <span>Total</span>
                  <span>${total.toFixed(2)}</span>
                </div>
                <button className="btn-primary w-full mt-5" onClick={() => navigate('/checkout')}>
                  Proceed to checkout
                </button>
                <Link to="/products" className="block text-center text-sm text-slate-500 mt-3 hover:text-slate-800">
                  Continue shopping
                </Link>
              </div>
            </aside>
          </div>
        )}
      </div>
    </>
  );
}
