#!/usr/bin/env bash
# الزوار يصلون عبر بروكسي أمام السيرفر (مثل Caddy في شبكة الوزارة)، فيرى السيرفر كل الزوار
# بعنوان البروكسي. هذا يجعل تحديد المعدل وقفل الحساب والسجل الأمني تعامل الجميع كشخص واحد.
# السكربت يجعل Nginx المضيف يأخذ عنوان الزائر الحقيقي من ترويسة X-Forwarded-For
# — فقط عندما يأتي الطلب من عنوان البروكسي الموثوق (لا يستطيع زائر عادي تزوير عنوانه).
#
# الاستخدام:
#   sudo bash scripts/setup-real-ip.sh              # يكتشف عنوان البروكسي من سجل nginx ويسأل للتأكيد
#   sudo bash scripts/setup-real-ip.sh 192.168.1.10 # تحديد عنوان البروكسي مباشرة
#   sudo bash scripts/setup-real-ip.sh --remove     # التراجع

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "شغّله بـ sudo: sudo bash $0" >&2
    exit 1
fi

CONF=/etc/nginx/conf.d/01-real-ip.conf
LOG=/var/log/nginx/access.log

if [[ "${1:-}" == "--remove" ]]; then
    rm -f "$CONF"
    nginx -t && systemctl reload nginx
    echo "تم التراجع: السيرفر يرى عنوان البروكسي كما كان."
    exit 0
fi

PROXY_IP="${1:-}"

if [[ -z "$PROXY_IP" ]]; then
    if [[ ! -r "$LOG" ]]; then
        echo "تعذر قراءة $LOG — حدّد عنوان البروكسي يدوياً: sudo bash $0 <IP>" >&2
        exit 1
    fi
    echo "== العناوين التي أرسلت آخر 2000 طلب إلى nginx:"
    tail -n 2000 "$LOG" | awk '{print $1}' | sort | uniq -c | sort -rn | head -5
    PROXY_IP="$(tail -n 2000 "$LOG" | awk '{print $1}' | grep -vE '^(127\.|::1)' | sort | uniq -c | sort -rn | awk 'NR==1{print $2}')"
    if [[ -z "$PROXY_IP" ]]; then
        echo "لم يُعثر على عنوان. حدّده يدوياً: sudo bash $0 <IP>" >&2
        exit 1
    fi
    echo
    echo "العنوان الأكثر تكراراً: $PROXY_IP"
    echo "إن كان هذا عنوان بروكسي الوزارة (وليس جهاز مستخدم عادي) اكتب yes للمتابعة."
    read -r -p "متابعة؟ [yes/no] " answer
    [[ "$answer" == "yes" ]] || { echo "أُلغي. يمكنك التحديد يدوياً: sudo bash $0 <IP>"; exit 1; }
fi

if ! [[ "$PROXY_IP" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}(/[0-9]{1,2})?$ ]]; then
    echo "عنوان غير صالح: $PROXY_IP" >&2
    exit 1
fi

cat > "$CONF" <<EOF
# عنوان الزائر الحقيقي من X-Forwarded-For، فقط عندما يأتي الطلب من البروكسي الموثوق.
# أنشأه scripts/setup-real-ip.sh — للتراجع: sudo bash scripts/setup-real-ip.sh --remove
set_real_ip_from ${PROXY_IP};
real_ip_header X-Forwarded-For;
real_ip_recursive on;
EOF

if ! nginx -t; then
    rm -f "$CONF"
    echo "الإعداد غير صالح — تم التراجع ولم يتغير شيء." >&2
    exit 1
fi
systemctl reload nginx

echo
echo "تم. البروكسي الموثوق: $PROXY_IP"
echo "التحقق: افتح الموقع من متصفحك ثم نفّذ:"
echo "  sudo tail -n 3 $LOG"
echo "يجب أن يظهر في أول السطر عنوان جهازك أنت، لا $PROXY_IP."
