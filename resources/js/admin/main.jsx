import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/admin.css';
import App from './App';
import { ToastProvider } from './ui';

createRoot(document.getElementById('admin')).render(
    <ToastProvider>
        <App />
    </ToastProvider>
);
