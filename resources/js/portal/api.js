// طبقة البيانات — تقرأ ملفات JSON ثابتة (public/data) يقدمها خادم الويب مباشرة دون PHP
// competitions.json ← {slug}/{version}/index.json ← {slug}/{version}/r/{n}.json
const DATA = (document.querySelector('meta[name="data-base"]')?.content || '/data').replace(/\/$/, '');

async function getJson(path, init) {
    const res = await fetch(DATA + '/' + path, { headers: { Accept: 'application/json' }, ...init });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
}

// توحيد الأحرف العربية للبحث (الهمزات، التاء المربوطة، الألف المقصورة، التشكيل)
export function normalize(s) {
    return String(s || '')
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي')
        .replace(/ؤ/g, 'و').replace(/ئ/g, 'ي')
        .replace(/\s+/g, ' ').trim().toLowerCase();
}

// فهرس المسابقة يُحمَّل مرة واحدة (ملفاته لا تتغير لأن كل تحديث يُنشر في نسخة جديدة)
const indexes = new Map();
function index(comp) {
    if (!indexes.has(comp.path)) {
        const p = getJson(comp.path + '/index.json');
        p.catch(() => indexes.delete(comp.path));
        indexes.set(comp.path, p);
    }
    return indexes.get(comp.path);
}

async function titleEntry(comp, gov, title) {
    const g = (await index(comp)).governorates.find((x) => x.name === gov);
    return g?.titles.find((t) => t.name === title);
}

// فهرس الأسماء لكل المسابقة — يُحمَّل فقط عند أول بحث بالاسم
const nameIndexes = new Map();
function nameIndex(comp) {
    if (!nameIndexes.has(comp.path)) {
        const p = getJson(comp.path + '/names.json').then(({ groups, rows }) =>
            rows.map(([name, g, seq, primary]) => ({
                name, seq, key: normalize(name),
                governorate: groups[g][0], jobTitle: groups[g][1],
                status: primary ? 'primary' : 'reserve',
            })));
        p.catch(() => nameIndexes.delete(comp.path));
        nameIndexes.set(comp.path, p);
    }
    return nameIndexes.get(comp.path);
}

export const NAME_SEARCH_MIN = 3;
export const NAME_SEARCH_LIMIT = 30;

export const ResultsAPI = {
    // البحث بالاسم في كل المحافظات والمسميات: يعيد { total, items }
    searchNames: async (comp, text) => {
        const q = normalize(text);
        if (q.length < NAME_SEARCH_MIN) return { total: 0, items: [] };
        const all = (await nameIndex(comp)).filter((c) => c.key.includes(q));
        // الأسماء التي تبدأ بالنص أولاً
        all.sort((a, b) => (b.key.startsWith(q) - a.key.startsWith(q)));
        return { total: all.length, items: all.slice(0, NAME_SEARCH_LIMIT) };
    },

    // no-cache: يتحقق المتصفح من الخادم (ETag) ليحصل على أحدث نسخة منشورة
    getCompetitions: () => getJson('competitions.json', { cache: 'no-cache' }),

    getGovernorates: async (comp) => (await index(comp)).governorates.map((g) => g.name),

    getJobTitles: async (comp, gov) =>
        ((await index(comp)).governorates.find((g) => g.name === gov)?.titles || []).map((t) => t.name),

    getResults: async (comp, gov, title) => {
        const entry = await titleEntry(comp, gov, title);
        const d = entry
            ? await getJson(comp.path + '/' + entry.file)
            : { governorate: gov, jobTitle: title, total: 0, primaryCount: 0, reserveCount: 0, candidates: [] };
        d.candidates = d.candidates.map((c) => ({ ...c, key: normalize(c.name) }));
        return d;
    },
};
