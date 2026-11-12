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

    let allAssignments = [];
    let currentFilter = 'all';
    let currentCourseId = 'all';
    let currentSearch = '';

    const detailsModalEl = document.getElementById('detailsModal');
    const progressModalEl = document.getElementById('progressModal');
    const submitModalEl = document.getElementById('submitModal');

    function esc(val) {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showPageAlert(message, type = 'success') {
        const box = document.getElementById('assignmentAlertArea');
        if (!box) return;
        box.className = `alert alert-${type} alert-dismissible fade show rounded-4 mb-3`;
        box.innerHTML = `${esc(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    }

    function formatDueDate(isoLike) {
        if (!isoLike) return '-';
        const d = new Date(isoLike.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return isoLike;
        return d.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function formatRemaining(item) {
        if (item.status === 'graded') return 'Graded';
        if (item.status === 'submitted') {
            return item.timeliness_status === 'late' ? 'Submitted Late' : 'Submitted On Time';
        }
        if (item.is_overdue) {
            const hrs = Math.abs(Math.round(item.hours_remaining || 0));
            return hrs >= 24 ? `Overdue by ${Math.floor(hrs / 24)}d` : `Overdue by ${hrs}h`;
        }
        const hrs = Math.round(item.hours_remaining || 0);
        if (hrs < 24) return `${Math.max(1, hrs)}h left`;
        return `${Math.floor(hrs / 24)}d left`;
    }

    function statusBadge(item) {
        if (item.status === 'graded') {
            return '<span class="badge bg-success-subtle text-success border border-success-subtle">Graded</span>';
        }
        if (item.status === 'submitted') {
            return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Submitted</span>';
        }
        if (item.status === 'in_progress') {
            return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">In Progress</span>';
        }
        if (item.is_overdue) {
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Overdue</span>';
        }
        return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pending</span>';
    }

    function loadAssignments() {
        const loading = document.getElementById('assignmentsLoading');
        if (loading) loading.classList.remove('d-none');

        fetch('./backend/api/get_assignments.php')
            .then(async (res) => {
                if (res.status === 401) {
                    window.location.href = 'index.html';
                    return null;
                }
                return res.json();
            })
            .then((payload) => {
                if (loading) loading.classList.add('d-none');
                if (!payload) return;
                if (!payload.success || !payload.data) {
                    showPageAlert(payload.message || 'Failed to load assignments.', 'danger');
                    return;
                }

                const d = payload.data;
                allAssignments = d.assignments || [];
                updateHeader(d);
                updateSummaryCards(d.summary || {}, d.timeliness || {}, d.has_data);
                populateCourseFilter(d.enrolled_courses || []);
                renderAssignments();
            })
            .catch((err) => {
                if (loading) loading.classList.add('d-none');
                console.error('Assignments fetch error:', err);
                showPageAlert('Unable to load assignments from server.', 'danger');
            });
    }

    function updateHeader(d) {
        const nameEl = document.getElementById('headerStudentName');
        const avatarEl = document.getElementById('headerAvatar');
        if (nameEl && d.student_name) nameEl.textContent = d.student_name;
        if (avatarEl && d.student_name) {
            avatarEl.src = d.profile_photo_url
                || ((d.profile_photo && d.profile_photo !== 'default-avatar.png')
                    ? 'backend/api/download_document.php?type=profile'
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(d.student_name)}&background=0F52BA&color=fff`);
        }
    }

    function updateSummaryCards(summary, timeliness, hasData) {
        document.getElementById('statPending').textContent = summary.pending_count ?? 0;
        document.getElementById('statInProgress').textContent = summary.in_progress_count ?? 0;
        document.getElementById('statCompleted').textContent = summary.completed_count ?? 0;
        document.getElementById('statOverdue').textContent = summary.overdue_count ?? 0;

        const banner = document.getElementById('timelinessBanner');
        if (!banner) return;
        if (!hasData) {
            banner.classList.add('d-none');
            return;
        }
        banner.classList.remove('d-none');
        const tlText = timeliness.has_submissions
            ? `On-time submission rate: <strong>${Number(timeliness.on_time_rate).toFixed(1)}%</strong> (${timeliness.on_time_count} on-time, ${timeliness.late_count} late)`
            : 'No submissions turned in yet.';
        const avgScore = timeliness.avg_score_pct !== null && timeliness.avg_score_pct !== undefined
            ? ` &bull; Average graded score: <strong>${Number(timeliness.avg_score_pct).toFixed(1)}%</strong>`
            : '';
        banner.innerHTML = `<i class="fas fa-info-circle text-primary me-2"></i>${tlText}${avgScore}`;
    }

    function populateCourseFilter(courses) {
        const sel = document.getElementById('courseFilterSelect');
        if (!sel) return;
        const prev = sel.value || 'all';
        sel.innerHTML = '<option value="all">All Enrolled Courses</option>';
        courses.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = String(c.course_id);
            opt.textContent = `${c.course_code} - ${c.course_name} (Sem ${c.semester})`;
            sel.appendChild(opt);
        });
        if (Array.from(sel.options).some((o) => o.value === prev)) {
            sel.value = prev;
        }
    }

    function getFilteredAssignments() {
        return allAssignments.filter((item) => {
            if (currentFilter === 'pending' && item.status !== 'pending') return false;
            if (currentFilter === 'in_progress' && item.status !== 'in_progress') return false;
            if (currentFilter === 'completed' && !['submitted', 'graded'].includes(item.status)) return false;
            if (currentFilter === 'overdue' && !item.is_overdue) return false;
            if (currentCourseId !== 'all' && String(item.course_id) !== String(currentCourseId)) return false;
            if (currentSearch !== '') {
                const hay = `${item.title} ${item.course_code} ${item.course_name} ${item.description}`.toLowerCase();
                if (!hay.includes(currentSearch)) return false;
            }
            return true;
        });
    }

    function renderAssignments() {
        const listEl = document.getElementById('assignmentsList');
        if (!listEl) return;

        if (allAssignments.length === 0) {
            listEl.innerHTML = `
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                        <div class="mb-3 text-muted"><i class="fas fa-inbox fa-3x"></i></div>
                        <h5 class="fw-bold text-dark">No Assignments Assigned Yet</h5>
                        <p class="text-muted mb-0 mx-auto" style="max-width: 520px;">
                            There are currently no assignments published for your enrolled courses. Once an instructor or administrator creates an assignment, it will appear here with deadlines, progress tracking, and submission tools.
                        </p>
                    </div>
                </div>
            `;
            return;
        }

        const filtered = getFilteredAssignments();
        if (filtered.length === 0) {
            listEl.innerHTML = `
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                        <i class="fas fa-filter fa-2x text-muted mb-2"></i>
                        <h6 class="fw-bold text-dark">No Matching Assignments</h6>
                        <p class="text-muted small mb-0">Try changing the status tab, course filter, or search query.</p>
                    </div>
                </div>
            `;
            return;
        }

        listEl.innerHTML = filtered.map((item) => {
            const isCompleted = ['submitted', 'graded'].includes(item.status);
            const cardTone = isCompleted ? 'completed' : (item.priority || 'medium');
            const progressBarClass = item.status === 'graded'
                ? 'bg-success'
                : (item.status === 'submitted' ? 'bg-primary' : (item.is_overdue ? 'bg-danger' : 'bg-info'));

            const marksHtml = item.status === 'graded' && item.marks_obtained !== null
                ? `<div class="alert alert-success py-2 px-3 small mb-3">
                       <div class="d-flex justify-content-between align-items-center">
                           <strong><i class="fas fa-award me-1"></i>Score: ${esc(item.marks_obtained)} / ${esc(item.max_marks)} (${esc(item.score_pct)}%)</strong>
                       </div>
                       ${item.feedback ? `<div class="mt-1 text-dark"><strong>Feedback:</strong> ${esc(item.feedback)}</div>` : ''}
                   </div>`
                : '';

            const actionButtons = isCompleted
                ? `<button type="button" class="btn btn-outline-primary btn-sm" data-action="details" data-id="${item.assignment_id}">
                       <i class="fas fa-eye me-1"></i>View Submission
                   </button>
                   ${item.status === 'submitted' ? `<button type="button" class="btn btn-outline-secondary btn-sm" data-action="submit" data-id="${item.assignment_id}"><i class="fas fa-edit me-1"></i>Update Submission</button>` : ''}`
                : `<button type="button" class="btn btn-outline-primary btn-sm" data-action="details" data-id="${item.assignment_id}">
                       <i class="fas fa-eye me-1"></i>Details
                   </button>
                   <button type="button" class="btn btn-outline-secondary btn-sm" data-action="progress" data-id="${item.assignment_id}">
                       <i class="fas fa-sliders-h me-1"></i>Progress
                   </button>
                   <button type="button" class="btn btn-primary btn-sm" data-action="submit" data-id="${item.assignment_id}">
                       <i class="fas fa-upload me-1"></i>Submit
                   </button>`;

            return `
                <div class="col-lg-6">
                    <div class="assignment-card ${esc(cardTone)} h-100 d-flex flex-column">
                        <div class="assignment-header">
                            <div class="assignment-title-section">
                                <h5>${esc(item.title)}</h5>
                                <p class="course-name"><i class="fas fa-book me-1"></i>${esc(item.course_code)} &mdash; ${esc(item.course_name)} (Sem ${esc(item.semester)})</p>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <span class="priority-badge ${esc(isCompleted ? 'completed' : item.priority)}">${esc(item.priority)}</span>
                                ${statusBadge(item)}
                            </div>
                        </div>
                        <div class="assignment-body flex-grow-1 d-flex flex-column justify-content-between">
                            <div>
                                <p class="description">${esc(item.description || 'No description provided.')}</p>
                                <div class="progress-section">
                                    <div class="progress-header">
                                        <span>Progress</span>
                                        <span>${esc(item.progress_pct)}%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar ${progressBarClass}" style="width: ${Number(item.progress_pct || 0)}%"></div>
                                    </div>
                                </div>
                                ${marksHtml}
                            </div>
                            <div>
                                <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                                    <span><i class="fas fa-calendar-alt me-1 text-primary"></i>Due: ${esc(formatDueDate(item.due_date))}</span>
                                    <span class="${item.is_overdue ? 'text-danger fw-semibold' : ''}"><i class="fas fa-clock me-1"></i>${esc(formatRemaining(item))}</span>
                                    <span><i class="fas fa-award me-1 text-warning"></i>Max: ${esc(item.max_marks)} marks</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    ${actionButtons}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function findAssignment(id) {
        return allAssignments.find((a) => Number(a.assignment_id) === Number(id)) || null;
    }

    function openDetailsModal(item) {
        document.getElementById('detailsTitle').textContent = item.title;
        document.getElementById('detailsSubtitle').textContent = `${item.course_code} — ${item.course_name} (Semester ${item.semester})`;
        const downloadHref = item.file_path
            ? `backend/api/download_document.php?type=assignment&assignment_id=${encodeURIComponent(item.assignment_id)}`
            : '';
        const fileHtml = downloadHref
            ? `<a href="${esc(downloadHref)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-download me-1"></i>Download Attached File</a>`
            : '<span class="text-muted">None</span>';

        document.getElementById('detailsBody').innerHTML = `
            <div class="mb-3">${statusBadge(item)} <span class="badge bg-light text-dark border ms-1">Priority: ${esc(item.priority)}</span> <span class="badge bg-light text-dark border ms-1">Max Marks: ${esc(item.max_marks)}</span></div>
            <h6 class="fw-bold">Description</h6>
            <p class="text-secondary">${esc(item.description || 'No description provided.')}</p>
            <div class="row g-3 bg-light p-3 rounded-3 border mb-3 small">
                <div class="col-sm-6"><strong>Due Date:</strong><br>${esc(formatDueDate(item.due_date))}</div>
                <div class="col-sm-6"><strong>Progress:</strong><br>${esc(item.progress_pct)}%</div>
                <div class="col-sm-6"><strong>Submitted At:</strong><br>${item.submitted_at ? esc(formatDueDate(item.submitted_at)) : 'Not submitted yet'}</div>
                <div class="col-sm-6"><strong>Uploaded File:</strong><br>${fileHtml}</div>
            </div>
            ${item.submission_text ? `<h6 class="fw-bold">Your Submission Notes</h6><div class="p-3 bg-light border rounded-3 small mb-3">${esc(item.submission_text)}</div>` : ''}
            ${item.status === 'graded' ? `<div class="alert alert-success mb-0"><strong>Grade: ${esc(item.marks_obtained)} / ${esc(item.max_marks)}</strong>${item.feedback ? `<div class="mt-1">Feedback: ${esc(item.feedback)}</div>` : ''}</div>` : ''}
        `;
        bootstrap.Modal.getOrCreateInstance(detailsModalEl).show();
    }

    function openProgressModal(item) {
        document.getElementById('progressAssignmentId').value = item.assignment_id;
        document.getElementById('progressAssignmentTitle').textContent = `${item.course_code}: ${item.title}`;
        const range = document.getElementById('progressPctRange');
        const pct = Math.min(99, Math.max(0, Number(item.progress_pct || 0)));
        range.value = pct;
        document.getElementById('progressPctLabel').textContent = `${pct}%`;
        document.getElementById('progressNotes').value = item.submission_text || '';
        document.getElementById('progressError').classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(progressModalEl).show();
    }

    function openSubmitModal(item) {
        document.getElementById('submitAssignmentId').value = item.assignment_id;
        document.getElementById('submitAssignmentTitle').textContent = `${item.course_code}: ${item.title}`;
        document.getElementById('submitAssignmentMeta').textContent = `Due: ${formatDueDate(item.due_date)} • Max Marks: ${item.max_marks}`;
        document.getElementById('submissionText').value = item.submission_text || '';
        document.getElementById('submissionFile').value = '';
        document.getElementById('submitError').classList.add('d-none');

        const existingFileEl = document.getElementById('submitExistingFile');
        if (item.file_path) {
            const dlUrl = `backend/api/download_document.php?type=assignment&assignment_id=${encodeURIComponent(item.assignment_id)}`;
            existingFileEl.classList.remove('d-none');
            existingFileEl.innerHTML = `<i class="fas fa-paperclip me-1"></i>Previously attached: <a href="${esc(dlUrl)}" target="_blank" rel="noopener noreferrer">${esc(item.file_path.split('/').pop())}</a>`;
        } else {
            existingFileEl.classList.add('d-none');
            existingFileEl.textContent = '';
        }
        bootstrap.Modal.getOrCreateInstance(submitModalEl).show();
    }

    // Event listeners
    document.querySelectorAll('#assignmentTabs .nav-link').forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('#assignmentTabs .nav-link').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            currentFilter = tab.getAttribute('data-filter') || 'all';
            renderAssignments();
        });
    });

    const courseSel = document.getElementById('courseFilterSelect');
    if (courseSel) {
        courseSel.addEventListener('change', () => {
            currentCourseId = courseSel.value;
            renderAssignments();
        });
    }

    const searchInput = document.getElementById('searchAssignment');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value.trim().toLowerCase();
            renderAssignments();
        });
    }

    const listEl = document.getElementById('assignmentsList');
    if (listEl) {
        listEl.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const item = findAssignment(btn.getAttribute('data-id'));
            if (!item) return;
            const action = btn.getAttribute('data-action');
            if (action === 'details') openDetailsModal(item);
            else if (action === 'progress') openProgressModal(item);
            else if (action === 'submit') openSubmitModal(item);
        });
    }

    const rangeEl = document.getElementById('progressPctRange');
    if (rangeEl) {
        rangeEl.addEventListener('input', () => {
            document.getElementById('progressPctLabel').textContent = `${rangeEl.value}%`;
        });
    }

    document.getElementById('progressForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errBox = document.getElementById('progressError');
        errBox.classList.add('d-none');

        const payload = {
            action: 'progress',
            assignment_id: Number(document.getElementById('progressAssignmentId').value),
            progress_pct: Number(document.getElementById('progressPctRange').value),
            submission_text: document.getElementById('progressNotes').value.trim(),
        };

        try {
            const res = await fetch('./backend/api/submit_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.success) {
                errBox.textContent = data.message || 'Failed to update progress.';
                errBox.classList.remove('d-none');
                return;
            }
            bootstrap.Modal.getInstance(progressModalEl).hide();
            showPageAlert(data.message || 'Progress updated.', 'success');
            loadAssignments();
        } catch (err) {
            errBox.textContent = 'Network error while saving progress.';
            errBox.classList.remove('d-none');
        }
    });

    document.getElementById('submitForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errBox = document.getElementById('submitError');
        errBox.classList.add('d-none');

        const formData = new FormData(e.target);
        formData.append('action', 'submit');

        const fileInput = document.getElementById('submissionFile');
        if (fileInput && fileInput.files.length > 0 && fileInput.files[0].size > 5 * 1024 * 1024) {
            errBox.textContent = 'Selected file exceeds the 5MB limit.';
            errBox.classList.remove('d-none');
            return;
        }

        try {
            const res = await fetch('./backend/api/submit_assignment.php', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            if (!data.success) {
                errBox.textContent = data.message || 'Failed to submit assignment.';
                errBox.classList.remove('d-none');
                return;
            }
            bootstrap.Modal.getInstance(submitModalEl).hide();
            showPageAlert(data.message || 'Assignment submitted successfully!', 'success');
            loadAssignments();
        } catch (err) {
            errBox.textContent = 'Network error while submitting assignment.';
            errBox.classList.remove('d-none');
        }
    });

    window.addEventListener('DOMContentLoaded', loadAssignments);
})();
