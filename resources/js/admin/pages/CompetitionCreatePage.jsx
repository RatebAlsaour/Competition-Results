import React from 'react';
import { api } from '../api';
import { Link, navigate } from '../router';
import { useToast } from '../ui';
import CompetitionForm, { emptyCompetition } from './CompetitionForm';

export default function CompetitionCreatePage() {
    const toast = useToast();

    const create = async (payload) => {
        const competition = await api.post('/competitions', payload);
        toast('تم إنشاء المسابقة. الخطوة التالية: استيراد النتائج.');
        navigate(`/competitions/${competition.id}/import`);
    };

    return (
        <>
            <nav className="crumbs" aria-label="مسار التنقل"><Link to="/">المسابقات</Link><span>/</span><span>مسابقة جديدة</span></nav>
            <div className="page-head"><h1>مسابقة جديدة</h1></div>
            <CompetitionForm initial={emptyCompetition()} onSubmit={create} submitLabel="إنشاء المسابقة" />
        </>
    );
}
