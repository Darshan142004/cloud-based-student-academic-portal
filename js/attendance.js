let attendancePayload = null;
let breakdownChartInstance = null;
let comparisonChartInstance = null;
let defaultSemester = null;

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    const semesterFilter = document.getElementById('semesterFilter');
    const courseFilter = document.getElementById('courseFilter');
    const viewAllBtn = document.getElementById('viewAllSemestersBtn');
    const exportBtn = document.getElementById('exportAttendanceBtn');
    const resetBtn = document.getElementById('resetAttendanceFiltersBtn');

    if (semesterFilter) {
        semesterFilter.addEventListener('change', function () {
            loadAttendanceData(this.value, 'all');
        });
    }

    if (courseFilter) {
        courseFilter.addEventListener('change', function () {
            const sem = semesterFilter ? semesterFilter.value : '';
            loadAttendanceData(sem, this.value);
        });
    }

    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', () => loadAttendanceData('all', 'all'));
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => loadAttendanceData(defaultSemester || '', 'all'));
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', exportAttendanceCsv);
    }

    loadAttendanceData();
});

async function loadAttendanceData(semesterParam = '', courseParam = 'all') {
    const loadingEl = document.getElementById('attendanceLoadingState');
    const errorEl = document.getElementById('attendanceErrorBanner');
    const emptyEl = document.getElementById('attendanceEmptyState');
    const mainEl = document.getElementById('attendanceMainContent');

    if (loadingEl) loadingEl.classList.remove('d-none');
    if (errorEl) errorEl.classList.add('d-none');

    try {
        const params = new URLSearchParams();
        if (semesterParam !== '' && semesterParam !== null && semesterParam !== undefined) {
            params.set('semester', String(semesterParam));
        }
        if (courseParam && courseParam !== 'all') {
            params.set('course_id', String(courseParam));
        }

        const qs = params.toString();
        const url = `./backend/api/get_attendance.php${qs ? `?${qs}` : ''}`;
        const res = await fetch(url, { credentials: 'same-origin' });

        if (res.status === 401) {
            window.location.href = 'index.html';
            return;
        }

        const json = await res.json();
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Failed to load attendance data.');
        }

        attendancePayload = json.data;
        if (defaultSemester === null && attendancePayload.semester) {
            defaultSemester = attendancePayload.semester;
        }

        updateHeaderProfile(attendancePayload);
        populateFilters(attendancePayload);

        if (loadingEl) loadingEl.classList.add('d-none');

        const records = Array.isArray(attendancePayload.attendance) ? attendancePayload.attendance : [];
        if (!records.length) {
            if (mainEl) mainEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.remove('d-none');
            const emptyMsg = document.getElementById('attendanceEmptyMessage');
            if (emptyMsg) {
                const semText = attendancePayload.selected_semester === 'all'
                    ? 'across your semesters'
                    : `for Semester ${attendancePayload.selected_semester}`;
                emptyMsg.textContent = `No recorded attendance sessions were found ${semText}. Attendance is recorded when classes are logged for that semester.`;
            }
            return;
        }

        if (emptyEl) emptyEl.classList.add('d-none');
        if (mainEl) mainEl.classList.remove('d-none');

        renderSummaryCards(attendancePayload.overall, attendancePayload.selected_semester);
        renderStatusAlert(records, attendancePayload.overall);
        renderSubjectProgress(records);
        renderBreakdownChart(attendancePayload.overall);
        renderComparisonChart(records);
        renderEligibilityTable(records);
    } catch (err) {
        console.error('Error loading attendance:', err);
        if (loadingEl) loadingEl.classList.add('d-none');
        if (mainEl) mainEl.classList.add('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');
        if (errorEl) {
            errorEl.textContent = err.message || 'Unable to load attendance data.';
            errorEl.classList.remove('d-none');
        }
    }
}

