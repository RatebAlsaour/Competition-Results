import React, { useCallback, useEffect, useState } from 'react';
import { api, errorText } from '../api';
import { Link, navigate } from '../router';
import { Icon, Spinner, StatusBadge, useToast } from '../ui';
import CompetitionForm from './CompetitionForm';
import OverviewTab from './OverviewTab';
import CandidatesTab from './CandidatesTab';
import ImportTab from './ImportTab';

// [المفتاح، الاسم، الاسم المختصر للهاتف، الأيقونة]
const TABS = [
    ['overview', 'نظرة عامة', 'عامة', 'chart'],
    ['candidates', 'متابعة الأسماء', 'الأسماء', 'users'],
    ['import', 'استيراد Excel', 'استيراد', 'upload'],
    ['settings', 'الإعدادات', 'إعدادات', 'settings'],
];

export default function CompetitionPage({ id, tab }) {
    const [competition, setCompetition] = useState(null);
    const [error, setError] = useState('');
    const toast = useToast();

    const reload = useCallback(() => {
        api.get(`/competitions/${id}`).then(setCompetition).catch((e) => setError(errorText(e)));
    }, [id]);

    useEffect(reload, [reload]);

    if (error) return <div className="alert alert-error">{error} — <Link to="/">العودة</Link></div>;
    if (!competition) return <Spinner />;

    const publicUrl = (document.querySelector('meta[name="portal-url"]')?.content || '/').replace(/\/$/, '') + '/?c=' + encodeURIComponent(competition.slug);

    const save = async (payload) => {
        const updated = await api.put(`/competitions/${id}`, payload);
        setCompetition({ ...competition, ...updated });
        toast('تم حفظ التعديلات');
    };

    const remove = async () => {
        if (!window.confirm(`حذف «${competition.title}» مع جميع أسمائها (${competition.candidates_count}) نهائياً؟`)) return;
        try {
            await api.del(`/competitions/${id}`);
            toast('تم حذف المسابقة');
            navigate('/');
        } catch (e) { toast(errorText(e), 'error'); }
    };

    const setStatus = async (status) => {
        try {
            await save({ status });
        } catch (e) { toast(errorText(e), 'error'); }
    };

    return (
        <>
            <nav className="crumbs" aria-label="مسار التنقل"><Link to="/">المسابقات</Link><span>/</span><span>{competition.title}</span></nav>
            <div className="page-head">
                <div className="stack" style={{ gap: 8 }}>
                    <div className="row">
                        <h1>{competition.title}</h1>
                        <StatusBadge status={competition.status} label={competition.status_label} />
                    </div>
                    <span className="small muted">{competition.candidates_count} اسم · الرابط: <a className="public-link" href={publicUrl} target="_blank" rel="noreferrer" dir="ltr">{publicUrl}</a></span>
                </div>
                <div className="row">
                    {competition.status !== 'published'
                        ? <button type="button" className="btn btn-primary" onClick={() => setStatus('published')} disabled={!competition.candidates_count} title={!competition.candidates_count ? 'استورد النتائج أولاً' : undefined}>نشر على البوابة</button>
                        : <button type="button" className="btn btn-outline" onClick={() => setStatus('draft')}>إلغاء النشر</button>}
                </div>
            </div>

            <nav className="tabs" aria-label="أقسام المسابقة">
                {TABS.map(([key, label, short, icon]) => (
                    <Link key={key} to={`/competitions/${id}${key === 'overview' ? '' : '/' + key}`} className={'tab' + (tab === key ? ' active' : '')} aria-current={tab === key ? 'page' : undefined} aria-label={label}>
                        <Icon name={icon} /> <span className="lbl-long">{label}</span><span className="lbl-short">{short}</span>
                    </Link>
                ))}
            </nav>

            {tab === 'overview' && <OverviewTab competition={competition} />}
            {tab === 'candidates' && <CandidatesTab competition={competition} onChanged={reload} />}
            {tab === 'import' && <ImportTab competition={competition} onImported={reload} />}
            {tab === 'settings' && (
                <CompetitionForm
                    key={competition.id + competition.status}
                    initial={competition}
                    onSubmit={save}
                    submitLabel="حفظ التعديلات"
                    extraActions={<button type="button" className="btn btn-danger" onClick={remove}><Icon name="trash" /> حذف المسابقة</button>}
                />
            )}
        </>
    );
}
