import { create } from 'zustand';
import api from '../lib/api';

export const useAuth = create((set, get) => ({
  user: JSON.parse(localStorage.getItem('eshop_user') || 'null'),
  token: localStorage.getItem('eshop_token'),

  async login(email, password) {
    const { data } = await api.post('/auth/login', { email, password });
    localStorage.setItem('eshop_token', data.token);
    localStorage.setItem('eshop_user', JSON.stringify(data.user));
    set({ user: data.user, token: data.token });
  },

  async register(payload) {
    const { data } = await api.post('/auth/register', payload);
    localStorage.setItem('eshop_token', data.token);
    localStorage.setItem('eshop_user', JSON.stringify(data.user));
    set({ user: data.user, token: data.token });
  },

  async logout() {
    try { await api.post('/auth/logout'); } catch (_) {}
    localStorage.removeItem('eshop_token');
    localStorage.removeItem('eshop_user');
    set({ user: null, token: null });
  },

  isAuthed: () => !!get().token,
}));
