// Brokers page JavaScript

function renderBrokersTable(brokers) {
    const tbody = document.querySelector('#brokersTable tbody');
    if (!tbody) return;
    if (brokers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-body-tertiary py-5">No brokers added yet.</td></tr>';
        return;
    }
    tbody.innerHTML = '';
    brokers.forEach(function(broker) {
        const statusBadge = broker.status === 'inactive'
            ? '<span class="badge bg-secondary">Inactive</span>'
            : '<span class="badge bg-success">Active</span>';
        const row = document.createElement('tr');
        row.innerHTML = '<td class="fw-semibold text-white">' + esc(broker.name) + '</td>'
            + '<td>' + esc(broker.email) + '</td>'
            + '<td>' + esc(broker.phone || '-') + '</td>'
            + '<td><span class="text-capitalize">' + esc(broker.role || 'broker') + '</span></td>'
            + '<td>' + statusBadge + '</td>'
            + '<td class="text-end">'
            + '<button class="btn btn-sm btn-outline-primary me-1" onclick="showBrokerModal(' + esc(String(broker.id)) + ')"><i class="bi bi-pencil"></i></button>'
            + '<button class="btn btn-sm btn-outline-danger" onclick="deleteBroker(' + esc(String(broker.id)) + ')"><i class="bi bi-trash"></i></button>'
            + '</td>';
        tbody.appendChild(row);
    });
}

function showBrokerModal(brokerId) {
    const modal = new bootstrap.Modal(document.getElementById('brokerModal'));
    document.getElementById('brokerEditId').value = '';
    document.getElementById('brokerName').value = '';
    document.getElementById('brokerEmail').value = '';
    document.getElementById('brokerPhone').value = '';
    document.getElementById('brokerRole').value = 'broker';
    document.getElementById('brokerModalTitle').textContent = 'Add Broker';

    const inviteNote = document.getElementById('brokerInviteNote');

    if (brokerId) {
        const broker = brokersCache.find(b => b.id == brokerId);
        if (broker) {
            document.getElementById('brokerEditId').value = broker.id;
            document.getElementById('brokerName').value = broker.name || '';
            document.getElementById('brokerEmail').value = broker.email || '';
            document.getElementById('brokerPhone').value = broker.phone || '';
            document.getElementById('brokerRole').value = broker.role || 'broker';
            document.getElementById('brokerModalTitle').textContent = 'Edit Broker';
        }
        if (inviteNote) inviteNote.style.display = 'none';
    } else {
        if (inviteNote) inviteNote.style.display = 'block';
    }
    modal.show();
}

async function saveBroker() {
    const id = document.getElementById('brokerEditId').value;
    const payload = {
        name: document.getElementById('brokerName').value.trim(),
        email: document.getElementById('brokerEmail').value.trim(),
        phone: document.getElementById('brokerPhone').value.trim(),
        role: document.getElementById('brokerRole').value
    };

    if (!payload.name || !payload.email) {
        alert('Name and email are required.');
        return;
    }


    try {
        const action = id ? 'update_broker' : 'add_broker';
        if (id) payload.id = id;
        const res = await fetch('/api/admin-api.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('brokerModal')).hide();
            fetchBrokers(renderBrokersTable);
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while saving broker.');
    }
}

async function deleteBroker(id) {
    if (!confirm('Are you sure you want to delete this broker?')) return;
    try {
        const res = await fetch('/api/admin-api.php?action=delete_broker', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        if (result.success) {
            fetchBrokers(renderBrokersTable);
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Connection error while deleting broker.');
    }
}

document.addEventListener('DOMContentLoaded', () => fetchBrokers(renderBrokersTable));
