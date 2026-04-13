import { API, fetchJson } from './api-client.js';

const form       = document.getElementById('forgotForm');
const emailInput = document.getElementById('email');
const errorBox   = document.getElementById('errorBox');
const successBox = document.getElementById('successBox');
const submitBtn  = document.getElementById('submitBtn');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display   = 'none';
    successBox.style.display = 'none';
    submitBtn.disabled       = true;
    submitBtn.textContent    = 'Sending…';

    try {
        console.log('[debug] forgotPassword URL:', API.forgotPassword);
        const { res, data } = await fetchJson(API.forgotPassword, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ email: emailInput.value.trim() }),
        });

        if (!res.ok) {
            errorBox.textContent   = data?.error ?? `Server error (${res.status}).`;
            errorBox.style.display = 'block';
        } else {
            successBox.textContent   = data?.message ?? 'Reset link sent — check your email (or app.log on XAMPP).';
            successBox.style.display = 'block';
            form.style.display       = 'none';
        }
    } catch (err) {
        errorBox.textContent   = `Error: ${err.message}`;
        errorBox.style.display = 'block';
        console.error('Forgot password error:', err);
    }

    submitBtn.disabled    = false;
    submitBtn.textContent = 'Send reset link';
});