function updateHeaderProfile(data) {
    const nameEl = document.getElementById('headerStudentName');
    const avatarEl = document.getElementById('headerAvatar');
    const name = data.student_name || 'Student';
    if (nameEl) nameEl.textContent = name;
    if (avatarEl) {
        avatarEl.src = data.profile_photo_url
            || ((data.profile_photo && data.profile_photo !== 'default-avatar.png')
                ? 'backend/api/download_document.php?type=profile'
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=0F52BA&color=fff`);
    }
}

function populateFilters(data) {
    const semSelect = document.getElementById('semesterFilter');
    const courseSelect = document.getElementById('courseFilter');

    if (semSelect) {
        const sems = Array.isArray(data.available_semesters) ? data.available_semesters : [];
        let html = `<option value="all">All Semesters</option>`;
        sems.forEach((s) => {
            html += `<option value="${s}">Semester ${s}${Number(s) === Number(data.semester) ? ' (Current)' : ''}</option>`;
        });
        semSelect.innerHTML = html;
        semSelect.value = String(data.selected_semester ?? data.semester ?? 'all');
    }

    if (courseSelect) {
        const courses = Array.isArray(data.available_courses) ? data.available_courses : [];
        let html = `<option value="all">All Courses (${courses.length})</option>`;
        courses.forEach((c) => {
            html += `<option value="${c.course_id}">${escapeHtml(c.course_code)} - ${escapeHtml(c.course_name)}</option>`;
        });
        courseSelect.innerHTML = html;
        courseSelect.value = data.selected_course_id && Number(data.selected_course_id) > 0
            ? String(data.selected_course_id)
            : 'all';
    }
}

function getStatusBadgeInfo(percentage) {
    const pct = Number(percentage || 0);
    if (pct >= 85) return { key: 'safe', label: 'Excellent (>=85%)', badgeClass: 'bg-success', barClass: 'bg-success' };
    if (pct >= 75) return { key: 'warning', label: 'Eligible (75-84%)', badgeClass: 'bg-primary', barClass: 'bg-primary' };
    if (pct >= 65) return { key: 'warning', label: 'At Risk (<75%)', badgeClass: 'bg-warning text-dark', barClass: 'bg-warning' };
    return { key: 'danger', label: 'Critical (<65%)', badgeClass: 'bg-danger', barClass: 'bg-danger' };
}

function renderSummaryCards(overall, selectedSemester) {
    const pct = overall.percentage !== null && overall.percentage !== undefined ? Number(overall.percentage) : 0;
    const total = Number(overall.total_classes || 0);
    const attended = Number(overall.attended || 0);
    const missed = Number(overall.missed || 0);
    const needed75 = Number(overall.classes_needed_75 || 0);
    const safe75 = Number(overall.safe_absences_75 || 0);

    document.getElementById('overallAttPct').textContent = total > 0 ? `${pct.toFixed(1)}%` : '--';
    const statusInfo = getStatusBadgeInfo(pct);
    const statusEl = document.getElementById('overallAttStatus');
    if (statusEl) {
        statusEl.textContent = total > 0 ? statusInfo.label : 'No data';
        statusEl.className = `status-text ${statusInfo.key === 'danger' ? 'danger' : statusInfo.key === 'warning' ? 'warning' : 'safe'}`;
    }

    document.getElementById('totalAttended').textContent = String(attended);
    document.getElementById('totalClassesLabel').textContent = `Out of ${total} conducted`;
    document.getElementById('totalMissed').textContent = String(missed);
    document.getElementById('missedScopeLabel').textContent = selectedSemester === 'all' ? 'All semesters' : `Semester ${selectedSemester}`;

    const bufferMetric = document.getElementById('bufferMetric');
    const bufferTitle = document.getElementById('bufferMetricTitle');
    const bufferNote = document.getElementById('bufferMetricNote');

    if (needed75 > 0) {
        bufferMetric.textContent = `+${needed75}`;
        bufferTitle.textContent = 'Classes Needed for 75%';
        bufferNote.textContent = 'Consecutive sessions required';
    } else {
        bufferMetric.textContent = String(safe75);
        bufferTitle.textContent = 'Safe Absences Left';
        bufferNote.textContent = 'While staying at or above 75%';
    }
}

function renderStatusAlert(records, overall) {
    const alertEl = document.getElementById('attendanceStatusAlert');
    if (!alertEl) return;

    const belowThreshold = records.filter((r) => Number(r.attendance_percentage) < 75);
    if (belowThreshold.length > 0) {
        const details = belowThreshold
            .map((r) => `<strong>${escapeHtml(r.course_code)}</strong> (${Number(r.attendance_percentage).toFixed(1)}% — attend next <strong>${r.classes_needed_75}</strong> consecutive class(es) to reach 75%)`)
            .join('; ');
        alertEl.className = 'alert alert-warning mb-4';
        alertEl.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i><strong>Attendance Alert:</strong> ${belowThreshold.length} course(s) below the 75% threshold: ${details}.`;
        alertEl.classList.remove('d-none');
        return;
    }

    const total = Number(overall.total_classes || 0);
    if (total > 0) {
        alertEl.className = 'alert alert-success mb-4';
        alertEl.innerHTML = `<i class="fas fa-check-circle me-2"></i>All displayed courses meet or exceed the 75% attendance requirement (Weighted average: <strong>${Number(overall.percentage).toFixed(1)}%</strong>).`;
        alertEl.classList.remove('d-none');
        return;
    }

    alertEl.classList.add('d-none');
}

function renderSubjectProgress(records) {
    const listEl = document.getElementById('subjectAttendanceList');
    if (!listEl) return;

    listEl.innerHTML = records.map((r) => {
        const pct = Number(r.attendance_percentage || 0);
        const info = getStatusBadgeInfo(pct);
        const helperText = r.classes_needed_75 > 0
            ? `Attend ${r.classes_needed_75} more consecutive class(es) for 75%`
            : `${r.safe_absences_75} safe absence(s) remaining before 75%`;

        return `
            <div class="subject-item mb-3 pb-3 border-bottom">
                <div class="subject-info d-flex justify-content-between align-items-start gap-2 mb-1">
                    <div>
                        <h6 class="mb-0 fw-bold">${escapeHtml(r.course_code)} - ${escapeHtml(r.course_name)}</h6>
                        <p class="text-muted small mb-0">Semester ${r.semester} &bull; ${r.classes_attended}/${r.total_classes} attended &bull; ${helperText}</p>
                    </div>
                    <span class="percentage ${info.key}">${pct.toFixed(1)}%</span>
                </div>
                <div class="attendance-progress">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${info.barClass}" role="progressbar" style="width: ${Math.min(100, Math.max(0, pct))}%" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderBreakdownChart(overall) {
    const attended = Number(overall.attended || 0);
    const missed = Number(overall.missed || 0);
    const total = Number(overall.total_classes || 0);
    const pct = total > 0 ? Number(overall.percentage || 0).toFixed(1) : '--';

    document.getElementById('circleAttPct').textContent = total > 0 ? `${pct}%` : '--';
    document.getElementById('breakdownAttended').textContent = String(attended);
    document.getElementById('breakdownMissed').textContent = String(missed);
    document.getElementById('breakdownTotal').textContent = String(total);

    const canvas = document.getElementById('attendanceBreakdownChart');
    if (!canvas) return;

    if (breakdownChartInstance) {
        breakdownChartInstance.destroy();
    }

    breakdownChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Attended', 'Missed'],
            datasets: [{
                data: [attended, missed],
                backgroundColor: ['#10B981', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderComparisonChart(records) {
    const canvas = document.getElementById('courseComparisonChart');
    if (!canvas) return;

    if (comparisonChartInstance) {
        comparisonChartInstance.destroy();
    }

    comparisonChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: records.map((r) => r.course_code),
            datasets: [{
                label: 'Attendance %',
                data: records.map((r) => Number(r.attendance_percentage || 0)),
                backgroundColor: records.map((r) => {
                    const p = Number(r.attendance_percentage || 0);
                    if (p >= 85) return 'rgba(16, 185, 129, 0.8)';
                    if (p >= 75) return 'rgba(37, 99, 235, 0.8)';
                    if (p >= 65) return 'rgba(245, 158, 11, 0.85)';
                    return 'rgba(239, 68, 68, 0.85)';
                }),
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: { callback: (v) => `${v}%` }
                }
            }
        }
    });
}

function renderEligibilityTable(records) {
    const tbody = document.getElementById('attendanceTableBody');
    if (!tbody) return;

    tbody.innerHTML = records.map((r) => {
        const pct = Number(r.attendance_percentage || 0);
        const info = getStatusBadgeInfo(pct);
        const targetBuffer = r.classes_needed_75 > 0
            ? `<span class="text-danger fw-semibold">Need +${r.classes_needed_75} classes</span>`
            : `<span class="text-success fw-semibold">${r.safe_absences_75} safe miss(es)</span>`;

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(r.course_code)}</strong>
                    <div class="small text-muted">${escapeHtml(r.course_name)}</div>
                </td>
                <td>Sem ${r.semester}</td>
                <td>${r.classes_attended} / ${r.total_classes}</td>
                <td><strong>${pct.toFixed(1)}%</strong></td>
                <td>${targetBuffer}</td>
                <td><span class="badge ${info.badgeClass}">${info.label}</span></td>
            </tr>
        `;
    }).join('');
}

function exportAttendanceCsv() {
    if (!attendancePayload || !Array.isArray(attendancePayload.attendance) || !attendancePayload.attendance.length) {
        return;
    }

    const header = [
        'Semester',
        'Course Code',
        'Course Name',
        'Attended',
        'Total Conducted',
        'Missed',
        'Attendance %',
        'Classes Needed for 75%',
        'Safe Absences Allowed (75%)',
        'Status'
    ];

    const rows = attendancePayload.attendance.map((r) => [
        r.semester,
        r.course_code,
        r.course_name,
        r.classes_attended,
        r.total_classes,
        r.classes_missed,
        r.attendance_percentage,
        r.classes_needed_75,
        r.safe_absences_75,
        r.status
    ]);

    const csv = [header, ...rows]
        .map((line) => line.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    const semTag = attendancePayload.selected_semester === 'all' ? 'all' : `sem-${attendancePayload.selected_semester}`;
    link.download = `edupulse-attendance-${semTag}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
