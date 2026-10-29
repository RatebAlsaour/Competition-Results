import React, { useEffect, useState } from 'react';
import { api, errorText } from '../../api';
import { Link } from '../../router';
import { Icon, Spinner, formatDate } from '../../ui';

export default function ExamOverviewTab({ exam }) {
    const [stats, setStats] = useState(null);
    const [sessions, setSessions] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        Promise.all([api.get(`/exams/${exam.id}/stats`), api.get(`/exams/${exam.id}/sessions`)])
            .then(([s, list]) => { setStats(s); setSessions(list); })
            .catch((e) => setError(errorText(e)));
    }, [exam.id, exam.examinees_count, exam.results_published]);

    if (error) return <div className="alert alert-error">{error}</div>;
    if (!stats || !sessions) return <Spinner />;

    const base = `/exams/${exam.id}`;
    const withNames = sessions.filter((s) => s.examinees_count > 0);
    const scheduled = withNames.filter((s) => s.exam_date && s.start_time && s.location);
    const withHalls = withNames.filter((s) => s.halls.length > 0);
    const graded = stats.total - stats.pending;

    // خطوات العمل بالترتيب، مع ما تم وما بقي
    const steps = [
        { done: stats.total > 0, label: 'استيراد الأسماء والأرقام الامتحانية', detail: stats.total ? `${stats.total} متقدم في ${withNames.length} لغة` : 'لم يُستورد بعد', to: `${base}/import` },
        { done: withNames.length > 0 && scheduled.length === withNames.length, label: 'تحديد التاريخ والوقت والمكان لكل لغة', detail: `${scheduled.length} من ${withNames.length} لغة`, to: `${base}/schedule` },
        { done: withNames.length > 0 && withHalls.length === withNames.length, label: 'توزيع القاعات', detail: `${withHalls.length} من ${withNames.length} لغة`, to: `${base}/schedule` },
        { done: exam.status === 'published', label: 'نشر الأرقام والمواعيد للمتقدمين', detail: exam.status === 'published' ? 'منشور' : 'غير منشور — الزر أعلى الصفحة' },
        { done: stats.total > 0 && stats.pending === 0, label: 'إدخال النتائج (بعد الامتحان)', detail: `${graded} من ${stats.total}`, to: `${base}/import` },
        { done: exam.results_published, label: 'نشر النتائج', detail: exam.results_published ? 'منشورة' : 'غير منشورة — الزر أعلى الصفحة' },
    ];

    return (
        <div className="stack">
            <div className="grid grid-stats">
                <div className="stat dark"><span className="stat-label">المتقدمون</span><span className="stat-value">{stats.total}</span></div>
                <div className="stat"><span className="stat-label">اللغات</span><span className="stat-value">{withNames.length}</span></div>
                <div className="stat primary"><span className="stat-label">ناجح</span><span className="stat-value">{stats.passed}</span></div>
                <div className="stat reserve"><span className="stat-label">راسب</span><span className="stat-value">{stats.failed}</span></div>
                <div className="stat"><span className="stat-label">غائب</span><span className="stat-value">{stats.absent}</span></div>
            </div>

            <div className="grid grid-2">
                <section className="card card-pad">
                    <h2 className="card-title">خطوات العمل</h2>
                    <ol className="stack" style={{ listStyle: 'none', margin: 0, padding: 0, gap: 10 }}>
                        {steps.map((step, i) => (
                            <li key={i} className="row" style={{ alignItems: 'flex-start', flexWrap: 'nowrap' }}>
                                <span aria-hidden="true" style={{ flexShrink: 0, width: 26, height: 26, borderRadius: '50%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 700, background: step.done ? 'var(--ink-2)' : '#fff', color: step.done ? 'var(--sand)' : 'var(--muted)', border: '1px solid ' + (step.done ? 'var(--ink-2)' : 'var(--line)') }}>{step.done ? '✓' : i + 1}</span>
                                <span style={{ flex: 1, minWidth: 0 }}>
                                    <span style={{ fontWeight: 600 }}>{step.to ? <Link to={step.to}>{step.label}</Link> : step.label}</span><br />
                                    <span className="small muted">{step.detail}</span>
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>

                <section className="card card-pad">
                    <h2 className="card-title">حسب اللغة</h2>
                    {withNames.length === 0 ? (
                        <div style={{ textAlign: 'center' }}>
                            <p className="muted">لا يوجد متقدمون بعد.</p>
                            <Link to={`${base}/import`} className="btn btn-primary"><Icon name="upload" /> استيراد ملف Excel</Link>
                        </div>
                    ) : (
                        <div className="table-wrap">
                            <table className="table">
                                <thead><tr><th>اللغة</th><th>العدد</th><th className="hide-sm">الموعد</th><th>القاعات</th><th className="hide-sm">نتائج</th></tr></thead>
                                <tbody>
                                    {withNames.map((s) => {
                                        const st = stats.by_session[s.id] || {};
                                        return (
                                            <tr key={s.id}>
                                                <td className="name"><Link to={`${base}/examinees?exam_session_id=${s.id}`}>{s.name}</Link></td>
                                                <td className="num"><strong>{s.examinees_count}</strong></td>
                                                <td className="hide-sm small">{s.exam_date ? `${formatDate(s.exam_date)}${s.start_time ? ' · ' + s.start_time : ''}` : <span className="muted">غير محدد</span>}</td>
                                                <td className="num">{s.halls.length || <span className="muted">—</span>}</td>
                                                <td className="num hide-sm">{(st.total || 0) - (st.pending || 0)} / {st.total || 0}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
}
