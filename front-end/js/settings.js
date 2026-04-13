import { API, fetchJson } from './api-client.js';

// ─── 2FA UI ───────────────────────────────────────────────────────────────────

const twoFaOff        = document.getElementById('twoFaOff');
const twoFaSetup      = document.getElementById('twoFaSetup');
const twoFaOn         = document.getElementById('twoFaOn');
const twoFaQr         = document.getElementById('twoFaQr');
const twoFaAlert      = document.getElementById('twoFaAlert');
const twoFaEnableForm = document.getElementById('twoFaEnableForm');
const twoFaCode       = document.getElementById('twoFaCode');
const setup2faBtn     = document.getElementById('setup2faBtn');
const cancel2faBtn    = document.getElementById('cancel2faBtn');
const disable2faBtn   = document.getElementById('disable2faBtn');

function show2faState(state) {
    twoFaOff.hidden   = state !== 'off';
    twoFaSetup.hidden = state !== 'setup';
    twoFaOn.hidden    = state !== 'on';
}

function show2faAlert(message, isError = false) {
    twoFaAlert.textContent = message;
    twoFaAlert.className   = 'settings-alert ' + (isError ? 'settings-alert-error' : 'settings-alert-success');
    twoFaAlert.hidden      = false;
}

async function load2faStatus() {
    const { res, data } = await fetchJson(API.twoFaStatus);
    if (!res.ok) return;
    show2faState(data.enabled ? 'on' : 'off');
}

setup2faBtn?.addEventListener('click', async () => {
    setup2faBtn.disabled    = true;
    setup2faBtn.textContent = 'Loading…';
    try {
        const { res, data } = await fetchJson(API.twoFaSetup);
        if (!res.ok) {
            show2faAlert(data?.error ?? 'Could not start setup. Check the browser console.', true);
            console.error('2FA setup error:', res.status, data);
            return;
        }
        twoFaQr.src ='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='
                      + encodeURIComponent(data.otpauth_url);
        twoFaAlert.hidden = true;
        twoFaCode.value   = '';
        show2faState('setup');
    } catch (err) {
        show2faAlert('Network error — could not reach server.', true);
        console.error('2FA setup exception:', err);
    } finally {
        setup2faBtn.disabled    = false;
        setup2faBtn.textContent = 'Set up 2FA';
    }
});

cancel2faBtn?.addEventListener('click', () => show2faState('off'));

twoFaEnableForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    twoFaAlert.hidden = true;
    try {
        const { res, data } = await fetchJson(API.twoFaEnable, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ code: twoFaCode.value.trim() }),
        });
        if (!res.ok) {
            show2faAlert(data?.error ?? 'Invalid code.', true);
            twoFaCode.value = '';
            return;
        }
        show2faState('on');
    } catch (err) {
        show2faAlert('Network error — could not reach server.', true);
        console.error('2FA enable exception:', err);
    }
});

disable2faBtn?.addEventListener('click', async () => {
    if (!confirm('Disable two-factor authentication? Your account will be less secure.')) return;
    try {
        const { res, data } = await fetchJson(API.twoFaDisable, { method: 'DELETE' });
        if (!res.ok) {
            show2faAlert(data?.error ?? 'Could not disable 2FA.', true);
            return;
        }
        show2faState('off');
    } catch (err) {
        show2faAlert('Network error — could not reach server.', true);
        console.error('2FA disable exception:', err);
    }
});

const form          = document.getElementById('settingsForm');
const nameInput     = document.getElementById('settingsName');
const emailInput    = document.getElementById('settingsEmail');
const alertBox      = document.getElementById('settingsAlert');
const saveBtn       = document.getElementById('saveBtn');
const sidebarName   = document.getElementById('sidebarUserName');
const sidebarRole   = document.getElementById('sidebarUserRole');
const avatarInput   = document.getElementById('avatarInput');
const avatarPreview = document.getElementById('avatarPreview');

// ── Avatar preview (local only — no upload endpoint yet) ──────────────────────
avatarInput.addEventListener('change', () => {
    const file = avatarInput.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    avatarPreview.src = url;
});

// ── Alert helper ──────────────────────────────────────────────────────────────
function showAlert(message, isError = false) {
    alertBox.textContent = message;
    alertBox.className   = 'settings-alert ' + (isError ? 'settings-alert-error' : 'settings-alert-success');
    alertBox.hidden      = false;
}

// ── Load current values into the form ────────────────────────────────────────
async function loadCurrentSettings() {
    const { res, data } = await fetchJson(API.settings);
    if (res.status === 401) {
        window.location.href = 'login.html';
        return;
    }
    if (!res.ok) return;

    nameInput.value  = data.name  ?? '';
    emailInput.value = data.email ?? '';

    if (sidebarName) sidebarName.textContent = data.name  ?? '';
    if (sidebarRole) sidebarRole.textContent = data.email ?? '';
}

// ── Save ──────────────────────────────────────────────────────────────────────
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    alertBox.hidden      = true;
    saveBtn.disabled     = true;
    saveBtn.textContent  = 'Saving…';

    const { res, data } = await fetchJson(API.settings, {
        method:  'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            name:  nameInput.value.trim(),
            email: emailInput.value.trim(),
        }),
    });

    saveBtn.disabled    = false;
    saveBtn.textContent = 'Save changes';

    if (res.status === 401) {
        window.location.href = 'login.html';
        return;
    }

    if (!res.ok) {
        showAlert(data?.error ?? 'Could not save settings.', true);
        return;
    }

    // Refresh sidebar and inputs with the values the server confirmed.
    if (sidebarName) sidebarName.textContent = data.name;
    if (sidebarRole) sidebarRole.textContent = data.email;
    nameInput.value  = data.name;
    emailInput.value = data.email;

    showAlert('Settings saved!');
});

loadCurrentSettings();
load2faStatus();
