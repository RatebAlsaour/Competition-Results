import React, { useEffect, useState } from 'react';
import { api, errorText } from '../../api';
import { Link } from '../../router';
import { Field, Icon, Modal, Spinner, useToast } from '../../ui';

export default function ExamScheduleTab({ exam }) {
    const [sessions, setSessions] = useState(null);
    const [error, setError] = useState('');
    const [bulk, setBulk] = useState({ capacity: 35, prefix: 'القاعة' });
    const [busy, setBusy] = useState(false);
    const toast = useToast();

    const load = () => api.get(`/exams/${exam.id}/sessions`).then(setSessions).catch((e) => setError(errorText(e)));
    useEffect(() => { load(); }, [exam.id]);

    if (error) return <div className="alert alert-error">{error}</div>;
    if (!sessions) return <Spinner />;

    const active = sessions.filter((s) => s.examinees_count > 0);

    if (active.length === 0) {
        return (
            <div className="card card-pad" style={{ textAlign: 'center' }}>
                <p className="muted">تظهر اللغات هنا بعد استيراد ملف الأسماء.</p>
                <Link to={`/exams/${exam.id}/import`} className="btn btn-primary"><Icon name="upload" /> استيراد ملف Excel</Link>
            </div>
        );
    }

    const replace = (updated) => setSessions((list) => list.map((s) => (s.id === updated.id ? updated : s)));

    // توزيع كل اللغات دفعة واحدة بترقيم متسلسل: لا يتكرر اسم قاعة بين لغتين
    const distributeAll = async () => {
        if (!window.confirm(`توزيع قاعات كل اللغات (${bulk.capacity} في القاعة) بترقيم متسلسل؟ سيُستبدل أي توزيع حالي.`)) return;
        setBusy(true);
        try {
            let startAt = 1;
            for (const s of active) {
                const updated = await api.put(`/exam-sessions/${s.id}/halls`, { capacity: Number(bulk.capacity), prefix: bulk.prefix, start_at: startAt });
                startAt += updated.halls.length;
                replace(updated);
            }
            toast(`تم توزيع ${startAt - 1} قاعة على ${active.length} لغة`);
        } catch (e) { toast(errorText(e), 'error'); load(); }
        finally { setBusy(false); }
    };

    return (
        <div className="stack">
            <section className="card card-pad stack">
                <h2 className="card-title" style={{ margin: 0 }}>توزيع القاعات لكل اللغات</h2>
                <p className="small muted" style={{ margin: 0 }}>
                    يوزّع الأرقام الامتحانية لكل لغة على قاعات بالتتابع (مثال: 1–35 في القاعة 1، 36–70 في القاعة 2...)، وترقيم القاعات يكمل من لغة إلى أخرى فلا يتكرر اسم قاعة.
                    يمكنك بعدها تعديل أي لغة يدوياً من بطاقتها.
                </p>
                <div className="row" style={{ alignItems: 'flex-end' }}>
                    <Field label="عدد المتقدمين في القاعة" htmlFor="b-cap">
                        <input id="b-cap" className="input" type="number" min="1" style={{ width: 140 }} value={bulk.capacity} onChange={(e) => setBulk({ ...bulk, capacity: e.target.value })} />
                    </Field>
                    <Field label="تسمية القاعات" htmlFor="b-prefix">
                        <input id="b-prefix" className="input" style={{ width: 160 }} value={bulk.prefix} onChange={(e) => setBulk({ ...bulk, prefix: e.target.value })} />
                    </Field>
                    <button type="button" className="btn btn-primary" onClick={distributeAll} disabled={busy || !bulk.capacity}>{busy ? 'جارٍ التوزيع...' : 'توزيع الكل'}</button>
                </div>
            </section>

            <div className="grid grid-2">
                {active.map((s) => (
                    <SessionCard key={[s.id, s.exam_date, s.start_time, s.end_time, s.location, s.notes].join('|')} session={s} othersCount={active.length - 1} onSaved={replace} onCopied={setSessions} />
                ))}
            </div>
        </div>
    );
}

