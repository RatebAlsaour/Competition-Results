import React, { useEffect, useState } from 'react';
import { api, errorText } from '../api';
import { Link } from '../router';
import { Icon, Pager, Spinner, StatusBadge, formatDate, useDebounced } from '../ui';

const STATUS_TABS = [['', 'الكل'], ['published', 'منشورة'], ['draft', 'مسودة'], ['archived', 'مؤرشفة']];

export default function CompetitionsPage() {
    const [status, setStatus] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const q = useDebounced(search);

    useEffect(() => { setPage(1); }, [status, q]);

    useEffect(() => {
        let alive = true;
        setError('');
        api.get('/competitions', { page, max: 24, 'search-key': q, filters: { competition: { status } } })
            .then((d) => alive && setData(d))
            .catch((e) => alive && setError(errorText(e)));
        return () => { alive = false; };
    }, [status, q, page]);

    return (
        <>
            <div className="page-head">
                <div>
                    <h1>المسابقات</h1>
                    <p className="muted small" style={{ margin: '6px 0 0' }}>أنشئ مسابقة، استورد نتائجها من ملف Excel، ثم انشرها على البوابة.</p>
                </div>
                <Link to="/competitions/new" className="btn btn-primary"><Icon name="plus" /> مسابقة جديدة</Link>
            </div>

            <div className="row" style={{ marginBottom: 16 }}>
                <div className="tabs" style={{ marginBottom: 0, borderBottom: 0 }}>
                    {STATUS_TABS.map(([v, label]) => (
                        <button key={v} type="button" className={'tab' + (status === v ? ' active' : '')} onClick={() => setStatus(v)}>{label}</button>
                    ))}
                </div>
                <div className="spacer" />
                <div className="search-box" style={{ maxWidth: 320 }}>
                    <Icon name="search" color="var(--muted-2)" />
                    <input className="input" type="search" placeholder="ابحث عن مسابقة..." value={search} onChange={(e) => setSearch(e.target.value)} aria-label="ابحث عن مسابقة" />
                </div>
            </div>

            {error && <div className="alert alert-error">{error}</div>}
            {!data && !error && <Spinner />}

            {data && data.data.length === 0 && (
                <div className="card card-pad" style={{ textAlign: 'center' }}>
                    <p className="muted">لا توجد مسابقات{status || q ? ' مطابقة' : ' بعد'}.</p>
                    {!status && !q && <Link to="/competitions/new" className="btn btn-primary"><Icon name="plus" /> أنشئ أول مسابقة</Link>}
                </div>
            )}

            {data && data.data.length > 0 && (
                <>
                    <div className="grid grid-cards">
                        {data.data.map((c) => (
                            <Link key={c.id} to={`/competitions/${c.id}`} className="card comp-card">
                                <div className="row" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                    <h3>{c.title}</h3>
                                    <StatusBadge status={c.status} label={c.status_label} />
                                </div>
                                <div className="counts">
                                    <span><strong className="num">{c.candidates_count}</strong> مرشح</span>
                                    <span><strong className="num">{c.primary_count}</strong> أساسي</span>
                                    <span><strong className="num">{c.reserve_count}</strong> احتياطي</span>
                                </div>
                                <div className="small muted">
                                    {c.published_at ? 'نُشرت ' + formatDate(c.published_at) : 'أُنشئت ' + formatDate(c.created_at)}
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
