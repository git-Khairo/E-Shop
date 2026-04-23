import { create } from 'zustand';
import api from '../lib/api';

/**
 * Cart store.
 *
 * Concurrency notes
 * ─────────────────
 * All mutations go through the API first, then we refetch the canonical cart.
 * This eliminates drift between tabs / devices because the server is the
 * single source of truth. The server uses a UNIQUE (user_id, product_id)
 * index so parallel writes can never create duplicate rows.
 */
export const useCart = create((set, get) => ({
  items: [],
  total: 0,
  loading: false,
  // per-line "busy" map for disabling stepper buttons during in-flight updates
  busy: {},

  async fetch() {
    set({ loading: true });
    try {
      const { data } = await api.get('/cart');
      set({ items: data.data, total: data.total });
    } finally {
      set({ loading: false });
    }
  },

  async add(product_id, quantity = 1) {
    await api.post('/cart', { product_id, quantity });
    await get().fetch();
  },

  /**
   * Set an item's quantity to an exact value (idempotent).
   * If newQty <= 0 the server removes the row.
   */
  async update(product_id, quantity) {
    set((s) => ({ busy: { ...s.busy, [product_id]: true } }));
    try {
      await api.patch(`/cart/${product_id}`, { quantity });
      await get().fetch();
    } finally {
      set((s) => {
        const busy = { ...s.busy };
        delete busy[product_id];
        return { busy };
      });
    }
  },

  async remove(product_id) {
    await api.delete(`/cart/${product_id}`);
    await get().fetch();
  },

  async clear() {
    await api.delete('/cart');
    set({ items: [], total: 0 });
  },
}));
