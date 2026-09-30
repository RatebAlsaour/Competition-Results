import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';

/* ---------- icons ---------- */
const paths = {
    search: <><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></>,
    plus: <><path d="M12 5v14" /><path d="M5 12h14" /></>,
    edit: <path d="M4 20h4L19 9l-4-4L4 16z" />,
    trash: <><path d="M4 7h16" /><path d="M9 7V4.5h6V7" /><path d="M6.5 7l1 13h9l1-13" /></>,
    download: <><path d="M12 4v11" /><path d="m7 10 5 5 5-5" /><path d="M5 20h14" /></>,
    upload: <><path d="M12 20V9" /><path d="m7 14 5-5 5 5" /><path d="M5 4h14" /></>,
    close: <><path d="M6 6l12 12" /><path d="M18 6 6 18" /></>,
    external: <><path d="M14 4h6v6" /><path d="M20 4 11 13" /><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" /></>,
    logout: <><path d="M10 5H5v14h5" /><path d="M14 8l4 4-4 4" /><path d="M18 12H9" /></>,
    file: <><path d="M14 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8z" /><path d="M14 3.5V8h4.5" /></>,
    users: <><circle cx="9" cy="8.5" r="3.2" /><path d="M3.5 19c.6-3 2.8-4.8 5.5-4.8s4.9 1.8 5.5 4.8" /><circle cx="16.5" cy="9.5" r="2.5" /><path d="M16 14.3c2.3.2 3.9 1.7 4.5 4.2" /></>,
    chart: <><path d="M4 20V10" /><path d="M10 20V4" /><path d="M16 20v-7" /><path d="M3 20h18" /></>,
    settings: <><circle cx="12" cy="12" r="3" /><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14.2 3h-4.4l-.4 2.7a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2 1.2l.4 2.7h4.4l.4-2.7a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z" /></>,
    back: <path d="m9 6 6 6-6 6" />,
    next: <path d="m15 6-6 6 6 6" />,
};

export function Icon({ name, size = 16, color = 'currentColor', width = 1.8 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={width} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={{ flexShrink: 0 }}>
            {paths[name]}
        </svg>
    );
}

export function Spinner({ label = 'جارٍ التحميل...' }) {
    return <div className="center" role="status"><span className="spinner" aria-hidden="true" /> {label}</div>;
}

/* ---------- toast ---------- */
const ToastContext = createContext(() => {});
export const useToast = () => useContext(ToastContext);

export function ToastProvider({ children }) {
    const [items, setItems] = useState([]);
    const push = useCallback((text, type = 'ok') => {
        const id = Math.random();
        setItems((list) => [...list, { id, text, type }]);
        setTimeout(() => setItems((list) => list.filter((t) => t.id !== id)), 4000);
    }, []);
    return (
        <ToastContext.Provider value={push}>
            {children}
            <div className="toasts" aria-live="polite">
                {items.map((t) => <div key={t.id} className={'toast ' + (t.type === 'error' ? 'error' : '')}>{t.text}</div>)}
            </div>
        </ToastContext.Provider>
    );
}

/* ---------- modal ---------- */
export function Modal({ title, onClose, children, footer, width }) {
    const ref = useRef(null);
    useEffect(() => {
        const prev = document.activeElement;
        ref.current?.querySelector('input, select, textarea, button')?.focus();
        const onKey = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => { document.removeEventListener('keydown', onKey); prev?.focus?.(); };
    }, []);
    return (
        <div className="modal-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
            <div className="modal" role="dialog" aria-modal="true" aria-label={title} ref={ref} style={width ? { maxWidth: width } : undefined}>
                <div className="modal-head">
                    <h2>{title}</h2>
                    <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={onClose} aria-label="إغلاق"><Icon name="close" /></button>
                </div>
                <div className="modal-body">{children}</div>
                {footer && <div className="modal-foot">{footer}</div>}
            </div>
        </div>
    );
}

/* ---------- form field ---------- */
export function Field({ label, hint, error, children, className = '', htmlFor }) {
    return (
        <div className={'field ' + className}>
            {label && <label htmlFor={htmlFor}>{label}</label>}
            {children}
            {error ? <span className="error">{error}</span> : hint ? <span className="hint">{hint}</span> : null}
        </div>
    );
}

export function StatusBadge({ status, label }) {
    return <span className={'badge badge-' + status}>{label}</span>;
}

/* ---------- pagination ---------- */
export function Pager({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) {
        return meta?.total ? <div className="pager"><span>{meta.total} سجل</span></div> : null;
    }
    const { current_page: page, last_page: last } = meta;
    return (
        <div className="pager">
            <span>عرض {meta.from}–{meta.to} من {meta.total}</span>
            <div className="row">
                <button type="button" className="btn btn-outline btn-sm" disabled={page <= 1} onClick={() => onPage(page - 1)}><Icon name="back" /> السابق</button>
                <span className="num">صفحة {page} من {last}</span>
                <button type="button" className="btn btn-outline btn-sm" disabled={page >= last} onClick={() => onPage(page + 1)}>التالي <Icon name="next" /></button>
            </div>
        </div>
    );
}

/* ---------- hooks ---------- */
export function useDebounced(value, delay = 350) {
    const [v, setV] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setV(value), delay);
        return () => clearTimeout(t);
    }, [value, delay]);
    return v;
}

export function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value.replace(' ', 'T')).toLocaleString('ar-SY-u-nu-latn', { dateStyle: 'medium', timeStyle: value.length > 10 ? 'short' : undefined });
    } catch { return value; }
}
