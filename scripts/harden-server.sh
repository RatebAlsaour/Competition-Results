#!/usr/bin/env bash
# تأمين سيرفر Ubuntu الذي يشغّل بوابة النتائج. آمن لإعادة التشغيل أكثر من مرة.
# الاستخدام: sudo bash scripts/harden-server.sh
#
# ما يفعله:
#   1. جدار ناري (ufw): يسمح فقط بـ SSH ومنفذ الموقع، ويمنع كل شيء آخر
#   2. fail2ban: حظر تلقائي لمن يخمّن كلمات مرور SSH
#   3. تحديثات الأمان التلقائية للنظام
#   4. إخفاء نسخة nginx، وإعدادات شبكة ضد هجمات الإغراق
# لا يغيّر طريقة دخولك SSH (لا يعطّل كلمة المرور) حتى لا تُقفل خارج السيرفر.

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "شغّله بـ sudo: sudo bash $0" >&2
    exit 1
fi

SITE_PORT="${SITE_PORT:-8052}"
SSH_PORT="$(sshd -T 2>/dev/null | awk '/^port /{print $2; exit}')"
SSH_PORT="${SSH_PORT:-22}"

echo "==> منفذ SSH: ${SSH_PORT} | منفذ الموقع: ${SITE_PORT}"

# تثبيت حزمة إن لم تكن موجودة؛ إن كان السيرفر بلا إنترنت نتخطاها ونكمل الباقي
ensure_pkg() {
    dpkg -s "$1" >/dev/null 2>&1 && return 0
    timeout 120 apt-get install -y "$1" >/dev/null 2>&1 && return 0
    echo "    ⚠ تعذر تثبيت $1 (لا إنترنت؟) — تم تخطي هذا الجزء، أعد تشغيل السكربت عند توفر الإنترنت"
    return 1
}

# ---------------------------------------------------------------- 1. ufw
echo "==> [1/4] الجدار الناري (ufw)"
if ensure_pkg ufw; then
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw limit "${SSH_PORT}/tcp" comment 'SSH (rate limited)' >/dev/null   # limit = يحظر من يفتح اتصالات كثيرة بسرعة
ufw allow "${SITE_PORT}/tcp" comment 'Competition Results' >/dev/null
if [[ "${ALLOW_HTTPS:-false}" == "true" ]]; then
    ufw allow 80/tcp comment 'HTTP (certbot)' >/dev/null
    ufw allow 443/tcp comment 'HTTPS' >/dev/null
fi
ufw --force enable >/dev/null
ufw status verbose | sed -n '1,20p'
fi
# ملاحظة: منافذ Docker (8060 و MySQL) مربوطة على 127.0.0.1 أو غير منشورة أصلاً، فلا يصلها أحد من الخارج.

# ---------------------------------------------------------------- 2. fail2ban
echo "==> [2/4] fail2ban"
if ensure_pkg fail2ban; then
cat > /etc/fail2ban/jail.d/competition-results.local <<EOF
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = ${SSH_PORT}

# من يُحظر مراراً يُحظر أسبوعاً
[recidive]
enabled  = true
logpath  = /var/log/fail2ban.log
backend  = auto
bantime  = 1w
findtime = 1d
maxretry = 3
EOF
systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban
sleep 2
fail2ban-client status | sed -n '1,5p'
fi

# ---------------------------------------------------------------- 3. updates
echo "==> [3/4] تحديثات الأمان التلقائية"
if ensure_pkg unattended-upgrades; then
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
systemctl enable --now unattended-upgrades >/dev/null
echo "    مفعّلة (تحديثات الأمان فقط، يومياً — تعمل عند توفر الإنترنت)"
fi

# ---------------------------------------------------------------- 4. nginx + network
echo "==> [4/4] nginx والشبكة"
if [[ -d /etc/nginx/conf.d ]]; then
    cat > /etc/nginx/conf.d/00-security.conf <<'EOF'
# إخفاء نسخة nginx ومهلات قصيرة ضد الاتصالات البطيئة (Slowloris)
server_tokens off;
client_header_timeout 15s;
client_body_timeout 30s;
send_timeout 30s;
EOF
    nginx -t && systemctl reload nginx
fi

cat > /etc/sysctl.d/99-competition-results.conf <<'EOF'
# حماية من إغراق SYN وتزوير العناوين
net.ipv4.tcp_syncookies = 1
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.icmp_echo_ignore_broadcasts = 1
# طوابير أكبر للضغط العالي
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
EOF
sysctl --system >/dev/null

echo
echo "==> تم. التحقق:"
echo "    sudo ufw status"
echo "    sudo fail2ban-client status sshd"
echo
echo "توصية: بعد التأكد أن الدخول بمفتاح SSH يعمل، عطّل الدخول بكلمة المرور (انظر deploy.md - قسم الحماية)."
