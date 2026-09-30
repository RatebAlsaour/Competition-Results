// عميل API للوحة التحكم (جلسة + CSRF عبر ملف تعريف الارتباط XSRF-TOKEN)
const BASE = (document.querySelector('meta[name="api-base"]')?.content || '/api').replace(/\/$/, '') + '/admin';

export class ApiError extends Error {
    constructor(status, message, errors = []) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

let onUnauthorized = () => {};
export function setUnauthorizedHandler(fn) { onUnauthorized = fn; }

function xsrfToken() {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

// يحوّل كائن المعاملات إلى query string يدعم المصفوفات المتداخلة (filters[candidate][status]=...)
export function toQuery(params, prefix = '') {
    const parts = [];
    for (const [k, v] of Object.entries(params || {})) {
        if (v === undefined || v === null || v === '') continue;
        const key = prefix ? `${prefix}[${k}]` : k;
        if (typeof v === 'object') {
            const nested = toQuery(v, key);
            if (nested) parts.push(nested);
        } else parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(v));
    }
    return parts.join('&');
}

async function request(method, path, { params, body } = {}) {
    const qs = toQuery(params);
    const headers = {
        Accept: 'application/json',
        'Accept-Language': 'ar',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };
    let payload;
    if (body instanceof FormData) payload = body;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }

    let res;
    try {
        res = await fetch(BASE + path + (qs ? '?' + qs : ''), { method, headers, body: payload, credentials: 'same-origin' });
    } catch {
        throw new ApiError(0, 'تعذر الاتصال بالخادم');
    }

    let json = null;
    try { json = await res.json(); } catch { /* empty */ }

    if (res.status === 401) onUnauthorized();
    if (res.status === 419) throw new ApiError(419, 'انتهت صلاحية الجلسة، أعد تحميل الصفحة');
    if (!res.ok || (json && json.success === false)) {
        const errors = Array.isArray(json?.errors) ? json.errors : Object.values(json?.errors || {}).flat();
        throw new ApiError(res.status, json?.message || 'حدث خطأ غير متوقع', errors);
    }
    return json?.data;
}

export const api = {
    get: (path, params) => request('GET', path, { params }),
    post: (path, body) => request('POST', path, { body }),
    put: (path, body) => request('PUT', path, { body }),
    del: (path) => request('DELETE', path),
    url: (path, params) => BASE + path + (params ? '?' + toQuery(params) : ''),
};

export function errorText(e) {
    if (!(e instanceof ApiError)) return 'حدث خطأ غير متوقع';
    return e.errors?.length ? e.errors.join('، ') : e.message;
}
