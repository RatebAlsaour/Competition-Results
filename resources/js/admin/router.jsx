import React, { useEffect, useState } from 'react';

// موجّه بسيط يعتمد History API (المسارات تحت /admin)
const BASE = new URL(document.querySelector('meta[name="app-base"]')?.content || '/admin', window.location.origin).pathname.replace(/\/$/, '');

export function currentPath() {
    const p = window.location.pathname.startsWith(BASE) ? window.location.pathname.slice(BASE.length) : window.location.pathname;
    return p.replace(/\/$/, '') || '/';
}

export function navigate(to, { replace = false } = {}) {
    const url = BASE + (to === '/' ? '' : to);
    replace ? window.history.replaceState(null, '', url) : window.history.pushState(null, '', url);
    window.dispatchEvent(new PopStateEvent('popstate'));
    window.scrollTo({ top: 0 });
}

export function usePath() {
    const [path, setPath] = useState(currentPath);
    useEffect(() => {
        const onPop = () => setPath(currentPath());
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
    }, []);
    return path;
}

// يطابق نمطاً مثل /competitions/:id/:tab? ويعيد المعاملات أو null
export function match(pattern, path) {
    const names = [];
    const re = new RegExp('^' + pattern.replace(/\/:(\w+)(\?)?/g, (_, name, opt) => {
        names.push(name);
        return opt ? '(?:/([^/]+))?' : '/([^/]+)';
    }) + '$');
    const m = path.match(re);
    if (!m) return null;
    return Object.fromEntries(names.map((n, i) => [n, m[i + 1] ? decodeURIComponent(m[i + 1]) : undefined]));
}

export function Link({ to, children, ...props }) {
    return (
        <a href={BASE + (to === '/' ? '' : to)} onClick={(e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
            e.preventDefault();
            navigate(to);
        }} {...props}>{children}</a>
    );
}
