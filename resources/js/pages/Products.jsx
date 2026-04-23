import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../lib/api';
import ProductCard from '../components/ProductCard';

export default function Products() {
  const [params, setParams] = useSearchParams();
  const [state, setState] = useState({ items: [], meta: null, loading: true });
  const [categories, setCategories] = useState([]);

  const q          = params.get('q') ?? '';
  const sort       = params.get('sort') ?? 'newest';
  const categoryId = params.get('category_id') ?? '';
  const page       = Number(params.get('page') ?? 1);

  function patchParams(patch) {
    const next = new URLSearchParams(params);
    Object.entries(patch).forEach(([k, v]) => {
      if (v === '' || v === null || v === undefined) next.delete(k);
      else next.set(k, v);
    });
    if (!('page' in patch)) next.delete('page');
    setParams(next, { replace: true });
  }

  useEffect(() => {
    api.get('/categories').then(({ data }) => setCategories(data.data)).catch(() => {});
  }, []);

  useEffect(() => {
    let alive = true;
    setState((s) => ({ ...s, loading: true }));
    api.get('/products', { params: { q, sort, page, category_id: categoryId || undefined, per_page: 12 } })
      .then(({ data }) => {
        if (!alive) return;
        setState({ items: data.data, meta: data.meta, loading: false });
      })
      .catch(() => alive && setState((s) => ({ ...s, loading: false })));
    return () => { alive = false; };
  }, [q, sort, categoryId, page]);

  const activeCategoryName = categories.find((c) => String(c.id) === String(categoryId))?.name;

  return (
    <>
      {/* Page header */}
      <section className="bg-slate-50 border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-4 py-14 text-center">
          <span className="text-xs tracking-[0.22em] uppercase text-brand-700">The Collection</span>
          <h1 className="heading-serif text-4xl md:text-5xl text-slate-900 mt-3">
            {activeCategoryName ?? 'All products'}
          </h1>
          <p className="mt-4 text-slate-600 max-w-xl mx-auto">
            Modern essentials — sorted, filtered, and always in stock.
          </p>
        </div>
      </section>

      {/* Toolbar */}
      <section className="max-w-7xl mx-auto px-4 py-8">
        <div className="flex flex-col md:flex-row md:items-center gap-3 mb-8 pb-5 border-b border-slate-200">
          <div className="text-sm text-slate-500">
            {state.meta ? `${state.meta.total} ${state.meta.total === 1 ? 'piece' : 'pieces'}` : '\u00A0'}
          </div>
          <div className="md:ml-auto flex flex-wrap gap-2">
            <input
              className="input md:w-56"
              placeholder="Search..."
              value={q}
              onChange={(e) => patchParams({ q: e.target.value })}
            />
            <select className="input md:w-48" value={categoryId}
                    onChange={(e) => patchParams({ category_id: e.target.value })}>
              <option value="">All categories</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
            <select className="input md:w-44" value={sort}
                    onChange={(e) => patchParams({ sort: e.target.value })}>
              <option value="newest">Newest</option>
              <option value="price_asc">Price — Low to high</option>
              <option value="price_desc">Price — High to low</option>
              <option value="name">Name (A–Z)</option>
            </select>
          </div>
        </div>

        {state.loading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i}>
                <div className="aspect-square bg-slate-100 rounded-md animate-pulse" />
                <div className="h-3 mt-3 bg-slate-100 rounded animate-pulse w-3/4" />
                <div className="h-3 mt-2 bg-slate-100 rounded animate-pulse w-1/2" />
              </div>
            ))}
          </div>
        ) : state.items.length === 0 ? (
          <div className="card p-14 text-center text-slate-500">No products match your filters.</div>
        ) : (
          <>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
              {state.items.map((p) => <ProductCard key={p.id} product={p} />)}
            </div>
            {state.meta && state.meta.last_page > 1 && (
              <div className="flex justify-center gap-2 mt-14">
                <button className="btn-ghost" disabled={page <= 1}
                        onClick={() => patchParams({ page: page - 1 })}>Previous</button>
                <span className="px-4 py-2 text-sm text-slate-600">
                  Page {state.meta.current_page} of {state.meta.last_page}
                </span>
                <button className="btn-ghost" disabled={page >= state.meta.last_page}
                        onClick={() => patchParams({ page: page + 1 })}>Next</button>
              </div>
            )}
          </>
        )}
      </section>
    </>
  );
}
