import React from 'react';
import sx from './sx';
import { ResultsAPI, normalize, NAME_SEARCH_MIN } from './api';
import { PatternSides, Crumbs, StatusBadge, DocsNote } from './parts';

const PAGE_SIZE = 20;
const RING = '0 0 0 3px rgba(185,167,121,.32)';
const SPLASH_KEY = 'results-portal:splash-seen';

function splashSeen() {
    try { return sessionStorage.getItem(SPLASH_KEY) === '1'; } catch { return false; }
}
function markSplashSeen() {
    try { sessionStorage.setItem(SPLASH_KEY, '1'); } catch { /* ignore */ }
}

export default class App extends React.Component {
    state = {
        splash: !splashSeen(), revealed: splashSeen(), wide: true,
        comps: null, comp: null,
        nameQ: '', nameRes: null, nameLoading: false, nameError: false,
        govs: [], govsError: false, gov: null, titles: [], titlesLoading: false, title: null,
        open: null, q: '', active: 0,
        view: 'query', status: 'idle', data: null, search: '', filter: 'all', page: 1, fading: false,
        congrats: null, docsFrom: null,
    };

    compRef = React.createRef(); compBtn = React.createRef();
    govRef = React.createRef(); titleRef = React.createRef();
    govBtn = React.createRef(); titleBtn = React.createRef();
    inputRef = React.createRef(); listRef = React.createRef();
    panelRef = React.createRef(); resultsRef = React.createRef(); searchRef = React.createRef();
    dialogRef = React.createRef();
    req = 0;

    componentDidMount() {
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.onResize = () => { const w = window.innerWidth >= 640; if (w !== this.state.wide) this.setState({ wide: w }); };
        this.onResize(); window.addEventListener('resize', this.onResize);
        this.onDown = (e) => {
            if (!this.state.open) return;
            const r = { comp: this.compRef, gov: this.govRef, title: this.titleRef }[this.state.open];
            if (r.current && !r.current.contains(e.target)) this.setState({ open: null });
        };
        document.addEventListener('mousedown', this.onDown);
        this.loadCompetitions();
        if (this.state.splash) {
            this.t1 = setTimeout(() => this.setState({ revealed: true }), reduced ? 1400 : 4400);
            this.t2 = setTimeout(() => this.skipSplash(), reduced ? 1500 : 5000);
        }
    }

    componentWillUnmount() {
        window.removeEventListener('resize', this.onResize);
        document.removeEventListener('mousedown', this.onDown);
        clearTimeout(this.t1); clearTimeout(this.t2);
    }

    componentDidUpdate(pp, ps) {
        if (this.state.open && this.listRef.current && (ps.active !== this.state.active || ps.open !== this.state.open)) {
            const ul = this.listRef.current, el = ul.querySelector('[data-active="true"]');
            if (el) {
                if (el.offsetTop < ul.scrollTop) ul.scrollTop = el.offsetTop - 6;
                else if (el.offsetTop + el.offsetHeight > ul.scrollTop + ul.clientHeight) ul.scrollTop = el.offsetTop + el.offsetHeight - ul.clientHeight + 6;
            }
        }
    }

    // المسابقات المنشورة: مسابقة واحدة تُختار تلقائياً، وأكثر من واحدة تظهر قائمة لاختيارها
    loadCompetitions() {
        this.setState({ govsError: false });
        ResultsAPI.getCompetitions()
            .then((comps) => {
                const wanted = new URLSearchParams(window.location.search).get('c');
                const comp = comps.find((c) => c.slug === wanted) || (comps.length === 1 ? comps[0] : null);
                this.setState({ comps });
                if (comp) this.selectCompetition(comp, true);
            })
            .catch(() => this.setState({ govsError: true }));
    }

    selectCompetition(comp, fromUrl) {
        this.req++;
        // علامات الطلب الأخير (لا نعتمد على this.state لأن البيانات الثابتة قد تصل قبل تطبيق setState)
        this.pendingComp = comp;
        this.pendingGov = null;
        this.nameReq++;
        this.setState({ comp, govs: [], gov: null, titles: [], title: null, status: 'idle', data: null, govsError: false, nameQ: '', nameRes: null, nameLoading: false });
        ResultsAPI.getGovernorates(comp)
            .then((govs) => {
                if (this.pendingComp !== comp) return;
                this.setState({ govs });
                if (fromUrl) this.restoreFromUrl(comp, govs);
            })
            .catch(() => this.setState({ govsError: true }));
    }

    // رابط مباشر: ?c=...&gov=...&title=...
    restoreFromUrl(comp, govs) {
        const p = new URLSearchParams(window.location.search);
        const gov = p.get('gov'), title = p.get('title');
        if (!gov || !govs.includes(gov)) return;
        this.pendingGov = gov;
        this.setState({ gov, titlesLoading: true });
        ResultsAPI.getJobTitles(comp, gov)
            .then((titles) => {
                if (this.pendingGov !== gov) return;
                this.setState({ titles, titlesLoading: false });
                if (title && titles.includes(title)) this.setState({ title }, () => this.submit());
            })
            .catch(() => this.setState({ titlesLoading: false }));
    }

