(function () {
    'use strict';

    // Sidebar toggle for mobile
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

    let sgpaChartInstance = null;
    let benchmarkChartInstance = null;
    let intExtChartInstance = null;
    let correlationChartInstance = null;
    let timelinessChartInstance = null;
    let assignPerfChartInstance = null;
    let cachedAnalyticsData = null;

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function loadAnalytics() {
        fetch('./backend/api/get_performance_analytics.php')
            .then(async (res) => {
                if (res.status === 401) {
                    window.location.href = 'index.html';
                    return null;
                }
                return res.json();
            })
            .then((payload) => {
                if (!payload) return;
                if (!payload.success || !payload.data) {
                    showLoadError(payload.message || 'Failed to load analytics.');
                    return;
                }
                cachedAnalyticsData = payload.data;
                renderAll(payload.data);
            })
            .catch((err) => {
                console.error('Analytics load error:', err);
                showLoadError('Unable to reach the analytics API. Please check your session.');
            });
    }

    function showLoadError(msg) {
        const container = document.getElementById('analyticsContent');
        if (container) {
            container.innerHTML = `<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-triangle me-2"></i>${esc(msg)}</div>`;
        }
    }

    function renderAll(data) {
        renderHeaderAndKpis(data);
        renderSgpaTrendChart(data.sgpa_trend || [], data.cohort_scope || {});
        populateSemesterFilters(data.course_performance || []);
        renderCourseBenchmarkChart(data.course_performance || [], 'all', data.cohort_scope || {});
        renderInternalExternalChart(data.course_performance || [], 'all');
        renderCorrelation(data.attendance_grade_correlation || {});
        renderAssignmentVsPerformance(data.assignment_vs_performance || {});
        renderPerformanceFactors(data.performance_factors || []);
        renderStrengthsAndWeaknesses(data.strengths_weaknesses || {});
        renderRecommendations(data.recommendations || []);
        renderAssignmentTimeliness(data.assignment_analytics || {});
        renderAttendanceTable(data.attendance_performance || [], data.cohort_scope || {});
        renderCourseDetailsTable(data.course_performance || [], data.attendance_performance || []);
    }

    function renderHeaderAndKpis(data) {
        const student = data.student || {};
        const ov = data.overview || {};
        const trend = ov.trend || {};
        const att = ov.overall_attendance || {};
        const assign = data.assignment_analytics || {};

        const nameEl = document.getElementById('headerStudentName');
        const avatarEl = document.getElementById('headerAvatar');
        if (nameEl && student.name) nameEl.textContent = student.name;
        if (avatarEl && student.name) {
            avatarEl.src = student.profile_photo_url
                || ((student.profile_photo && student.profile_photo !== 'default-avatar.png')
                    ? 'backend/api/download_document.php?type=profile'
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(student.name)}&background=0F52BA&color=fff`);
        }

        const bannerMeta = document.getElementById('analyticsStudentMeta');
        if (bannerMeta) {
            bannerMeta.textContent = `${student.program || 'Student'} • ${student.department || ''} • Semester ${student.current_semester || '-'}`;
        }

        const cgpaVal = document.getElementById('kpiCgpa');
        const cgpaBand = document.getElementById('kpiCgpaBand');
        if (cgpaVal) cgpaVal.textContent = Number(ov.cgpa || 0).toFixed(2);
        if (cgpaBand) cgpaBand.textContent = `${trend.performance_band || '-'} • ${ov.total_graded_credits || 0} credits`;

        const sgpaVal = document.getElementById('kpiLatestSgpa');
        const sgpaSub = document.getElementById('kpiSgpaTrend');
        if (sgpaVal) sgpaVal.textContent = ov.latest_sgpa !== null && ov.latest_sgpa !== undefined ? Number(ov.latest_sgpa).toFixed(2) : 'N/A';
        if (sgpaSub) {
            const delta = trend.delta_latest;
            const deltaTxt = (delta !== null && delta !== undefined) ? ` (${delta >= 0 ? '+' : ''}${Number(delta).toFixed(2)})` : '';
            sgpaSub.textContent = `Sem ${ov.latest_semester || '-'} • ${trend.label || 'Baseline'}${deltaTxt}`;
        }

        const attVal = document.getElementById('kpiAttendance');
        const attSub = document.getElementById('kpiAttendanceStatus');
        if (attVal) attVal.textContent = `${Number(att.percentage || 0).toFixed(1)}%`;
        if (attSub) {
            attSub.textContent = `${att.status || 'No Data'} (${att.classes_attended || 0}/${att.total_classes || 0} classes)`;
        }

        const assignVal = document.getElementById('kpiTimeliness');
        const assignSub = document.getElementById('kpiTimelinessSub');
        if (!assign.has_data) {
            if (assignVal) assignVal.textContent = 'No Data';
            if (assignSub) assignSub.textContent = 'No assignments recorded yet';
        } else {
            const tl = assign.timeliness || {};
            if (assignVal) {
                assignVal.textContent = tl.has_submissions && tl.on_time_rate !== null
                    ? `${Number(tl.on_time_rate).toFixed(0)}%`
                    : `${assign.summary.completed_count}/${assign.summary.total_assigned}`;
            }
            if (assignSub) {
                assignSub.textContent = tl.has_submissions
                    ? `${tl.on_time_count} on-time • ${tl.late_count} late`
                    : `${assign.summary.pending_count + assign.summary.in_progress_count} active • ${assign.summary.overdue_count} overdue`;
            }
        }
    }

    function renderSgpaTrendChart(semesters, cohortScope) {
        const ctx = document.getElementById('sgpaTrendChart');
        if (!ctx) return;
        if (sgpaChartInstance) sgpaChartInstance.destroy();

        if (!semesters.length) {
            return;
        }

        const labels = semesters.map((s) => `Sem ${s.semester}`);
        const sgpaData = semesters.map((s) => s.sgpa);
        const cgpaData = semesters.map((s) => s.cumulative_cgpa);
        const hasPeer = semesters.some((s) => s.benchmark_available && s.class_avg_sgpa !== null);
        const peerData = semesters.map((s) => (s.benchmark_available ? s.class_avg_sgpa : null));

        const allVals = [...sgpaData, ...cgpaData, ...peerData.filter((v) => v !== null)];
        const minY = Math.max(0, Math.floor(Math.min(...allVals) - 0.5));

        const datasets = [
            {
                label: 'Your SGPA',
                data: sgpaData,
                borderColor: '#0F52BA',
                backgroundColor: 'rgba(15, 82, 186, 0.12)',
                tension: 0.35,
                fill: true,
                pointRadius: 5,
            },
            {
                label: 'Cumulative CGPA',
                data: cgpaData,
                borderColor: '#10B981',
                backgroundColor: 'transparent',
                borderDash: [5, 4],
                tension: 0.35,
                pointRadius: 4,
            },
        ];

        if (hasPeer) {
            const maxCohort = Math.max(...semesters.map((s) => s.cohort_size || 0));
            const scopeName = cohortScope && cohortScope.label ? cohortScope.label : 'Cohort';
            datasets.push({
                label: `${scopeName} Avg SGPA (n=${maxCohort})`,
                data: peerData,
                borderColor: '#F59E0B',
                backgroundColor: 'transparent',
                tension: 0.35,
                pointRadius: 4,
            });
        }

        sgpaChartInstance = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    y: {
                        min: minY,
                        max: 10,
                        ticks: { stepSize: 0.5 },
                    },
                },
            },
        });
    }

    function populateSemesterFilters(courses) {
        const sel = document.getElementById('courseSemesterFilter');
        if (!sel) return;
        const sems = Array.from(new Set(courses.map((c) => Number(c.semester)))).sort((a, b) => a - b);
        sel.innerHTML = '<option value="all">All Graded Semesters</option>';
        sems.forEach((s) => {
            const opt = document.createElement('option');
            opt.value = String(s);
            opt.textContent = `Semester ${s}`;
            sel.appendChild(opt);
        });
        sel.onchange = () => {
            renderCourseBenchmarkChart(cachedAnalyticsData.course_performance || [], sel.value, cachedAnalyticsData.cohort_scope || {});
            renderInternalExternalChart(cachedAnalyticsData.course_performance || [], sel.value);
        };
    }

    function filterCoursesBySem(courses, semValue) {
        if (!semValue || semValue === 'all') return courses;
        return courses.filter((c) => String(c.semester) === String(semValue));
    }

    function renderCourseBenchmarkChart(allCourses, semFilter, cohortScope) {
        const ctx = document.getElementById('courseBenchmarkChart');
        const cohortBadge = document.getElementById('benchmarkCohortBadge');
        if (!ctx) return;
        if (benchmarkChartInstance) benchmarkChartInstance.destroy();

        const courses = filterCoursesBySem(allCourses, semFilter);
        const maxCohort = courses.reduce((m, c) => Math.max(m, Number(c.cohort_size || 0)), 0);
        const scopeLabel = cohortScope && cohortScope.value ? cohortScope.value : 'Program';
        if (cohortBadge) {
            cohortBadge.textContent = maxCohort >= 3
                ? `${scopeLabel} Cohort Avg (n=${maxCohort} peers)`
                : `Cohort avg hidden (<3 ${scopeLabel} peers)`;
        }

        const labels = courses.map((c) => `${c.course_code} (S${c.semester})`);
        const studentMarks = courses.map((c) => c.total_marks);
        const classAvgMarks = courses.map((c) => (c.benchmark_available ? c.class_avg_marks : null));

        const datasets = [
            {
                label: 'Your Total Marks',
                data: studentMarks,
                backgroundColor: '#0F52BA',
                borderRadius: 6,
            },
        ];

        if (courses.some((c) => c.benchmark_available)) {
            datasets.push({
                label: `${scopeLabel} Cohort Average`,
                data: classAvgMarks,
                backgroundColor: '#94A3B8',
                borderRadius: 6,
            });
        }

        benchmarkChartInstance = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                },
                scales: {
                    y: { beginAtZero: true, max: 100 },
                },
            },
        });
    }

    function renderInternalExternalChart(allCourses, semFilter) {
        const ctx = document.getElementById('internalExternalChart');
        if (!ctx) return;
        if (intExtChartInstance) intExtChartInstance.destroy();

        const courses = filterCoursesBySem(allCourses, semFilter);
        const labels = courses.map((c) => `${c.course_code} (S${c.semester})`);

        intExtChartInstance = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Internal Marks',
                        data: courses.map((c) => c.internal_marks),
                        backgroundColor: '#06B6D4',
                        borderRadius: 6,
                    },
                    {
                        label: 'External Marks',
                        data: courses.map((c) => c.external_marks),
                        backgroundColor: '#3B82F6',
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, max: 50 } },
            },
        });
    }

    function renderCorrelation(corr) {
        const badge = document.getElementById('correlationBadge');
        const emptyBox = document.getElementById('correlationEmptyState');
        const chartWrap = document.getElementById('correlationChartWrap');
        const noteEl = document.getElementById('correlationNote');
        const canvas = document.getElementById('correlationChart');

        if (correlationChartInstance) {
            correlationChartInstance.destroy();
            correlationChartInstance = null;
        }

        if (corr.status !== 'ok') {
            if (badge) {
                badge.className = 'badge bg-secondary-subtle text-secondary border px-3 py-2 rounded-pill';
                badge.textContent = corr.status === 'zero_variance'
                    ? `Undefined (Zero Variance, n=${corr.paired_count || 0})`
                    : `Insufficient Overlap (n=${corr.paired_count || 0})`;
            }
            if (chartWrap) chartWrap.classList.add('d-none');
            if (emptyBox) {
                emptyBox.classList.remove('d-none');
                emptyBox.innerHTML = `
                    <i class="fas fa-circle-info d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">Attendance–Grade Correlation Unavailable</h6>
                    <p class="small mb-0">${esc(corr.note || 'At least 3 matched course-semester attendance and grade observations are required.')}</p>
                `;
            }
            return;
        }

        if (emptyBox) emptyBox.classList.add('d-none');
        if (chartWrap) chartWrap.classList.remove('d-none');
        if (badge) {
            badge.className = 'badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill';
            badge.textContent = `r = ${Number(corr.pearson_r).toFixed(2)} (${corr.strength} ${corr.direction}, n=${corr.paired_count})`;
        }
        if (noteEl) noteEl.textContent = corr.note || '';

        if (canvas) {
            const pts = (corr.points || []).map((p) => ({
                x: p.attendance_percentage,
                y: p.total_marks,
                label: `${p.course_code} (Sem ${p.semester})`,
            }));
            correlationChartInstance = new Chart(canvas.getContext('2d'), {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Course (Attendance % vs Marks)',
                        data: pts,
                        backgroundColor: '#0F52BA',
                        pointRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const raw = ctx.raw;
                                    return `${raw.label}: ${raw.x}% att, ${raw.y}/100 marks`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: { title: { display: true, text: 'Attendance (%)' }, min: 50, max: 100 },
                        y: { title: { display: true, text: 'Total Marks (/100)' }, min: 0, max: 100 },
                    },
                },
            });
        }
    }

    function renderAssignmentVsPerformance(avp) {
        const badge = document.getElementById('assignPerfBadge');
        const emptyBox = document.getElementById('assignPerfEmptyState');
        const chartWrap = document.getElementById('assignPerfChartWrap');
        const noteEl = document.getElementById('assignPerfNote');
        const canvas = document.getElementById('assignPerfChart');

        if (assignPerfChartInstance) {
            assignPerfChartInstance.destroy();
            assignPerfChartInstance = null;
        }

        if (avp.status !== 'ok') {
            if (badge) {
                badge.className = 'badge bg-secondary-subtle text-secondary border px-3 py-2 rounded-pill';
                badge.textContent = avp.status === 'no_assignments'
                    ? 'No Assignments (n=0)'
                    : (avp.status === 'zero_variance'
                        ? `Zero Variance (n=${avp.paired_count || 0})`
                        : `Insufficient Overlap (n=${avp.paired_count || 0})`);
            }
            if (chartWrap) chartWrap.classList.add('d-none');
            if (emptyBox) {
                emptyBox.classList.remove('d-none');
                emptyBox.innerHTML = `
                    <i class="fas fa-circle-info d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">Assignment-Performance Correlation Unavailable</h6>
                    <p class="small mb-2">${esc(avp.note || 'At least 3 matched graded course-semester observations with assignment completion records are required.')}</p>
                    ${avp.total_assigned > 0 ? `<span class="badge bg-light text-dark border">Current completion: ${esc(avp.completed_count)}/${esc(avp.total_assigned)} (${esc(avp.completion_rate)}%)</span>` : ''}
                `;
            }
            return;
        }

        if (emptyBox) emptyBox.classList.add('d-none');
        if (chartWrap) chartWrap.classList.remove('d-none');
        if (badge) {
            badge.className = 'badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill';
            badge.textContent = `r = ${Number(avp.pearson_r).toFixed(2)} (${avp.strength} ${avp.direction}, n=${avp.paired_count})`;
        }
        if (noteEl) noteEl.textContent = avp.note || '';

        if (canvas) {
            const pts = (avp.points || []).map((p) => ({
                x: p.completion_rate,
                y: p.total_marks,
                label: `${p.course_code} (Sem ${p.semester})`,
            }));
            assignPerfChartInstance = new Chart(canvas.getContext('2d'), {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Course (Assignment Completion % vs Marks)',
                        data: pts,
                        backgroundColor: '#10B981',
                        pointRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.raw.label}: ${ctx.raw.x}% completed, ${ctx.raw.y}/100 marks`,
                            },
                        },
                    },
                    scales: {
                        x: { title: { display: true, text: 'Assignment Completion (%)' }, min: 0, max: 100 },
                        y: { title: { display: true, text: 'Total Marks (/100)' }, min: 0, max: 100 },
                    },
                },
            });
        }
    }

    function renderPerformanceFactors(factors) {
        const el = document.getElementById('performanceFactorsList');
        if (!el) return;
        if (!factors.length) {
            el.innerHTML = '<div class="empty-analytics-box"><p class="mb-0 small">No factor diagnostics available yet.</p></div>';
            return;
        }
        el.innerHTML = factors.map((f) => {
            const badgeClass = f.impact === 'positive'
                ? 'bg-success-subtle text-success border-success-subtle'
                : (f.impact === 'watch'
                    ? 'bg-danger-subtle text-danger border-danger-subtle'
                    : (f.impact === 'neutral'
                        ? 'bg-primary-subtle text-primary border-primary-subtle'
                        : 'bg-secondary-subtle text-secondary border'));
            const label = f.impact === 'watch' ? 'Needs Attention'
                : (f.impact === 'positive' ? 'Positive'
                    : (f.impact === 'neutral' ? 'Balanced' : 'Insufficient Data'));
            return `
                <div class="p-3 rounded-3 border bg-light-subtle mb-2">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1 flex-wrap">
                        <strong class="text-dark small">${esc(f.factor)}</strong>
                        <span class="badge border ${badgeClass}">${esc(label)}</span>
                    </div>
                    <div class="small fw-semibold text-primary mb-1">${esc(f.metric)}</div>
                    <p class="mb-0 small text-secondary">${esc(f.detail)}</p>
                </div>
            `;
        }).join('');
    }

    function renderStrengthsAndWeaknesses(sw) {
        const sEl = document.getElementById('strengthsList');
        const wEl = document.getElementById('weaknessesList');
        const strengths = sw.strengths || [];
        const weaknesses = sw.weaknesses || [];

        if (sEl) {
            sEl.innerHTML = strengths.length
                ? strengths.map((item) => `
                    <div class="sw-item strength mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-dark small">${esc(item.title)}</strong>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">${esc(item.category)}</span>
                        </div>
                        <p class="mb-0 small text-secondary">${esc(item.evidence)}</p>
                    </div>
                `).join('')
                : '<div class="empty-analytics-box"><p class="mb-0 small">No standout strengths identified yet.</p></div>';
        }

        if (wEl) {
            wEl.innerHTML = weaknesses.length
                ? weaknesses.map((item) => `
                    <div class="sw-item weakness ${esc(item.severity || 'medium')} mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-dark small">${esc(item.title)}</strong>
                            <span class="badge ${item.severity === 'high' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis'} border">${esc(item.category)}</span>
                        </div>
                        <p class="mb-0 small text-secondary">${esc(item.evidence)}</p>
                    </div>
                `).join('')
                : '<div class="empty-analytics-box"><i class="fas fa-check-circle text-success d-block"></i><p class="mb-0 small">No critical academic or attendance weaknesses detected.</p></div>';
        }
    }

    function renderRecommendations(recs) {
        const container = document.getElementById('recommendationsList');
        if (!container) return;

        if (!recs.length) {
            container.innerHTML = '<div class="empty-analytics-box"><p class="mb-0 small">No specific recommendations generated yet.</p></div>';
            return;
        }

        container.innerHTML = recs.map((r) => {
            const pBadge = r.priority === 'high'
                ? 'bg-danger-subtle text-danger border-danger-subtle'
                : (r.priority === 'medium'
                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                    : 'bg-success-subtle text-success border-success-subtle');
            const evidenceHtml = (r.evidence || [])
                .map((ev) => `<span class="evidence-pill">${esc(ev)}</span>`)
                .join(' ');
            return `
                <div class="rec-card priority-${esc(r.priority)} mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-1">
                        <h6 class="fw-bold text-dark m-0">${esc(r.title)}</h6>
                        <span class="badge border ${pBadge} text-uppercase">${esc(r.priority)} Priority</span>
                    </div>
                    <p class="text-dark small mb-2">${esc(r.summary)}</p>
                    <div class="small text-muted mb-2"><i class="fas fa-lightbulb me-1 text-primary"></i><strong>Why:</strong> ${esc(r.explanation)}</div>
                    <div class="d-flex flex-wrap gap-1 mb-2">${evidenceHtml}</div>
                    ${r.action_url ? `<a href="${esc(r.action_url)}" class="btn btn-sm btn-outline-primary rounded-pill">${esc(r.action_label || 'View Details')} <i class="fas fa-arrow-right ms-1"></i></a>` : ''}
                </div>
            `;
        }).join('');
    }

    function renderAssignmentTimeliness(assign) {
        const emptyBox = document.getElementById('timelinessEmptyState');
        const contentWrap = document.getElementById('timelinessContentWrap');
        const canvas = document.getElementById('timelinessChart');
        const statsEl = document.getElementById('timelinessStats');

        if (timelinessChartInstance) {
            timelinessChartInstance.destroy();
            timelinessChartInstance = null;
        }

        if (!assign.has_data) {
            if (contentWrap) contentWrap.classList.add('d-none');
            if (emptyBox) {
                emptyBox.classList.remove('d-none');
                emptyBox.innerHTML = `
                    <i class="fas fa-inbox d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">No Assignment Records Found</h6>
                    <p class="small mb-2">No assignments have been published for your enrolled courses yet. Timeliness analytics will populate automatically from real submissions.</p>
                    <a href="assignments.html" class="btn btn-sm btn-outline-primary rounded-pill">Go to Assignments</a>
                `;
            }
            return;
        }

        if (emptyBox) emptyBox.classList.add('d-none');
        if (contentWrap) contentWrap.classList.remove('d-none');

        const s = assign.summary || {};
        const tl = assign.timeliness || {};
        if (statsEl) {
            statsEl.innerHTML = `
                <div class="row g-2 text-center">
                    <div class="col-6 col-sm-3"><div class="p-2 rounded bg-light border"><div class="fw-bold fs-5 text-dark">${s.total_assigned}</div><div class="small text-muted">Assigned</div></div></div>
                    <div class="col-6 col-sm-3"><div class="p-2 rounded bg-success-subtle border"><div class="fw-bold fs-5 text-success">${tl.on_time_count}</div><div class="small text-muted">On-Time</div></div></div>
                    <div class="col-6 col-sm-3"><div class="p-2 rounded bg-warning-subtle border"><div class="fw-bold fs-5 text-warning-emphasis">${tl.late_count}</div><div class="small text-muted">Late</div></div></div>
                    <div class="col-6 col-sm-3"><div class="p-2 rounded bg-danger-subtle border"><div class="fw-bold fs-5 text-danger">${s.overdue_count}</div><div class="small text-muted">Overdue</div></div></div>
                </div>
                <div class="mt-2 small text-muted">
                    ${tl.has_submissions ? `On-time rate: <strong>${tl.on_time_rate}%</strong>` : 'No submissions turned in yet.'}
                    ${tl.avg_lead_hours !== null ? ` • Avg lead time: <strong>${tl.avg_lead_hours} hrs</strong> before due` : ''}
                    ${tl.avg_score_pct !== null ? ` • Avg graded score: <strong>${tl.avg_score_pct}%</strong>` : ''}
                </div>
            `;
        }

        if (canvas) {
            timelinessChartInstance = new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['On-Time', 'Late', 'Pending/In-Progress', 'Overdue'],
                    datasets: [{
                        data: [
                            tl.on_time_count || 0,
                            tl.late_count || 0,
                            Math.max(0, (s.pending_count || 0) + (s.in_progress_count || 0) - (s.overdue_count || 0)),
                            s.overdue_count || 0,
                        ],
                        backgroundColor: ['#10B981', '#F59E0B', '#3B82F6', '#EF4444'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        }
    }

    function renderAttendanceTable(attCourses, cohortScope) {
        const tbody = document.getElementById('analyticsAttendanceTableBody');
        if (!tbody) return;
        if (!attCourses.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No current-semester attendance records available.</td></tr>';
            return;
        }
        const scopeName = cohortScope && cohortScope.value ? cohortScope.value : 'Cohort';
        tbody.innerHTML = attCourses.map((a) => {
            const badgeClass = a.percentage >= 85
                ? 'bg-success-subtle text-success border-success-subtle'
                : (a.percentage >= 75
                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                    : 'bg-danger-subtle text-danger border-danger-subtle');
            const bufferText = a.percentage < 75
                ? `Need <strong>${esc(a.classes_needed_75)}</strong> class(es) for 75%`
                : (a.percentage < 85
                    ? `Need <strong>${esc(a.classes_needed_85)}</strong> for 85% Safe · Can miss ${esc(a.safe_absences_75)} (≥75%)`
                    : `Can miss up to <strong>${esc(a.safe_absences_75)}</strong> (≥75%) / <strong>${esc(a.safe_absences_85)}</strong> (≥85%)`);
            const cohortHtml = a.benchmark_available && a.class_avg_attendance !== null
                ? `<span class="badge ${a.attendance_delta >= 0 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'} border">${Number(a.class_avg_attendance).toFixed(1)}% (${a.attendance_delta >= 0 ? '+' : ''}${Number(a.attendance_delta).toFixed(1)}%, n=${esc(a.cohort_size)})</span>`
                : `<span class="text-muted small">&lt;3 ${esc(scopeName)} peers</span>`;

            return `
                <tr>
                    <td><strong>${esc(a.course_code)}</strong><div class="small text-muted">${esc(a.course_name)}</div></td>
                    <td>${esc(a.classes_attended)} / ${esc(a.total_classes)}</td>
                    <td><strong>${Number(a.percentage).toFixed(1)}%</strong></td>
                    <td><span class="badge border ${badgeClass}">${esc(a.status)}</span></td>
                    <td class="small text-secondary">${bufferText}</td>
                    <td>${cohortHtml}</td>
                </tr>
            `;
        }).join('');
    }

    function renderCourseDetailsTable(gradedCourses, attCourses) {
        const tbody = document.getElementById('analyticsCourseTableBody');
        if (!tbody) return;
        if (!gradedCourses.length && !attCourses.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No course performance records available.</td></tr>';
            return;
        }

        const rowsHtml = gradedCourses.map((c) => {
            const deltaHtml = c.benchmark_available && c.marks_delta !== null
                ? `<span class="badge ${c.marks_delta >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} border">${c.marks_delta >= 0 ? '+' : ''}${Number(c.marks_delta).toFixed(1)} vs ${Number(c.class_avg_marks).toFixed(1)} (n=${c.cohort_size})</span>`
                : '<span class="text-muted small">Cohort &lt; 3</span>';
            return `
                <tr>
                    <td><strong>${esc(c.course_code)}</strong></td>
                    <td>${esc(c.course_name)}</td>
                    <td>Sem ${esc(c.semester)}</td>
                    <td>${esc(c.internal_marks)}/50</td>
                    <td>${esc(c.external_marks)}/50</td>
                    <td><strong>${esc(c.total_marks)}/100</strong></td>
                    <td><span class="badge bg-primary-subtle text-primary border">${esc(c.grade)} (${esc(c.grade_points)})</span></td>
                    <td>${deltaHtml}</td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = rowsHtml;
    }

    window.addEventListener('DOMContentLoaded', loadAnalytics);
})();
