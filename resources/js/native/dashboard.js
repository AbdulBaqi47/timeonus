import './native.css';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
let keyboardEvents = 0;
let mouseEvents = 0;
let touchEvents = 0;
let lastHeartbeat = Date.now();

const attendanceStatusElement = document.getElementById('attendance-status');
const salarySummaryElement = document.getElementById('salary-summary');
const helpForm = document.getElementById('help-request-form');
const helpList = document.getElementById('help-requests');

function incrementCounters(eventType) {
    if (eventType === 'keyboard') {
        keyboardEvents += 1;
    } else if (eventType === 'mouse') {
        mouseEvents += 1;
    } else if (eventType === 'touch') {
        touchEvents += 1;
    }
}

['keydown', 'keypress'].forEach((event) => document.addEventListener(event, () => incrementCounters('keyboard')));
['mousemove', 'click', 'wheel'].forEach((event) => document.addEventListener(event, () => incrementCounters('mouse')));
['touchstart', 'touchmove'].forEach((event) => document.addEventListener(event, () => incrementCounters('touch')));

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() - lastHeartbeat > 120000) {
        sendHeartbeat();
    }
});

async function postJson(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({ message: response.statusText }));
        throw new Error(error.message || 'Request failed');
    }

    return response.json();
}

async function fetchJson(url) {
    const response = await fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({ message: response.statusText }));
        throw new Error(error.message || 'Request failed');
    }

    return response.json();
}

function renderAttendance(attendance) {
    if (!attendanceStatusElement || !attendance) {
        return;
    }

    attendanceStatusElement.querySelector('[data-field="status"]').textContent = attendance.status ?? 'unknown';
    attendanceStatusElement.querySelector('[data-field="login"]').textContent = attendance.login_at ?? '--';
    attendanceStatusElement.querySelector('[data-field="logout"]').textContent = attendance.logout_at ?? '--';
    attendanceStatusElement.querySelector('[data-field="work"]').textContent = formatDuration(attendance.total_work_seconds);
    attendanceStatusElement.querySelector('[data-field="idle"]').textContent = formatDuration(attendance.total_idle_seconds);
    attendanceStatusElement.querySelector('[data-field="effective"]').textContent = formatDuration(attendance.effective_work_seconds);
}

function renderSalary(summary) {
    if (!salarySummaryElement || !summary) {
        return;
    }

    salarySummaryElement.querySelector('[data-field="range"]').textContent = `${summary.range.start} → ${summary.range.end}`;
    salarySummaryElement.querySelector('[data-field="hours"]').textContent = summary.effective_hours.toFixed(2);
    salarySummaryElement.querySelector('[data-field="gross"]').textContent = currency(summary.gross_pay);
    salarySummaryElement.querySelector('[data-field="net"]').textContent = currency(summary.net_pay);
}

function renderHelpRequests(requests = []) {
    if (!helpList) {
        return;
    }

    helpList.innerHTML = '';

    requests.forEach((request) => {
        const li = document.createElement('li');
        li.className = 'border border-slate-200 rounded p-3 flex flex-col gap-1';
        li.innerHTML = `
            <div class="font-medium">${request.topic}</div>
            <div class="text-sm text-slate-500">${request.status} · ${request.requested_at ?? '--'}</div>
            <div class="text-xs text-slate-400">Duration: ${formatDuration(request.duration_seconds ?? 0)}</div>
        `;
        helpList.appendChild(li);
    });
}

function formatDuration(seconds = 0) {
    const total = Number(seconds ?? 0);
    const hrs = Math.floor(total / 3600);
    const mins = Math.floor((total % 3600) / 60);
    return `${hrs}h ${mins}m`;
}

function currency(amount = 0) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount ?? 0);
}

async function bootstrapAttendance() {
    try {
        const location = await resolveLocation();
        await postJson('/api/v1/attendance/login', { location });
        await refreshAttendance();
    } catch (error) {
        console.error('Failed to register attendance login', error);
    }
}

async function resolveLocation() {
    if (!('geolocation' in navigator)) {
        return null;
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    source: 'geolocation',
                });
            },
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 5000 }
        );
    });
}

async function sendHeartbeat() {
    try {
        const payload = {
            timestamp: new Date().toISOString(),
            metrics: {
                keyboard_events: keyboardEvents,
                mouse_events: mouseEvents,
                touch_events: touchEvents,
                active_window: document.title,
                active_process: 'timeonus-desktop',
            },
        };

        await postJson('/api/v1/activity/heartbeat', payload);
    } catch (error) {
        console.error('Heartbeat failed', error);
    } finally {
        keyboardEvents = 0;
        mouseEvents = 0;
        touchEvents = 0;
        lastHeartbeat = Date.now();
    }
}

async function refreshAttendance() {
    try {
        const { attendance } = await fetchJson('/api/v1/attendance/status');
        renderAttendance(attendance);
    } catch (error) {
        console.error('Attendance status failed', error);
    }
}

async function refreshSalary() {
    try {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString();
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString();
        const { summary } = await fetchJson(`/api/v1/salary/summary?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
        renderSalary(summary);
    } catch (error) {
        console.error('Salary summary failed', error);
    }
}

async function refreshHelpRequests() {
    try {
        const { help_request } = await fetchJson('/api/v1/help-requests/current');
        renderHelpRequests(help_request ? [help_request] : []);
    } catch (error) {
        console.error('Help request fetch failed', error);
    }
}

if (helpForm) {
    helpForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(helpForm);
        const payload = {
            topic: formData.get('topic'),
            primary_recipient_id: formData.get('recipient') || null,
            channel: 'desktop',
        };

        try {
            await postJson('/api/v1/help-requests', payload);
            helpForm.reset();
            await refreshHelpRequests();
        } catch (error) {
            alert(`Unable to send help request: ${error.message}`);
        }
    });
}

setInterval(sendHeartbeat, 60000);
setInterval(refreshAttendance, 60000);
setInterval(refreshSalary, 300000);
setInterval(refreshHelpRequests, 120000);

bootstrapAttendance().then(() => {
    refreshSalary();
    refreshHelpRequests();
});window.addEventListener('beforeunload', () => {
    if (!csrfToken) {
        return;
    }

    fetch('/api/v1/attendance/logout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        keepalive: true,
        body: JSON.stringify({ device: { name: 'timeonus-desktop' } }),
    }).catch(() => {});
});