import React, { useEffect, useRef, useState } from 'react';
import sx from './sx';
import { ExamsAPI, NAME_SEARCH_MIN } from './api';
import { normalize, highlightSegments } from './nameMatch';
import { PatternSides } from './parts';

const RESULT = {
    passed: { label: 'ناجح', bg: '#054239', color: '#EDEBE0', dot: '#B9A779' },
    failed: { label: 'راسب', bg: '#FBEFF0', color: '#6B1F2A', dot: '#6B1F2A' },
    absent: { label: 'غائب', bg: '#ECEBE6', color: '#4F5D58', dot: '#6B736F' },
};

// "2026-11-15" → "الأحد 15 تشرين الثاني 2026"
function formatDate(date) {
    if (!date) return null;
    try {
        return new Date(date + 'T00:00:00').toLocaleDateString('ar-SY-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch { return date; }
}

// "09:00" → "9:00 صباحاً"
function formatTime(time) {
    if (!time) return null;
    const [h, m] = time.split(':').map(Number);
    return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'صباحاً' : h === 12 ? 'ظهراً' : 'مساءً'}`;
}

function ResultBadge({ result }) {
    const r = RESULT[result];
    if (!r) return null;
    return (
        <span style={sx(`flex-shrink:0;display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 12px;border-radius:14px;background:${r.bg};color:${r.color};font-size:13px;font-weight:600;white-space:nowrap`)}>
            <span aria-hidden="true" style={sx(`width:6px;height:6px;border-radius:50%;background:${r.dot}`)} />{r.label}
        </span>
    );
}

function Highlighted({ name, q }) {
    return highlightSegments(name, q).map((seg, i) => (seg.hit
        ? <mark key={i} style={sx('background:rgba(185,167,121,.38);color:#002623;border-radius:2px;padding:0 1px')}>{seg.text}</mark>
        : <React.Fragment key={i}>{seg.text}</React.Fragment>));
}

/**
 * قسم الامتحانات: المتقدم يبحث باسمه فيرى رقمه الامتحاني وموعد ومكان الامتحان والقاعة،
 * وبعد نشر النتائج يرى نتيجته في المكان نفسه.
 */
export default function ExamSection({ exams, exam, onSelectExam, wide }) {
    const [text, setText] = useState('');
    const [res, setRes] = useState(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [open, setOpen] = useState(null);
    const req = useRef(0);
    const lastTrigger = useRef(null);
    const dialogRef = useRef(null);

    // تبديل الامتحان يمسح البحث السابق
    useEffect(() => { req.current++; setText(''); setRes(null); setOpen(null); }, [exam?.slug]);

    useEffect(() => {
        if (open) setTimeout(() => dialogRef.current?.focus(), 30);
    }, [open]);

    const onSearch = (e) => {
        const value = e.target.value;
        const token = ++req.current;
        setText(value);
        if (normalize(value).length < NAME_SEARCH_MIN) { setRes(null); setLoading(false); return; }
        setLoading(true);
        ExamsAPI.search(exam, value)
            .then((r) => { if (token === req.current) { setRes(r); setLoading(false); setFailed(false); } })
            .catch(() => { if (token === req.current) { setLoading(false); setFailed(true); } });
    };

    const close = () => {
        setOpen(null);
        const t = lastTrigger.current;
        setTimeout(() => t?.focus?.({ preventScroll: true }), 0);
    };

    const q = normalize(text);
    const showResults = exam?.resultsPublished;

    return (
        <section aria-labelledby="exam-title" style={sx('position:relative;background:#FBFAF6;border:1px solid rgba(152,133,97,.3);border-radius:8px;box-shadow:0 1px 2px rgba(0,38,35,.04),0 22px 44px -26px rgba(0,38,35,.32);padding:clamp(22px,4vw,40px)')}>
            <div aria-hidden="true" style={sx('position:absolute;top:-1px;right:clamp(22px,4vw,40px);width:56px;height:3px;background:#B9A779;border-radius:0 0 2px 2px')} />
            <h2 id="exam-title" style={sx("margin:0;font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(22px,2.4vw,26px);line-height:1.35;color:#002623")}>
                {showResults ? 'استعلم عن نتيجتك' : 'استعلم عن رقمك الامتحاني'}
            </h2>
            <p style={sx('margin:8px 0 0;font-size:14px;line-height:1.8;color:#4F5D58')}>
                {showResults
                    ? 'اكتب اسمك لمعرفة نتيجتك في الامتحان.'
                    : 'اكتب اسمك لمعرفة رقمك الامتحاني، وتاريخ ووقت ومكان الامتحان، والقاعة.'}
            </p>

            {exams.length > 1 && (
                <div style={sx('margin-top:24px;display:flex;flex-direction:column;gap:8px')}>
                    <label htmlFor="exam-select" style={sx('font-size:15px;line-height:1.6;font-weight:600;color:#002623')}>الامتحان</label>
                    <div style={sx('position:relative')}>
                        <select id="exam-select" value={exam?.slug || ''} onChange={(e) => onSelectExam(exams.find((x) => x.slug === e.target.value))}
                            style={sx(`width:100%;height:54px;padding:0 16px 0 44px;border:1px solid ${exam ? '#B9A779' : '#CFC8B4'};border-radius:6px;background:#FFFFFF;font-size:16px;font-weight:${exam ? 600 : 400};color:#002623;-webkit-appearance:none;appearance:none;outline:none`)}>
                            <option value="" disabled>اختر الامتحان</option>
                            {exams.map((x) => <option key={x.slug} value={x.slug}>{x.title}</option>)}
                        </select>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#054239" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('position:absolute;left:16px;top:50%;transform:translateY(-50%);pointer-events:none')}><path d="m6 9 6 6 6-6" /></svg>
                    </div>
                </div>
            )}

            {exam && (
                <div style={sx('margin-top:24px;display:flex;flex-direction:column;gap:8px')}>
                    <label htmlFor="exam-search" style={sx('font-size:15px;line-height:1.6;font-weight:600;color:#002623')}>الاسم الثلاثي</label>
                    <div style={sx('position:relative')}>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#054239" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true" style={sx('position:absolute;right:16px;top:50%;transform:translateY(-50%)')}><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                        <input id="exam-search" type="search" className="f-search" value={text} onChange={onSearch} autoComplete="off" enterKeyHint="search"
                            placeholder="مثال: محمد أحمد الخطيب" aria-describedby="exam-search-hint"
                            style={sx('width:100%;height:54px;border:1px solid #CFC8B4;border-radius:6px;background:#FFFFFF;padding:0 48px 0 16px;font-size:16px;color:#002623;outline:none;-webkit-appearance:none;appearance:none')} />
                    </div>
                    <span id="exam-search-hint" style={sx('font-size:13px;color:#4F5D58')}>
                        {q.length > 0 && q.length < NAME_SEARCH_MIN ? `اكتب ${NAME_SEARCH_MIN} أحرف على الأقل` : 'يكفي الاسم الأول واسم العائلة. لا يهم الفرق بين (أ/ا) أو (ة/ه).'}
                    </span>

                    <div role="status" aria-live="polite">
                        {loading && <span style={sx('font-size:14px;color:#054239')}>جارٍ البحث...</span>}
                        {failed && <span style={sx('font-size:14px;color:#6B1F2A')}>تعذر البحث، تحقق من الاتصال وحاول مجدداً.</span>}
                        {res && !loading && res.total === 0 && (
                            <div style={sx('padding:14px 16px;border:1px dashed rgba(152,133,97,.5);border-radius:6px;background:#FFFFFF;font-size:14px;line-height:1.8;color:#4F5D58')}>
                                لم يُعثر على الاسم. تأكد من كتابته كما في طلب التقدم، أو جرّب الاسم الأول واسم العائلة فقط.
                            </div>
                        )}
                    </div>

                    {res && res.total > 0 && !loading && (
                        <div style={sx('border:1px solid rgba(152,133,97,.3);border-radius:8px;background:#FFFFFF;overflow:hidden')}>
                            <div style={sx('padding:10px 14px;background:#F4F2EA;font-size:13px;font-weight:600;color:#054239')}>
                                {res.total > res.items.length ? `${res.total} نتيجة — تظهر أول ${res.items.length}، أضف اسم الأب أو العائلة لتضييق البحث` : `${res.total} ${res.total === 1 ? 'نتيجة' : 'نتائج'} — اضغط على اسمك لعرض التفاصيل`}
                            </div>
                            <ul style={sx('list-style:none;margin:0;padding:0')}>
                                {res.items.map((x, i) => (
                                    <li key={x.session.name + x.examNumber + x.name} style={sx(i ? 'border-top:1px solid #EFECE3' : '')}>
                                        <button type="button" className="h-row" onClick={(e) => { lastTrigger.current = e.currentTarget; setOpen(x); }}
                                            style={sx('width:100%;display:flex;align-items:center;gap:12px;padding:12px 14px;min-height:64px;background:#FFFFFF;border:0;text-align:right;cursor:pointer')}>
                                            <span style={sx('flex:1;min-width:0;display:flex;flex-direction:column;gap:2px')}>
                                                <span style={sx('font-size:16px;font-weight:600;color:#002623;line-height:1.5')}><Highlighted name={x.name} q={q} /></span>
                                                <span style={sx('font-size:13px;color:#4F5D58;line-height:1.6')}>{x.session.name} · الرقم الامتحاني {x.examNumber}{x.fileNumber ? ` · ملف ${x.fileNumber}` : ''}</span>
                                            </span>
                                            {showResults && x.result
                                                ? <ResultBadge result={x.result} />
                                                : <span aria-hidden="true" style={sx("flex-shrink:0;min-width:44px;height:44px;padding:0 8px;border-radius:22px;border:1px solid #B9A779;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#054239;font-variant-numeric:tabular-nums")}>{x.examNumber}</span>}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {open && <ExamineeDialog x={open} exam={exam} wide={wide} onClose={close} dialogRef={dialogRef} />}
        </section>
    );
}

function ExamineeDialog({ x, exam, onClose, dialogRef }) {
    const s = x.session;
    const date = formatDate(s.date);
    const time = s.start ? formatTime(s.start) + (s.end ? ' — ' + formatTime(s.end) : '') : null;
    const showResult = exam.resultsPublished;
    const instructions = (exam.instructions || '').split('\n').map((l) => l.trim()).filter(Boolean);

    const onKey = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); onClose(); return; }
        if (e.key === 'Tab' && dialogRef.current) {
            const f = dialogRef.current.querySelectorAll('button'); if (!f.length) return;
            const first = f[0], last = f[f.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === dialogRef.current)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    };

    const row = (label, value, strong) => (
        <div style={sx('display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #EAE5D8;align-items:baseline')}>
            <dt style={sx('flex:0 0 96px;font-size:13px;font-weight:600;color:#6E5E3E')}>{label}</dt>
            <dd style={sx(`margin:0;flex:1;min-width:0;font-size:${strong ? 17 : 15}px;font-weight:${strong ? 700 : 600};line-height:1.7;color:${value ? '#002623' : '#7D847F'};white-space:pre-line`)}>{value || 'سيُعلن لاحقاً'}</dd>
        </div>
    );

    return (
        <div onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }} style={sx('position:fixed;inset:0;z-index:80;background:rgba(0,26,24,.62);display:flex;align-items:center;justify-content:center;padding:16px;animation:dd-in 200ms ease both')}>
            <div role="dialog" aria-modal="true" aria-labelledby="xd-title" ref={dialogRef} tabIndex={-1} onKeyDown={onKey} style={sx('position:relative;width:100%;max-width:560px;max-height:calc(100vh - 32px);overflow-y:auto;background:#FBFAF6;border-radius:10px;box-shadow:0 30px 60px -20px rgba(0,20,18,.55);outline:none;animation:fade-up 280ms cubic-bezier(.22,.61,.36,1) both')}>
                <div style={sx('position:relative;background:#002623;padding:28px 24px 24px;text-align:center;overflow:hidden;border-bottom:1px solid rgba(185,167,121,.45)')}>
                    <PatternSides width="90px" opacity=".12" fade="0%" />
                    <button type="button" className="h-close" onClick={onClose} aria-label="إغلاق" style={sx('position:absolute;top:12px;left:12px;width:44px;height:44px;border-radius:50%;border:0;background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background 180ms')}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EDEBE0" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                    </button>
                    <div style={sx('position:relative;font-size:13px;color:#B9A779;font-weight:600')}>الرقم الامتحاني</div>
                    <div style={sx("position:relative;font-weight:700;font-size:56px;line-height:1.15;color:#EDEBE0;font-variant-numeric:tabular-nums;direction:ltr")}>{x.examNumber}</div>
                    <h2 id="xd-title" style={sx('position:relative;margin:8px 0 0;font-size:19px;font-weight:600;line-height:1.5;color:#EDEBE0')}>{x.name}</h2>
                    <div style={sx('position:relative;margin-top:6px;font-size:14px;color:#B9A779;font-weight:600')}>اللغة: {s.name}</div>
                </div>

                <div style={sx('padding:20px clamp(18px,4vw,28px) 24px')}>
                    {showResult && (
                        <div style={sx(`margin-bottom:18px;padding:16px 18px;border-radius:8px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;background:${x.result === 'passed' ? '#E4EEEA' : '#FFFFFF'};border:1px solid ${x.result === 'passed' ? 'rgba(5,66,57,.3)' : '#E6E1D3'}`)}>
                            <span style={sx('font-size:15px;font-weight:700;color:#002623')}>النتيجة:</span>
                            {x.result ? <ResultBadge result={x.result} /> : <span style={sx('font-size:15px;color:#4F5D58')}>لم تصدر بعد</span>}
                            {x.score !== null && <span style={sx('font-size:15px;color:#002623')}>العلامة: <strong style={sx('font-variant-numeric:tabular-nums')}>{x.score}</strong></span>}
                            {x.resultNote && <span style={sx('flex:1 1 100%;font-size:14px;line-height:1.8;color:#3F3A2E')}>{x.resultNote}</span>}
                        </div>
                    )}

                    <dl style={sx('margin:0;border-top:1px solid #EAE5D8')}>
                        {row('التاريخ', date, true)}
                        {row('الوقت', time, true)}
                        {row('المكان', s.location)}
                        {row('القاعة', x.hall, true)}
                        {x.building && row('البناء', x.building, true)}
                        {x.fileNumber && row('رقم الملف', String(x.fileNumber))}
                    </dl>

                    {s.notes && (
                        <div style={sx('margin-top:16px;display:flex;gap:10px;align-items:flex-start;background:#F3EEE1;border-radius:6px;padding:12px 14px;font-size:14px;line-height:1.8;color:#3F3A2E;white-space:pre-line')}>{s.notes}</div>
                    )}

                    {instructions.length > 0 && !showResult && (
                        <div style={sx('margin-top:16px')}>
                            <div style={sx('font-size:14px;font-weight:700;color:#002623;margin-bottom:6px')}>تعليمات</div>
                            <ul style={sx('margin:0;padding:0 18px 0 0;font-size:14px;line-height:1.9;color:#3F3A2E')}>
                                {instructions.map((line, i) => <li key={i}>{line}</li>)}
                            </ul>
                        </div>
                    )}

                    <div style={sx('margin-top:20px;display:flex')}>
                        <button type="button" className="h-dark" onClick={onClose} style={sx('flex:1 1 auto;height:48px;background:#002623;color:#EDEBE0;border:0;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms')}>حسناً</button>
                    </div>
                </div>
            </div>
        </div>
    );
}
