import React, { useCallback, useEffect, useState } from 'react';
import { api, errorText } from '../api';
import { Field, Icon, Modal, Pager, Spinner, StatusBadge, useDebounced, useToast } from '../ui';

const FILTER_KEYS = ['governorate', 'job_title', 'status', 'follow_up_status'];

function initialFilters() {
    const p = new URLSearchParams(window.location.search);
    return Object.fromEntries(FILTER_KEYS.map((k) => [k, p.get(k) || '']));
}

export default function CandidatesTab({ competition, onChanged }) {
    const [filters, setFilters] = useState(initialFilters);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [options, setOptions] = useState(null);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null); // candidate | {} for new
    const [reloadKey, setReloadKey] = useState(0);
    const [showFilters, setShowFilters] = useState(false);
    const toast = useToast();
    const q = useDebounced(search);

    const base = `/competitions/${competition.id}/candidates`;
    const params = { 'search-key': q, filters: { candidate: filters } };

    // خيارات القوائم (المسميات تتبع المحافظة المختارة)
    useEffect(() => {
        api.get(`${base}/options`, { governorate: filters.governorate }).then(setOptions).catch(() => {});
    }, [base, filters.governorate, reloadKey]);

    useEffect(() => { setPage(1); }, [q, filters]);

    // حفظ الفلاتر في الرابط ليسهل مشاركته أو الرجوع إليه
    useEffect(() => {
        const qs = new URLSearchParams(Object.entries(filters).filter(([, v]) => v)).toString();
        window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
    }, [filters]);

    useEffect(() => {
        let alive = true;
        setError('');
        api.get(base, { ...params, page, max: 25 })
            .then((d) => alive && setData(d))
            .catch((e) => alive && setError(errorText(e)));
        return () => { alive = false; };
    }, [base, q, filters, page, reloadKey]);

    const refresh = useCallback(() => { setReloadKey((k) => k + 1); onChanged?.(); }, [onChanged]);

    const setFilter = (k) => (e) => {
        const v = e.target.value;
        setFilters((f) => ({ ...f, [k]: v, ...(k === 'governorate' ? { job_title: '' } : {}) }));
    };
    const clearFilters = () => { setFilters(Object.fromEntries(FILTER_KEYS.map((k) => [k, '']))); setSearch(''); };
    const activeFilters = FILTER_KEYS.filter((k) => filters[k]).length;
    const hasFilters = search || activeFilters > 0;

    const updateFollowUp = async (c, value) => {
        setData((d) => ({ ...d, data: d.data.map((x) => (x.id === c.id ? { ...x, follow_up_status: value } : x)) }));
        try {
            await api.put(`/candidates/${c.id}`, { follow_up_status: value });
            toast(`تم تحديث متابعة: ${c.full_name}`);
        } catch (e) {
            toast(errorText(e), 'error');
            setReloadKey((k) => k + 1);
        }
    };

    const remove = async (c) => {
        if (!window.confirm(`حذف «${c.full_name}» من النتائج؟ سيُعاد ترتيب مجموعته.`)) return;
        try {
            await api.del(`/candidates/${c.id}`);
            toast('تم الحذف');
            refresh();
        } catch (e) { toast(errorText(e), 'error'); }
    };

    return (
        <div className="stack">
            <div className="card card-pad stack">
                <div className="search-box" style={{ flex: 'none' }}>
                    <Icon name="search" color="var(--muted-2)" />
                    <input className="input" type="search" enterKeyHint="search" placeholder="ابحث بالاسم... (لا يهم أ/ا أو ة/ه)" value={search} onChange={(e) => setSearch(e.target.value)} aria-label="ابحث بالاسم" />
                </div>
                <div className="row toolbar">
                    <button type="button" className="btn btn-outline filters-toggle" onClick={() => setShowFilters((v) => !v)} aria-expanded={showFilters} aria-controls="filters-panel">
                        تصفية{activeFilters ? ` (${activeFilters})` : ''}
                    </button>
                    <button type="button" className="btn btn-primary" onClick={() => setEditing({})}><Icon name="plus" /> إضافة<span className="btn-text"> اسم</span></button>
                    <a className="btn btn-outline" href={api.url(`${base}/export`, params)} download><Icon name="download" /> تصدير<span className="btn-text"> Excel</span></a>
                </div>
                <div id="filters-panel" className={'form-grid filters-panel' + (showFilters ? ' open' : '')} style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 190px), 1fr))' }}>
                    <select className="select" value={filters.governorate} onChange={setFilter('governorate')} aria-label="المحافظة">
                        <option value="">كل المحافظات</option>
                        {options?.governorates.map((g) => <option key={g} value={g}>{g}</option>)}
                    </select>
                    <select className="select" value={filters.job_title} onChange={setFilter('job_title')} aria-label="المسمى الوظيفي">
                        <option value="">كل المسميات</option>
                        {options?.job_titles.map((t) => <option key={t} value={t}>{t}</option>)}
                    </select>
                    <select className="select" value={filters.status} onChange={setFilter('status')} aria-label="النتيجة">
                        <option value="">أساسي واحتياطي</option>
                        {options?.statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    <select className="select" value={filters.follow_up_status} onChange={setFilter('follow_up_status')} aria-label="المتابعة">
                        <option value="">كل حالات المتابعة</option>
                        {options?.follow_up_statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </div>
                {hasFilters && <div><button type="button" className="btn btn-ghost btn-sm" onClick={clearFilters}><Icon name="close" /> مسح البحث والتصفية</button></div>}
            </div>

            {error && <div className="alert alert-error">{error}</div>}
            {!data && !error && <Spinner />}

            {/* الهاتف: بطاقة لكل اسم بدل الجدول */}
            {data && (
                <div className="only-mobile">
                    <div className="small muted" style={{ marginBottom: 8 }}>{data.total} اسم{hasFilters ? ' مطابق' : ''}</div>
                    <div className="cards">
                        {data.data.map((c) => (
                            <article key={c.id} className="cand-card">
                                <div className="cand-head">
                                    <span className="cand-rank num" title="الترتيب">{c.rank}</span>
                                    <span className="cand-name">{c.full_name}</span>
                                    <StatusBadge status={c.status} label={c.status_label} />
                                </div>
                                <div className="cand-meta">{c.governorate} · {c.job_title}{c.score != null ? ` · العلامة ${c.score}` : ''}</div>
                                {c.notes && <div className="cand-notes">{c.notes}</div>}
                                <div className="cand-actions">
                                    <select className={'select fu-' + c.follow_up_status} value={c.follow_up_status} onChange={(e) => updateFollowUp(c, e.target.value)} aria-label={`متابعة ${c.full_name}`}>
                                        {options?.follow_up_statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                    </select>
                                    <button type="button" className="btn btn-outline btn-icon" onClick={() => setEditing(c)} aria-label={`تعديل ${c.full_name}`}><Icon name="edit" /></button>
                                    <button type="button" className="btn btn-outline btn-icon" onClick={() => remove(c)} aria-label={`حذف ${c.full_name}`}><Icon name="trash" /></button>
                                </div>
                            </article>
                        ))}
                    </div>
                    {data.data.length === 0 && <div className="card table-empty">لا توجد أسماء مطابقة.</div>}
                    <div className="card" style={{ marginTop: 10, overflow: 'hidden' }}><Pager meta={data} onPage={(p) => { setPage(p); window.scrollTo({ top: 0, behavior: 'smooth' }); }} /></div>
                </div>
            )}

            {data && (
                <div className="table-wrap only-desktop">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>الترتيب</th>
                                <th>الاسم</th>
                                <th className="hide-sm">المحافظة</th>
                                <th className="hide-sm">المسمى الوظيفي</th>
                                <th className="hide-sm">العلامة</th>
                                <th>النتيجة</th>
                                <th>المتابعة</th>
                                <th className="hide-sm">ملاحظات</th>
                                <th><span className="sr-only">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.data.map((c) => (
                                <tr key={c.id}>
                                    <td className="num">{c.rank}</td>
                                    <td className="name">{c.full_name}</td>
                                    <td className="hide-sm">{c.governorate}</td>
                                    <td className="hide-sm">{c.job_title}</td>
                                    <td className="num hide-sm">{c.score ?? '—'}</td>
                                    <td><StatusBadge status={c.status} label={c.status_label} /></td>
                                    <td>
                                        <select className={'select select-sm fu-' + c.follow_up_status} value={c.follow_up_status} onChange={(e) => updateFollowUp(c, e.target.value)} aria-label={`متابعة ${c.full_name}`}>
                                            {options?.follow_up_statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                        </select>
                                    </td>
                                    <td className="hide-sm small muted" style={{ maxWidth: 220 }} title={c.notes || ''}>
                                        {c.notes ? (c.notes.length > 40 ? c.notes.slice(0, 40) + '…' : c.notes) : '—'}
                                    </td>
                                    <td className="actions">
                                        <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => setEditing(c)} aria-label={`تعديل ${c.full_name}`} title="تعديل"><Icon name="edit" /></button>
                                        <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => remove(c)} aria-label={`حذف ${c.full_name}`} title="حذف"><Icon name="trash" /></button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {data.data.length === 0 && <div className="table-empty">لا توجد أسماء مطابقة.</div>}
                    <Pager meta={data} onPage={setPage} />
                </div>
            )}

            {editing && (
                <CandidateModal
                    competition={competition}
                    candidate={editing}
                    options={options}
                    onClose={() => setEditing(null)}
                    onSaved={(msg) => { setEditing(null); toast(msg); refresh(); }}
                />
            )}
        </div>
    );
}

function CandidateModal({ competition, candidate, options, onClose, onSaved }) {
    const isNew = !candidate.id;
    const [form, setForm] = useState({
        full_name: candidate.full_name || '',
        governorate: candidate.governorate || '',
        job_title: candidate.job_title || '',
        status: candidate.status || 'primary',
        score: candidate.score ?? '',
        interview_date: candidate.interview_date || '',
        follow_up_status: candidate.follow_up_status || 'pending',
        notes: candidate.notes || '',
    });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const save = async (e) => {
        e?.preventDefault();
        setBusy(true); setError('');
        const payload = { ...form, score: form.score === '' ? null : form.score, interview_date: form.interview_date || null, notes: form.notes || null };
        try {
            if (isNew) await api.post(`/competitions/${competition.id}/candidates`, payload);
            else await api.put(`/candidates/${candidate.id}`, payload);
            onSaved(isNew ? 'تمت إضافة الاسم' : 'تم حفظ التعديلات');
        } catch (err) {
            setError(errorText(err));
            setBusy(false);
        }
    };

    return (
        <Modal
            title={isNew ? 'إضافة اسم' : 'تعديل: ' + candidate.full_name}
            onClose={onClose}
            footer={<>
                <button type="button" className="btn btn-primary" onClick={save} disabled={busy}>{busy ? 'جارٍ الحفظ...' : 'حفظ'}</button>
                <button type="button" className="btn btn-outline" onClick={onClose}>إلغاء</button>
            </>}
        >
            <form className="stack" onSubmit={save} noValidate>
                {error && <div className="alert alert-error" role="alert">{error}</div>}
                <div className="form-grid">
                    <Field label="الاسم الكامل *" htmlFor="f-name" className="full">
                        <input id="f-name" className="input" value={form.full_name} onChange={set('full_name')} required />
                    </Field>
                    <Field label="المحافظة *" htmlFor="f-gov">
                        <input id="f-gov" className="input" list="dl-govs" value={form.governorate} onChange={set('governorate')} required />
                        <datalist id="dl-govs">{options?.governorates.map((g) => <option key={g} value={g} />)}</datalist>
                    </Field>
                    <Field label="المسمى الوظيفي *" htmlFor="f-title">
                        <input id="f-title" className="input" list="dl-titles" value={form.job_title} onChange={set('job_title')} required />
                        <datalist id="dl-titles">{options?.job_titles.map((t) => <option key={t} value={t} />)}</datalist>
                    </Field>
                    <Field label="النتيجة *" htmlFor="f-status">
                        <select id="f-status" className="select" value={form.status} onChange={set('status')}>
                            <option value="primary">أساسي</option>
                            <option value="reserve">احتياطي</option>
                        </select>
                    </Field>
                    <Field label="العلامة" htmlFor="f-score" hint="تُستخدم للترتيب ولا تُنشر.">
                        <input id="f-score" className="input" type="number" step="0.01" min="0" value={form.score} onChange={set('score')} />
                    </Field>
                    <Field label="تاريخ المقابلة" htmlFor="f-date">
                        <input id="f-date" className="input" type="date" value={form.interview_date} onChange={set('interview_date')} />
                    </Field>
                    <Field label="المتابعة" htmlFor="f-fu">
                        <select id="f-fu" className="select" value={form.follow_up_status} onChange={set('follow_up_status')}>
                            {options?.follow_up_statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                    </Field>
                    <Field label="ملاحظات" htmlFor="f-notes" className="full" hint="للاستخدام الداخلي فقط.">
                        <textarea id="f-notes" className="textarea" rows={3} value={form.notes} onChange={set('notes')} />
                    </Field>
                </div>
                <button type="submit" hidden />
            </form>
        </Modal>
    );
}