    syncUrl(gov, title) {
        const { comp, comps } = this.state;
        const params = {};
        if (comp && comps && comps.length > 1) params.c = comp.slug;
        if (gov && title) Object.assign(params, { gov, title });
        const qs = new URLSearchParams(params).toString();
        try { window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '')); } catch { /* ignore */ }
    }

    docs() {
        return (this.state.comp?.required_documents || []).map((d, i) => ({ n: i + 1, title: d.title, notes: d.notes || [] }));
    }

    skipSplash = () => {
        clearTimeout(this.t1); clearTimeout(this.t2);
        markSplashSeen();
        this.setState({ splash: false, revealed: true });
    };

    listFor(which) {
        if (which === 'comp') return (this.state.comps || []).map((c) => c.title);
        return which === 'gov' ? this.state.govs : this.state.titles;
    }
    current(which) {
        return { comp: this.state.comp?.title, gov: this.state.gov, title: this.state.title }[which] ?? null;
    }
    filtered() {
        const list = this.listFor(this.state.open), q = normalize(this.state.q);
        return q ? list.filter((x) => normalize(x).includes(q)) : list;
    }
    openList(which) {
        if (which === 'gov' && !this.state.comp) return;
        if (which === 'title' && (!this.state.gov || this.state.titlesLoading)) return;
        const list = this.listFor(which), cur = this.current(which);
        this.setState({ open: which, q: '', active: Math.max(0, list.indexOf(cur)) });
        setTimeout(() => this.inputRef.current && this.inputRef.current.focus({ preventScroll: true }), 20);
    }
    close(focusBack) {
        const w = this.state.open;
        this.setState({ open: null });
        if (focusBack) {
            const b = { comp: this.compBtn, gov: this.govBtn, title: this.titleBtn }[w];
            setTimeout(() => b && b.current && b.current.focus(), 0);
        }
    }
    select(which, val) {
        if (which === 'comp') {
            const comp = this.state.comps.find((c) => c.title === val);
            if (comp && comp !== this.state.comp) this.selectCompetition(comp, false);
        } else if (which === 'gov') {
            if (val !== this.state.gov) {
                this.req++;
                this.pendingGov = val;
                this.setState({ gov: val, title: null, titles: [], titlesLoading: true, status: 'idle', data: null, search: '', page: 1 });
                ResultsAPI.getJobTitles(this.state.comp, val)
                    .then((t) => { if (this.pendingGov === val) this.setState({ titles: t, titlesLoading: false }); })
                    .catch(() => { if (this.pendingGov === val) this.setState({ titlesLoading: false }); });
            }
        } else if (val !== this.state.title) {
            this.req++;
            this.setState({ title: val, status: 'idle', data: null, search: '', page: 1 });
        }
        this.close(true);
    }
    triggerKey(which, e) {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); this.openList(which); }
        else if (e.key === 'Escape' && this.state.open) { e.preventDefault(); this.close(true); }
    }
    listKey = (e) => {
        const f = this.filtered(), n = f.length;
        if (e.key === 'ArrowDown') { e.preventDefault(); this.setState((s) => ({ active: n ? (s.active + 1) % n : 0 })); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); this.setState((s) => ({ active: n ? (s.active - 1 + n) % n : 0 })); }
        else if (e.key === 'Home') { e.preventDefault(); this.setState({ active: 0 }); }
        else if (e.key === 'End') { e.preventDefault(); this.setState({ active: Math.max(0, n - 1) }); }
        else if (e.key === 'Enter') { e.preventDefault(); if (f[this.state.active]) this.select(this.state.open, f[this.state.active]); }
        else if (e.key === 'Escape') { e.preventDefault(); this.close(true); }
        else if (e.key === 'Tab') { this.setState({ open: null }); }
    };

    scrollToEl(ref, offset) {
        const el = ref.current; if (!el) return;
        window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - (offset || 20), behavior: 'smooth' });
    }
    submit = () => {
        const { comp, gov, title } = this.state; if (!comp || !gov || !title) return;
        const id = ++this.req;
        this.setState({ status: 'loading', data: null, search: '', filter: 'all', page: 1, view: 'results' });
        window.scrollTo({ top: 0 });
        this.syncUrl(gov, title);
        ResultsAPI.getResults(comp, gov, title)
            .then((d) => { if (id === this.req) this.setState({ status: d.total ? 'ok' : 'empty', data: d }); })
            .catch(() => { if (id === this.req) this.setState({ status: 'error' }); });
    };
    editSearch = (e) => {
        e && e.preventDefault && e.preventDefault();
        if (this.state.view === 'results') {
            this.setState({ view: 'query' });
            window.scrollTo({ top: 0 });
            setTimeout(() => this.scrollToEl(this.panelRef, 20), 80);
        } else this.scrollToEl(this.panelRef, 20);
        setTimeout(() => this.titleBtn.current && this.titleBtn.current.focus({ preventScroll: true }), 350);
    };
    goHome = (e) => {
        e && e.preventDefault(); this.req++; this.pendingGov = null;
        this.setState({ view: 'query', gov: null, title: null, titles: [], open: null, status: 'idle', data: null, search: '', page: 1 });
        this.syncUrl(null, null);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    openCongrats(c, e) {
        this.lastTrigger = e && e.currentTarget;
        this.setState({ congrats: c });
        setTimeout(() => this.dialogRef.current && this.dialogRef.current.focus(), 30);
    }
    closeCongrats = () => {
        this.setState({ congrats: null });
        const t = this.lastTrigger; setTimeout(() => t && t.focus && t.focus({ preventScroll: true }), 0);
    };
    dialogKey = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); this.closeCongrats(); return; }
        if (e.key === 'Tab' && this.dialogRef.current) {
            const f = this.dialogRef.current.querySelectorAll('button'); if (!f.length) return;
            const first = f[0], last = f[f.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === this.dialogRef.current)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    };
    openDocs = (e) => {
        e && e.preventDefault && e.preventDefault();
        this.setState((s) => ({ docsFrom: s.view === 'docs' ? s.docsFrom : s.view, open: null, view: 'docs' }));
        window.scrollTo({ top: 0 });
    };
    closeDocs = (e) => {
        e && e.preventDefault && e.preventDefault();
        this.setState((s) => ({ view: s.docsFrom || 'query' }));
        window.scrollTo({ top: 0 });
    };
    toTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });
    setPage(p) {
        if (p === this.state.page) return;
        this.setState({ fading: true });
        setTimeout(() => { this.setState({ page: p, fading: false }); this.scrollToEl(this.resultsRef, 20); }, 140);
    }
    clearSearch = () => {
        this.setState({ search: '', page: 1 });
        setTimeout(() => this.searchRef.current && this.searchRef.current.focus({ preventScroll: true }), 0);
    };
    clearAll = () => {
        this.setState({ search: '', filter: 'all', page: 1 });
        setTimeout(() => this.searchRef.current && this.searchRef.current.focus({ preventScroll: true }), 0);
    };

    // تمييز جزء الاسم المطابق للبحث مع مراعاة توحيد الأحرف
    highlight(name, q) {
        if (!q) return { pre: name, hit: '', post: '' };
        let ns = ''; const map = [];
        for (let i = 0; i < name.length; i++) {
            const n = normalize(name[i]);
            if (/\s/.test(name[i])) { if (ns && ns[ns.length - 1] !== ' ') { ns += ' '; map.push(i); } continue; }
            for (const ch of n) { ns += ch; map.push(i); }
        }
        const at = ns.indexOf(q);
        if (at < 0) return { pre: name, hit: '', post: '' };
        const a = map[at], b = map[at + q.length - 1] + 1;
        return { pre: name.slice(0, a), hit: name.slice(a, b), post: name.slice(b) };
    }

    renderDropdown(which) {
        const s = this.state;
        const f = this.filtered();
        const cur = this.current(which);
        const placeholder = { comp: 'ابحث عن مسابقة', gov: 'ابحث عن محافظة', title: 'ابحث عن مسمى وظيفي' }[which];
        const listLabel = { comp: 'المسابقات', gov: 'المحافظات', title: 'المسميات الوظيفية' }[which];
        return (
            <div style={sx(`position:absolute;top:${which === 'title' ? '92px' : 'calc(100% + 6px)'};left:0;right:0;z-index:30;background:#FFFFFF;border:1px solid rgba(152,133,97,.35);border-radius:6px;box-shadow:0 18px 40px -20px rgba(0,38,35,.4);overflow:hidden;animation:dd-in 200ms cubic-bezier(.2,.7,.2,1) both`)}>
                <div style={sx('position:relative;padding:10px;border-bottom:1px solid #EFEBE0')}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B736F" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true" style={sx('position:absolute;right:22px;top:50%;transform:translateY(-50%)')}><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                    <input ref={this.inputRef} className="f-input" type="text" value={s.q} onChange={(e) => this.setState({ q: e.target.value, active: 0 })} onKeyDown={this.listKey}
                        placeholder={placeholder} aria-label={placeholder} aria-controls={which + '-list'} aria-activedescendant={f.length ? which + '-opt-' + s.active : undefined} autoComplete="off"
                        style={sx('width:100%;height:42px;border:1px solid #E3DED0;border-radius:4px;padding:0 38px 0 12px;font-size:15px;background:#FBFAF6;color:#002623;outline:none')} />
                </div>
                <ul id={which + '-list'} role="listbox" aria-label={listLabel} ref={this.listRef} style={sx('list-style:none;margin:0;padding:6px;max-height:292px;overflow-y:auto')}>
                    {f.map((label, i) => {
                        const act = i === s.active, sel = label === cur;
                        return (
                            <li key={label} id={which + '-opt-' + i} role="option" aria-selected={sel} data-active={act ? 'true' : 'false'}
                                onMouseDown={(e) => { e.preventDefault(); this.select(which, label); }}
                                onMouseEnter={() => { if (this.state.active !== i) this.setState({ active: i }); }}
                                style={sx(`display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:44px;padding:0 12px;border-radius:4px;cursor:pointer;background:${act ? '#EDEBE0' : 'transparent'};color:${sel ? '#054239' : '#002623'};font-size:15px;font-weight:${sel ? 600 : 400};transition:background 150ms`)}>
                                <span>{label}</span>
                                {sel && <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>}
                            </li>
                        );
                    })}
                    {f.length === 0 && <li role="presentation" style={sx('padding:14px 12px;font-size:14px;color:#4F5D58')}>لا توجد نتائج مطابقة</li>}
                </ul>
            </div>
        );
    }

    renderSplash() {
        const line = (dir, origin) => <span data-anim="1" style={sx(`display:block;width:clamp(40px,9vw,120px);height:1px;background:linear-gradient(to ${dir},#B9A779,rgba(185,167,121,.1));transform-origin:${origin} center;animation:sp-line 5s cubic-bezier(.22,.61,.36,1) both`)} />;
        const diamond = <span style={sx('width:5px;height:5px;background:#B9A779;transform:rotate(45deg)')} />;
        return (
            <div role="dialog" aria-label="وزارة العدل — جارٍ التحميل" data-anim="1" style={sx('position:fixed;inset:0;z-index:100;background:#002623;display:flex;align-items:center;justify-content:center;overflow:hidden;animation:sp-out 5s ease both')}>
                <div aria-hidden="true" data-anim="1" style={sx('position:absolute;inset:0;background:radial-gradient(ellipse 55% 45% at 50% 44%, rgba(185,167,121,.13), rgba(185,167,121,0) 70%);animation:sp-bg 5s ease both')} />
                <PatternSides width="clamp(80px,13vw,200px)" opacity=".11" fade="10%" anim="sp-pat 5s ease both" />
                <div style={sx('position:relative;display:flex;flex-direction:column;align-items:center;padding:24px')}>
                    <div style={sx('display:flex;align-items:center;gap:clamp(16px,3vw,32px)')}>
                        <div aria-hidden="true" style={sx('display:flex;align-items:center;gap:8px')}>{diamond}{line('left', 'left')}</div>
                        <div data-anim="1" style={sx('position:relative;width:clamp(200px,34vw,320px);aspect-ratio:575/304;animation:sp-logo 5s cubic-bezier(.22,.61,.36,1) both')}>
                            <img src="assets/syrian-logo.png" alt="شعار الجمهورية العربية السورية" style={sx('display:block;width:100%;height:auto')} />
                            <div aria-hidden="true" data-anim="1" style={sx("position:absolute;inset:0;opacity:0;background:linear-gradient(105deg,rgba(255,248,230,0) 38%,rgba(255,248,230,.6) 50%,rgba(255,248,230,0) 62%);background-size:260% 100%;-webkit-mask:url('assets/syrian-logo.png') center / 100% 100% no-repeat;mask:url('assets/syrian-logo.png') center / 100% 100% no-repeat;animation:sp-sweep 5s ease-in-out both")} />
                        </div>
                        <div aria-hidden="true" style={sx('display:flex;align-items:center;gap:8px')}>{line('right', 'right')}{diamond}</div>
                    </div>
                    <div style={sx('margin-top:44px;display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center')}>
                        <div data-anim="1" style={sx("font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(28px,4vw,38px);line-height:1.3;color:#EDEBE0;animation:sp-t1 5s cubic-bezier(.22,.61,.36,1) both")}>وزارة العدل</div>
                        <div data-anim="1" style={sx('font-size:clamp(16px,1.8vw,19px);font-weight:500;color:#B9A779;animation:sp-t2 5s cubic-bezier(.22,.61,.36,1) both')}>مديرية التنمية الإدارية</div>
                        <div data-anim="1" style={sx('font-size:14px;letter-spacing:.02em;color:#A8997A;animation:sp-t3 5s cubic-bezier(.22,.61,.36,1) both')}>الجمهورية العربية السورية</div>
                    </div>
                    <div data-anim="1" role="progressbar" aria-label="جارٍ التحميل" style={sx('margin-top:40px;width:160px;height:1px;background:rgba(185,167,121,.22);overflow:hidden;animation:sp-barwrap 5s ease both')}>
                        <div data-anim="1" style={sx('height:100%;background:#B9A779;transform-origin:right center;animation:sp-bar 5s cubic-bezier(.65,0,.35,1) both')} />
                    </div>
                </div>
                <button type="button" className="h-light" onClick={this.skipSplash} style={sx('position:absolute;bottom:28px;left:50%;transform:translateX(-50%);background:transparent;border:0;color:#A8997A;font-size:13px;padding:10px 16px;cursor:pointer;border-radius:4px')}>تخطي</button>
            </div>
        );
    }

    renderHero() {
        return (
            <section aria-labelledby="hero-title" style={sx('position:relative;background:#002623;overflow:hidden;padding:clamp(44px,7vw,84px) clamp(16px,4vw,40px) clamp(128px,13vw,156px)')}>
                <PatternSides width="clamp(70px,13vw,190px)" opacity=".09" fade="5%" />
                <div aria-hidden="true" style={sx('position:absolute;inset:0;background:radial-gradient(ellipse 50% 60% at 50% 30%, rgba(185,167,121,.08), rgba(185,167,121,0) 70%)')} />
                <div style={sx('position:relative;max-width:720px;margin:0 auto;text-align:center;display:flex;flex-direction:column;align-items:center')}>
                    <div aria-hidden="true" style={sx('display:flex;align-items:center;gap:10px;margin-bottom:22px')}>
                        <span style={sx('width:44px;height:1px;background:linear-gradient(to left,#B9A779,rgba(185,167,121,0))')} />
                        <span style={sx('width:6px;height:6px;border:1px solid #B9A779;transform:rotate(45deg)')} />
                        <span style={sx('width:44px;height:1px;background:linear-gradient(to right,#B9A779,rgba(185,167,121,0))')} />
                    </div>
                    <h1 id="hero-title" style={sx("margin:0;font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(32px,4.6vw,44px);line-height:1.25;color:#EDEBE0;text-wrap:balance")}>{this.state.comp ? 'نتائج ' + this.state.comp.title : 'نتائج المسابقات'}</h1>
                    <p style={sx('margin:14px 0 0;font-size:clamp(16px,1.7vw,18px);font-weight:500;color:#B9A779')}>وزارة العدل — مديرية التنمية الإدارية</p>
                    <p style={sx('margin:18px 0 0;max-width:560px;font-size:15px;line-height:1.9;color:rgba(237,235,224,.82);text-wrap:pretty')}>{this.state.comp?.description || 'يمكنكم الاستعلام عن أسماء المقبولين من خلال اختيار المسابقة والمحافظة والمسمى الوظيفي.'}</p>
                </div>
            </section>
        );
    }

    renderSubHero({ id, crumbs, title, subtitle, children, action, actionLabel }) {
        return (
            <section aria-labelledby={id} style={sx('position:relative;background:#002623;overflow:hidden;padding:clamp(24px,4vw,40px) clamp(16px,4vw,40px) clamp(128px,13vw,150px);animation:fade-up 300ms cubic-bezier(.22,.61,.36,1) both')}>
                <PatternSides width="clamp(70px,13vw,190px)" opacity=".09" fade="5%" />
                <div style={sx('position:relative;max-width:980px;margin:0 auto')}>
                    <Crumbs items={crumbs} />
                    <div style={sx('margin-top:22px;display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:20px')}>
                        <div style={sx('display:flex;flex-direction:column;gap:12px;min-width:0')}>
                            <h1 id={id} style={sx("margin:0;font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(28px,4vw,38px);line-height:1.25;color:#EDEBE0")}>{title}</h1>
                            {subtitle && <p style={sx('margin:0;font-size:15px;line-height:1.8;color:#B9A779')}>{subtitle}</p>}
                            {children}
                        </div>
                        <button type="button" className="h-gold" onClick={action} style={sx('height:46px;padding:0 18px;display:flex;align-items:center;gap:8px;background:transparent;border:1px solid rgba(185,167,121,.5);border-radius:6px;color:#EDEBE0;font-size:15px;white-space:nowrap;cursor:pointer;transition:background 200ms, border-color 200ms')}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                            <span>{actionLabel}</span>
                        </button>
                    </div>
                </div>
            </section>
        );
    }

    // على الهاتف: قائمة اختيار أصلية (يفتح منتقي النظام، ولا تظهر لوحة المفاتيح فوق الخيارات)
    renderNativeSelect({ which, id, options, value, disabled, placeholder }) {
        return (
            <div style={sx('position:relative')}>
                <select id={id} value={value || ''} disabled={disabled} aria-describedby={which === 'title' ? 'title-hint' : undefined}
                    onChange={(e) => e.target.value && this.select(which, e.target.value)}
                    style={sx(`width:100%;height:54px;padding:0 16px 0 44px;border:1px solid ${value ? '#B9A779' : (disabled ? '#E0DCCF' : '#CFC8B4')};border-radius:6px;background:${disabled ? '#F1EFE7' : '#FFFFFF'};font-size:16px;font-weight:${value ? 600 : 400};color:${disabled ? '#7D847F' : (value ? '#002623' : '#5F6763')};-webkit-appearance:none;appearance:none;outline:none;text-overflow:ellipsis`)}>
                    <option value="" disabled>{placeholder}</option>
                    {options.map((o) => <option key={o} value={o}>{o}</option>)}
                </select>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={disabled ? '#A3A89F' : '#054239'} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('position:absolute;left:16px;top:50%;transform:translateY(-50%);pointer-events:none')}><path d="m6 9 6 6 6-6" /></svg>
            </div>
        );
    }

    onNameSearch = (e) => {
        const text = e.target.value;
        const token = ++this.nameReq;
        this.setState({ nameQ: text });
        if (normalize(text).length < NAME_SEARCH_MIN) { this.setState({ nameRes: null, nameLoading: false }); return; }
        this.setState({ nameLoading: true });
        ResultsAPI.searchNames(this.state.comp, text)
            .then((res) => { if (token === this.nameReq) this.setState({ nameRes: res, nameLoading: false, nameError: false }); })
            .catch(() => { if (token === this.nameReq) this.setState({ nameLoading: false, nameError: true }); });
    };
    nameReq = 0;

    // من نتيجة البحث بالاسم إلى القائمة الكاملة لمحافظته ومسماه
    showGroup(gov, title) {
        const comp = this.state.comp;
        this.pendingGov = gov;
        this.setState({ congrats: null, gov, title: null, titlesLoading: true });
        ResultsAPI.getJobTitles(comp, gov).then((titles) => {
            if (this.pendingGov !== gov) return;
            this.setState({ titles, titlesLoading: false, title }, () => this.submit());
        }).catch(() => this.setState({ titlesLoading: false }));
    }

    renderNameSearch() {
        const s = this.state;
        const q = normalize(s.nameQ);
        const res = s.nameRes;
        return (
            <div style={sx('margin-top:24px;display:flex;flex-direction:column;gap:8px')}>
                <label htmlFor="name-quick" style={sx('font-size:15px;line-height:1.6;font-weight:600;color:#002623')}>ابحث باسمك مباشرة</label>
                <div style={sx('position:relative')}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#054239" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true" style={sx('position:absolute;right:16px;top:50%;transform:translateY(-50%)')}><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                    <input id="name-quick" type="search" className="f-search" value={s.nameQ} onChange={this.onNameSearch} autoComplete="off" enterKeyHint="search"
                        placeholder="مثال: محمد أحمد الخطيب" aria-describedby="name-quick-hint"
                        style={sx('width:100%;height:54px;border:1px solid #CFC8B4;border-radius:6px;background:#FFFFFF;padding:0 48px 0 16px;font-size:16px;color:#002623;outline:none;-webkit-appearance:none;appearance:none')} />
                </div>
                <span id="name-quick-hint" style={sx('font-size:13px;color:#4F5D58')}>
                    {q.length > 0 && q.length < NAME_SEARCH_MIN ? `اكتب ${NAME_SEARCH_MIN} أحرف على الأقل` : 'لا يهم الفرق بين (أ/ا) أو (ة/ه).'}
                </span>

                <div role="status" aria-live="polite">
                    {s.nameLoading && <span style={sx('font-size:14px;color:#054239')}>جارٍ البحث...</span>}
                    {s.nameError && <span style={sx('font-size:14px;color:#6B1F2A')}>تعذر البحث، تحقق من الاتصال وحاول مجدداً.</span>}
                    {res && !s.nameLoading && res.total === 0 && (
                        <div style={sx('padding:14px 16px;border:1px dashed rgba(152,133,97,.5);border-radius:6px;background:#FFFFFF;font-size:14px;line-height:1.8;color:#4F5D58')}>
                            لم يُعثر على الاسم. تأكد من كتابته كما في طلب التقدم، أو جرّب جزءاً منه فقط (الاسم الأول واسم العائلة).
                        </div>
                    )}
                </div>

                {res && res.total > 0 && !s.nameLoading && (
                    <div style={sx('border:1px solid rgba(152,133,97,.3);border-radius:8px;background:#FFFFFF;overflow:hidden')}>
                        <div style={sx('padding:10px 14px;background:#F4F2EA;font-size:13px;font-weight:600;color:#054239')}>
                            {res.total > res.items.length ? `${res.total} نتيجة — تظهر أول ${res.items.length}، أضف اسم الأب أو العائلة لتضييق البحث` : `${res.total} ${res.total === 1 ? 'نتيجة' : 'نتائج'}`}
                        </div>
                        <ul style={sx('list-style:none;margin:0;padding:0')}>
                            {res.items.map((c, i) => {
                                const h = this.highlight(c.name, q);
                                return (
                                    <li key={c.governorate + c.jobTitle + c.seq + c.name} style={sx(i ? 'border-top:1px solid #EFECE3' : '')}>
                                        <button type="button" className="h-row" onClick={(e) => this.openCongrats(c, e)}
                                            style={sx('width:100%;display:flex;align-items:center;gap:12px;padding:12px 14px;min-height:64px;background:#FFFFFF;border:0;text-align:right;cursor:pointer')}>
                                            <span style={sx('flex:1;min-width:0;display:flex;flex-direction:column;gap:2px')}>
                                                <span style={sx('font-size:16px;font-weight:600;color:#002623;line-height:1.5')}>{h.pre}{h.hit && <mark style={sx('background:rgba(185,167,121,.38);color:#002623;border-radius:2px')}>{h.hit}</mark>}{h.post}</span>
                                                <span style={sx('font-size:13px;color:#4F5D58;line-height:1.6')}>{c.governorate} · {c.jobTitle} · الترتيب {c.seq}</span>
                                            </span>
                                            <StatusBadge primary={c.status === 'primary'} row />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </div>
        );
    }

    renderQuery() {
        const s = this.state;
        const opened = s.open;
        const titleEnabled = !!s.gov && !s.titlesLoading;
        const canSubmit = !!(s.gov && s.title);
        const govBorder = opened === 'gov' ? '#988561' : (s.gov ? '#B9A779' : '#CFC8B4');
        const titleBorder = opened === 'title' ? '#988561' : (s.title ? '#B9A779' : (titleEnabled ? '#CFC8B4' : '#E0DCCF'));
        const titleIcon = titleEnabled ? '#054239' : '#A3A89F';
        const titleHint = !s.comp ? 'يرجى اختيار المسابقة أولاً.' : !s.gov ? 'يرجى اختيار المحافظة أولاً.'
            : (s.titlesLoading ? 'جارٍ جلب المسميات المتاحة في ' + s.gov : 'المسميات المتاحة في ' + s.gov + ': ' + s.titles.length);

        return (
            <section ref={this.panelRef} aria-labelledby="q-title" style={sx('position:relative;background:#FBFAF6;border:1px solid rgba(152,133,97,.3);border-radius:8px;box-shadow:0 1px 2px rgba(0,38,35,.04),0 22px 44px -26px rgba(0,38,35,.32);padding:clamp(22px,4vw,40px)')}>
                <div aria-hidden="true" style={sx('position:absolute;top:-1px;right:clamp(22px,4vw,40px);width:56px;height:3px;background:#B9A779;border-radius:0 0 2px 2px')} />
                <h2 id="q-title" style={sx("margin:0;font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(22px,2.4vw,26px);line-height:1.35;color:#002623")}>استعلم عن نتيجتك</h2>
                <p style={sx('margin:8px 0 0;font-size:14px;line-height:1.8;color:#4F5D58')}>اكتب اسمك مباشرة، أو اختر المحافظة والمسمى الوظيفي لعرض قائمة المقبولين.</p>

                {s.govsError && (
                    <div role="alert" style={sx('margin-top:18px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:12px 16px;border:1px solid rgba(107,31,42,.28);border-radius:6px;background:#FFFFFF;color:#6B1F2A;font-size:14px')}>
                        <span>تعذر تحميل البيانات.</span>
                        <button type="button" onClick={() => this.loadCompetitions()} style={sx('background:transparent;border:0;color:#054239;font-weight:700;text-decoration:underline;cursor:pointer;font-size:14px;padding:0')}>إعادة المحاولة</button>
                    </div>
                )}

                {s.comps && s.comps.length === 0 && (
                    <div role="status" style={sx('margin-top:18px;padding:14px 16px;border:1px dashed rgba(152,133,97,.5);border-radius:6px;background:#FFFFFF;color:#4F5D58;font-size:14px')}>
                        لا توجد نتائج منشورة حالياً.
                    </div>
                )}

                {s.comps && s.comps.length > 1 && (
                    <div ref={this.compRef} style={sx('position:relative;margin-top:28px;display:flex;flex-direction:column;gap:8px')}>
                        <label id="comp-label" htmlFor="comp-btn" style={sx('font-size:15px;line-height:1.6;font-weight:600;color:#002623')}>المسابقة</label>
                        {!s.wide ? this.renderNativeSelect({
                            which: 'comp', id: 'comp-btn', options: s.comps.map((c) => c.title), value: s.comp?.title, placeholder: 'اختر المسابقة',
                        }) : <button id="comp-btn" ref={this.compBtn} type="button" role="combobox" aria-haspopup="listbox" aria-expanded={opened === 'comp'} aria-controls="comp-list" aria-labelledby="comp-label"
                            onClick={() => (opened === 'comp' ? this.close(false) : this.openList('comp'))} onKeyDown={(e) => this.triggerKey('comp', e)}
                            style={sx(`height:54px;width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 16px;background:#FFFFFF;border:1px solid ${opened === 'comp' ? '#988561' : (s.comp ? '#B9A779' : '#CFC8B4')};box-shadow:${opened === 'comp' ? RING : 'none'};border-radius:6px;font-size:16px;font-weight:${s.comp ? 600 : 400};color:${s.comp ? '#002623' : '#5F6763'};cursor:pointer;text-align:right;transition:border-color 200ms, box-shadow 200ms`)}>
                            <span style={sx('display:flex;align-items:center;gap:10px;min-width:0')}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('flex-shrink:0')}><path d="M14 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8z" /><path d="M14 3.5V8h4.5" /><path d="M9 13h6" /><path d="M9 16.5h4" /></svg>
                                <span style={sx('overflow:hidden;text-overflow:ellipsis;white-space:nowrap')}>{s.comp?.title || 'اختر المسابقة'}</span>
                            </span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#054239" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx(`flex-shrink:0;transition:transform 220ms;transform:${opened === 'comp' ? 'rotate(180deg)' : 'none'}`)}><path d="m6 9 6 6 6-6" /></svg>
                        </button>}
                        {opened === 'comp' && this.renderDropdown('comp')}
                    </div>
                )}

                {s.comp && this.renderNameSearch()}

                {s.comp && (
                    <div aria-hidden="true" style={sx('margin-top:26px;display:flex;align-items:center;gap:12px;font-size:13px;font-weight:600;color:#6E5E3E')}>
                        <span style={sx('flex:1;height:1px;background:rgba(152,133,97,.35)')} />
                        <span>أو تصفّح القائمة الكاملة</span>
                        <span style={sx('flex:1;height:1px;background:rgba(152,133,97,.35)')} />
                    </div>
                )}

                <div style={sx(`margin-top:${s.comp ? 18 : 28}px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:${s.wide ? 20 : 16}px;align-items:start`)}>
                    <div ref={this.govRef} style={sx('position:relative;display:flex;flex-direction:column;gap:8px')}>
                        <label id="gov-label" htmlFor="gov-btn" style={sx('font-size:15px;line-height:1.6;font-weight:600;color:#002623')}>المحافظة</label>
                        {!s.wide ? this.renderNativeSelect({
                            which: 'gov', id: 'gov-btn', options: s.govs, value: s.gov, disabled: !s.comp, placeholder: 'اختر المحافظة',
                        }) : <button id="gov-btn" ref={this.govBtn} type="button" role="combobox" aria-haspopup="listbox" aria-expanded={opened === 'gov'} aria-controls="gov-list" aria-labelledby="gov-label"
                            onClick={() => (opened === 'gov' ? this.close(false) : this.openList('gov'))} onKeyDown={(e) => this.triggerKey('gov', e)}
                            style={sx(`height:54px;width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 16px;background:#FFFFFF;border:1px solid ${govBorder};box-shadow:${opened === 'gov' ? RING : 'none'};border-radius:6px;font-size:16px;font-weight:${s.gov ? 600 : 400};color:${s.gov ? '#002623' : '#5F6763'};cursor:pointer;text-align:right;transition:border-color 200ms, box-shadow 200ms`)}>
                            <span style={sx('display:flex;align-items:center;gap:10px;min-width:0')}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('flex-shrink:0')}><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
                                <span style={sx('overflow:hidden;text-overflow:ellipsis;white-space:nowrap')}>{s.gov || 'اختر المحافظة'}</span>
                            </span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#054239" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx(`flex-shrink:0;transition:transform 220ms;transform:${opened === 'gov' ? 'rotate(180deg)' : 'none'}`)}><path d="m6 9 6 6 6-6" /></svg>
                        </button>}
                        {opened === 'gov' && this.renderDropdown('gov')}
                    </div>

                    <div ref={this.titleRef} style={sx('position:relative;display:flex;flex-direction:column;gap:8px')}>
                        <label id="title-label" htmlFor="title-btn" style={sx(`font-size:15px;line-height:1.6;font-weight:600;color:${titleEnabled ? '#002623' : '#5F6763'};transition:color 250ms`)}>المسمى الوظيفي</label>
                        {!s.wide ? this.renderNativeSelect({
                            which: 'title', id: 'title-btn', options: s.titles, value: s.title, disabled: !titleEnabled,
                            placeholder: s.titlesLoading ? 'جارٍ تحميل المسميات...' : 'اختر المسمى الوظيفي',
                        }) : <button id="title-btn" ref={this.titleBtn} type="button" role="combobox" aria-haspopup="listbox" aria-expanded={opened === 'title'} aria-controls="title-list" aria-labelledby="title-label" aria-disabled={!titleEnabled} aria-describedby="title-hint"
                            onClick={() => (opened === 'title' ? this.close(false) : this.openList('title'))} onKeyDown={(e) => this.triggerKey('title', e)}
                            style={sx(`height:54px;width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 16px;background:${titleEnabled ? '#FFFFFF' : '#F1EFE7'};border:1px solid ${titleBorder};box-shadow:${opened === 'title' ? RING : 'none'};border-radius:6px;font-size:16px;font-weight:${s.title ? 600 : 400};color:${!titleEnabled ? '#7D847F' : (s.title ? '#002623' : '#5F6763')};cursor:${titleEnabled ? 'pointer' : 'not-allowed'};text-align:right;transition:background 250ms, border-color 200ms, box-shadow 200ms, color 250ms`)}>
                            <span style={sx('display:flex;align-items:center;gap:10px;min-width:0')}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={titleIcon} strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('flex-shrink:0')}><rect x="3.5" y="7" width="17" height="12.5" rx="1.5" /><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" /><path d="M3.5 12.5h17" /></svg>
                                <span style={sx('overflow:hidden;text-overflow:ellipsis;white-space:nowrap')}>{s.titlesLoading ? 'جارٍ تحميل المسميات...' : (s.title || 'اختر المسمى الوظيفي')}</span>
                            </span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={titleIcon} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx(`flex-shrink:0;transition:transform 220ms;transform:${opened === 'title' ? 'rotate(180deg)' : 'none'}`)}><path d="m6 9 6 6 6-6" /></svg>
                        </button>}
                        <div id="title-hint" style={sx('display:flex;align-items:center;gap:6px;font-size:13px;line-height:1.6;color:#4F5D58')}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true" style={sx('flex-shrink:0')}><circle cx="12" cy="12" r="9" /><path d="M12 11v5.5" /><path d="M12 7.6v.1" /></svg>
                            <span>{titleHint}</span>
                        </div>
                        {opened === 'title' && this.renderDropdown('title')}
                    </div>

                    <div style={sx('display:flex;flex-direction:column;gap:8px')}>
                        {s.wide && <span aria-hidden="true" style={sx('font-size:15px;line-height:1.6;visibility:hidden')}>—</span>}
                        {canSubmit ? (
                            <button type="button" className="h-dark" onClick={this.submit} style={sx('height:54px;width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:#002623;color:#EDEBE0;border:0;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;transition:background 200ms, transform 180ms;animation:dd-in 240ms ease both')}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                                <span>عرض النتائج</span>
                            </button>
                        ) : (
                            <>
                                <button type="button" disabled aria-disabled="true" aria-describedby="submit-hint" style={sx('height:54px;width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:#DCD8CA;color:#59625E;border:0;border-radius:6px;font-size:16px;font-weight:600;cursor:not-allowed')}>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7D847F" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                                    <span>عرض النتائج</span>
                                </button>
                                <span id="submit-hint" className="sr-only">اختر المحافظة والمسمى الوظيفي لتفعيل الزر</span>
                            </>
                        )}
                    </div>
                </div>
            </section>
        );
    }

    renderLoading() {
        const shimmer = (w, h, r, light) => <div style={sx(`width:${w};height:${h};border-radius:${r};background:linear-gradient(90deg,${light ? '#ECE9DF 0%,#F7F5EF 50%,#ECE9DF 100%' : '#E4E0D3 0%,#F2F0E8 50%,#E4E0D3 100%'});background-size:200% 100%;animation:shimmer 1.4s linear infinite`)} />;
        return (
            <div style={sx('display:flex;flex-direction:column;gap:20px')}>
                <div style={sx('display:flex;align-items:center;gap:10px;font-size:14px;color:#054239')}>
                    <span aria-hidden="true" style={sx('width:16px;height:16px;border-radius:50%;border:2px solid rgba(66,129,119,.25);border-top-color:#428177;animation:spin 800ms linear infinite')} />
                    <span id="res-title">جارٍ تحميل النتائج...</span>
                </div>
                <div style={sx('display:flex;flex-wrap:wrap;justify-content:space-between;gap:20px;align-items:flex-end')}>
                    <div style={sx('display:flex;flex-direction:column;gap:12px')}>{shimmer('200px', '28px', '4px')}{shimmer('min(280px,70vw)', '16px', '4px')}</div>
                    {shimmer('230px', '84px', '8px')}
                </div>
                {shimmer('100%', '54px', '6px')}
                <div style={sx('background:#FFFFFF;border:1px solid rgba(152,133,97,.25);border-radius:8px;overflow:hidden')}>
                    {['62%', '48%', '70%', '55%', '66%', '44%'].map((w, i) => (
                        <div key={i} style={sx('display:grid;grid-template-columns:72px 1fr;gap:16px;align-items:center;height:56px;padding:0 24px;border-bottom:1px solid #EFECE3')}>
                            {shimmer('28px', '14px', '3px', true)}{shimmer(w, '14px', '3px', true)}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    renderError() {
        return (
            <div role="alert" style={sx('background:#FBFAF6;border:1px solid rgba(107,31,42,.28);border-radius:8px;padding:clamp(32px,5vw,52px) 24px;display:flex;flex-direction:column;align-items:center;text-align:center')}>
                <span aria-hidden="true" style={sx('width:56px;height:56px;border-radius:50%;background:rgba(107,31,42,.08);display:flex;align-items:center;justify-content:center')}>
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#6B1F2A" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3.5 21.5 20h-19z" /><path d="M12 10v4.5" /><path d="M12 17.4v.1" /></svg>
                </span>
                <h2 id="res-title" style={sx("margin:18px 0 0;font-family:'Qomra',sans-serif;font-weight:700;font-size:22px;color:#4A151E")}>تعذر تحميل النتائج</h2>
                <p style={sx('margin:8px 0 0;font-size:14px;line-height:1.8;color:#6B1F2A;max-width:420px')}>حدث خطأ أثناء تحميل البيانات، يرجى المحاولة مرة أخرى.</p>
                <button type="button" className="h-dark" onClick={this.submit} style={sx('margin-top:22px;height:48px;padding:0 24px;display:flex;align-items:center;gap:8px;background:#002623;color:#EDEBE0;border:0;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms')}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.4-5.7" /><path d="M20 4v4.5h-4.5" /></svg>
                    <span>إعادة المحاولة</span>
                </button>
            </div>
        );
    }

    renderEmpty(resGov, resTitle) {
        return (
            <div style={sx('background:#FBFAF6;border:1px solid rgba(152,133,97,.3);border-radius:8px;padding:clamp(32px,5vw,52px) 24px;display:flex;flex-direction:column;align-items:center;text-align:center')}>
                <span aria-hidden="true" style={sx('width:56px;height:56px;border-radius:50%;border:1px solid rgba(152,133,97,.45);display:flex;align-items:center;justify-content:center')}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="3.5" width="16" height="17" rx="1.5" /><path d="M8 8.5h8" /><path d="M8 12.5h8" /><path d="M8 16.5h4" /></svg>
                </span>
                <h2 id="res-title" style={sx("margin:18px 0 0;font-family:'Qomra',sans-serif;font-weight:700;font-size:22px;color:#002623")}>لا توجد نتائج متاحة</h2>
                <p style={sx('margin:8px 0 0;font-size:14px;line-height:1.8;color:#4F5D58;max-width:440px')}>لا تتوفر أسماء مقبولين للمحافظة والمسمى الوظيفي المحددين حالياً.</p>
                <div style={sx('margin-top:12px;display:flex;flex-wrap:wrap;justify-content:center;gap:8px 20px;font-size:13px;color:#4F5D58')}>
                    <span>المحافظة: <strong style={sx('color:#002623;font-weight:600')}>{resGov}</strong></span>
                    <span>المسمى الوظيفي: <strong style={sx('color:#002623;font-weight:600')}>{resTitle}</strong></span>
                </div>
                <button type="button" className="h-outline" onClick={this.editSearch} style={sx('margin-top:22px;height:48px;padding:0 24px;display:flex;align-items:center;gap:8px;background:transparent;color:#002623;border:1px solid #002623;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms, color 200ms')}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" /></svg>
                    <span>تعديل البحث</span>
                </button>
            </div>
        );
    }

    renderOk() {
        const s = this.state, d = s.data;
        const hasDocs = this.docs().length > 0;
        const q = normalize(s.search);
        const searched = q ? d.candidates.filter((c) => c.key.includes(q)) : d.candidates;
        const fil = s.filter;
        const matches = fil === 'all' ? searched : searched.filter((c) => c.status === fil);
        const nPrim = searched.filter((c) => c.status === 'primary').length;
        const pages = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));
        const page = Math.min(s.page, pages);
        const start = (page - 1) * PAGE_SIZE;
        const slice = matches.slice(start, start + PAGE_SIZE);
        const rangeText = !matches.length ? 'لا توجد أسماء مطابقة'
            : 'عرض ' + (start + 1) + '–' + (start + slice.length) + ' من ' + matches.length + (q ? ' نتيجة مطابقة' : '');

        const nums = [];
        if (pages <= 7) for (let i = 1; i <= pages; i++) nums.push(i);
        else {
            nums.push(1);
            if (page > 3) nums.push('gap-a');
            for (let i = Math.max(2, page - 1); i <= Math.min(pages - 1, page + 1); i++) nums.push(i);
            if (page < pages - 2) nums.push('gap-b');
            nums.push(pages);
        }

        const stat = (label, value, variant) => {
            const dark = variant === 'total';
            const cfg = {
                total: { box: 'background:#002623;position:relative;overflow:hidden', ring: 'border:1px solid rgba(185,167,121,.55)', lc: '#B9A779', vc: '#EDEBE0', stroke: '#B9A779' },
                primary: { box: 'background:#FFFFFF;border:1px solid rgba(5,66,57,.28)', ring: 'background:#E4EEEA', lc: '#054239', vc: '#002623', stroke: '#054239' },
                reserve: { box: 'background:#FFFFFF;border:1px solid rgba(152,133,97,.4)', ring: 'background:#F3EEE1', lc: '#6E5E3E', vc: '#002623', stroke: '#7A6843' },
            }[variant];
            return (
                <div style={sx(`display:flex;align-items:center;gap:16px;border-radius:8px;padding:18px 22px;${cfg.box}`)}>
                    {dark && <div aria-hidden="true" style={sx("position:absolute;top:0;bottom:0;left:0;width:64px;background:url('assets/pattern.png') left top / 100% auto repeat-y;opacity:.12;-webkit-mask-image:linear-gradient(to right,#000,transparent);mask-image:linear-gradient(to right,#000,transparent)")} />}
                    <span aria-hidden="true" style={sx(`position:relative;width:46px;height:46px;flex-shrink:0;border-radius:50%;display:flex;align-items:center;justify-content:center;${cfg.ring}`)}>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={cfg.stroke} strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                            {variant === 'total' && <><circle cx="9" cy="8.5" r="3.2" /><path d="M3.5 19c.6-3 2.8-4.8 5.5-4.8s4.9 1.8 5.5 4.8" /><circle cx="16.5" cy="9.5" r="2.5" /><path d="M16 14.3c2.3.2 3.9 1.7 4.5 4.2" /></>}
                            {variant === 'primary' && <><circle cx="12" cy="12" r="8.5" /><path d="m8.2 12.3 2.6 2.6 5-5.4" /></>}
                            {variant === 'reserve' && <><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></>}
                        </svg>
                    </span>
                    <div style={sx('position:relative;display:flex;flex-direction:column')}>
                        <span style={sx(`font-size:13px;color:${cfg.lc};font-weight:500`)}>{label}</span>
                        <span style={sx(`font-family:'Qomra',sans-serif;font-weight:700;font-size:34px;line-height:1.15;color:${cfg.vc};font-variant-numeric:tabular-nums`)}>{value}</span>
                    </div>
                </div>
            );
        };

        const gridCols = 'display:grid;grid-template-columns:clamp(44px,9vw,96px) minmax(0,1fr) auto;gap:clamp(10px,2vw,16px);align-items:center';

        return (
            <>
                <div style={sx('display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:20px')}>
                    <div style={sx('display:flex;flex-direction:column;gap:6px;min-width:0')}>
                        <h2 id="res-title" style={sx("margin:0;font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(20px,2.2vw,24px);color:#002623")}>قائمة المقبولين</h2>
                        <p style={sx('margin:0;font-size:14px;line-height:1.8;color:#4F5D58')}>استخدم البحث للوصول إلى اسم محدد.</p>
                    </div>
                    {hasDocs && (
                        <button type="button" className="h-dark" onClick={this.openDocs} style={sx('height:48px;padding:0 20px;display:flex;align-items:center;gap:10px;background:#002623;color:#EDEBE0;border:0;border-radius:6px;font-size:15px;font-weight:600;white-space:nowrap;cursor:pointer;transition:background 200ms, transform 180ms')}>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M14 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8z" /><path d="M14 3.5V8h4.5" /><path d="m9 13.5 2 2 4-4" /></svg>
                            <span>الأوراق المطلوبة</span>
                        </button>
                    )}
                </div>

                {s.wide ? (
                    <div style={sx('margin-top:24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:14px')}>
                        {stat('إجمالي المقبولين', d.total, 'total')}
                        {stat('إجمالي المقبولين الأساسيين', d.primaryCount, 'primary')}
                        {stat('إجمالي المقبولين الاحتياطيين', d.reserveCount, 'reserve')}
                    </div>
                ) : (
                    // على الهاتف: الأرقام الثلاثة في سطر واحد حتى تظهر الأسماء بسرعة
                    <div style={sx('margin-top:18px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px')}>
                        {[['المجموع', d.total, 'background:#002623;color:#EDEBE0', '#B9A779'], ['أساسي', d.primaryCount, 'background:#FFFFFF;border:1px solid rgba(5,66,57,.28);color:#002623', '#054239'], ['احتياطي', d.reserveCount, 'background:#FFFFFF;border:1px solid rgba(152,133,97,.4);color:#002623', '#6E5E3E']].map(([label, value, box, lc]) => (
                            <div key={label} style={sx(`border-radius:8px;padding:10px 8px;text-align:center;${box}`)}>
                                <div style={sx(`font-size:12px;font-weight:600;color:${lc}`)}>{label}</div>
                                <div style={sx("font-family:'Qomra',sans-serif;font-weight:700;font-size:24px;line-height:1.2;font-variant-numeric:tabular-nums")}>{value}</div>
                            </div>
                        ))}
                    </div>
                )}

                {!s.wide && (
                    <details style={sx('margin-top:10px;border:1px solid rgba(152,133,97,.3);border-radius:8px;background:#FFFFFF')}>
                        <summary style={sx('padding:12px 14px;font-size:14px;font-weight:600;color:#054239;cursor:pointer')}>ما الفرق بين أساسي واحتياطي؟</summary>
                        <div style={sx('padding:0 14px 14px;display:flex;flex-direction:column;gap:10px;font-size:14px;line-height:1.9')}>
                            <p style={sx('margin:0;color:#1E3A36')}><strong style={sx('color:#002623')}>الأساسي:</strong> مؤهل للتعاقد فوراً{hasDocs && <> وعليه استخراج <a href="#docs" onClick={this.openDocs} style={sx('color:#054239;font-weight:700')}>الأوراق المطلوبة</a></>}.</p>
                            <p style={sx('margin:0;color:#3F3A2E')}><strong style={sx('color:#002623')}>الاحتياطي:</strong> ناجح ومؤهل للتعاقد لحين الحاجة أو اعتذار أحد الأساسيين.</p>
                        </div>
                    </details>
                )}

                {s.wide && <div style={sx('margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:14px')}>
                    <div style={sx('display:flex;gap:14px;align-items:flex-start;background:#F6F8F5;border:1px solid rgba(5,66,57,.18);border-radius:8px;padding:18px 20px')}>
                        <StatusBadge primary />
                        <p style={sx('margin:0;font-size:14px;line-height:1.9;color:#1E3A36;text-wrap:pretty')}><strong style={sx('color:#002623;font-weight:700')}>المقبول الأساسي:</strong> هو المؤهل للتعاقد فوراً{hasDocs && <> وعليه استخراج <a href="#docs" className="h-link" onClick={this.openDocs} style={sx('color:#054239;font-weight:700;text-decoration:underline;text-decoration-color:#B9A779;text-underline-offset:4px;text-decoration-thickness:2px')}>الأوراق المطلوبة</a></>}</p>
                    </div>
                    <div style={sx('display:flex;gap:14px;align-items:flex-start;background:#FAF7EF;border:1px solid rgba(152,133,97,.3);border-radius:8px;padding:18px 20px')}>
                        <StatusBadge />
                        <p style={sx('margin:0;font-size:14px;line-height:1.9;color:#3F3A2E;text-wrap:pretty')}><strong style={sx('color:#002623;font-weight:700')}>المقبول الاحتياطي:</strong> هو الناجح والمؤهل للتعاقد معه لحين الحاجة أو اعتذار أحد المقبولين الأساسيين</p>
                    </div>
                </div>}

                <div style={sx(`margin-top:${s.wide ? 24 : 16}px;display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px`)}>
                    <div style={sx('position:relative;flex:1 1 320px')}>
                        <label htmlFor="name-search" className="sr-only">ابحث عن اسم المقبول</label>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#054239" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true" style={sx('position:absolute;right:16px;top:50%;transform:translateY(-50%)')}><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                        <input id="name-search" ref={this.searchRef} className="f-search" type="search" value={s.search}
                            onChange={(e) => this.setState({ search: e.target.value, page: 1 })}
                            onKeyDown={(e) => { if (e.key === 'Escape' && s.search) { e.preventDefault(); this.setState({ search: '', page: 1 }); } }}
                            placeholder="ابحث عن اسم المقبول..." autoComplete="off"
                            style={sx('width:100%;height:54px;border:1px solid #CFC8B4;border-radius:6px;background:#FFFFFF;padding:0 48px 0 52px;font-size:16px;color:#002623;outline:none;transition:border-color 200ms, box-shadow 200ms;-webkit-appearance:none;appearance:none')} />
                        {s.search.length > 0 && (
                            <button type="button" className="h-clear" onClick={this.clearSearch} aria-label="مسح البحث" style={sx('position:absolute;left:8px;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:50%;border:0;background:#F1EEE4;display:flex;align-items:center;justify-content:center;cursor:pointer;animation:dd-in 180ms ease both;transition:background 180ms')}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#002623" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                            </button>
                        )}
                    </div>
                    <span role="status" style={sx('font-size:14px;color:#4F5D58;white-space:nowrap')}>{rangeText}</span>
                </div>

                <div style={sx('margin-top:14px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 20px')}>
                    <div role="group" aria-label="تصفية حسب الحالة" style={sx((s.wide ? '' : 'width:100%;') + 'display:flex;gap:4px;padding:4px;background:#F1EEE4;border:1px solid #E3DDCD;border-radius:8px')}>
                        {[['all', 'الكل', searched.length], ['primary', 'أساسي', nPrim], ['reserve', 'احتياطي', searched.length - nPrim]].map(([k, label, count]) => {
                            const on = k === fil;
                            return (
                                <button key={k} type="button" onClick={() => this.setState({ filter: k, page: 1 })} aria-pressed={on}
                                    style={sx(`${s.wide ? '' : 'flex:1;justify-content:center;'}height:40px;padding:0 ${s.wide ? 14 : 8}px;display:flex;align-items:center;gap:8px;background:${on ? '#002623' : 'transparent'};color:${on ? '#EDEBE0' : '#054239'};border:0;border-radius:5px;box-shadow:${on ? '0 1px 2px rgba(0,38,35,.2)' : 'none'};font-size:14px;font-weight:600;white-space:nowrap;cursor:pointer;transition:background 200ms, color 200ms, box-shadow 200ms`)}>
                                    <span>{label}</span>
                                    <span style={sx(`min-width:24px;height:22px;padding:0 6px;border-radius:11px;background:${on ? 'rgba(185,167,121,.28)' : '#FFFFFF'};color:${on ? '#EDEBE0' : '#054239'};font-size:12px;display:inline-flex;align-items:center;justify-content:center;font-variant-numeric:tabular-nums;transition:background 200ms, color 200ms`)}>{count}</span>
                                </button>
                            );
                        })}
                    </div>
                    {s.comp?.rankedByScore && (
                        <div style={sx('display:flex;align-items:center;gap:8px;font-size:13px;line-height:1.7;color:#054239')}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('flex-shrink:0')}><path d="M7 4v16" /><path d="m3.5 16.5 3.5 3.5 3.5-3.5" /><path d="M13 6h8" /><path d="M13 11h6" /><path d="M13 16h4" /></svg>
                            <span style={sx('font-weight:500')}>تم الترتيب حسب العلامة الأعلى</span>
                        </div>
                    )}
                </div>

                <div style={sx('margin-top:14px;background:#FFFFFF;border:1px solid rgba(152,133,97,.28);border-radius:8px;overflow:clip')}>
                    <div role="table" aria-label="أسماء المقبولين" aria-rowcount={matches.length}>
                        <div role="row" style={sx(`position:sticky;top:0;z-index:1;${gridCols};height:48px;padding:0 clamp(14px,3vw,28px);background:#F4F2EA;border-bottom:1px solid rgba(152,133,97,.35);font-size:13px;font-weight:600;color:#054239`)}>
                            <span role="columnheader">الرقم</span>
                            <span role="columnheader">اسم المقبول</span>
                            <span role="columnheader" style={sx(s.wide ? 'min-width:84px;text-align:center' : 'text-align:center')}>الحالة</span>
                        </div>
                        <div role="rowgroup" style={sx(`opacity:${s.fading ? 0.25 : 1};transition:opacity 200ms`)}>
                            {slice.map((c, i) => {
                                const h = this.highlight(c.name, q);
                                const primary = c.status === 'primary';
                                return (
                                    <div key={c.seq} role="row" className="h-row" style={sx(`${gridCols};min-height:56px;padding:10px clamp(14px,3vw,28px);background:${i % 2 ? '#FCFBF8' : '#FFFFFF'};border-bottom:1px solid #EFECE3;transition:background 180ms`)}>
                                        <span role="cell" style={sx('font-size:15px;font-weight:600;color:#428177;font-variant-numeric:tabular-nums')}>{c.seq}</span>
                                        <span role="cell" style={sx('min-width:0')}>
                                            <button type="button" className="h-name" onClick={(e) => this.openCongrats(c, e)} aria-label={c.name + (primary ? ' — مقبول أساسي' : ' — مقبول احتياطي') + '، عرض التفاصيل'}
                                                style={sx('display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:4px 0;background:transparent;border:0;font-size:16px;font-weight:600;line-height:1.6;color:#002623;text-align:right;cursor:pointer;text-decoration:underline;text-decoration-color:rgba(185,167,121,.55);text-underline-offset:5px;transition:color 180ms, text-decoration-color 180ms')}>
                                                <span>{h.pre}{h.hit && <mark style={sx('background:rgba(185,167,121,.38);color:#002623;border-radius:2px;padding:0 1px')}>{h.hit}</mark>}{h.post}</span>
                                                {s.wide && <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('flex-shrink:0')}><path d="m15 6-6 6 6 6" /></svg>}
                                            </button>
                                        </span>
                                        <span role="cell" style={sx(s.wide ? 'min-width:84px;display:flex;justify-content:center' : 'display:flex;justify-content:center')}><StatusBadge primary={primary} row /></span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {matches.length === 0 && (
                        <div style={sx('padding:clamp(36px,5vw,56px) 24px;display:flex;flex-direction:column;align-items:center;text-align:center;animation:fade-up 260ms ease both')}>
                            <span aria-hidden="true" style={sx('width:56px;height:56px;border-radius:50%;border:1px solid rgba(152,133,97,.45);display:flex;align-items:center;justify-content:center')}>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#988561" strokeWidth="1.7" strokeLinecap="round"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /><path d="M8.8 8.8l4.4 4.4" /><path d="M13.2 8.8l-4.4 4.4" /></svg>
                            </span>
                            <h3 style={sx("margin:16px 0 0;font-family:'Qomra',sans-serif;font-weight:700;font-size:20px;color:#002623")}>لا توجد نتائج مطابقة</h3>
                            <p style={sx('margin:8px 0 0;font-size:14px;line-height:1.8;color:#4F5D58;max-width:420px')}>لم نعثر على أسماء تطابق بحثك. يرجى التحقق من الاسم والمحاولة مجدداً.</p>
                            <button type="button" className="h-outline" onClick={this.clearAll} style={sx('margin-top:20px;height:46px;padding:0 22px;background:transparent;color:#002623;border:1px solid #002623;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms, color 200ms')}>مسح البحث</button>
                        </div>
                    )}

                    {matches.length > 0 && pages > 1 && (
                        <nav aria-label="التنقل بين صفحات النتائج" style={sx('display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:14px clamp(16px,3vw,28px);background:#FBFAF6;border-top:1px solid #EFECE3')}>
                            <span style={sx('font-size:14px;color:#4F5D58')}>{rangeText}</span>
                            <div style={sx('display:flex;align-items:center;gap:6px;flex-wrap:wrap')}>
                                <button type="button" className="h-pager" onClick={() => page > 1 && this.setPage(page - 1)} disabled={page <= 1} aria-label="الصفحة السابقة"
                                    style={sx(`height:42px;padding:0 12px;display:flex;align-items:center;gap:6px;background:#FFFFFF;border:1px solid #E0DACB;border-radius:4px;font-size:14px;color:#002623;cursor:pointer;opacity:${page <= 1 ? 0.45 : 1};transition:border-color 180ms`)}>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
                                    {s.wide && <span>السابق</span>}
                                </button>
                                {nums.map((n) => (typeof n === 'number' ? (
                                    <button key={n} type="button" className="h-pager" onClick={() => this.setPage(n)} aria-label={'الصفحة ' + n} aria-current={n === page ? 'page' : undefined}
                                        style={sx(`height:42px;min-width:42px;padding:0 6px;background:${n === page ? '#002623' : '#FFFFFF'};color:${n === page ? '#EDEBE0' : '#002623'};border:1px solid ${n === page ? '#002623' : '#E0DACB'};border-radius:4px;font-size:14px;font-weight:600;cursor:pointer;font-variant-numeric:tabular-nums;transition:background 180ms, border-color 180ms`)}>{n}</button>
                                ) : (
                                    <span key={n} aria-hidden="true" style={sx('min-width:24px;text-align:center;color:#6B736F')}>…</span>
                                )))}
                                <button type="button" className="h-pager" onClick={() => page < pages && this.setPage(page + 1)} disabled={page >= pages} aria-label="الصفحة التالية"
                                    style={sx(`height:42px;padding:0 12px;display:flex;align-items:center;gap:6px;background:#FFFFFF;border:1px solid #E0DACB;border-radius:4px;font-size:14px;color:#002623;cursor:pointer;opacity:${page >= pages ? 0.45 : 1};transition:border-color 180ms`)}>
                                    {s.wide && <span>التالي</span>}
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6" /></svg>
                                </button>
                            </div>
                        </nav>
                    )}
                </div>
            </>
        );
    }

    renderDocs() {
        return (
            <section aria-label="قائمة الأوراق المطلوبة" style={sx('background:#FBFAF6;border:1px solid rgba(152,133,97,.3);border-radius:8px;box-shadow:0 1px 2px rgba(0,38,35,.04),0 22px 44px -26px rgba(0,38,35,.32);padding:clamp(20px,4vw,40px);animation:fade-up 300ms cubic-bezier(.22,.61,.36,1) both')}>
                {this.state.comp?.primary_note && <div role="note" style={sx('display:flex;gap:14px;align-items:flex-start;background:#002623;border-radius:8px;padding:18px 20px;margin-bottom:24px;position:relative;overflow:hidden')}>
                    <div aria-hidden="true" style={sx("position:absolute;top:0;bottom:0;left:0;width:64px;background:url('assets/pattern.png') left top / 100% auto repeat-y;opacity:.12;-webkit-mask-image:linear-gradient(to right,#000,transparent);mask-image:linear-gradient(to right,#000,transparent)")} />
                    <span aria-hidden="true" style={sx('position:relative;flex-shrink:0;width:40px;height:40px;border-radius:50%;border:1px solid rgba(185,167,121,.55);display:flex;align-items:center;justify-content:center')}>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="1.5" /><path d="M3.5 10h17" /><path d="M8 3v4" /><path d="M16 3v4" /></svg>
                    </span>
                    <div style={sx('position:relative;display:flex;flex-direction:column;gap:4px')}>
                        <span style={sx('font-size:13px;font-weight:600;color:#B9A779')}>ملاحظة</span>
                        <p style={sx('margin:0;font-size:16px;line-height:1.8;color:#EDEBE0;text-wrap:pretty;white-space:pre-line')}>{this.state.comp.primary_note}</p>
                    </div>
                </div>}
                <ol style={sx('list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:12px')}>
                    {this.docs().map((d) => (
                        <li key={d.n} className="h-doc" style={sx('display:flex;align-items:flex-start;gap:14px;min-height:64px;padding:16px 18px;background:#FFFFFF;border:1px solid #E6E1D3;border-radius:6px;transition:border-color 200ms')}>
                            <span aria-hidden="true" style={sx('flex-shrink:0;width:32px;height:32px;border-radius:50%;border:1px solid #B9A779;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;color:#054239;font-variant-numeric:tabular-nums')}>{d.n}</span>
                            <span style={sx('display:flex;flex-direction:column;gap:2px;padding-top:4px')}>
                                <span style={sx('font-size:16px;font-weight:600;line-height:1.6;color:#002623')}>{d.title}</span>
                                {d.notes.map((nt) => <span key={nt} style={sx('font-size:13px;line-height:1.7;color:#4F5D58')}>{nt}</span>)}
                            </span>
                        </li>
                    ))}
                </ol>
            </section>
        );
    }

    renderCongrats() {
        const c = this.state.congrats, d = this.state.data;
        const primary = c.status === 'primary';
        const docs = this.docs();
        // من البحث بالاسم تأتي المحافظة والمسمى مع الاسم نفسه
        const fromSearch = !!c.governorate;
        const gov = c.governorate || d?.governorate, jobTitle = c.jobTitle || d?.jobTitle;
        const groupLink = fromSearch && (
            <button type="button" className="h-outline" onClick={() => this.showGroup(c.governorate, c.jobTitle)} style={sx('flex:1 1 100%;height:48px;background:transparent;color:#002623;border:1px solid #002623;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms, color 200ms')}>
                عرض قائمة المقبولين الكاملة
            </button>
        );
        return (
            <div onMouseDown={(e) => { if (e.target === e.currentTarget) this.closeCongrats(); }} style={sx('position:fixed;inset:0;z-index:80;background:rgba(0,26,24,.62);display:flex;align-items:center;justify-content:center;padding:16px;animation:dd-in 200ms ease both')}>
                <div role="dialog" aria-modal="true" aria-labelledby="cg-title" ref={this.dialogRef} tabIndex={-1} onKeyDown={this.dialogKey} style={sx('position:relative;width:100%;max-width:560px;max-height:calc(100vh - 32px);overflow-y:auto;background:#FBFAF6;border-radius:10px;box-shadow:0 30px 60px -20px rgba(0,20,18,.55);outline:none;animation:fade-up 280ms cubic-bezier(.22,.61,.36,1) both')}>
                    <div style={sx('position:relative;background:#002623;padding:32px 24px 28px;text-align:center;overflow:hidden;border-bottom:1px solid rgba(185,167,121,.45)')}>
                        <PatternSides width="90px" opacity=".12" fade="0%" />
                        <button type="button" className="h-close" onClick={this.closeCongrats} aria-label="إغلاق" style={sx('position:absolute;top:12px;left:12px;width:44px;height:44px;border-radius:50%;border:0;background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background 180ms')}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EDEBE0" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                        </button>
                        <span aria-hidden="true" style={sx('position:relative;margin:0 auto;width:60px;height:60px;border-radius:50%;border:1px solid #B9A779;display:flex;align-items:center;justify-content:center')}>
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
                                {primary ? <path d="m6.5 12.5 3.5 3.5 7.5-8" /> : <><circle cx="12" cy="12" r="8" /><path d="M12 7.5V12l3 2" /></>}
                            </svg>
                        </span>
                        <h2 id="cg-title" style={sx(`position:relative;margin:16px 0 0;font-family:'Qomra',sans-serif;font-weight:700;font-size:${primary ? 34 : 30}px;line-height:1.3;color:#EDEBE0`)}>{primary ? 'مبارك' : 'مقبول احتياطي'}</h2>
                        <p style={sx('position:relative;margin:6px 0 0;font-size:18px;font-weight:600;color:#B9A779')}>{c.name}</p>
                        <div style={sx('position:relative;margin-top:12px;display:flex;flex-wrap:wrap;justify-content:center;gap:6px 18px;font-size:13px;color:rgba(237,235,224,.82)')}>
                            <span>{jobTitle}</span>
                            <span>محافظة {gov}</span>
                            <span>الترتيب {c.seq}</span>
                            <span style={sx('display:inline-flex;align-items:center;gap:6px;color:#EDEBE0;font-weight:600')}>
                                <span aria-hidden="true" style={sx(primary ? 'width:6px;height:6px;border-radius:50%;background:#B9A779' : 'width:6px;height:6px;border-radius:50%;border:1.5px solid #B9A779')} />
                                {primary ? 'مقبول أساسي' : 'مقبول احتياطي'}
                            </span>
                        </div>
                    </div>
                    {primary ? (
                        <div style={sx('padding:24px clamp(18px,4vw,28px) 26px')}>
                            {docs.length > 0 && <>
                            <p style={sx('margin:0;font-size:16px;font-weight:600;line-height:1.8;color:#002623;text-wrap:pretty')}>الرجاء احضار الاوراق المطلوبة الى العدلية الموافق لها</p>
                            <ol style={sx('list-style:none;margin:16px 0 0;padding:0;display:flex;flex-direction:column;border-top:1px solid #EAE5D8')}>
                                {docs.map((doc) => (
                                    <li key={doc.n} style={sx('display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #EAE5D8')}>
                                        <span aria-hidden="true" style={sx('flex-shrink:0;width:26px;height:26px;border-radius:50%;border:1px solid #B9A779;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:#054239;font-variant-numeric:tabular-nums')}>{doc.n}</span>
                                        <span style={sx('display:flex;flex-direction:column;gap:2px;padding-top:1px')}>
                                            <span style={sx('font-size:15px;font-weight:600;line-height:1.6;color:#002623')}>{doc.title}</span>
                                            {doc.notes.map((nt) => <span key={nt} style={sx('font-size:13px;line-height:1.6;color:#4F5D58')}>{nt}</span>)}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                            </>}
                            {this.state.comp?.primary_note && <DocsNote text={this.state.comp.primary_note} />}
                            <div style={sx('margin-top:20px;display:flex;flex-wrap:wrap;gap:10px')}>
                                <button type="button" className="h-dark" onClick={this.closeCongrats} style={sx('flex:1 1 160px;height:48px;background:#002623;color:#EDEBE0;border:0;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms')}>حسناً</button>
                                {docs.length > 0 && <button type="button" className="h-outline" onClick={() => { this.setState({ congrats: null }); this.openDocs(); }} style={sx('flex:1 1 160px;height:48px;background:transparent;color:#002623;border:1px solid #002623;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms, color 200ms')}>صفحة الأوراق المطلوبة</button>}
                                {groupLink}
                            </div>
                        </div>
                    ) : (
                        <div style={sx('padding:24px clamp(18px,4vw,28px) 26px')}>
                            <div style={sx('display:flex;gap:12px;align-items:flex-start;background:#FAF7EF;border:1px solid rgba(152,133,97,.3);border-radius:6px;padding:16px 18px')}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7A6843" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true" style={sx('flex-shrink:0;margin-top:5px')}><circle cx="12" cy="12" r="9" /><path d="M12 11v5.5" /><path d="M12 7.6v.1" /></svg>
                                <p style={sx('margin:0;font-size:16px;line-height:1.9;color:#3F3A2E;text-wrap:pretty')}><strong style={sx('color:#002623;font-weight:700')}>المقبول الاحتياطي:</strong> هو الناجح والمؤهل للتعاقد معه لحين الحاجة أو اعتذار أحد المقبولين الأساسيين</p>
                            </div>
                            <div style={sx('margin-top:20px;display:flex;flex-wrap:wrap;gap:10px')}>
                                <button type="button" className="h-dark" onClick={this.closeCongrats} style={sx('flex:1 1 auto;height:48px;background:#002623;color:#EDEBE0;border:0;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background 200ms')}>حسناً</button>
                                {groupLink}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        );
    }

    render() {
        const s = this.state, d = s.data;
        const resGov = d ? d.governorate : s.gov, resTitle = d ? d.jobTitle : s.title;
        const fromResults = s.docsFrom === 'results';

        return (
            <div id="top" dir="rtl" lang="ar" style={sx('min-height:100vh;display:flex;flex-direction:column;background:#EDEBE0')}>
                {s.splash && this.renderSplash()}

                <div style={sx(`flex:1;display:flex;flex-direction:column;opacity:${s.revealed ? 1 : 0};transform:${s.revealed ? 'none' : 'translateY(12px)'};transition:opacity 700ms cubic-bezier(.22,.61,.36,1), transform 700ms cubic-bezier(.22,.61,.36,1)`)}>
                    <header style={sx('background:#002623;border-bottom:1px solid rgba(185,167,121,.4)')}>
                        <div style={sx('max-width:1200px;margin:0 auto;padding:14px clamp(16px,4vw,40px);display:flex;align-items:center;justify-content:space-between;gap:16px')}>
                            <a href="#top" onClick={this.goHome} aria-label="وزارة العدل — مديرية التنمية الإدارية" style={sx('display:flex;align-items:center;gap:clamp(12px,2vw,20px);text-decoration:none;min-width:0')}>
                                <img src="assets/syrian-logo.png" alt="شعار الجمهورية العربية السورية" style={sx('height:clamp(40px,5vw,54px);width:auto;display:block;flex-shrink:0')} />
                                <span aria-hidden="true" style={sx('width:1px;height:38px;background:rgba(185,167,121,.45);flex-shrink:0')} />
                                <span style={sx('display:flex;flex-direction:column;gap:2px;min-width:0')}>
                                    <span style={sx("font-family:'Qomra',sans-serif;font-weight:700;font-size:clamp(17px,2vw,21px);line-height:1.3;color:#EDEBE0")}>وزارة العدل</span>
                                    <span style={sx('font-size:13px;line-height:1.5;color:#B9A779;white-space:nowrap')}>مديرية التنمية الإدارية</span>
                                </span>
                            </a>
                            <a href="#top" className="h-gold" onClick={this.goHome} aria-label="الصفحة الرئيسية" style={sx('display:flex;align-items:center;gap:8px;height:44px;padding:0 14px;border:1px solid rgba(185,167,121,.4);border-radius:6px;color:#EDEBE0;text-decoration:none;font-size:14px;white-space:nowrap;flex-shrink:0;transition:background 200ms, border-color 200ms')}>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /><path d="M10 21v-6h4v6" /></svg>
                                {s.wide && <span>الصفحة الرئيسية</span>}
                            </a>
                        </div>
                    </header>

                    {s.view === 'query' && this.renderHero()}

                    {s.view === 'results' && this.renderSubHero({
                        id: 'rp-title',
                        crumbs: [['الرئيسية', this.goHome], [s.comp?.title || 'النتائج', this.editSearch], ['أسماء المقبولين']],
                        title: 'أسماء المقبولين',
                        action: this.editSearch, actionLabel: 'تعديل البحث',
                        children: (
                            <dl style={sx('margin:0;display:flex;flex-wrap:wrap;gap:8px 28px;font-size:15px')}>
                                <div style={sx('display:flex;gap:6px')}><dt style={sx('color:#B9A779')}>المحافظة:</dt><dd style={sx('margin:0;font-weight:600;color:#EDEBE0')}>{resGov}</dd></div>
                                <div style={sx('display:flex;gap:6px')}><dt style={sx('color:#B9A779')}>المسمى الوظيفي:</dt><dd style={sx('margin:0;font-weight:600;color:#EDEBE0')}>{resTitle}</dd></div>
                            </dl>
                        ),
                    })}

                    {s.view === 'docs' && this.renderSubHero({
                        id: 'docs-title',
                        crumbs: [['الرئيسية', this.goHome], ...(fromResults ? [['أسماء المقبولين', this.closeDocs]] : []), ['الأوراق المطلوبة']],
                        title: 'الأوراق المطلوبة',
                        subtitle: 'للمقبولين الأساسيين في ' + (s.comp?.title || 'المسابقة'),
                        action: this.closeDocs, actionLabel: fromResults ? 'العودة إلى النتائج' : 'العودة',
                    })}

                    <main style={sx('flex:1;padding:0 clamp(12px,4vw,40px)')}>
                        <div style={sx('max-width:980px;margin:clamp(-104px,-9vw,-88px) auto 0;position:relative;z-index:2')}>
                            {s.view === 'query' && this.renderQuery()}

                            {s.view === 'results' && s.status !== 'idle' && (
                                <section ref={this.resultsRef} aria-labelledby="res-title" aria-live="polite" aria-busy={s.status === 'loading'}
                                    style={sx('background:#FBFAF6;border:1px solid rgba(152,133,97,.3);border-radius:8px;padding:clamp(18px,3.5vw,36px);box-shadow:0 1px 2px rgba(0,38,35,.04),0 22px 44px -26px rgba(0,38,35,.32);animation:fade-up 300ms cubic-bezier(.22,.61,.36,1) both')}>
                                    {s.status === 'loading' && this.renderLoading()}
                                    {s.status === 'error' && this.renderError()}
                                    {s.status === 'empty' && this.renderEmpty(resGov, resTitle)}
                                    {s.status === 'ok' && this.renderOk()}
                                </section>
                            )}

                            {s.view === 'docs' && this.renderDocs()}
                        </div>
                    </main>

                    {s.congrats && this.renderCongrats()}

                    <footer style={sx('margin-top:clamp(64px,8vw,104px);background:#002623;border-top:1px solid rgba(185,167,121,.4);color:#EDEBE0')}>
                        <div style={sx('max-width:1200px;margin:0 auto;padding:clamp(28px,4vw,40px) clamp(16px,4vw,40px) 0;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:24px')}>
                            <div style={sx('display:flex;align-items:center;gap:20px;flex-wrap:wrap')}>
                                <img src="assets/syrian-logo.png" alt="شعار الجمهورية العربية السورية" style={sx('height:52px;width:auto;display:block')} />
                                <span aria-hidden="true" style={sx('width:1px;height:44px;background:rgba(185,167,121,.35)')} />
                                <div style={sx('display:flex;flex-direction:column;gap:4px')}>
                                    <span style={sx("font-family:'Qomra',sans-serif;font-weight:700;font-size:17px;line-height:1.4")}>وزارة العدل — مديرية التنمية الإدارية</span>
                                    <span style={sx('font-size:14px;color:#B9A779')}>الجمهورية العربية السورية</span>
                                    <span style={sx('font-size:14px;color:rgba(237,235,224,.82)')}>بوابة نتائج المسابقات</span>
                                </div>
                            </div>
                            <button type="button" className="h-gold" onClick={this.toTop} style={sx('height:44px;padding:0 16px;display:flex;align-items:center;gap:8px;background:transparent;border:1px solid rgba(185,167,121,.4);border-radius:6px;color:#EDEBE0;font-size:14px;cursor:pointer;transition:background 200ms, border-color 200ms')}>
                                <span>العودة إلى أعلى الصفحة</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B9A779" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 19V5" /><path d="m6 11 6-6 6 6" /></svg>
                            </button>
                        </div>
                        <div style={sx('max-width:1200px;margin:24px auto 0;padding:16px clamp(16px,4vw,40px) 22px;border-top:1px solid rgba(185,167,121,.18);font-size:13px;color:rgba(237,235,224,.72)')}>© {new Date().getFullYear()} جميع الحقوق محفوظة</div>
                    </footer>
                </div>
            </div>
        );
    }
}
