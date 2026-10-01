// مطابقة الأسماء العربية: كل كلمة يكتبها المستخدم تُبحث لوحدها، بأي ترتيب.
// "محمد الخطيب" تجد "محمد أحمد الخطيب"، و"خطيب" تجد "الخطيب"، و"عبد الله" تجد "عبدالله".

// توحيد الأحرف (الهمزات، التاء المربوطة، الألف المقصورة، التشكيل والتطويل)
export function normalize(s) {
    return String(s || '')
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي')
        .replace(/ؤ/g, 'و').replace(/ئ/g, 'ي')
        .replace(/\s+/g, ' ').trim().toLowerCase();
}

// الكلمات القصيرة جداً (حرف أو حرفان) تُطابق بداية كلمة فقط، والأطول تُطابق أي جزء من الاسم
const SHORT = 2;

/**
 * makeMatcher("محمد الخطيب") → { test(key), score(key) }
 * key = normalize(name)
 */
export function makeMatcher(text) {
    const q = normalize(text);
    const tokens = q ? q.split(' ') : [];

    const tokenMatches = (t, words, compact) => (
        t.length <= SHORT ? words.some((w) => w.startsWith(t)) : compact.includes(t)
    );

    return {
        q,
        tokens,
        test(key) {
            if (!q) return true;
            const words = key.split(' ');
            const compact = key.replace(/ /g, '');
            return tokens.every((t) => tokenMatches(t, words, compact));
        },
        // ترتيب النتائج: الأصغر أولاً
        score(key) {
            if (key.startsWith(q)) return 0;          // يبدأ بالنص كما كُتب
            if (key.includes(q)) return 1;            // يحتوي النص كما كُتب
            const words = key.split(' ');
            let pos = -1;
            for (const t of tokens) {                 // الكلمات بنفس ترتيب الاسم؟
                const j = words.findIndex((w, k) => k > pos && (w.startsWith(t) || w.replace(/^ال/, '').startsWith(t)));
                if (j < 0) return 4;
                pos = j;
            }
            return words[0].startsWith(tokens[0]) ? 2 : 3;
        },
    };
}

/**
 * تقسيم الاسم الأصلي إلى أجزاء مع تمييز ما يطابق البحث:
 * [{ text: 'محمد', hit: true }, { text: ' أحمد ', hit: false }, { text: 'الخطيب', hit: true }]
 */
export function highlightSegments(name, text) {
    const q = normalize(text);
    if (!q) return [{ text: name, hit: false }];

    // نسخة موحّدة من الاسم مع خريطة: موقع كل حرف موحّد ← موقعه في الاسم الأصلي
    let ns = '';
    const map = [];
    for (let i = 0; i < name.length; i++) {
        if (/\s/.test(name[i])) {
            if (ns && ns[ns.length - 1] !== ' ') { ns += ' '; map.push(i); }
            continue;
        }
        for (const ch of normalize(name[i])) { ns += ch; map.push(i); }
    }

    const marked = new Array(name.length).fill(false);
    const mark = (at, len) => {
        for (let k = at; k < at + len; k++) if (map[k] !== undefined && ns[k] !== ' ') marked[map[k]] = true;
    };

    // النص كاملاً كما كُتب (إن لم يحتوِ كلمات قصيرة قد تطابق منتصف كلمة)
    if (q.split(' ').every((t) => t.length > SHORT) && ns.includes(q)) {
        mark(ns.indexOf(q), q.length);
    } else {
        for (const t of q.split(' ')) {
            let at = -1;
            if (t.length <= SHORT) {
                // بداية كلمة فقط
                for (let k = ns.indexOf(t); k >= 0; k = ns.indexOf(t, k + 1)) {
                    if (k === 0 || ns[k - 1] === ' ') { at = k; break; }
                }
            } else {
                at = ns.indexOf(t);
            }
            if (at >= 0) mark(at, t.length);
        }
    }

    const segments = [];
    for (let i = 0; i < name.length; i++) {
        const last = segments[segments.length - 1];
        if (last && last.hit === marked[i]) last.text += name[i];
        else segments.push({ text: name[i], hit: marked[i] });
    }
    return segments;
}
