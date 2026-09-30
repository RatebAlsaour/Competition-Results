// يحوّل نص CSS المضمّن ("a:b;c:d") إلى كائن style في React، مع تخزين مؤقت.
const cache = new Map();

export default function sx(css) {
    let out = cache.get(css);
    if (out) return out;
    out = {};
    for (const decl of css.split(';')) {
        const i = decl.indexOf(':');
        if (i < 0) continue;
        const prop = decl.slice(0, i).trim();
        const value = decl.slice(i + 1).trim();
        if (!prop) continue;
        const key = prop.startsWith('--')
            ? prop
            : prop.replace(/^-(\w)/, (_, c) => c.toUpperCase()).replace(/-(\w)/g, (_, c) => c.toUpperCase());
        out[key] = value;
    }
    if (cache.size > 2000) cache.clear();
    cache.set(css, out);
    return out;
}
