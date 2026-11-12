(function () {
    'use strict';

    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('active'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 991 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }

    let progressChart = null;
    let gradeChart = null;

    function esc(val) {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function initCharts() {
        const progressCtx = document.getElementById('progressChart');
        if (progressCtx) {
            progressChart = new Chart(progressCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'SGPA',
                        data: [],
                        borderColor: '#0F52BA',
                        backgroundColor: 'rgba(15, 82, 186, 0.15)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#0F52BA',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: {
                        y: { min: 5, max: 10, ticks: { stepSize: 0.5 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } },
                    },
                },
            });
        }

        const gradeCtx = document.getElementById('gradeChart');
        if (gradeCtx) {
            gradeChart = new Chart(gradeCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['O / A+', 'A', 'B+', 'B & Below'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#0F52BA', '#4AB7E0', '#3B82F6', '#F59E0B'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } },
                    },
                },
            });
        }
    }

    function renderDashboard(data) {
        const student = data.student || {};
        const stats = data.stats || {};
        const sgpaTrend = data.sgpa_trend || [];
        const dist = data.grade_distribution || {};
        const recentGrades = data.recent_grades || [];
        const upcoming = data.upcoming_assignments || [];
        const alerts = data.alerts || [];

        const firstName = (student.name || 'Student').split(' ')[0];
        document.getElementById('welcomeHeading').textContent = `Welcome back, ${firstName}!`;
        document.getElementById('welcomeSubtext').textContent =
            `${student.department || ''} • Enrollment ID: ${student.enrollment_id || '-'}`;

        document.getElementById('headerStudentName').textContent = student.name || 'Student';
        const avatarEl = document.getElementById('headerAvatar');
        if (avatarEl && student.name) {
            const fallbackSvg = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect width="96" height="96" rx="48" fill="#0F52BA"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#FFF" font-family="Arial,sans-serif" font-size="36" font-weight="700">${(student.name || 'S').trim().charAt(0).toUpperCase()}</text></svg>`
            );
            avatarEl.onerror = () => {
                avatarEl.onerror = null;
                avatarEl.src = fallbackSvg;
            };
            avatarEl.src = student.profile_photo_url
                || (student.profile_photo && student.profile_photo !== 'default-avatar.png'
                    ? 'backend/api/download_document.php?type=profile'
                    : fallbackSvg);
        }

        document.getElementById('bannerSemester').textContent = student.semester ? `Sem ${student.semester}` : '-';
        document.getElementById('bannerProgram').textContent = student.program || '-';

        // Stat cards
        const hasGrades = Number(stats.total_graded_credits || 0) > 0;
        const hasAtt = Number(stats.total_classes || 0) > 0;

        document.getElementById('statCgpa').textContent = hasGrades ? Number(stats.cgpa).toFixed(2) : 'N/A';
        document.getElementById('statAttendance').textContent = hasAtt ? `${Number(stats.attendance).toFixed(1)}%` : 'N/A';
        document.getElementById('statAttendanceLabel').textContent = hasAtt
            ? `Attendance (${stats.attendance_status})`
            : 'Attendance (No Records)';
        document.getElementById('statCourses').textContent = stats.total_courses ?? 0;
        document.getElementById('statPendingAssignments').textContent = stats.pending_assignments ?? 0;

        // Academic summary panel
        document.getElementById('summaryCgpa').textContent = hasGrades ? Number(stats.cgpa).toFixed(2) : 'N/A';
        document.getElementById('summaryLatestSgpa').textContent = stats.latest_sgpa !== null && stats.latest_sgpa !== undefined
            ? `${Number(stats.latest_sgpa).toFixed(2)} (Sem ${stats.latest_semester})`
            : 'N/A';
        document.getElementById('summaryHighestSgpa').textContent = Number(stats.highest_sgpa || 0) > 0
            ? `${Number(stats.highest_sgpa).toFixed(2)} (Sem ${stats.highest_semester})`
            : 'N/A';
        document.getElementById('summarySemester').textContent = student.semester ? `Sem ${student.semester}` : '-';

        // SGPA trend chart
        if (progressChart && sgpaTrend.length > 0) {
            const labels = sgpaTrend.map((s) => `Sem ${s.semester}`);
            const values = sgpaTrend.map((s) => Number(s.sgpa));
            progressChart.data.labels = labels;
            progressChart.data.datasets[0].data = values;
            progressChart.options.scales.y.min = Math.max(0, Math.floor(Math.min(...values) - 0.5));
            progressChart.options.scales.y.max = Math.min(10, Math.ceil(Math.max(...values) + 0.5));
            progressChart.update();
        }

        // Grade distribution chart & legend
        const totalG = Number(dist.total || 0);
        const pct = (n) => (totalG > 0 ? Math.round((n / totalG) * 100) : 0);
        const aPlus = Number(dist.a_plus || 0);
        const a = Number(dist.a || 0);
        const bPlus = Number(dist.b_plus || 0);
        const b = Number(dist.b || 0);

        document.getElementById('gradeCountAPlus').textContent = aPlus;
        document.getElementById('gradePctAPlus').textContent = `(${pct(aPlus)}%)`;
        document.getElementById('gradeCountA').textContent = a;
        document.getElementById('gradePctA').textContent = `(${pct(a)}%)`;
        document.getElementById('gradeCountBPlus').textContent = bPlus;
        document.getElementById('gradePctBPlus').textContent = `(${pct(bPlus)}%)`;
        document.getElementById('gradeCountB').textContent = b;
        document.getElementById('gradePctB').textContent = `(${pct(b)}%)`;

        if (gradeChart) {
            gradeChart.data.datasets[0].data = [aPlus, a, bPlus, b];
            gradeChart.update();
        }

        // Attendance overview box
        const attVal = Number(stats.attendance || 0);
        const attValElem = document.getElementById('overviewAttendanceVal');
        const attClassesElem = document.getElementById('overviewAttendanceClasses');
        const attBarElem = document.getElementById('overviewAttendanceBar');
        const attBadgeElem = document.getElementById('overviewAttendanceBadge');

        attValElem.textContent = hasAtt ? `${attVal.toFixed(1)}%` : 'N/A';
        attClassesElem.textContent = hasAtt
            ? `${stats.classes_attended}/${stats.total_classes} classes attended`
            : 'No classes recorded for this semester yet';
        attBarElem.style.width = `${hasAtt ? Math.min(100, Math.max(0, attVal)) : 0}%`;

        if (!hasAtt) {
            attBadgeElem.className = 'badge bg-secondary-subtle text-secondary border px-2.5 py-1.5 rounded-pill';
            attBadgeElem.innerHTML = '<i class="fas fa-info-circle me-1"></i>No Data';
            attBarElem.className = 'progress-bar bg-secondary';
        } else if (attVal >= 85) {
            attBadgeElem.className = 'badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill';
            attBadgeElem.innerHTML = '<i class="fas fa-check-circle me-1"></i>Safe (&ge;85%)';
            attBarElem.className = 'progress-bar bg-success';
        } else if (attVal >= 75) {
            attBadgeElem.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5 rounded-pill';
            attBadgeElem.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Warning (75-84.9%)';
            attBarElem.className = 'progress-bar bg-warning';
        } else {
            attBadgeElem.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill';
            attBadgeElem.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Critical (&lt;75%)';
            attBarElem.className = 'progress-bar bg-danger';
        }

        // Alerts dropdown
        const badgeEl = document.getElementById('alertsBadgeCount');
        const menuEl = document.getElementById('alertsDropdownMenu');
        if (alerts.length > 0) {
            badgeEl.textContent = alerts.length;
            badgeEl.classList.remove('d-none');
            menuEl.innerHTML = alerts.map((al) => `
                <li>
                    <a class="dropdown-item rounded-3 py-2" href="${esc(al.url || 'dashboard.html')}">
                        <div class="fw-semibold small text-${al.type === 'danger' ? 'danger' : 'warning-emphasis'}">${esc(al.title)}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">${esc(al.detail)}</div>
                    </a>
                </li>
            `).join('');
        } else {
            badgeEl.classList.add('d-none');
            menuEl.innerHTML = '<li class="dropdown-item text-muted small py-2"><i class="fas fa-check-circle text-success me-1"></i>No urgent attendance or deadline alerts.</li>';
        }

        // Upcoming deadlines (real DB)
        document.getElementById('pendingDeadlinesBadge').textContent = `${stats.pending_assignments ?? 0} Pending`;
        const deadlinesEl = document.getElementById('upcomingDeadlinesList');
        if (upcoming.length === 0) {
            deadlinesEl.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    <p class="small mb-2">No pending assignments in the database.</p>
                    <a href="assignments.html" class="btn btn-sm btn-outline-primary rounded-pill">Open Assignments</a>
                </div>
            `;
        } else {
            const badgeMap = {
                urgent: 'bg-danger-subtle text-danger border-danger-subtle',
                high: 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                medium: 'bg-primary-subtle text-primary border-primary-subtle',
                low: 'bg-success-subtle text-success border-success-subtle',
            };
            deadlinesEl.innerHTML = upcoming.map((item) => {
                const due = new Date(item.due_date.replace(' ', 'T')).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
                return `
                    <a href="assignments.html" class="text-decoration-none d-flex justify-content-between align-items-center p-3 mb-2 rounded-3 border bg-light">
                        <div>
                            <h6 class="fw-bold text-dark m-0 mb-1">${esc(item.title)}</h6>
                            <p class="text-muted small m-0">${esc(item.course_code)} &bull; Due: ${esc(due)}${item.is_overdue ? ' <span class="text-danger fw-semibold">(Overdue)</span>' : ''}</p>
                        </div>
                        <span class="badge border text-uppercase ${badgeMap[item.priority] || badgeMap.medium}">${esc(item.priority)}</span>
                    </a>
                `;
            }).join('');
        }

        // Recent grades (real DB)
        const gradesEl = document.getElementById('recentGradesList');
        if (recentGrades.length === 0) {
            gradesEl.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-graduation-cap fa-2x mb-2 d-block"></i>
                    <p class="small mb-0">No graded course evaluations recorded yet.</p>
                </div>
            `;
        } else {
            gradesEl.innerHTML = recentGrades.map((g) => {
                const gp = Number(g.grade_points || 0);
                const badgeCls = gp >= 9 ? 'badge-a-plus' : (gp >= 8 ? 'badge-a' : 'badge-b-plus');
                return `
                    <div class="p-3 mb-2 rounded-3 border bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold text-dark m-0 mb-1">${esc(g.course_name)}</h6>
                            <p class="text-muted small m-0">${esc(g.course_code)} &bull; Semester ${esc(g.semester)} &bull; ${esc(g.total_marks)}/100</p>
                        </div>
                        <div class="grade-badge-custom ${badgeCls}">${esc(g.grade || '-')}</div>
                    </div>
                `;
            }).join('');
        }
    }

    function loadDashboard() {
        initCharts();
        fetch('./backend/api/get_dashboard.php')
            .then(async (res) => {
                if (res.status === 401) {
                    window.location.href = 'index.html';
                    return null;
                }
                return res.json();
            })
            .then((payload) => {
                if (!payload || !payload.success || !payload.data) return;
                renderDashboard(payload.data);
            })
            .catch((err) => console.error('Dashboard load error:', err));
    }

    window.addEventListener('DOMContentLoaded', loadDashboard);
})();
