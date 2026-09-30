import React, { useEffect, useRef, useState } from 'react';
import { api, errorText } from '../api';
import { Link } from '../router';
import { Icon, Spinner, formatDate, useToast } from '../ui';

export default function ImportTab({ competition, onImported }) {
    const [file, setFile] = useState(null);
    const [mode, setMode] = useState('replace');
    const [preview, setPreview] = useState(null);
    const [busy, setBusy] = useState('');
    const [error, setError] = useState('');
    const [over, setOver] = useState(false);
    const [history, setHistory] = useState(null);
    const inputRef = useRef(null);
    const toast = useToast();
    const base = `/competitions/${competition.id}/imports`;

    const loadHistory = () => api.get(base).then(setHistory).catch(() => setHistory([]));
    useEffect(() => { loadHistory(); }, [competition.id]);

    const pick = (f) => {
        setPreview(null); setError('');
        if (!f) return;
        if (!/\.xlsx$/i.test(f.name)) { setError('الرجاء اختيار ملف Excel بصيغة ‎.xlsx'); return; }
        setFile(f);
        check(f);
    };

    const form = (f) => {
        const fd = new FormData();
        fd.append('file', f);
        fd.append('mode', mode);
        return fd;
    };

    const check = async (f = file) => {
        setBusy('preview'); setError('');
        try { setPreview(await api.post(`${base}/preview`, form(f))); }
        catch (e) { setError(errorText(e)); }
        finally { setBusy(''); }
    };

    const confirmImport = async () => {
        if (mode === 'replace' && preview.current_count > 0
            && !window.confirm(`سيتم حذف ${preview.current_count} اسماً حالياً (مع بيانات المتابعة) واستبدالها بـ ${preview.valid_count} من الملف. متابعة؟`)) return;
        setBusy('import'); setError('');
        try {
            const result = await api.post(base, form(file));
            toast(`تم استيراد ${result.imported_count} اسماً`);
            setFile(null); setPreview(null);
            if (inputRef.current) inputRef.current.value = '';
            loadHistory();
            onImported?.();
        } catch (e) { setError(errorText(e)); }
        finally { setBusy(''); }
    };

    return (
        <div className="stack">
            <section className="card card-pad stack">
                <h2 className="card-title" style={{ margin: 0 }}>استيراد النتائج من ملف Excel</h2>
                <div className="alert alert-info small">
                    يجب أن يحتوي السطر الأول على عناوين الأعمدة: <strong>الاسم الكامل</strong>، <strong>المحافظة</strong>، <strong>المسمى الوظيفي</strong>، <strong>نتيجة</strong> (يحتوي «أساسي» أو «احتياط»)،
                    واختيارياً <strong>العلامة</strong> و<strong>تاريخ المقابلة</strong>. تُقرأ الورقة الأولى فقط.
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

                <div className="radio-cards" role="radiogroup" aria-label="طريقة الاستيراد">
                    <label className={'radio-card' + (mode === 'replace' ? ' checked' : '')}>
                        <input type="radio" name="mode" value="replace" checked={mode === 'replace'} onChange={() => setMode('replace')} />
                        <span><strong>استبدال</strong><br /><span className="small muted">حذف الأسماء الحالية واعتماد الملف بالكامل (للنشر الأول أو عند تصحيح الملف).</span></span>
                    </label>
                    <label className={'radio-card' + (mode === 'append' ? ' checked' : '')}>
                        <input type="radio" name="mode" value="append" checked={mode === 'append'} onChange={() => setMode('append')} />
                        <span><strong>إضافة</strong><br /><span className="small muted">إضافة أسماء الملف إلى الأسماء الحالية (مثلاً نتائج دفعة جديدة).</span></span>
                    </label>
                </div>

                {error && <div className="alert alert-error" role="alert">{error}</div>}
                {busy === 'preview' && <Spinner label="جارٍ فحص الملف..." />}

                {preview && (
                    <div className="stack">
                        <div className="grid grid-stats">
                            <div className="stat primary"><span className="stat-label">أسطر صالحة</span><span className="stat-value">{preview.valid_count}</span></div>
                            <div className={'stat' + (preview.skipped_count ? ' reserve' : '')}><span className="stat-label">أسطر ستُتجاهل</span><span className="stat-value">{preview.skipped_count}</span></div>
                            <div className="stat"><span className="stat-label">الأسماء الحالية في المسابقة</span><span className="stat-value">{preview.current_count}</span></div>
                        </div>

                        {preview.errors.length > 0 && (
                            <div className="alert alert-warn">
                                <strong>أسطر فيها مشاكل (لن تُستورد):</strong>
                                <ul>
                                    {preview.errors.slice(0, 20).map((e) => <li key={e.row}>السطر {e.row}: {e.message}</li>)}
                                    {preview.errors.length > 20 && <li>و {preview.errors.length - 20} أخرى...</li>}
                                </ul>
                            </div>
                        )}

                        <div className="table-wrap">
                            <table className="table">
                                <thead><tr><th>المحافظة</th><th>المسميات</th><th>أساسي</th><th>احتياطي</th><th>المجموع</th></tr></thead>
                                <tbody>
                                    {preview.summary.map((g) => (
                                        <tr key={g.governorate}>
                                            <td className="name">{g.governorate}</td>
                                            <td className="num">{g.job_titles}</td>
                                            <td className="num">{g.primary}</td>
                                            <td className="num">{g.reserve}</td>
                                            <td className="num"><strong>{g.total}</strong></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="row">
                            <button type="button" className="btn btn-primary" onClick={confirmImport} disabled={!!busy || !preview.valid_count}>
                                <Icon name="upload" /> {busy === 'import' ? 'جارٍ الاستيراد...' : `تأكيد استيراد ${preview.valid_count} اسماً (${mode === 'replace' ? 'استبدال' : 'إضافة'})`}
                            </button>
                            <button type="button" className="btn btn-outline" onClick={() => { setFile(null); setPreview(null); if (inputRef.current) inputRef.current.value = ''; }} disabled={!!busy}>إلغاء</button>
                        </div>
                    </div>
                )}
            </section>

            <section className="card card-pad">
                <h2 className="card-title">سجل الاستيراد</h2>
                {!history ? <Spinner /> : history.length === 0 ? <p className="muted small" style={{ margin: 0 }}>لم يتم أي استيراد بعد.</p> : (
                    <div className="table-wrap">
                        <table className="table">
                            <thead><tr><th>التاريخ</th><th>الملف</th><th>الطريقة</th><th>مستورد</th><th>متجاهل</th><th className="hide-sm">بواسطة</th></tr></thead>
                            <tbody>
                                {history.map((h) => (
                                    <tr key={h.id}>
                                        <td className="nowrap">{formatDate(h.created_at)}</td>
                                        <td>{h.file_name}</td>
                                        <td>{h.mode === 'replace' ? 'استبدال' : 'إضافة'}</td>
                                        <td className="num">{h.imported_count}</td>
                                        <td className="num" title={(h.errors || []).map((e) => `السطر ${e.row}: ${e.message}`).join('\n')}>{h.skipped_count}</td>
                                        <td className="hide-sm">{h.user || 'سطر الأوامر'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {history?.length > 0 && competition.status !== 'published' && (
                    <p className="small muted" style={{ marginBottom: 0 }}>
                        المسابقة غير منشورة بعد — راجع الأسماء في <Link to={`/competitions/${competition.id}/candidates`}>متابعة الأسماء</Link> ثم اضغط «نشر على البوابة».
                    </p>
                )}
            </section>
        </div>
    );
}
