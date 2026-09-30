import React, { useState } from 'react';
import { api, errorText } from '../api';
import { Field } from '../ui';

export default function LoginPage({ onLogin }) {
    const [form, setForm] = useState({ email: '', password: '', remember: true });
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true); setError('');
        try {
            onLogin(await api.post('/login', form));
        } catch (err) {
            setError(err.status === 429 ? 'محاولات كثيرة، انتظر دقيقة ثم حاول مجدداً' : errorText(err));
        } finally {
            setBusy(false);
        }
    };

    const set = (k) => (e) => setForm({ ...form, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

    return (
        <div className="login-wrap">
            <div className="login-card">
                <div className="login-logo">
                    <img src={document.querySelector('link[rel=icon]')?.href} alt="شعار الجمهورية العربية السورية" />
                    <h1 style={{ fontSize: 26 }}>لوحة تحكم نتائج المسابقات</h1>
                    <span style={{ color: 'var(--gold)', fontSize: 14 }}>وزارة العدل — مديرية التنمية الإدارية</span>
                </div>
                <form className="card card-pad stack" onSubmit={submit} noValidate>
                    {error && <div className="alert alert-error" role="alert">{error}</div>}
                    <Field label="البريد الإلكتروني" htmlFor="email">
                        <input id="email" className="input input-ltr" type="email" autoComplete="username" value={form.email} onChange={set('email')} required />
                    </Field>
                    <Field label="كلمة المرور" htmlFor="password">
                        <input id="password" className="input input-ltr" type="password" autoComplete="current-password" value={form.password} onChange={set('password')} required />
                    </Field>
                    <label className="row small" style={{ cursor: 'pointer' }}>
                        <input type="checkbox" checked={form.remember} onChange={set('remember')} style={{ accentColor: 'var(--ink)' }} /> تذكرني
                    </label>
                    <button type="submit" className="btn btn-primary" disabled={busy || !form.email || !form.password}>
                        {busy ? 'جارٍ الدخول...' : 'تسجيل الدخول'}
                    </button>
                </form>
            </div>
        </div>
    );
}
