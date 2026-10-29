import React, { useCallback, useEffect, useState } from 'react';
import { api, errorText } from '../../api';
import { Field, Icon, Modal, Pager, Spinner, StatusBadge, useDebounced, useToast } from '../../ui';

const FILTER_KEYS = ['exam_session_id', 'result'];

function initialFilters() {
    const p = new URLSearchParams(window.location.search);
    return Object.fromEntries(FILTER_KEYS.map((k) => [k, p.get(k) || '']));
}

export default function ExamineesTab({ exam, onChanged }) {
    const [filters, setFilters] = useState(initialFilters);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [options, setOptions] = useState(null);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null);
    const [reloadKey, setReloadKey] = useState(0);
    const [showFilters, setShowFilters] = useState(false);
    const toast = useToast();
    const q = useDebounced(search);

    const base = `/exams/${exam.id}/examinees`;

    useEffect(() => { api.get(`${base}/options`).then(setOptions).catch(() => {}); }, [base, reloadKey]);
    useEffect(() => { setPage(1); }, [q, filters]);

    useEffect(() => {
        const qs = new URLSearchParams(Object.entries(filters).filter(([, v]) => v)).toString();
        window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
    }, [filters]);

    useEffect(() => {
        let alive = true;
        setError('');
        api.get(base, { 'search-key': q, filters: { examinee: filters }, page, max: 25 })
            .then((d) => alive && setData(d))
            .catch((e) => alive && setError(errorText(e)));
        return () => { alive = false; };
    }, [base, q, filters, page, reloadKey]);

    const refresh = useCallback(() => { setReloadKey((k) => k + 1); onChanged?.(); }, [onChanged]);

    const setFilter = (k) => (e) => setFilters((f) => ({ ...f, [k]: e.target.value }));
    const activeFilters = FILTER_KEYS.filter((k) => filters[k]).length;
    const hasFilters = search || activeFilters > 0;
    const clearFilters = () => { setFilters(Object.fromEntries(FILTER_KEYS.map((k) => [k, '']))); setSearch(''); };

    const setResult = async (x, value) => {
        setData((d) => ({ ...d, data: d.data.map((r) => (r.id === x.id ? { ...r, result: value || null } : r)) }));
        try {
            await api.put(`/examinees/${x.id}`, { result: value || null });
            toast(`تم تسجيل نتيجة: ${x.full_name}`);
        } catch (e) {
            toast(errorText(e), 'error');
            setReloadKey((k) => k + 1);
        }
    };

    const remove = async (x) => {
        if (!window.confirm(`حذف «${x.full_name}» (الرقم الامتحاني ${x.exam_number})؟`)) return;
        try {
            await api.del(`/examinees/${x.id}`);
            toast('تم الحذف');
            refresh();
        } catch (e) { toast(errorText(e), 'error'); }
    };

    const resultSelect = (x) => (
        <select className="select select-sm" style={{ minWidth: 110 }} value={x.result || ''} onChange={(e) => setResult(x, e.target.value)} aria-label={`نتيجة ${x.full_name}`}>
            <option value="">— لم تُدخل —</option>
            {options?.results.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
        </select>
    );

    return (
        <div className="stack">
            <div className="card card-pad stack">
                <div className="search-box" style={{ flex: 'none' }}>
                    <Icon name="search" color="var(--muted-2)" />
                    <input className="input" type="search" enterKeyHint="search" placeholder="ابحث بالاسم، أو رقم الملف، أو الرقم الامتحاني..." value={search} onChange={(e) => setSearch(e.target.value)} aria-label="بحث" />
                </div>
                <div className="row toolbar">
                    <button type="button" className="btn btn-outline filters-toggle" onClick={() => setShowFilters((v) => !v)} aria-expanded={showFilters}>تصفية{activeFilters ? ` (${activeFilters})` : ''}</button>
                    <button type="button" className="btn btn-primary" onClick={() => setEditing({})}><Icon name="plus" /> إضافة<span className="btn-text"> متقدم</span></button>
                </div>
                <div className={'form-grid filters-panel' + (showFilters ? ' open' : '')} style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 200px), 1fr))' }}>
                    <select className="select" value={filters.exam_session_id} onChange={setFilter('exam_session_id')} aria-label="اللغة">
                        <option value="">كل اللغات</option>
                        {options?.sessions.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <select className="select" value={filters.result} onChange={setFilter('result')} aria-label="النتيجة">
                        <option value="">كل النتائج</option>
                        <option value="none">لم تُدخل نتيجته</option>
                        {options?.results.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </select>
                </div>
                {hasFilters && <div><button type="button" className="btn btn-ghost btn-sm" onClick={clearFilters}><Icon name="close" /> مسح البحث والتصفية</button></div>}
            </div>

            {error && <div className="alert alert-error">{error}</div>}
            {!data && !error && <Spinner />}

            {data && (
                <div className="only-mobile">
                    <div className="small muted" style={{ marginBottom: 8 }}>{data.total} متقدم{hasFilters ? ' مطابق' : ''}</div>
                    <div className="cards">
                        {data.data.map((x) => (
                            <article key={x.id} className="cand-card">
                                <div className="cand-head">
                                    <span className="cand-rank num" title="الرقم الامتحاني">{x.exam_number}</span>
                                    <span className="cand-name">{x.full_name}</span>
                                </div>
                                <div className="cand-meta">{x.session} · القاعة: {x.hall || 'غير محددة'}{x.file_number ? ` · ملف ${x.file_number}` : ''}</div>
                                {x.admin_note && <div className="cand-notes">ملاحظة داخلية: {x.admin_note}</div>}
                                <div className="cand-actions">
                                    {resultSelect(x)}
                                    <button type="button" className="btn btn-outline btn-icon" onClick={() => setEditing(x)} aria-label={`تعديل ${x.full_name}`}><Icon name="edit" /></button>
                                    <button type="button" className="btn btn-outline btn-icon" onClick={() => remove(x)} aria-label={`حذف ${x.full_name}`}><Icon name="trash" /></button>
                                </div>
                            </article>
                        ))}
                    </div>
                    {data.data.length === 0 && <div className="card table-empty">لا يوجد متقدمون مطابقون.</div>}
                    <div className="card" style={{ marginTop: 10, overflow: 'hidden' }}><Pager meta={data} onPage={(p) => { setPage(p); window.scrollTo({ top: 0, behavior: 'smooth' }); }} /></div>
                </div>
            )}

            {data && (
                <div className="table-wrap only-desktop">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>الرقم الامتحاني</th>
                                <th>الاسم</th>
                                <th>اللغة</th>
                                <th>رقم الملف</th>
                                <th>القاعة</th>
                                <th>النتيجة</th>
                                <th>العلامة</th>
                                <th>ملاحظة داخلية</th>
                                <th><span className="sr-only">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.data.map((x) => (
                                <tr key={x.id}>
                                    <td className="num"><strong>{x.exam_number}</strong></td>
                                    <td className="name">{x.full_name}</td>
                                    <td>{x.session}</td>
                                    <td className="small">{x.file_number || '—'}</td>
                                    <td className="small">{x.hall || <span className="muted">غير محددة</span>}{x.hall_name && <span className="muted"> (خاصة)</span>}</td>
                                    <td>{resultSelect(x)}</td>
                                    <td className="num">{x.score ?? '—'}</td>
                                    <td className="small muted" style={{ maxWidth: 200 }} title={x.admin_note || ''}>{x.admin_note ? (x.admin_note.length > 36 ? x.admin_note.slice(0, 36) + '…' : x.admin_note) : '—'}</td>
                                    <td className="actions">
                                        <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => setEditing(x)} aria-label={`تعديل ${x.full_name}`} title="تعديل"><Icon name="edit" /></button>
                                        <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => remove(x)} aria-label={`حذف ${x.full_name}`} title="حذف"><Icon name="trash" /></button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {data.data.length === 0 && <div className="table-empty">لا يوجد متقدمون مطابقون.</div>}
                    <Pager meta={data} onPage={setPage} />
                </div>
            )}

            {editing && (
                <ExamineeModal
                    exam={exam}
                    examinee={editing}
                    options={options}
                    defaultSession={filters.exam_session_id}
                    onClose={() => setEditing(null)}
                    onSaved={(msg) => { setEditing(null); toast(msg); refresh(); }}
                />
            )}
        </div>
    );
}

