// ── Auto Text / AI Messaging ──

let autoTextEmail = '';
let autoTextPhone = '';

async function engageAI(email) {
    autoTextEmail = email;
    autoTextPhone = '';

    // Reset modal states
    document.getElementById('autoTextLoading').style.display = 'block';
    document.getElementById('autoTextEditor').style.display = 'none';
    document.getElementById('autoTextSuccess').style.display = 'none';
    document.getElementById('autoTextError').style.display = 'none';
    document.getElementById('autoTextSendBtn').style.display = 'none';
    document.getElementById('autoTextLeadInfo').style.display = 'none';
    document.getElementById('autoTextFooter').style.display = '';

    // Open modal
    const modal = new bootstrap.Modal(document.getElementById('autoTextModal'));
    modal.show();

    // Generate preview
    try {
        const res = await fetch(API + '?action=engage_ai_preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        });
        const result = await res.json();

        if (result.success) {
            autoTextPhone = result.phone;
            document.getElementById('autoTextLeadName').textContent = result.name || 'Lead';
            document.getElementById('autoTextLeadPhone').textContent = result.phone || '';
            document.getElementById('autoTextLeadInfo').style.display = 'flex';
            document.getElementById('autoTextBody').value = result.message;
            updateAutoTextCharCount();
            document.getElementById('autoTextLoading').style.display = 'none';
            document.getElementById('autoTextEditor').style.display = 'block';
            document.getElementById('autoTextSendBtn').style.display = '';
        } else {
            document.getElementById('autoTextLoading').style.display = 'none';
            document.getElementById('autoTextError').textContent = result.error || 'Could not generate message';
            document.getElementById('autoTextError').style.display = 'block';
        }
    } catch(err) {
        document.getElementById('autoTextLoading').style.display = 'none';
        document.getElementById('autoTextError').textContent = 'Network error — please try again';
        document.getElementById('autoTextError').style.display = 'block';
    }
}

async function sendAutoText() {
    const body = document.getElementById('autoTextBody').value.trim();
    if (!body) return;

    const sendBtn = document.getElementById('autoTextSendBtn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

    try {
        const res = await fetch(API + '?action=engage_ai_send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: autoTextEmail, phone: autoTextPhone, body: body })
        });
        const result = await res.json();

        if (result.success) {
            document.getElementById('autoTextEditor').style.display = 'none';
            document.getElementById('autoTextFooter').style.display = 'none';
            document.getElementById('autoTextSuccess').style.display = 'block';
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('autoTextModal')).hide();
                // Refresh leads table if available
                if (typeof fetchLeadsData === 'function') fetchLeadsData();
                if (typeof fetchData === 'function') fetchData();
            }, 1500);
        } else {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
            document.getElementById('autoTextError').textContent = result.error || 'Send failed';
            document.getElementById('autoTextError').style.display = 'block';
        }
    } catch(err) {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
        document.getElementById('autoTextError').textContent = 'Network error';
        document.getElementById('autoTextError').style.display = 'block';
    }
}

function updateAutoTextCharCount() {
    const len = document.getElementById('autoTextBody').value.length;
    const segments = Math.ceil(len / 160) || 1;
    document.getElementById('autoTextCharCount').textContent = len + ' / ' + (segments * 160);
}

document.addEventListener('DOMContentLoaded', function() {
    const ta = document.getElementById('autoTextBody');
    if (ta) ta.addEventListener('input', updateAutoTextCharCount);
});
