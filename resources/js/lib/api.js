import axios from 'axios';

/**
 * Central HTTP client.
 * - Reads bearer token from localStorage on every request.
 * - Auto-logs-out on 401 by clearing the token.
 */
const api = axios.create({
  baseURL: '/api/v1',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('eshop_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err?.response?.status === 401) {
      localStorage.removeItem('eshop_token');
      localStorage.removeItem('eshop_user');
    }
    return Promise.reject(err);
  }
);

export default api;
