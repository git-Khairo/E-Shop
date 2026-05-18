import { create } from 'zustand';
import api from '../lib/api';

export const useCart = create((set, get) => ({
  items: [],
  total: 0,
  loading: false,
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
