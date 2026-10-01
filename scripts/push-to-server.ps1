# إرسال التحديث من جهاز المطور إلى السيرفر عبر الشبكة الداخلية (عندما لا يصل السيرفر إلى الإنترنت).
# الاستخدام (PowerShell داخل مجلد المشروع):
#   powershell -ExecutionPolicy Bypass -File scripts/push-to-server.ps1
#   powershell -ExecutionPolicy Bypass -File scripts/push-to-server.ps1 -Deploy     # ويشغّل التحديث على السيرفر أيضاً
# يطلب كلمة مرور السيرفر عند كل اتصال (git push، scp، ssh).

param(
    [string]$Server = "hr@192.168.1.52",
    [string]$RemotePath = "/opt/competition-results",
    [switch]$Deploy
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$dirty = git status --porcelain
if ($dirty) {
    Write-Host "يوجد تعديلات غير محفوظة (commit) — احفظها أولاً:" -ForegroundColor Yellow
    git status --short
    exit 1
}

Write-Host "==> 1/3 بناء ملفات الواجهة (npm run build)" -ForegroundColor Cyan
npm run build
if ($LASTEXITCODE -ne 0) { throw "فشل npm run build" }

Write-Host "==> 2/3 إرسال الكود (git push server main)" -ForegroundColor Cyan
git push server main
if ($LASTEXITCODE -ne 0) { throw "فشل git push server — هل أضفت الـ remote؟ git remote add server ssh://$Server$RemotePath" }

Write-Host "==> 3/3 إرسال ملفات الواجهة (scp public/build)" -ForegroundColor Cyan
scp -r public/build "${Server}:${RemotePath}/public/"
if ($LASTEXITCODE -ne 0) { throw "فشل scp" }

if ($Deploy) {
    Write-Host "==> تشغيل التحديث على السيرفر" -ForegroundColor Cyan
    ssh -t $Server "cd $RemotePath && bash scripts/deploy-docker-server.sh main"
} else {
    Write-Host ""
    Write-Host "تم. الآن على السيرفر:" -ForegroundColor Green
    Write-Host "  cd $RemotePath && bash scripts/deploy-docker-server.sh main"
}
