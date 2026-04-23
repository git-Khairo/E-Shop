import { useState } from 'react';
import toast from 'react-hot-toast';
import { useAuth } from '../store/authStore';
import { useCart } from '../store/cartStore';
import { useNavigate } from 'react-router-dom';
import QuantitySelector from './QuantitySelector';

export default function ProductCard({ product }) {
  const { isAuthed } = useAuth();
  const { add } = useCart();
  const navigate = useNavigate();

  const [qty, setQty] = useState(1);
  const [busy, setBusy] = useState(false);

  async function handleAdd() {
    if (!isAuthed()) return navigate('/login');
    setBusy(true);
    try {
      await add(product.id, qty);
      toast.success(`${qty} × ${product.name} added`);
    } catch (e) {
      toast.error(e?.response?.data?.message ?? 'Could not add to cart');
    } finally {
      setBusy(false);
    }
  }

  const price = Number(product.price).toFixed(2);
  const maxQty = Math.max(1, Math.min(product.stock ?? 1, 99));

  return (
    <article className="group flex flex-col">
      <div className="relative aspect-square overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200/60">
        {product.image ? (
          <img
            src={product.image}
            alt={product.name}
            loading="lazy"
            className="object-cover w-full h-full transition duration-500 group-hover:scale-105"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-slate-300 text-5xl">◻︎</div>
        )}
        {!product.in_stock && (
          <span className="absolute top-3 left-3 chip bg-slate-900 text-white">Sold out</span>
        )}
        {product.in_stock && product.stock <= 5 && (
          <span className="absolute top-3 left-3 chip bg-accent-500 text-white">
            Only {product.stock} left
          </span>
        )}
      </div>

      <div className="pt-3 flex flex-col flex-1">
        <h3 className="text-sm font-medium text-slate-900 truncate">{product.name}</h3>
        {product.description && (
          <p className="text-xs text-slate-500 mt-1 line-clamp-2">{product.description}</p>
        )}
        <div className="mt-2 flex items-center justify-between">
          <span className="text-sm font-semibold text-slate-900">${price}</span>
          {product.in_stock ? (
            <span className="text-[11px] tracking-wide uppercase text-emerald-700">In stock</span>
          ) : (
            <span className="text-[11px] tracking-wide uppercase text-slate-400">Unavailable</span>
          )}
        </div>

        <div className="mt-3 flex items-center gap-2">
          <QuantitySelector
            value={qty}
            onChange={setQty}
            min={1}
            max={maxQty}
            disabled={!product.in_stock || busy}
            size="sm"
          />
          <button
            type="button"
            onClick={handleAdd}
            disabled={!product.in_stock || busy}
            className="btn-dark flex-1 h-9 text-sm disabled:opacity-60"
          >
            {busy ? 'Adding…' : product.in_stock ? 'Add to cart' : 'Unavailable'}
          </button>
        </div>
      </div>
    </article>
  );
}