function SessionCard({ session, othersCount, onSaved, onCopied }) {
    const toast = useToast();
    const [form, setForm] = useState({
        exam_date: session.exam_date || '', start_time: session.start_time || '', end_time: session.end_time || '',
        location: session.location || '', notes: session.notes || '',
    });
    const [busy, setBusy] = useState(false);
    const [editHalls, setEditHalls] = useState(false);

    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });
    const dirty = ['exam_date', 'start_time', 'end_time', 'location', 'notes'].some((k) => (session[k] || '') !== form[k]);
    const complete = session.exam_date && session.start_time && session.location;

    const payload = () => ({
        exam_date: form.exam_date || null, start_time: form.start_time || null, end_time: form.end_time || null,
        location: form.location || null, notes: form.notes || null,
    });

    const save = async () => {
        setBusy(true);
        try {
            onSaved(await api.put(`/exam-sessions/${session.id}`, payload()));
            toast(`تم حفظ موعد ${session.name}`);
        } catch (e) { toast(errorText(e), 'error'); }
        finally { setBusy(false); }
    };

    const copyToAll = async () => {
        if (!window.confirm(`نسخ التاريخ والوقت والمكان من «${session.name}» إلى ${othersCount} لغة أخرى؟`)) return;
        setBusy(true);
        try {
            if (dirty) await api.put(`/exam-sessions/${session.id}`, payload());
            onCopied(await api.post(`/exam-sessions/${session.id}/copy-to-all`));
            toast('تم نسخ الموعد لكل اللغات');
        } catch (e) { toast(errorText(e), 'error'); }
        finally { setBusy(false); }
    };

    return (
        <section className={'session-card' + (complete ? '' : ' incomplete')}>
            <div className="row" style={{ justifyContent: 'space-between' }}>
                <h3 style={{ fontSize: 18 }}>{session.name}</h3>
                <span className="small muted">{session.examinees_count} متقدم · الأرقام 1–{session.max_exam_number}</span>
            </div>

            <div className="form-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 130px), 1fr))', gap: 10 }}>
                <Field label="التاريخ" htmlFor={`d-${session.id}`}>
                    <input id={`d-${session.id}`} className="input" type="date" value={form.exam_date} onChange={set('exam_date')} />
                </Field>
                <Field label="من الساعة" htmlFor={`s-${session.id}`}>
                    <input id={`s-${session.id}`} className="input" type="time" value={form.start_time} onChange={set('start_time')} />
                </Field>
                <Field label="إلى الساعة" htmlFor={`e-${session.id}`}>
                    <input id={`e-${session.id}`} className="input" type="time" value={form.end_time} onChange={set('end_time')} />
                </Field>
                <Field label="مكان الامتحان" htmlFor={`l-${session.id}`} className="full">
                    <input id={`l-${session.id}`} className="input" value={form.location} onChange={set('location')} placeholder="مثال: كلية الآداب — جامعة دمشق، المزة" />
                </Field>
                <Field label="ملاحظة تظهر للمتقدم (اختياري)" htmlFor={`n-${session.id}`} className="full">
                    <input id={`n-${session.id}`} className="input" value={form.notes} onChange={set('notes')} />
                </Field>
            </div>

            <div className="row">
                <button type="button" className="btn btn-primary btn-sm" onClick={save} disabled={busy || !dirty}>حفظ الموعد</button>
                {othersCount > 0 && <button type="button" className="btn btn-ghost btn-sm" onClick={copyToAll} disabled={busy || !(form.exam_date && form.location)}>نسخ لكل اللغات</button>}
                {!complete && <span className="small" style={{ color: '#6E5E3E' }}>الموعد غير مكتمل</span>}
            </div>

            <div style={{ borderTop: '1px dashed var(--line)', paddingTop: 12 }}>
                <div className="row" style={{ justifyContent: 'space-between', marginBottom: 8 }}>
                    <strong className="small">القاعات ({session.halls.length})</strong>
                    <button type="button" className="btn btn-outline btn-sm" onClick={() => setEditHalls(true)}><Icon name="edit" /> تعديل القاعات</button>
                </div>
                {session.halls.length === 0
                    ? <span className="small muted">لم تُوزَّع بعد — المتقدم لن يرى اسم القاعة.</span>
                    : <div className="row" style={{ gap: 6 }}>
                        {session.halls.slice(0, 12).map((h) => <span key={h.id} className="hall-chip" title={h.building || undefined}><strong>{h.name}</strong><span className="num muted">{h.from_number}–{h.to_number}</span></span>)}
                        {session.halls.length > 12 && <span className="small muted">و {session.halls.length - 12} أخرى</span>}
                    </div>}
            </div>

            {editHalls && <HallsModal session={session} onClose={() => setEditHalls(false)} onSaved={(s) => { onSaved(s); setEditHalls(false); }} />}
        </section>
    );
}

