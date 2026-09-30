import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/portal/main.jsx', 'resources/js/admin/main.jsx'],
            refresh: true,
        }),
        react(),
    ],
    build: {
        // الإبقاء على ملفات البناء السابقة: الزائر الذي لديه نسخة مخزنة من الصفحة
        // يبقى قادراً على تحميل ملفاتها بعد نشر تحديث جديد (لا صفحة بيضاء)
        emptyOutDir: false,
    },
});
