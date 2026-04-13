// Derive the project root from the current URL so every page works regardless
// of its depth in the folder tree.
// e.g. http://localhost/simple-chat/front-end/html/chat.html
//   → origin  = http://localhost
//   → project = simple-chat
//   → BASE    = http://localhost/simple-chat
const _origin  = window.location.origin;
const _project = window.location.pathname.split('/')[1];   // 'simple-chat'
const _base    = `${_origin}/${_project}`;

export const API = {
    me:            `${_base}/api/me`,
    users:         `${_base}/api/users`,
    conversations: `${_base}/api/conversations`,
    messages:      `${_base}/api/messages`,
    send:          `${_base}/api/send`,
    settings:      `${_base}/api/settings`,
    // Password reset (no auth required)
    forgotPassword: `${_base}/api/auth/forgot-password`,
    resetPassword:  `${_base}/api/auth/reset-password`,
    // 2FA
    twoFaStatus:  `${_base}/api/2fa/status`,
    twoFaSetup:   `${_base}/api/2fa/setup`,
    twoFaEnable:  `${_base}/api/2fa/enable`,
    twoFaDisable: `${_base}/api/2fa`,
    twoFaVerify:  `${_base}/api/auth/2fa-verify`,
    // PATCH /api/messages/{id}  and  DELETE /api/messages/{id}
    message: (id) => `${_base}/api/messages/${id}`,
};

export async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...options,
    });
    let data = null;
    try {
        data = await res.json();
    } catch (e) {
        data = null;
    }
    return { res, data };
}

