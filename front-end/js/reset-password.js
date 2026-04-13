import { API, fetchJson } from './api-client.js';

const form        = document.getElementById('resetForm');
const passwordIn  = document.getElementById('password');
const confirmIn   = document.getElementById('confirm');
const errorBox    = document.getElementById('errorBox');
const successBox  = document.getElementById('successBox');
const submitBtn   = document.getElementById('submitBtn');

// Pull the token from the URL: reset-password.html?token=abc123
const token = new URLSearchParams(window.location.search).get('token') ?? '';

if (!token) {
    errorBox.textContent   = 'Invalid or missing reset token. Please request a new link.';
    errorBox.style.display = 'block';
    form.style.display     = 'none';
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display   = 'none';
    successBox.style.display = 'none';
    submitBtn.disabled       = true;
    submitBtn.textContent    = 'Updating…';

    try {
        const { res, data } = await fetchJson(API.resetPassword, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                token,
                password:         passwordIn.value,
                password_confirm: confirmIn.value,
            }),
        });

        if (!res.ok) {
            errorBox.textContent   = data?.error ?? `Server error (${res.status}).`;
            errorBox.style.display = 'block';
        } else {
            successBox.textContent   = data.message + ' Redirecting to login…';
            successBox.style.display = 'block';
            form.style.display       = 'none';
            setTimeout(() => { window.location.href = 'login.html'; }, 2500);
        }
    } catch (err) {
        errorBox.textContent   = 'Could not reach the server. Is XAMPP running?';
        errorBox.style.display = 'block';
        console.error('Reset password error:', err);
    }

    submitBtn.disabled    = false;
    submitBtn.textContent = 'Update password';
});
