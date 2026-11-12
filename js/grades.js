let gradesPayload = null;
let performanceChartInstance = null;
let gradeDistChartInstance = null;

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('active'));
    }

    const chartFilter = document.getElementById('chartFilter');
    if (chartFilter) {
        chartFilter.addEventListener('change', renderPerformanceChart);
    }

    const semesterSelect = document.getElementById('semesterSelect');
    if (semesterSelect) {
        semesterSelect.addEventListener('change', function () {
            renderGradesTable(this.value);
        });
    }

    const calcBtn = document.getElementById('calculateGpaBtn');
    if (calcBtn) {
        calcBtn.addEventListener('click', calculateGPA);
    }

    const exportBtn = document.getElementById('exportGradesBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportGradesCsv);
    }

    loadGradesData();
});

async function loadGradesData() {
    const loadingEl = document.getElementById('gradesLoadingState');
    const errorEl = document.getElementById('gradesErrorBanner');
    const mainEl = document.getElementById('gradesMainContent');

    try {
        const res = await fetch('./backend/api/get_grades.php', { credentials: 'same-origin' });
        if (res.status === 401) {
            window.location.href = 'index.html';
            return;
        }
        const json = await res.json();
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Unable to load grades.');
        }

        gradesPayload = json.data;
        updateHeaderProfile(gradesPayload);
        renderSummaryCards(gradesPayload);
        renderPerformanceChart();
        renderGradeDistribution(gradesPayload.grade_distribution);
        populateSemesterFilter(gradesPayload);
        initGpaSimulator(gradesPayload);

        if (loadingEl) loadingEl.classList.add('d-none');
        if (mainEl) mainEl.classList.remove('d-none');
    } catch (err) {
        console.error('Error loading grades:', err);
        if (loadingEl) loadingEl.classList.add('d-none');
        if (errorEl) {
            errorEl.textContent = err.message || 'Failed to load academic grades.';
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

function renderSummaryCards(data) {
    const cgpaEl = document.getElementById('overallCGPA');
    const sgpaEl = document.getElementById('currentSGPA');
    const latestBadge = document.getElementById('latestSemBadge');
    const highestEl = document.getElementById('highestSGPA');
    const highestBadge = document.getElementById('highestSemBadge');
    const creditsEl = document.getElementById('totalCreditsEarned');
    const currSemEl = document.getElementById('currentSemesterLabel');
    const schemeEl = document.getElementById('markingSchemeNote');

    const hasGrades = Number(data.total_credits || 0) > 0;
    if (cgpaEl) cgpaEl.textContent = hasGrades && data.cgpa !== null ? Number(data.cgpa).toFixed(2) : '--';
    if (sgpaEl) sgpaEl.textContent = data.latest_sgpa !== null && data.latest_sgpa !== undefined ? Number(data.latest_sgpa).toFixed(2) : '--';
    if (latestBadge) latestBadge.textContent = data.latest_semester ? `Semester ${data.latest_semester}` : 'No grades yet';
    if (highestEl) highestEl.textContent = data.highest_sgpa !== null && data.highest_sgpa !== undefined ? Number(data.highest_sgpa).toFixed(2) : '--';
    if (highestBadge) highestBadge.textContent = data.highest_semester ? `Semester ${data.highest_semester}` : '--';
    if (creditsEl) creditsEl.textContent = String(data.total_credits || 0);
    if (currSemEl) currSemEl.textContent = data.current_semester ? `Current: Sem ${data.current_semester}` : 'Current Semester --';

    if (schemeEl && data.marking_scheme) {
        schemeEl.textContent = `Marking scheme: Internal / ${data.marking_scheme.internal_max} + External / ${data.marking_scheme.external_max} = Total / ${data.marking_scheme.total_max}`;
    }
}

function renderPerformanceChart() {
    if (!gradesPayload) return;
    const filter = document.getElementById('chartFilter')?.value || 'trend';
    const trend = Array.isArray(gradesPayload.sgpa_trend) ? gradesPayload.sgpa_trend : [];
    const emptyEl = document.getElementById('performanceChartEmpty');
    const containerEl = document.getElementById('performanceChartContainer');
    const canvas = document.getElementById('performanceChart');

    if (!trend.length) {
        if (emptyEl) emptyEl.classList.remove('d-none');
        if (containerEl) containerEl.classList.add('d-none');
        return;
    }
    if (emptyEl) emptyEl.classList.add('d-none');
    if (containerEl) containerEl.classList.remove('d-none');
    if (!canvas) return;

    if (performanceChartInstance) {
        performanceChartInstance.destroy();
    }

    const labels = trend.map((item) => `Semester ${item.semester}`);

    if (filter === 'distribution') {
        performanceChartInstance = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Average Total Marks (/100)',
                    data: trend.map((item) => item.avg_marks ?? 0),
                    backgroundColor: 'rgba(15, 82, 186, 0.78)',
                    borderColor: '#0F52BA',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' }
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
        return;
    }

    const values = trend.flatMap((t) => [Number(t.sgpa), Number(t.cumulative_cgpa)]).filter((v) => !Number.isNaN(v));
    const minVal = values.length ? Math.max(0, Math.floor(Math.min(...values) - 1)) : 0;

    performanceChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Semester SGPA',
                    data: trend.map((item) => item.sgpa),
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#2563EB'
                },
                {
                    label: 'Cumulative CGPA',
                    data: trend.map((item) => item.cumulative_cgpa),
                    borderColor: '#0891B2',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.35,
                    fill: false,
                    pointRadius: 4,
                    pointBackgroundColor: '#0891B2'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                y: {
                    min: minVal,
                    max: 10,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

function renderGradeDistribution(dist) {
    const emptyEl = document.getElementById('gradeDistEmpty');
    const wrapperEl = document.getElementById('gradeDistWrapper');
    const legendEl = document.getElementById('gradeDistLegend');
    const canvas = document.getElementById('gradeDistributionChart');

    if (!dist || !dist.total) {
        if (emptyEl) emptyEl.classList.remove('d-none');
        if (wrapperEl) wrapperEl.classList.add('d-none');
        return;
    }
    if (emptyEl) emptyEl.classList.add('d-none');
    if (wrapperEl) wrapperEl.classList.remove('d-none');

    const rows = [
        { label: 'A+ / O (9.0 - 10.0)', count: Number(dist.a_plus || 0), color: '#10B981' },
        { label: 'A (8.0 - 8.9)', count: Number(dist.a || 0), color: '#2563EB' },
        { label: 'B+ (7.0 - 7.9)', count: Number(dist.b_plus || 0), color: '#06B6D4' },
        { label: 'B & Below (<7.0)', count: Number(dist.b || 0), color: '#F59E0B' }
    ];

    if (legendEl) {
        legendEl.innerHTML = rows.map((row) => `
            <div class="d-flex justify-content-between align-items-center mb-2 small">
                <span class="d-flex align-items-center gap-2">
                    <span class="badge-dot" style="background-color: ${row.color};"></span>
                    <span>${row.label}</span>
                </span>
                <strong>${row.count}</strong>
            </div>
        `).join('');
    }

    if (canvas) {
        if (gradeDistChartInstance) {
            gradeDistChartInstance.destroy();
        }
        gradeDistChartInstance = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{
                    data: rows.map((r) => r.count),
                    backgroundColor: rows.map((r) => r.color),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
}

function populateSemesterFilter(data) {
    const select = document.getElementById('semesterSelect');
    if (!select) return;

    const semesters = Object.keys(data.grades || {})
        .map((s) => Number(s))
        .filter((s) => !Number.isNaN(s))
        .sort((a, b) => b - a);

    if (!semesters.length) {
        select.innerHTML = '<option value="">No graded semesters</option>';
        renderGradesTable('');
        return;
    }

    select.innerHTML = `<option value="all">All Graded Semesters (${semesters.length})</option>` +
        semesters.map((sem) => `<option value="${sem}">Semester ${sem}</option>`).join('');

    select.value = String(semesters[0]);
    renderGradesTable(select.value);
}

function formatMark(val, max) {
    if (val === null || val === undefined || val === '') return '—';
    const num = Number(val);
    if (Number.isNaN(num)) return '—';
    return `${num} / ${max}`;
}

function getGradeBadgeClass(gradePoint) {
    const gp = Number(gradePoint);
    if (gp >= 9) return 'excellent';
    if (gp >= 8) return 'good';
    if (gp >= 6.5) return 'average';
    return 'poor';
}

function renderGradesTable(selectedSem) {
    const tbody = document.getElementById('gradesTableBody');
    const emptyEl = document.getElementById('gradesTableEmpty');
    const containerEl = document.getElementById('gradesTableContainer');
    const summaryCreditsEl = document.getElementById('summaryCredits');
    const summaryAvgEl = document.getElementById('summaryAvgMarks');
    const summarySgpaEl = document.getElementById('summarySGPA');

    if (!gradesPayload || !tbody) return;

    const scheme = gradesPayload.marking_scheme || { internal_max: 50, external_max: 50, total_max: 100 };
    const gradesMap = gradesPayload.grades || {};
    let rows = [];

    if (selectedSem === 'all') {
        Object.keys(gradesMap)
            .sort((a, b) => Number(b) - Number(a))
            .forEach((sem) => {
                const list = gradesMap[sem]?.courses || [];
                list.forEach((c) => rows.push({ ...c, semester_num: sem }));
            });
    } else if (selectedSem && gradesMap[selectedSem]) {
        rows = (gradesMap[selectedSem].courses || []).map((c) => ({ ...c, semester_num: selectedSem }));
    }

    if (!rows.length) {
        tbody.innerHTML = '';
        if (emptyEl) emptyEl.classList.remove('d-none');
        if (containerEl) containerEl.classList.add('d-none');
        return;
    }

    if (emptyEl) emptyEl.classList.add('d-none');
    if (containerEl) containerEl.classList.remove('d-none');

    tbody.innerHTML = rows.map((c) => {
        const gp = Number(c.grade_points ?? c.grade_point ?? 0);
        const passed = gp >= 5.0;
        const badgeClass = getGradeBadgeClass(gp);
        const semPrefix = selectedSem === 'all' ? `<span class="badge bg-light text-secondary border me-1">S${c.semester_num}</span>` : '';
        return `
            <tr>
                <td><strong class="course-code">${semPrefix}${escapeHtml(c.course_code || '')}</strong></td>
                <td>${escapeHtml(c.course_name || '')}</td>
                <td>${Number(c.credits || 0)}</td>
                <td>${formatMark(c.internal_marks, scheme.internal_max)}</td>
                <td>${formatMark(c.external_marks, scheme.external_max)}</td>
                <td><strong>${formatMark(c.total_marks, scheme.total_max)}</strong></td>
                <td><span class="grade-badge ${badgeClass}">${escapeHtml(c.grade || '--')}</span></td>
                <td>${gp.toFixed(1)}</td>
                <td>
                    <span class="status-badge ${passed ? 'passed' : 'failed'}">
                        ${passed ? 'Passed' : 'Needs Repeat'}
                    </span>
                </td>
            </tr>
        `;
    }).join('');

    let totalCredits = 0;
    let weightedPts = 0;
    let totalMarksSum = 0;
    let marksCount = 0;

    rows.forEach((c) => {
        const cr = Number(c.credits || 0);
        const gp = Number(c.grade_points ?? c.grade_point ?? 0);
        if (cr > 0) {
            totalCredits += cr;
            weightedPts += cr * gp;
        }
        if (c.total_marks !== null && c.total_marks !== undefined && c.total_marks !== '') {
            totalMarksSum += Number(c.total_marks);
            marksCount += 1;
        }
    });

    const computedGpa = totalCredits > 0 ? (weightedPts / totalCredits).toFixed(2) : '--';
    const avgMarks = marksCount > 0 ? `${(totalMarksSum / marksCount).toFixed(1)} / ${scheme.total_max}` : 'Avg Marks: --';

    if (summaryCreditsEl) summaryCreditsEl.textContent = String(totalCredits);
    if (summaryAvgEl) summaryAvgEl.textContent = `Avg Total: ${avgMarks}`;
    if (summarySgpaEl) summarySgpaEl.textContent = computedGpa;
}

function initGpaSimulator(data) {
    const predictedEl = document.getElementById('predictedGPA');
    const deltaEl = document.getElementById('predictedGpaDelta');
    if (predictedEl) {
        predictedEl.textContent = data.cgpa !== null && data.cgpa !== undefined
            ? Number(data.cgpa).toFixed(2)
            : '--';
    }
    if (deltaEl) {
        deltaEl.textContent = 'Select a grade & credits and click Simulate';
    }
}

function calculateGPA() {
    if (!gradesPayload) return;
    const expectedGrade = Number(document.getElementById('expectedGrade')?.value || 0);
    const credits = Number(document.getElementById('credits')?.value || 0);
    const predictedEl = document.getElementById('predictedGPA');
    const deltaEl = document.getElementById('predictedGpaDelta');

    if (credits <= 0 || Number.isNaN(credits)) {
        if (deltaEl) deltaEl.textContent = 'Enter credits >= 1';
        return;
    }

    const currentCredits = Number(gradesPayload.total_credits || 0);
    const currentCgpa = Number(gradesPayload.cgpa || 0);
    const projected = (currentCgpa * currentCredits + expectedGrade * credits) / (currentCredits + credits);
    const diff = currentCredits > 0 ? projected - currentCgpa : 0;

    if (predictedEl) predictedEl.textContent = projected.toFixed(2);
    if (deltaEl) {
        if (currentCredits === 0) {
            deltaEl.textContent = `Based on ${credits} simulated credits`;
        } else {
            const sign = diff >= 0 ? '+' : '';
            deltaEl.textContent = `${sign}${diff.toFixed(3)} vs current CGPA (${currentCgpa.toFixed(2)})`;
        }
    }
}

function exportGradesCsv() {
    if (!gradesPayload || !gradesPayload.grades) return;
    const selectedSem = document.getElementById('semesterSelect')?.value || 'all';
    const gradesMap = gradesPayload.grades;
    const rows = [];

    const semKeys = selectedSem === 'all' || !gradesMap[selectedSem]
        ? Object.keys(gradesMap).sort((a, b) => Number(a) - Number(b))
        : [selectedSem];

    semKeys.forEach((sem) => {
        (gradesMap[sem]?.courses || []).forEach((c) => {
            rows.push([
                sem,
                c.course_code ?? '',
                c.course_name ?? '',
                c.credits ?? '',
                c.internal_marks ?? '',
                c.external_marks ?? '',
                c.total_marks ?? '',
                c.grade ?? '',
                c.grade_points ?? c.grade_point ?? ''
            ]);
        });
    });

    if (!rows.length) return;

    const header = ['Semester', 'Course Code', 'Course Name', 'Credits', 'Internal (/50)', 'External (/50)', 'Total (/100)', 'Grade', 'Grade Point'];
    const csv = [header, ...rows]
        .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `edupulse-grades-${selectedSem === 'all' ? 'all-semesters' : 'sem-' + selectedSem}.csv`;
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
