import React, { useEffect, useState } from 'react';
import { api, errorText } from '../../api';
import { Link } from '../../router';
import { Icon, Pager, Spinner, StatusBadge, formatDate } from '../../ui';

export default function ExamsPage() {
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let alive = true;
        api.get('/exams', { page, max: 24 })
            .then((d) => alive && setData(d))
            .catch((e) => alive && setError(errorText(e)));
        return () => { alive = false; };
    }, [page]);

    return (
        <>
            <div className="page-head">
                <div>
                    <h1>الامتحانات</h1>
                    <p className="muted small" style={{ margin: '6px 0 0' }}>
                        للمسابقات التي فيها امتحان (مثل التراجمة المحلفين): الأرقام الامتحانية، موعد ومكان الامتحان، ثم النتائج.
                    </p>
                </div>
                <Link to="/exams/new" className="btn btn-primary"><Icon name="plus" /> امتحان جديد</Link>
            </div>

            {error && <div className="alert alert-error">{error}</div>}
            {!data && !error && <Spinner />}

            {data && data.data.length === 0 && (
                <div className="card card-pad" style={{ textAlign: 'center' }}>
                    <p className="muted">لا توجد امتحانات بعد.</p>
                    <Link to="/exams/new" className="btn btn-primary"><Icon name="plus" /> أنشئ أول امتحان</Link>
                </div>
            )}

            {data && data.data.length > 0 && (
                <>
                    <div className="grid grid-cards">
                        {data.data.map((e) => (
                            <Link key={e.id} to={`/exams/${e.id}`} className="card comp-card">
                                <div className="row" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                    <h3>{e.title}</h3>
                                    <StatusBadge status={e.status} label={e.status_label} />
                                </div>
                                <div className="counts">
                                    <span><strong className="num">{e.examinees_count}</strong> متقدم</span>
                                    <span><strong className="num">{e.sessions_count}</strong> لغة</span>
                                </div>
                                <div className="row small">
                                    <StatusBadge status={e.results_published ? 'on' : 'off'} label={e.results_published ? 'النتائج منشورة' : 'النتائج غير منشورة'} />
                                    <span className="muted">{e.published_at ? 'نُشر ' + formatDate(e.published_at) : 'أُنشئ ' + formatDate(e.created_at)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                    <div style={{ marginTop: 16 }}><Pager meta={data} onPage={setPage} /></div>
                </>
            )}
        </>
    );
}
