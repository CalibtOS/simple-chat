export function formatTime(dateStr) {
    const d = new Date(dateStr);
    const h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? 'pm' : 'am';
    return (h % 12 || 12) + ':' + String(m).padStart(2, '0') + ampm;
}

export function shortenMessage(text, max = 40) {
    const msg = String(text ?? '');
    return msg.length > max ? msg.slice(0, max - 3) + '...' : msg;
}

