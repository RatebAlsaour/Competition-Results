import React, { useState } from 'react';
import { errorText } from '../../api';
import { Field } from '../../ui';

export function emptyExam() {
    return {
        title: '', slug: '', description: '', status: 'draft',
        instructions: 'يجب إحضار البطاقة الشخصية والحضور قبل موعد الامتحان بنصف ساعة.',
    };
}

/**
 * Create / edit exam form. onSubmit(payload) must return a promise.
 */
export default function ExamForm({ initial, onSubmit, submitLabel, extraActions }) {
    const [form, setForm] = useState({ ...initial });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true); setError('');
        try {
            await onSubmit({
                title: form.title,
                slug: form.slug || null,
                description: form.description || null,
                instructions: form.instructions || null,
                status: form.status,
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
                <h2 className="card-title">بيانات الامتحان</h2>
                <div className="form-grid">
                    <Field label="اسم الامتحان *" htmlFor="e-title" className="full" hint="مثال: مسابقة انتقاء التراجمة المحلفين 2026">
                        <input id="e-title" className="input" value={form.title} onChange={set('title')} required maxLength={255} />
                    </Field>
                    <Field label="الرابط المختصر" htmlFor="e-slug" hint="أحرف إنجليزية صغيرة وأرقام و - فقط. يُولَّد تلقائياً إن تُرك فارغاً.">
                        <input id="e-slug" className="input input-ltr" value={form.slug || ''} onChange={set('slug')} placeholder="translators-2026" maxLength={100} />
                    </Field>
                    <Field label="الحالة" htmlFor="e-status" hint="الامتحانات المنشورة فقط تظهر في البوابة العامة.">
                        <select id="e-status" className="select" value={form.status} onChange={set('status')}>
                            <option value="draft">مسودة</option>
                            <option value="published">منشور</option>
                            <option value="archived">مؤرشف</option>
                        </select>
                    </Field>
                    <Field label="وصف يظهر في البوابة" htmlFor="e-desc" className="full">
                        <textarea id="e-desc" className="textarea" rows={2} value={form.description || ''} onChange={set('description')} maxLength={2000} />
                    </Field>
                    <Field label="تعليمات للمتقدمين" htmlFor="e-inst" className="full" hint="تظهر للمتقدم مع رقمه الامتحاني وموعده (ما يجب إحضاره، وقت الحضور...). سطر لكل تعليمة.">
                        <textarea id="e-inst" className="textarea" rows={4} value={form.instructions || ''} onChange={set('instructions')} maxLength={5000} />
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
