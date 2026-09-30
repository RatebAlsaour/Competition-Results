import React from 'react';
import sx from './sx';

// الزخرفة الجانبية (يمين ويسار)
export function PatternSides({ width, opacity, fade, anim }) {
    const side = (dir) => {
        const toward = dir === 'right' ? 'left' : 'right';
        return (
            <div aria-hidden="true" data-anim={anim ? '1' : undefined}
                style={sx(`position:absolute;top:0;bottom:0;${dir}:0;width:${width};background:url('assets/pattern.png') ${dir} top / 100% auto repeat-y;opacity:${opacity};-webkit-mask-image:linear-gradient(to ${toward},#000 ${fade},transparent);mask-image:linear-gradient(to ${toward},#000 ${fade},transparent)${anim ? ';animation:' + anim : ''}`)} />
        );
    };
    return <>{side('right')}{side('left')}</>;
}

// مسار التنقل: [[label, onClick?], ...] — العنصر الأخير هو الصفحة الحالية
export function Crumbs({ items }) {
    return (
        <nav aria-label="مسار التنقل">
            <ol style={sx('list-style:none;margin:0;padding:0;display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:13px')}>
                {items.map(([label, onClick], i) => {
                    const last = i === items.length - 1;
                    return (
                        <React.Fragment key={label}>
                            {last
                                ? <li aria-current="page" style={sx('color:#EDEBE0')}>{label}</li>
                                : <li><a href="#top" className="h-light" onClick={onClick} style={sx('color:#B9A779;text-decoration:none')}>{label}</a></li>}
                            {!last && <li aria-hidden="true" style={sx('color:rgba(185,167,121,.6)')}>/</li>}
                        </React.Fragment>
                    );
                })}
            </ol>
        </nav>
    );
}

export function StatusBadge({ primary, row }) {
    const pad = row ? '0 12px' : '0 10px';
    return primary ? (
        <span style={sx(`flex-shrink:0;display:inline-flex;align-items:center;gap:6px;height:28px;padding:${pad};border-radius:14px;background:#054239;color:#EDEBE0;font-size:13px;font-weight:600;white-space:nowrap`)}>
            <span aria-hidden="true" style={sx('width:6px;height:6px;border-radius:50%;background:#B9A779')} />أساسي
        </span>
    ) : (
        <span style={sx(`flex-shrink:0;display:inline-flex;align-items:center;gap:6px;height:28px;padding:${pad};border-radius:14px;background:#FFFFFF;border:1px solid #B9A779;color:#6E5E3E;font-size:13px;font-weight:600;white-space:nowrap`)}>
            <span aria-hidden="true" style={sx('width:6px;height:6px;border-radius:50%;border:1.5px solid #988561')} />احتياطي
        </span>
    );
}

export function DocsNote({ text }) {
    return (
        <div style={sx('margin-top:16px;display:flex;gap:10px;align-items:flex-start;background:#F3EEE1;border-radius:6px;padding:12px 14px;font-size:14px;line-height:1.8;color:#3F3A2E')}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7A6843" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={sx('flex-shrink:0;margin-top:4px')}><rect x="3.5" y="5" width="17" height="15.5" rx="1.5" /><path d="M3.5 10h17" /><path d="M8 3v4" /><path d="M16 3v4" /></svg>
            <span style={sx('white-space:pre-line')}>{text}</span>
        </div>
    );
}
