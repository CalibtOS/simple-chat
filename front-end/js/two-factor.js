const form      = document.getElementById('twoFaForm');
const codeInput = document.getElementById('code');
const errorBox  = document.getElementById('errorBox');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';
    submitBtn.disabled     = true;
    submitBtn.textContent  = 'Verifying…';

    try {
        const res  = await fetch('../../api/auth/2fa-verify', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ code: codeInput.value.trim() }),
        });
        const data = await res.json();

        if (!res.ok) {
            errorBox.textContent   = data.error ?? 'Invalid code.';
            errorBox.style.display = 'block';
            codeInput.value        = '';
            codeInput.focus();
        } else {
            window.location.href = 'chat.html';
        }
    } catch {
        errorBox.textContent   = 'Network error. Please try again.';
        errorBox.style.display = 'block';
    }

    submitBtn.disabled    = false;
    submitBtn.textContent = 'Verify';
});