function ExamineeModal({ exam, examinee, options, defaultSession, onClose, onSaved }) {
    const isNew = !examinee.id;
    const [form, setForm] = useState({
        full_name: examinee.full_name || '',
        exam_session_id: examinee.exam_session_id || defaultSession || options?.sessions[0]?.id || '',
        exam_number: examinee.exam_number ?? '',
        file_number: examinee.file_number || '',
        hall_name: examinee.hall_name || '',
        admin_note: examinee.admin_note || '',
        result: examinee.result || '',
        score: examinee.score ?? '',
        result_note: examinee.result_note || '',
    });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const save = async (e) => {
        e?.preventDefault();
        setBusy(true); setError('');
        const payload = {
            ...form,
            exam_session_id: Number(form.exam_session_id),
            file_number: form.file_number || null, hall_name: form.hall_name || null, admin_note: form.admin_note || null,
            result: form.result || null, score: form.score === '' ? null : form.score, result_note: form.result_note || null,
        };
        try {
            if (isNew) await api.post(`/exams/${exam.id}/examinees`, payload);
            else await api.put(`/examinees/${examinee.id}`, payload);
            onSaved(isNew ? 'تمت إضافة المتقدم' : 'تم حفظ التعديلات');
        } catch (err) {
            setError(errorText(err));
            setBusy(false);
        }
    };

    return (
        <Modal
            title={isNew ? 'إضافة متقدم' : 'تعديل: ' + examinee.full_name}
            onClose={onClose}
            footer={<>
                <button type="button" className="btn btn-primary" onClick={save} disabled={busy}>{busy ? 'جارٍ الحفظ...' : 'حفظ'}</button>
                <button type="button" className="btn btn-outline" onClick={onClose}>إلغاء</button>
            </>}
        >
            <form className="stack" onSubmit={save} noValidate>
                {error && <div className="alert alert-error" role="alert">{error}</div>}
                <div className="form-grid">
                    <Field label="الاسم الثلاثي *" htmlFor="x-name" className="full">
                        <input id="x-name" className="input" value={form.full_name} onChange={set('full_name')} required />
                    </Field>
                    <Field label="اللغة *" htmlFor="x-session">
                        <select id="x-session" className="select" value={form.exam_session_id} onChange={set('exam_session_id')}>
                            {options?.sessions.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </Field>
                    <Field label="الرقم الامتحاني *" htmlFor="x-num">
                        <input id="x-num" className="input" type="number" min="1" value={form.exam_number} onChange={set('exam_number')} required />
                    </Field>
                    <Field label="رقم ملف المتسابق" htmlFor="x-file">
                        <input id="x-file" className="input" value={form.file_number} onChange={set('file_number')} />
                    </Field>
                    <Field label="قاعة خاصة" htmlFor="x-hall" hint="اتركها فارغة لتُحدد تلقائياً من توزيع القاعات. مثال: قاعة في الطابق الأرضي.">
                        <input id="x-hall" className="input" value={form.hall_name} onChange={set('hall_name')} />
                    </Field>
                    <Field label="ملاحظة داخلية" htmlFor="x-note" className="full" hint="للاستخدام الداخلي فقط — لا تظهر للمتقدم ولا للعموم.">
                        <textarea id="x-note" className="textarea" rows={2} value={form.admin_note} onChange={set('admin_note')} />
                    </Field>
                    <Field label="النتيجة" htmlFor="x-result">
                        <select id="x-result" className="select" value={form.result} onChange={set('result')}>
                            <option value="">— لم تُدخل —</option>
                            {options?.results.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </select>
                    </Field>
                    <Field label="العلامة" htmlFor="x-score">
                        <input id="x-score" className="input" type="number" step="0.01" min="0" value={form.score} onChange={set('score')} />
                    </Field>
                    <Field label="ملاحظة تظهر مع النتيجة" htmlFor="x-rnote" className="full" hint="تظهر للمتقدم عند نشر النتائج (اختياري).">
                        <input id="x-rnote" className="input" value={form.result_note} onChange={set('result_note')} />
                    </Field>
                </div>
                <button type="submit" hidden />
            </form>
        </Modal>
    );
}
