import React, { useEffect, useState } from 'react';
import { api, errorText } from '../api';
import { Link } from '../router';
import { Icon, Spinner } from '../ui';

const FOLLOW_UP = [
    ['pending', 'بانتظار المراجعة', 'grey'],
    ['documents_submitted', 'قدّم الأوراق', 'blue'],
    ['contracted', 'تم التعاقد', ''],
    ['withdrawn', 'اعتذر', 'red'],
    ['no_show', 'لم يراجع', 'red'],
];

export default function OverviewTab({ competition }) {
    const [stats, setStats] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get(`/competitions/${competition.id}/stats`).then(setStats).catch((e) => setError(errorText(e)));
    }, [competition.id, competition.candidates_count]);

    if (error) return <div className="alert alert-error">{error}</div>;
    if (!stats) return <Spinner />;

    if (!stats.total) {
        return (
            <div className="card card-pad" style={{ textAlign: 'center' }}>
                <p className="muted">لا توجد أسماء في هذه المسابقة بعد.</p>
                <Link to={`/competitions/${competition.id}/import`} className="btn btn-primary"><Icon name="upload" /> استيراد ملف Excel</Link>
            </div>
        );
    }

    const primaryTotal = stats.primary || 1;

    return (
        <div className="stack">
            <div className="grid grid-stats">
                <div className="stat dark"><span className="stat-label">إجمالي الأسماء</span><span className="stat-value">{stats.total}</span></div>
                <div className="stat primary"><span className="stat-label">مقبول أساسي</span><span className="stat-value">{stats.primary}</span></div>
                <div className="stat reserve"><span className="stat-label">مقبول احتياطي</span><span className="stat-value">{stats.reserve}</span></div>
                <div className="stat"><span className="stat-label">المحافظات / المسميات</span><span className="stat-value">{stats.by_governorate.length} / {stats.job_titles}</span></div>
                <div className="stat primary"><span className="stat-label">تم التعاقد</span><span className="stat-value">{stats.follow_up.contracted || 0}</span></div>
            </div>

            <div className="grid grid-2">
                <section className="card card-pad">
                    <h2 className="card-title">متابعة المقبولين الأساسيين</h2>
                    <div className="stack" style={{ gap: 12 }}>
                        {FOLLOW_UP.map(([key, label, color]) => {
                            const n = stats.primary_follow_up[key] || 0;
                            return (
                                <Link key={key} to={`/competitions/${competition.id}/candidates?status=primary&follow_up_status=${key}`} style={{ textDecoration: 'none', color: 'inherit' }}>
                                    <div className="row small" style={{ justifyContent: 'space-between', marginBottom: 4 }}>
                                        <span>{label}</span>
                                        <span className="num muted">{n} من {stats.primary} ({Math.round((n / primaryTotal) * 100)}%)</span>
                                    </div>
                                    <div className={'bar ' + color}><span style={{ width: (n / primaryTotal) * 100 + '%' }} /></div>
                                </Link>
                            );
                        })}
                    </div>
                    <p className="small muted" style={{ marginBottom: 0 }}>
                        إذا اعتذر أساسي أو لم يراجع، استخدم «متابعة الأسماء» لتصفية الاحتياطيين في نفس المحافظة والمسمى حسب الترتيب.
                    </p>
                </section>

                <section className="card card-pad">
                    <h2 className="card-title">حسب المحافظة</h2>
                    <div className="table-wrap">
                        <table className="table">
                            <thead>
                                <tr><th>المحافظة</th><th className="hide-sm">المسميات</th><th>أساسي</th><th>احتياطي</th><th>تعاقد</th><th>المجموع</th></tr>
                            </thead>
                            <tbody>
                                {stats.by_governorate.map((g) => (
                                    <tr key={g.governorate}>
                                        <td className="name"><Link to={`/competitions/${competition.id}/candidates?governorate=${encodeURIComponent(g.governorate)}`}>{g.governorate}</Link></td>
                                        <td className="num hide-sm">{g.job_titles}</td>
                                        <td className="num">{g.primary}</td>
                                        <td className="num">{g.reserve}</td>
                                        <td className="num">{g.contracted}</td>
                                        <td className="num"><strong>{g.total}</strong></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    );
}
