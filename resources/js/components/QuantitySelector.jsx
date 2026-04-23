import { useEffect, useState } from 'react';

/**
 * Accessible numeric stepper with clamping.
 *
 * Controlled component: pass `value` + `onChange`. Optional `max` clamps
 * increments to available stock. `disabled` can be used by parents to lock
 * the control while an API mutation is in flight.
 *
 * Supports both "compact" variants (used inline in the cart) and a larger
 * default variant (used on product cards / detail).
 */
export default function QuantitySelector({
  value,
  onChange,
  min = 1,
  max = 99,
  disabled = false,
  size = 'md',
  ariaLabel = 'Quantity',
}) {
  const [draft, setDraft] = useState(String(value));

  useEffect(() => { setDraft(String(value)); }, [value]);

  const clamp = (n) => Math.max(min, Math.min(max, n));

  function commit(next) {
    const parsed = Number.isFinite(next) ? Math.floor(next) : min;
    const clamped = clamp(parsed);
    setDraft(String(clamped));
    if (clamped !== value) onChange(clamped);
  }

  const btn = size === 'sm'
    ? 'w-7 h-7 text-sm'
    : 'w-9 h-9 text-base';

  const input = size === 'sm'
    ? 'w-10 h-7 text-sm'
    : 'w-12 h-9 text-sm';

  return (
    <div
      className="inline-flex items-center rounded-md border border-slate-300 bg-white overflow-hidden"
      role="group"
      aria-label={ariaLabel}
    >
      <button
        type="button"
        className={`${btn} text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white`}
        onClick={() => commit(value - 1)}
        disabled={disabled || value <= min}
        aria-label="Decrease quantity"
      >
        −
      </button>
      <input
        type="text"
        inputMode="numeric"
        pattern="[0-9]*"
        className={`${input} text-center border-x border-slate-200 bg-white text-slate-900 focus:outline-none focus:bg-slate-50`}
        value={draft}
        onChange={(e) => setDraft(e.target.value.replace(/[^\d]/g, ''))}
        onBlur={() => commit(Number(draft))}
        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); e.currentTarget.blur(); } }}
        disabled={disabled}
        aria-label={ariaLabel}
      />
      <button
        type="button"
        className={`${btn} text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white`}
        onClick={() => commit(value + 1)}
        disabled={disabled || value >= max}
        aria-label="Increase quantity"
      >
        +
      </button>
    </div>
  );
}
