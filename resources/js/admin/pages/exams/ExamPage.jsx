import React, { useCallback, useEffect, useState } from 'react';
import { api, errorText } from '../../api';
import { Link, navigate } from '../../router';
import { Icon, Spinner, StatusBadge, useToast } from '../../ui';
import ExamForm from './ExamForm';
import ExamOverviewTab from './ExamOverviewTab';
import ExamScheduleTab from './ExamScheduleTab';
import ExamineesTab from './ExamineesTab';
import ExamImportTab from './ExamImportTab';

// [المفتاح، الاسم، الاسم المختصر للهاتف، الأيقونة]
const TABS = [
    ['overview', 'نظرة عامة', 'عامة', 'chart'],
    ['schedule', 'المواعيد والقاعات', 'المواعيد', 'settings'],
    ['examinees', 'المتقدمون والنتائج', 'المتقدمون', 'users'],
    ['import', 'استيراد Excel', 'استيراد', 'upload'],
    ['settings', 'الإعدادات', 'إعدادات', 'edit'],
];

export default function ExamPage({ id, tab }) {
    const [exam, setExam] = useState(null);
    const [error, setError] = useState('');
    const toast = useToast();

    const reload = useCallback(() => {
        api.get(`/exams/${id}`).then(setExam).catch((e) => setError(errorText(e)));
    }, [id]);

    useEffect(reload, [reload]);

    if (error) return <div className="alert alert-error">{error} — <Link to="/exams">العودة</Link></div>;
    if (!exam) return <Spinner />;

    const publicUrl = (document.querySelector('meta[name="exams-url"]')?.content || '') + '?exam=' + encodeURIComponent(exam.slug);

    const save = async (payload) => {
        const updated = await api.put(`/exams/${id}`, payload);
        setExam(updated);
        toast('تم الحفظ');
    };

    const quickSave = async (payload, confirmText) => {
        if (confirmText && !window.confirm(confirmText)) return;
        try { await save(payload); } catch (e) { toast(errorText(e), 'error'); }
    };

    const remove = async () => {
        if (!window.confirm(`حذف «${exam.title}» مع جميع المتقدمين (${exam.examinees_count}) والمواعيد نهائياً؟`)) return;
        try {
            await api.del(`/exams/${id}`);
            toast('تم حذف الامتحان');
            navigate('/exams');
        } catch (e) { toast(errorText(e), 'error'); }
    };

    return (
        <>
            <nav className="crumbs" aria-label="مسار التنقل"><Link to="/exams">الامتحانات</Link><span>/</span><span>{exam.title}</span></nav>
            <div className="page-head">
                <div className="stack" style={{ gap: 8 }}>
                    <div className="row">
                        <h1>{exam.title}</h1>
                        <StatusBadge status={exam.status} label={exam.status_label} />
                        <StatusBadge status={exam.results_published ? 'on' : 'off'} label={exam.results_published ? 'النتائج منشورة' : 'النتائج غير منشورة'} />
                    </div>
                    <span className="small muted">{exam.examinees_count} متقدم · {exam.sessions_count} لغة · الرابط: <a className="public-link" href={publicUrl} target="_blank" rel="noreferrer" dir="ltr">{publicUrl}</a></span>
                </div>
                <div className="row">
                    {exam.status !== 'published'
                        ? <button type="button" className="btn btn-primary" onClick={() => quickSave({ status: 'published' })} disabled={!exam.examinees_count} title={!exam.examinees_count ? 'استورد الأسماء أولاً' : undefined}>نشر الأرقام والمواعيد</button>
                        : <button type="button" className="btn btn-outline" onClick={() => quickSave({ status: 'draft' })}>إلغاء النشر</button>}
                    {exam.status === 'published' && (exam.results_published
                        ? <button type="button" className="btn btn-outline" onClick={() => quickSave({ results_published: false })}>إخفاء النتائج</button>
                        : <button type="button" className="btn btn-primary" onClick={() => quickSave({ results_published: true }, 'نشر النتائج للعموم الآن؟ سيتمكن كل متقدم من رؤية نتيجته.')}>نشر النتائج</button>)}
                </div>
            </div>

            <nav className="tabs" aria-label="أقسام الامتحان">
                {TABS.map(([key, label, short, icon]) => (
                    <Link key={key} to={`/exams/${id}${key === 'overview' ? '' : '/' + key}`} className={'tab' + (tab === key ? ' active' : '')} aria-current={tab === key ? 'page' : undefined} aria-label={label}>
                        <Icon name={icon} /> <span className="lbl-long">{label}</span><span className="lbl-short">{short}</span>
                    </Link>
                ))}
            </nav>

            {tab === 'overview' && <ExamOverviewTab exam={exam} />}
            {tab === 'schedule' && <ExamScheduleTab exam={exam} />}
            {tab === 'examinees' && <ExamineesTab exam={exam} onChanged={reload} />}
            {tab === 'import' && <ExamImportTab exam={exam} onImported={reload} />}
            {tab === 'settings' && (
                <ExamForm
                    key={exam.id + exam.status}
                    initial={exam}
                    onSubmit={save}
                    submitLabel="حفظ التعديلات"
                    extraActions={<button type="button" className="btn btn-danger" onClick={remove}><Icon name="trash" /> حذف الامتحان</button>}
                />
            )}
        </>
    );
}
