import React, { useRef, useState } from 'react';
import { api, errorText } from '../../api';
import { Link } from '../../router';
import { Icon, Spinner, useToast } from '../../ui';

const MODES = { upsert: 'تحديث وإضافة', replace: 'استبدال', append: 'إضافة' };

const rangeText = ([a, b]) => (a === b ? `${a}` : `${a}–${b}`);

export default function ExamImportTab({ exam, onImported }) {
    const [type, setType] = useState('examinees');   // examinees | results
    const [mode, setMode] = useState('upsert');
    const [hallsSession, setHallsSession] = useState('');
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [done, setDone] = useState(null);
    const [busy, setBusy] = useState('');
    const [error, setError] = useState('');
    const [over, setOver] = useState(false);
    const inputRef = useRef(null);
    const toast = useToast();
    const base = `/exams/${exam.id}/imports`;

    const reset = () => { setFile(null); setPreview(null); setError(''); if (inputRef.current) inputRef.current.value = ''; };
    const switchType = (t) => { setType(t); setDone(null); reset(); };

    const form = (f) => {
        const fd = new FormData();
        fd.append('file', f);
        fd.append('type', type);
        fd.append('mode', mode);
        if (hallsSession) fd.append('halls_session', hallsSession);
        return fd;
    };

    const pick = async (f) => {
        setPreview(null); setError(''); setDone(null);
        if (!f) return;
        if (!/\.xlsx$/i.test(f.name)) { setError('الرجاء اختيار ملف Excel بصيغة ‎.xlsx'); return; }
        setFile(f);
        setBusy('preview');
        try {
            const p = await api.post(`${base}/preview`, form(f));
            // جدول القاعات بلا عمود لغة: يُقترح على اللغة الأكبر (أو التي لا يبقى فيها أحد بلا قاعة)
            const coverage = p.halls_coverage || {};
            const fits = p.summary.find((s) => coverage[s.session] && coverage[s.session].uncovered_count === 0);
            setHallsSession(p.halls?.some((h) => !h.session) ? (fits || p.summary[0])?.session || '' : '');
            setPreview(p);
        }
        catch (e) { setError(errorText(e)); }
        finally { setBusy(''); }
    };

    const confirmImport = async () => {
        if (type === 'examinees' && mode === 'replace' && preview.current_count > 0
            && !window.confirm(`سيتم حذف ${preview.current_count} متقدماً حالياً (مع نتائجهم) واستبدالهم بـ ${preview.valid_count} من الملف. المواعيد والقاعات تبقى كما هي. متابعة؟`)) return;
        setBusy('import'); setError('');
        try {
            const result = await api.post(base, form(file));
            setDone({ type, mode, ...result });
            if (result.halls) toast(`تم حفظ ${result.halls} قاعة`);
            toast(type === 'results' ? `تم تسجيل نتائج ${result.updated} متقدماً`
                : mode === 'upsert' ? `تمت إضافة ${result.imported} وتحديث ${result.updated} متقدماً`
                : `تم استيراد ${result.imported} متقدماً`);
            reset();
            onImported?.();
        } catch (e) { setError(errorText(e)); }
        finally { setBusy(''); }
    };

    const resultsWithoutColumn = type === 'results' && preview && !preview.has_results;

    return (
        <div className="stack">
            <section className="card card-pad stack">
                <h2 className="card-title" style={{ margin: 0 }}>استيراد من ملف Excel</h2>

                <div className="radio-cards" role="radiogroup" aria-label="نوع الاستيراد">
                    <label className={'radio-card' + (type === 'examinees' ? ' checked' : '')}>
                        <input type="radio" name="type" checked={type === 'examinees'} onChange={() => switchType('examinees')} />
                        <span><strong>الأسماء والأرقام الامتحانية</strong><br /><span className="small muted">قبل الامتحان: ملف الأرقام الامتحانية (ورقة لكل لغة).</span></span>
                    </label>
                    <label className={'radio-card' + (type === 'results' ? ' checked' : '')}>
                        <input type="radio" name="type" checked={type === 'results'} onChange={() => switchType('results')} />
                        <span><strong>النتائج</strong><br /><span className="small muted">بعد الامتحان: نفس الملف مع عمود «النتيجة» (ناجح / راسب / غائب) وعمود «العلامة» اختياري.</span></span>
                    </label>
                </div>

                <div className="alert alert-info small">
                    {type === 'examinees' ? <>
                        الأعمدة المطلوبة: <strong>الرقم الامتحاني</strong>، <strong>الاسم الثلاثي</strong>، <strong>اللغة</strong>، واختيارياً <strong>رقم ملف المتسابق</strong>.
                        تُقرأ كل الأوراق وكل الجداول. الأرقام بلا اسم تُتجاهل، وأي عمود إضافي يُحفظ كملاحظة داخلية لا تُنشر.
                    </> : <>
                        تُطابق كل نتيجة مع المتقدم عبر <strong>اللغة + الرقم الامتحاني</strong>. النتائج لا تظهر للعموم إلا بعد الضغط على «نشر النتائج».
                    </>}
                </div>

                <div className="row" style={{ background: '#fff', border: '1px solid var(--line)', borderRadius: 6, padding: '12px 14px' }}>
                    <span style={{ flex: '1 1 260px', minWidth: 0 }}>
                        <strong>{type === 'results' ? 'قالب النتائج' : 'قالب الأسماء'}</strong><br />
                        <span className="small muted">
                            {type === 'results'
                                ? `ملف Excel جاهز فيه كل المتقدمين (${exam.examinees_count})، ورقة لكل لغة. املأ عمودي «العلامة» و«النتيجة» ثم ارفعه هنا.`
                                : 'ملف Excel فارغ بعناوين الأعمدة الصحيحة لإدخال أسماء امتحان جديد.'}
                        </span>
                    </span>
                    {type === 'results' && !exam.examinees_count
                        ? <span className="small muted">استورد الأسماء أولاً</span>
                        : <a className="btn btn-outline" href={api.url(`/exams/${exam.id}/templates/${type}`)} download><Icon name="download" /> تحميل القالب</a>}
                </div>

                <div
                    className={'dropzone' + (over ? ' over' : '')}
                    onClick={() => inputRef.current?.click()}
                    onDragOver={(e) => { e.preventDefault(); setOver(true); }}
                    onDragLeave={() => setOver(false)}
                    onDrop={(e) => { e.preventDefault(); setOver(false); pick(e.dataTransfer.files[0]); }}
                    role="button" tabIndex={0}
                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inputRef.current?.click(); } }}
                >
                    <Icon name="file" size={32} color="var(--gold-2)" width={1.4} />
                    <p style={{ margin: '8px 0 4px', fontWeight: 600 }}>{file ? file.name : 'اسحب ملف Excel إلى هنا أو انقر للاختيار'}</p>
                    <span className="small muted">{file ? (file.size / 1024).toFixed(0) + ' كيلوبايت' : 'xlsx فقط'}</span>
                    <input ref={inputRef} type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden onChange={(e) => pick(e.target.files[0])} />
                </div>

                {type === 'examinees' && (
                    <div className="radio-cards" role="radiogroup" aria-label="طريقة الاستيراد">
                        <label className={'radio-card' + (mode === 'upsert' ? ' checked' : '')}>
                            <input type="radio" name="mode" checked={mode === 'upsert'} onChange={() => setMode('upsert')} />
                            <span><strong>تحديث وإضافة</strong><br /><span className="small muted">المتقدم الموجود تُحدَّث بياناته وتبقى نتيجته وقاعته، وغير الموجود يُضاف. لا يُحذف أحد.</span></span>
                        </label>
                        <label className={'radio-card' + (mode === 'replace' ? ' checked' : '')}>
                            <input type="radio" name="mode" checked={mode === 'replace'} onChange={() => setMode('replace')} />
                            <span><strong>استبدال</strong><br /><span className="small muted">حذف المتقدمين الحاليين واعتماد الملف. المواعيد والقاعات تبقى.</span></span>
                        </label>
                        <label className={'radio-card' + (mode === 'append' ? ' checked' : '')}>
                            <input type="radio" name="mode" checked={mode === 'append'} onChange={() => setMode('append')} />
                            <span><strong>إضافة</strong><br /><span className="small muted">إضافة كل أسماء الملف كما هي دون مقارنة (قد يتكرر الاسم إن كان موجوداً).</span></span>
                        </label>
                    </div>
                )}

                {error && <div className="alert alert-error" role="alert">{error}</div>}
                {busy === 'preview' && <Spinner label="جارٍ فحص الملف..." />}

                {preview && (
                    <div className="stack">
                        <div className="grid grid-stats">
                            <div className="stat primary"><span className="stat-label">أسماء صالحة</span><span className="stat-value">{preview.valid_count}</span></div>
                            <div className="stat"><span className="stat-label">أرقام بلا اسم (تُتجاهل)</span><span className="stat-value">{preview.placeholders}</span></div>
                            <div className={'stat' + (preview.skipped_count ? ' reserve' : '')}><span className="stat-label">أسطر فيها خطأ</span><span className="stat-value">{preview.skipped_count}</span></div>
                            <div className="stat"><span className="stat-label">الموجودون حالياً</span><span className="stat-value">{preview.current_count}</span></div>
                        </div>

                        {type === 'examinees' && mode === 'upsert' && (
                            <div className="alert alert-info">
                                موجودون مسبقاً (تُحدَّث بياناتهم): <strong>{preview.existing_count}</strong> — جدد (سيُضافون): <strong>{preview.new_count}</strong>.
                                <br /><span className="small">يُعرف المتقدم داخل لغته بالاسم أو بالرقم الامتحاني. من ليس في الملف يبقى كما هو.</span>
                            </div>
                        )}

                        {resultsWithoutColumn && (
                            <div className="alert alert-error">الملف لا يحتوي عمود «النتيجة» أو «العلامة». أضف العمود ثم أعد الرفع، أو اختر «الأسماء والأرقام الامتحانية».</div>
                        )}

                        {preview.warnings.length > 0 && (
                            <div className="alert alert-warn">
                                <strong>تحتاج مراجعة (ستُستورد كما هي):</strong>
                                <ul>{preview.warnings.slice(0, 15).map((w, i) => <li key={i}>{w.message} <span className="muted">({w.row})</span></li>)}</ul>
                            </div>
                        )}
                        {preview.errors.length > 0 && (
                            <div className="alert alert-warn">
                                <strong>أسطر لن تُستورد:</strong>
                                <ul>
                                    {preview.errors.slice(0, 15).map((e, i) => <li key={i}>{e.message} <span className="muted">({e.row})</span></li>)}
                                    {preview.errors.length > 15 && <li>و {preview.errors.length - 15} أخرى...</li>}
                                </ul>
                            </div>
                        )}

                        {type === 'examinees' && preview.halls?.length > 0 && <HallsPreview preview={preview} session={hallsSession} onChange={setHallsSession} />}

                        <div className="table-wrap">
                            <table className="table">
                                <thead><tr><th>اللغة</th><th>العدد</th>{preview.has_results && <><th>ناجح</th><th>راسب</th><th>غائب</th></>}</tr></thead>
                                <tbody>
                                    {preview.summary.map((s) => (
                                        <tr key={s.session}>
                                            <td className="name">{s.session}</td>
                                            <td className="num"><strong>{s.total}</strong></td>
                                            {preview.has_results && <><td className="num">{s.passed}</td><td className="num">{s.failed}</td><td className="num">{s.absent}</td></>}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="row">
                            <button type="button" className="btn btn-primary" onClick={confirmImport} disabled={!!busy || !preview.valid_count || resultsWithoutColumn}>
                                <Icon name="upload" /> {busy === 'import' ? 'جارٍ الاستيراد...' : type === 'results' ? 'تسجيل النتائج' : `تأكيد استيراد ${preview.valid_count} متقدماً (${MODES[mode]})`}
                            </button>
                            <button type="button" className="btn btn-outline" onClick={reset} disabled={!!busy}>إلغاء</button>
                        </div>
                    </div>
                )}

                {done && done.type === 'examinees' && (
                    <div className="alert alert-info">
                        {done.mode === 'upsert'
                            ? <>تمت إضافة <strong>{done.imported}</strong> وتحديث <strong>{done.updated}</strong> متقدماً، و<strong>{done.unchanged}</strong> بلا تغيير.</>
                            : <>تم استيراد <strong>{done.imported}</strong> متقدماً في <strong>{done.sessions}</strong> لغة.</>}{' '}
                        {done.halls > 0 && <>وحُفظت <strong>{done.halls}</strong> قاعة.{' '}</>}
                        الخطوة التالية: <Link to={`/exams/${exam.id}/schedule`}>تحديد المواعيد وتوزيع القاعات</Link>.
                    </div>
                )}
                {done && done.type === 'results' && (
                    <div className={'alert ' + (done.unmatched_count ? 'alert-warn' : 'alert-info')}>
                        تم تسجيل نتائج <strong>{done.updated}</strong> متقدماً.
                        {done.unmatched_count > 0 && <>
                            {' '}<strong>{done.unmatched_count}</strong> سطراً لم يُعثر على صاحبه:
                            <ul>{done.unmatched.slice(0, 20).map((u, i) => <li key={i}>{u.message} <span className="muted">({u.row})</span></li>)}</ul>
                        </>}
                        {' '}راجع النتائج في <Link to={`/exams/${exam.id}/examinees`}>المتقدمون والنتائج</Link> ثم اضغط «نشر النتائج» أعلى الصفحة.
                    </div>
                )}
            </section>
        </div>
    );
}

/**
 * The file's «توزيع القاعات» table: which language it applies to, and exam numbers left without a hall.
 */
function HallsPreview({ preview, session, onChange }) {
    const withoutLanguage = preview.halls.filter((h) => !h.session);
    const shown = withoutLanguage.length ? withoutLanguage : preview.halls;
    const coverage = (session && preview.halls_coverage?.[session]) || null;

    return (
        <div className="card card-pad stack" style={{ background: '#fff' }}>
            <strong>توزيع القاعات في الملف ({preview.halls.length} قاعة)</strong>

            {withoutLanguage.length > 0 && (
                <div className="row" style={{ alignItems: 'flex-end' }}>
                    <label className="small" htmlFor="halls-session" style={{ flex: '1 1 220px' }}>
                        الجدول لا يحدد اللغة. يُطبَّق على:
                        <select id="halls-session" className="input" value={session} onChange={(e) => onChange(e.target.value)} style={{ marginTop: 4 }}>
                            <option value="">لا تطبّق توزيع القاعات</option>
                            {preview.summary.map((s) => <option key={s.session} value={s.session}>{s.session} ({s.total} متقدم)</option>)}
                        </select>
                    </label>
                </div>
            )}

            {session && <p className="small muted" style={{ margin: 0 }}>سيحل محل قاعات «{session}» الحالية. القاعة الخاصة لمتقدم معيّن تبقى كما هي.{preview.halls.some((h) => h.building) && ' المتقدم يرى القاعة والبناء.'}</p>}

            {coverage && coverage.uncovered_count > 0 && (
                <div className="alert alert-warn small">
                    <strong>{coverage.uncovered_count}</strong> متقدماً في «{session}» أرقامهم خارج كل القاعات ولن تظهر لهم قاعة:
                    {' '}<span className="num">{coverage.uncovered.map(rangeText).join('، ')}</span>.
                    صحّح الجدول في الملف، أو أضف قاعة لهم من «المواعيد والقاعات» بعد الاستيراد.
                </div>
            )}
            {coverage && coverage.uncovered_count === 0 && <div className="alert alert-info small">كل متقدمي «{session}» لهم قاعة.</div>}

            <div className="table-wrap">
                <table className="table">
                    <thead><tr><th>القاعة</th><th>البناء</th><th>من رقم</th><th>إلى رقم</th></tr></thead>
                    <tbody>
                        {shown.map((h, i) => (
                            <tr key={i}>
                                <td className="name">{h.name}{h.session && <span className="small muted"> · {h.session}</span>}</td>
                                <td>{h.building || '—'}</td>
                                <td className="num">{h.from_number}</td>
                                <td className="num">{h.to_number}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
