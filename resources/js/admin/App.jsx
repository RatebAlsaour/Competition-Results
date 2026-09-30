import React, { useEffect, useState } from 'react';
import { api, setUnauthorizedHandler } from './api';
import { Link, match, navigate, usePath } from './router';
import { Icon, Spinner } from './ui';
import LoginPage from './pages/LoginPage';
import CompetitionsPage from './pages/CompetitionsPage';
import CompetitionCreatePage from './pages/CompetitionCreatePage';
import CompetitionPage from './pages/CompetitionPage';

export default function App() {
    const [user, setUser] = useState(undefined); // undefined = جارٍ التحقق، null = غير مسجل
    const path = usePath();

    useEffect(() => {
        setUnauthorizedHandler(() => setUser(null));
        api.get('/me').then(setUser).catch(() => setUser(null));
    }, []);

    if (user === undefined) return <Spinner />;
    if (!user) return <LoginPage onLogin={setUser} />;

    const logout = async () => {
        try { await api.post('/logout'); } catch { /* ignore */ }
        setUser(null);
        navigate('/', { replace: true });
    };

    return (
        <>
            <header className="topbar">
                <div className="topbar-inner">
                    <Link to="/" className="brand">
                        <img src={document.querySelector('link[rel=icon]')?.href} alt="" />
                        <span className="brand-sep" aria-hidden="true" />
                        <span>
                            <span className="brand-title">لوحة التحكم</span><br />
                            <span className="brand-sub">نتائج المسابقات — وزارة العدل</span>
                        </span>
                    </Link>
                    <div className="topbar-actions">
                        <span className="topbar-user">{user.name}</span>
                        <a className="btn btn-gold btn-sm" href={document.querySelector('meta[name="portal-url"]')?.content || '/'} target="_blank" rel="noreferrer" aria-label="البوابة العامة" title="البوابة العامة">
                            <Icon name="external" /><span className="btn-text"> البوابة العامة</span>
                        </a>
                        <button type="button" className="btn btn-gold btn-sm" onClick={logout} aria-label="خروج" title="خروج"><Icon name="logout" /><span className="btn-text"> خروج</span></button>
                    </div>
                </div>
            </header>
            <main className="page">
                <Routes path={path} />
            </main>
        </>
    );
}

function Routes({ path }) {
    if (path === '/') return <CompetitionsPage />;
    if (path === '/competitions/new') return <CompetitionCreatePage />;
    const m = match('/competitions/:id/:tab?', path);
    if (m) return <CompetitionPage key={m.id} id={m.id} tab={m.tab || 'overview'} />;
    return (
        <div className="card card-pad">
            <p>الصفحة غير موجودة.</p>
            <Link to="/">العودة إلى المسابقات</Link>
        </div>
    );
}
