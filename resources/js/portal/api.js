// طبقة البيانات — تقرأ ملفات JSON ثابتة (public/data) يقدمها خادم الويب مباشرة دون PHP
// competitions.json ← {slug}/{version}/index.json ← {slug}/{version}/r/{n}.json
const DATA = (document.querySelector('meta[name="data-base"]')?.content || '/data').replace(/\/$/, '');

async function getJson(path, init) {
    const res = await fetch(DATA + '/' + path, { headers: { Accept: 'application/json' }, ...init });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
}

import { normalize, makeMatcher } from './nameMatch';

export { normalize };

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
        const m = makeMatcher(text);
        if (m.q.length < NAME_SEARCH_MIN) return { total: 0, items: [] };
        // كل كلمة مكتوبة يجب أن توجد في الاسم (بأي ترتيب)؛ الأقرب للنص المكتوب أولاً
        const all = (await nameIndex(comp))
            .filter((c) => m.test(c.key))
            .map((c) => ({ c, s: m.score(c.key) }))
            .sort((a, b) => a.s - b.s)
            .map(({ c }) => c);
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

// ---- قسم الامتحانات (الأرقام الامتحانية، المواعيد، ثم النتائج) ----
// exams/index.json ← exams/{slug}/{version}/names.json
const examIndexes = new Map();
function examNames(exam) {
    if (!examIndexes.has(exam.path)) {
        const p = getJson(exam.path + '/names.json').then(({ sessions, rows }) =>
            rows.map(([name, s, examNumber, fileNumber, hall, result, score, resultNote]) => ({
                name, key: normalize(name), session: sessions[s], examNumber, fileNumber,
                hall: Array.isArray(hall) ? hall[0] : hall, building: Array.isArray(hall) ? hall[1] : null,
                result: result || null, score: score ?? null, resultNote: resultNote || null,
            })));
        p.catch(() => examIndexes.delete(exam.path));
        examIndexes.set(exam.path, p);
    }
    return examIndexes.get(exam.path);
}

export const ExamsAPI = {
    // لا امتحانات منشورة = الملف غير موجود بعد (404) → قائمة فارغة
    getExams: () => getJson('exams/index.json', { cache: 'no-cache' }).catch(() => []),

    // البحث بالاسم في كل اللغات: يعيد { total, items }
    search: async (exam, text) => {
        const m = makeMatcher(text);
        if (m.q.length < NAME_SEARCH_MIN) return { total: 0, items: [] };
        const all = (await examNames(exam))
            .filter((x) => m.test(x.key))
            .map((x) => ({ x, s: m.score(x.key) }))
            .sort((a, b) => a.s - b.s)
            .map(({ x }) => x);
        return { total: all.length, items: all.slice(0, NAME_SEARCH_LIMIT) };
    },
};
