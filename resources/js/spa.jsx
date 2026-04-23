import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import App from './App.jsx';
import '../css/spa.css';

/**
 * SPA entry point — mounted by resources/views/spa.blade.php into <div id="app" />.
 * All non-API routes fall through to the BrowserRouter below (see routes/web.php
 * catch-all route at the bottom of the file).
 */
ReactDOM.createRoot(document.getElementById('app')).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
      <Toaster position="top-right" />
    </BrowserRouter>
  </React.StrictMode>
);