function HallsModal({ session, onClose, onSaved }) {
    const toast = useToast();
    const [halls, setHalls] = useState(session.halls.map((h) => ({ name: h.name, building: h.building || '', from_number: h.from_number, to_number: h.to_number })));
    const [auto, setAuto] = useState({ capacity: 35, prefix: 'القاعة', start_at: 1 });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const setHall = (i, k, v) => setHalls(halls.map((h, j) => (j === i ? { ...h, [k]: v } : h)));
    const addHall = () => {
        const last = halls[halls.length - 1];
        const from = last ? Number(last.to_number) + 1 : 1;
        setHalls([...halls, { name: '', building: last?.building || '', from_number: from, to_number: Math.max(from, session.max_exam_number) }]);
    };

    const run = async (body, message) => {
        setBusy(true); setError('');
        try {
            const updated = await api.put(`/exam-sessions/${session.id}/halls`, body);
            toast(message);
            onSaved(updated);
        } catch (e) { setError(errorText(e)); setBusy(false); }
    };

    return (
        <Modal
            title={`قاعات ${session.name}`}
            onClose={onClose}
            footer={<>
                <button type="button" className="btn btn-primary" disabled={busy || halls.length === 0 || halls.some((h) => !h.name.trim())} onClick={() => run({ halls: halls.map((h) => ({ name: h.name, building: h.building || null, from_number: Number(h.from_number), to_number: Number(h.to_number) })) }, 'تم حفظ القاعات')}>حفظ القائمة</button>
                <button type="button" className="btn btn-outline" onClick={onClose}>إلغاء</button>
            </>}
        >
            <div className="stack">
                {error && <div className="alert alert-error" role="alert">{error}</div>}
                <div className="alert alert-info small">الأرقام الامتحانية في هذه اللغة من 1 إلى <strong>{session.max_exam_number}</strong> ({session.examinees_count} متقدم).</div>

                <div className="row" style={{ alignItems: 'flex-end' }}>
                    <Field label="في القاعة" htmlFor="a-cap"><input id="a-cap" className="input" type="number" min="1" style={{ width: 90 }} value={auto.capacity} onChange={(e) => setAuto({ ...auto, capacity: e.target.value })} /></Field>
                    <Field label="التسمية" htmlFor="a-pre"><input id="a-pre" className="input" style={{ width: 120 }} value={auto.prefix} onChange={(e) => setAuto({ ...auto, prefix: e.target.value })} /></Field>
                    <Field label="يبدأ من رقم" htmlFor="a-start"><input id="a-start" className="input" type="number" min="1" style={{ width: 90 }} value={auto.start_at} onChange={(e) => setAuto({ ...auto, start_at: e.target.value })} /></Field>
                    <button type="button" className="btn btn-outline" disabled={busy || !auto.capacity} onClick={() => run({ capacity: Number(auto.capacity), prefix: auto.prefix, start_at: Number(auto.start_at) || 1 }, 'تم التوزيع التلقائي')}>توزيع تلقائي</button>
                </div>

                <div style={{ borderTop: '1px dashed var(--line)', paddingTop: 12 }} className="stack">
                    <strong className="small">أو عدّل يدوياً (اسم القاعة · البناء · من رقم · إلى رقم)</strong>
                    <span className="small muted">البناء اختياري، ويظهر للمتقدم بسطر تحت القاعة.</span>
                    {halls.map((h, i) => (
                        <div key={i} className="hall-row">
                            <input className="input" value={h.name} onChange={(e) => setHall(i, 'name', e.target.value)} placeholder="اسم القاعة" aria-label={`اسم القاعة ${i + 1}`} />
                            <input className="input" value={h.building} onChange={(e) => setHall(i, 'building', e.target.value)} placeholder="البناء (اختياري)" aria-label={`بناء القاعة ${i + 1}`} />
                            <input className="input" type="number" min="1" value={h.from_number} onChange={(e) => setHall(i, 'from_number', e.target.value)} aria-label="من رقم" />
                            <input className="input" type="number" min="1" value={h.to_number} onChange={(e) => setHall(i, 'to_number', e.target.value)} aria-label="إلى رقم" />
                            <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={() => setHalls(halls.filter((_, j) => j !== i))} aria-label="حذف القاعة"><Icon name="trash" /></button>
                        </div>
                    ))}
                    <div><button type="button" className="btn btn-outline btn-sm" onClick={addHall}><Icon name="plus" /> إضافة قاعة</button></div>
                </div>
            </div>
        </Modal>
    );
}
