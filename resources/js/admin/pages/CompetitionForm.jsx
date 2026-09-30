import React, { useState } from 'react';
import { errorText } from '../api';
import { Field, Icon } from '../ui';

// قالب الأوراق المطلوبة المعتاد في وزارة العدل (يمكن تعديله لكل مسابقة)
export const DEFAULT_DOCUMENTS = [
    { title: 'غير محكوم', notes: [] },
    { title: 'غير موظف', notes: [] },
    { title: 'نسخة مصدقة من المؤهل العلمي', notes: [] },
    { title: 'صورة عن الهوية الشخصية', notes: [] },
    { title: 'صورة عن البيان العائلي', notes: ['في حال كان الموظف متزوجاً'] },
    { title: 'شهادة صحية', notes: ['لجنة فحص العاملين', 'يتطلب تحويلة من العدلية'] },
    { title: 'ترقين قيد من نقابة المحامين', notes: ['لحملة الاجازة في الحقوق'] },
    { title: 'صورة شخصية عدد 4', notes: [] },
];

export const DEFAULT_NOTE = 'يراجع الناجح الأساسي العدلية المتقدم اليها خلال مدة أقصاها اسبوع';

export function emptyCompetition() {
    return {
        title: '', slug: '', description: '', status: 'draft', ranking_method: 'score',
        primary_note: DEFAULT_NOTE, required_documents: DEFAULT_DOCUMENTS,
    };
}

/**
 * Create / edit competition form. onSubmit(payload) must return a promise.
 */
export default function CompetitionForm({ initial, onSubmit, submitLabel, extraActions }) {
    const [form, setForm] = useState(() => ({
        ...initial,
        required_documents: (initial.required_documents || []).map((d) => ({ title: d.title, notesText: (d.notes || []).join('\n') })),
    }));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });
    const setDoc = (i, k, v) => setForm({ ...form, required_documents: form.required_documents.map((d, j) => (j === i ? { ...d, [k]: v } : d)) });
    const moveDoc = (i, dir) => {
        const docs = [...form.required_documents];
        const j = i + dir;
        if (j < 0 || j >= docs.length) return;
        [docs[i], docs[j]] = [docs[j], docs[i]];
        setForm({ ...form, required_documents: docs });
    };

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true); setError('');
        try {
            await onSubmit({
                title: form.title,
                slug: form.slug || null,
                description: form.description || null,
                status: form.status,
                ranking_method: form.ranking_method,
                primary_note: form.primary_note || null,
                required_documents: form.required_documents
                    .filter((d) => d.title.trim())
                    .map((d) => ({ title: d.title.trim(), notes: d.notesText.split('\n').map((s) => s.trim()).filter(Boolean) })),
            });
        } catch (err) {
            setError(errorText(err));
        } finally {
            setBusy(false);
        }
    };

    return (
        <form className="stack" onSubmit={submit} noValidate>
            {error && <div className="alert alert-error" role="alert">{error}</div>}

            <section className="card card-pad">
                <h2 className="card-title">البيانات الأساسية</h2>
                <div className="form-grid">
                    <Field label="اسم المسابقة *" htmlFor="c-title" className="full" hint="يظهر للزوار بصيغة «نتائج ...»، مثال: مسابقة التوظيف 2026">
                        <input id="c-title" className="input" value={form.title} onChange={set('title')} required maxLength={255} />
                    </Field>
                    <Field label="الرابط المختصر" htmlFor="c-slug" hint="أحرف إنجليزية صغيرة وأرقام و - فقط. يُولَّد تلقائياً إن تُرك فارغاً.">
                        <input id="c-slug" className="input input-ltr" value={form.slug || ''} onChange={set('slug')} placeholder="recruitment-2026" maxLength={100} />
                    </Field>
                    <Field label="الحالة" htmlFor="c-status" hint="المسابقات المنشورة فقط تظهر في البوابة العامة.">
                        <select id="c-status" className="select" value={form.status} onChange={set('status')}>
                            <option value="draft">مسودة</option>
                            <option value="published">منشورة</option>
                            <option value="archived">مؤرشفة</option>
                        </select>
                    </Field>
                    <Field label="ترتيب المقبولين" htmlFor="c-rank" hint="داخل كل محافظة ومسمى وظيفي.">
                        <select id="c-rank" className="select" value={form.ranking_method} onChange={set('ranking_method')}>
                            <option value="score">حسب العلامة الأعلى</option>
                            <option value="sheet">حسب ترتيب ملف الإكسل</option>
                        </select>
                    </Field>
                    <Field label="وصف يظهر في البوابة" htmlFor="c-desc" className="full">
                        <textarea id="c-desc" className="textarea" rows={2} value={form.description || ''} onChange={set('description')} maxLength={2000} />
                    </Field>
                </div>
            </section>

            <section className="card card-pad">
                <div className="row" style={{ marginBottom: 14 }}>
                    <h2 className="card-title" style={{ margin: 0 }}>الأوراق المطلوبة من المقبول الأساسي</h2>
                    <div className="spacer" />
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setForm({ ...form, required_documents: DEFAULT_DOCUMENTS.map((d) => ({ title: d.title, notesText: d.notes.join('\n') })) })}>استخدام القالب الافتراضي</button>
                </div>
                <div className="stack">
                    {form.required_documents.map((d, i) => (
                        <div key={i} className="row" style={{ alignItems: 'flex-start', background: '#fff', border: '1px solid var(--line)', borderRadius: 6, padding: 10 }}>
                            <span className="num muted" style={{ width: 22, paddingTop: 10, textAlign: 'center' }}>{i + 1}</span>
                            <div style={{ flex: '2 1 220px' }}>
                                <input className="input" value={d.title} onChange={(e) => setDoc(i, 'title', e.target.value)} placeholder="اسم الوثيقة" aria-label={`الوثيقة ${i + 1}`} />
                            </div>
                            <div style={{ flex: '3 1 240px' }}>
                                <textarea className="textarea" style={{ minHeight: 42 }} rows={1} value={d.notesText} onChange={(e) => setDoc(i, 'notesText', e.target.value)} placeholder="ملاحظات (سطر لكل ملاحظة) — اختياري" aria-label={`ملاحظات الوثيقة ${i + 1}`} />
                            </div>
                            <div className="row" style={{ gap: 4 }}>
                                <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => moveDoc(i, -1)} disabled={i === 0} aria-label="تحريك للأعلى">↑</button>
                                <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => moveDoc(i, 1)} disabled={i === form.required_documents.length - 1} aria-label="تحريك للأسفل">↓</button>
                                <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => setForm({ ...form, required_documents: form.required_documents.filter((_, j) => j !== i) })} aria-label="حذف الوثيقة"><Icon name="trash" /></button>
                            </div>
                        </div>
                    ))}
                    <div>
                        <button type="button" className="btn btn-outline btn-sm" onClick={() => setForm({ ...form, required_documents: [...form.required_documents, { title: '', notesText: '' }] })}><Icon name="plus" /> إضافة وثيقة</button>
                    </div>
                    <Field label="ملاحظة للمقبولين الأساسيين" htmlFor="c-note" hint="تظهر في صفحة الأوراق المطلوبة ونافذة التهنئة.">
                        <textarea id="c-note" className="textarea" rows={2} value={form.primary_note || ''} onChange={set('primary_note')} maxLength={1000} />
                    </Field>
                </div>
            </section>

            <div className="row">
                <button type="submit" className="btn btn-primary" disabled={busy || !form.title.trim()}>{busy ? 'جارٍ الحفظ...' : submitLabel}</button>
                <div className="spacer" />
                {extraActions}
            </div>
        </form>
    );
}
