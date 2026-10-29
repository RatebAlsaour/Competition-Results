import React from 'react';
import { api } from '../../api';
import { Link, navigate } from '../../router';
import { useToast } from '../../ui';
import ExamForm, { emptyExam } from './ExamForm';

export default function ExamCreatePage() {
    const toast = useToast();

    const create = async (payload) => {
        const exam = await api.post('/exams', payload);
        toast('تم إنشاء الامتحان. الخطوة التالية: استيراد ملف الأسماء والأرقام الامتحانية.');
        navigate(`/exams/${exam.id}/import`);
    };

    return (
        <>
            <nav className="crumbs" aria-label="مسار التنقل"><Link to="/exams">الامتحانات</Link><span>/</span><span>امتحان جديد</span></nav>
            <div className="page-head"><h1>امتحان جديد</h1></div>
            <ExamForm initial={emptyExam()} onSubmit={create} submitLabel="إنشاء الامتحان" />
        </>
    );
}
