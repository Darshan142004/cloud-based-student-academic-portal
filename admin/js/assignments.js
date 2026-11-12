(function () {
    'use strict';

    const API_MANAGE = '../backend/api/admin/manage_assignments.php';
    const API_GRADE = '../backend/api/admin/grade_submission.php';

    let allAssignments = [];
    let allCourses = [];
    let activeAssignment = null;

    const formModalEl = document.getElementById('assignmentFormModal');
    const subsModalEl = document.getElementById('submissionsModal');

    function esc(val) {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showAlert(containerId, msg, type = 'success') {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.className = `alert alert-${type} alert-dismissible fade show rounded-3 mb-3`;
        el.innerHTML = `${esc(msg)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    }

    function formatDateTime(str) {
        if (!str) return '-';
        const d = new Date(str.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return str;
        return d.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function toDatetimeLocal(str) {
        if (!str) return '';
        return str.replace(' ', 'T').slice(0, 16);
    }

    async function loadAssignments() {
        try {
            const res = await fetch(API_MANAGE);
            if (res.status === 401) {
                window.location.href = '../index.html';
                return;
            }
            const payload = await res.json();
            if (!payload.success || !payload.data) {
                showAlert('adminAssignAlert', payload.message || 'Failed to load assignments.', 'danger');
                return;
            }
            allAssignments = payload.data.assignments || [];
            allCourses = payload.data.courses || [];
            populateCourseAndSemesterControls();
            renderAssignmentsTable();

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'add') {
                openCreateModal();
                window.history.replaceState({}, '', 'assignments.html');
            }
        } catch (err) {
            console.error('Admin assignments fetch error:', err);
            showAlert('adminAssignAlert', 'Unable to connect to assignments API.', 'danger');
        }
    }

    function populateCourseAndSemesterControls() {
        const filterCourse = document.getElementById('adminFilterCourse');
        const filterSem = document.getElementById('adminFilterSem');
        const formCourse = document.getElementById('formCourseId');

        const prevCourse = filterCourse.value || 'all';
        const prevSem = filterSem.value || 'all';

        filterCourse.innerHTML = '<option value="all">All Courses</option>';
        formCourse.innerHTML = '';

        const sems = new Set();
        allCourses.forEach((c) => {
            sems.add(Number(c.semester));
            const label = `${c.course_code} — ${c.course_name} (Sem ${c.semester})`;

            const opt1 = document.createElement('option');
            opt1.value = String(c.course_id);
            opt1.textContent = label;
            filterCourse.appendChild(opt1);

            const opt2 = document.createElement('option');
            opt2.value = String(c.course_id);
            opt2.textContent = label;
            formCourse.appendChild(opt2);
        });

        filterSem.innerHTML = '<option value="all">All Semesters</option>';
        Array.from(sems).sort((a, b) => b - a).forEach((s) => {
            const opt = document.createElement('option');
            opt.value = String(s);
            opt.textContent = `Semester ${s}`;
            filterSem.appendChild(opt);
        });

        if (Array.from(filterCourse.options).some((o) => o.value === prevCourse)) filterCourse.value = prevCourse;
        if (Array.from(filterSem.options).some((o) => o.value === prevSem)) filterSem.value = prevSem;
    }

    function renderAssignmentsTable() {
        const tbody = document.getElementById('adminAssignmentsBody');
        const countBadge = document.getElementById('assignmentCountBadge');
        const q = (document.getElementById('adminSearchAssign').value || '').trim().toLowerCase();
        const cid = document.getElementById('adminFilterCourse').value;
        const sem = document.getElementById('adminFilterSem').value;

        const filtered = allAssignments.filter((a) => {
            if (cid !== 'all' && String(a.course_id) !== cid) return false;
            if (sem !== 'all' && String(a.semester) !== sem) return false;
            if (q !== '') {
                const hay = `${a.title} ${a.course_code} ${a.course_name} ${a.description || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        countBadge.textContent = `${filtered.length} assignment${filtered.length === 1 ? '' : 's'}`;

        if (filtered.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                        ${allAssignments.length === 0
                            ? 'No assignments created yet. Click <strong>Create Assignment</strong> to publish one.'
                            : 'No assignments match the selected filters.'}
                    </td>
                </tr>
            `;
            return;
        }

        const pBadge = {
            urgent: 'bg-danger-subtle text-danger border-danger-subtle',
            high: 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
            medium: 'bg-primary-subtle text-primary border-primary-subtle',
            low: 'bg-success-subtle text-success border-success-subtle',
        };

        tbody.innerHTML = filtered.map((a) => `
            <tr>
                <td><strong>${esc(a.course_code)}</strong><div class="small text-muted">${esc(a.course_name)}</div></td>
                <td><div class="fw-semibold text-dark">${esc(a.title)}</div><div class="small text-muted text-truncate" style="max-width: 240px;">${esc(a.description || '')}</div></td>
                <td><span class="badge bg-light text-dark border">Sem ${esc(a.semester)}</span></td>
                <td class="small">${esc(formatDateTime(a.due_date))}</td>
                <td><strong>${esc(a.max_marks)}</strong></td>
                <td><span class="badge border text-uppercase ${pBadge[a.priority] || pBadge.medium}">${esc(a.priority)}</span></td>
                <td class="small">
                    <span class="badge bg-info-subtle text-info-emphasis border">${esc(a.submitted_count)}/${esc(a.enrolled_count)} submitted</span>
                    <div class="text-muted mt-1">${esc(a.graded_count)} graded</div>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="submissions" data-id="${a.assignment_id}" title="View & Grade Submissions">
                            <i class="fas fa-users-cog me-1"></i>Submissions
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="edit" data-id="${a.assignment_id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${a.assignment_id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function openCreateModal() {
        document.getElementById('assignmentModalTitle').textContent = 'Create Assignment';
        document.getElementById('formAssignmentId').value = '';
        document.getElementById('formTitle').value = '';
        document.getElementById('formDescription').value = '';
        document.getElementById('formMaxMarks').value = '20';
        document.getElementById('formPriority').value = 'medium';
        const d = new Date(Date.now() + 7 * 86400 * 1000);
        const pad = (n) => String(n).padStart(2, '0');
        document.getElementById('formDueDate').value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T23:59`;
        document.getElementById('assignmentFormError').classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(formModalEl).show();
    }

    function openEditModal(item) {
        document.getElementById('assignmentModalTitle').textContent = 'Edit Assignment';
        document.getElementById('formAssignmentId').value = item.assignment_id;
        document.getElementById('formCourseId').value = item.course_id;
        document.getElementById('formTitle').value = item.title;
        document.getElementById('formDescription').value = item.description || '';
        document.getElementById('formDueDate').value = toDatetimeLocal(item.due_date);
        document.getElementById('formMaxMarks').value = item.max_marks;
        document.getElementById('formPriority').value = item.priority || 'medium';
        document.getElementById('assignmentFormError').classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(formModalEl).show();
    }

    async function deleteAssignment(item) {
        if (!confirm(`Delete assignment "${item.title}" (${item.course_code})? This will also delete all student submissions for it.`)) {
            return;
        }
        try {
            const res = await fetch(API_MANAGE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', assignment_id: item.assignment_id }),
            });
            const data = await res.json();
            if (!data.success) {
                showAlert('adminAssignAlert', data.message || 'Failed to delete assignment.', 'danger');
                return;
            }
            showAlert('adminAssignAlert', 'Assignment deleted successfully.', 'success');
            loadAssignments();
        } catch (err) {
            showAlert('adminAssignAlert', 'Network error while deleting assignment.', 'danger');
        }
    }

    async function openSubmissionsModal(assignmentId) {
        document.getElementById('subsAlert').className = 'd-none';
        const tbody = document.getElementById('submissionsTableBody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Loading submissions...</td></tr>';
        bootstrap.Modal.getOrCreateInstance(subsModalEl).show();

        try {
            const res = await fetch(`${API_MANAGE}?assignment_id=${encodeURIComponent(assignmentId)}`);
            const payload = await res.json();
            if (!payload.success || !payload.data) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center py-4">${esc(payload.message || 'Failed to load submissions.')}</td></tr>`;
                return;
            }
            activeAssignment = payload.data.assignment;
            const subs = payload.data.submissions || [];
            document.getElementById('subsModalTitle').textContent = `Submissions: ${activeAssignment.title}`;
            document.getElementById('subsModalSubtitle').textContent = `${activeAssignment.course_code} — ${activeAssignment.course_name} (Sem ${activeAssignment.semester}) • Max Marks: ${activeAssignment.max_marks}`;

            if (subs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No students are enrolled in this course and semester.</td></tr>';
                return;
            }

            tbody.innerHTML = subs.map((s) => {
                const dlUrl = `../backend/api/download_document.php?type=assignment&assignment_id=${encodeURIComponent(activeAssignment.assignment_id)}&student_id=${encodeURIComponent(s.student_id)}`;
                const fileLink = s.file_path
                    ? `<div class="mt-1"><a href="${esc(dlUrl)}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2"><i class="fas fa-paperclip me-1"></i>Attachment</a></div>`
                    : '';
                return `
                    <tr data-student-id="${s.student_id}">
                        <td>
                            <div class="fw-bold text-dark">${esc(s.student_name)}</div>
                            <div class="small text-muted">${esc(s.enrollment_id)}</div>
                        </td>
                        <td>
                            <select class="form-select form-select-sm mb-1 sub-status">
                                <option value="pending" ${s.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="in_progress" ${s.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                <option value="submitted" ${s.status === 'submitted' ? 'selected' : ''}>Submitted</option>
                                <option value="graded" ${s.status === 'graded' ? 'selected' : ''}>Graded</option>
                            </select>
                            <div class="small text-muted">Progress: ${esc(s.progress_pct)}%${s.submitted_at ? `<br>Submitted: ${esc(formatDateTime(s.submitted_at))}` : ''}</div>
                        </td>
                        <td class="small">
                            ${s.submission_text ? `<div class="text-dark">${esc(s.submission_text)}</div>` : '<span class="text-muted">No notes submitted</span>'}
                            ${fileLink}
                        </td>
                        <td>
                            <div class="input-group input-group-sm mb-1">
                                <span class="input-group-text">Marks</span>
                                <input type="number" class="form-control sub-marks" min="0" max="${esc(activeAssignment.max_marks)}" step="0.5" value="${s.marks_obtained !== null && s.marks_obtained !== undefined ? esc(s.marks_obtained) : ''}" placeholder="0-${esc(activeAssignment.max_marks)}">
                            </div>
                            <input type="text" class="form-control form-control-sm sub-feedback" value="${esc(s.feedback || '')}" placeholder="Instructor feedback...">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary save-grade-btn" data-student-id="${s.student_id}">
                                <i class="fas fa-check me-1"></i>Save
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-4">Network error loading submissions.</td></tr>';
        }
    }

    document.getElementById('openCreateAssignmentBtn').addEventListener('click', openCreateModal);
    document.getElementById('adminSearchAssign').addEventListener('input', renderAssignmentsTable);
    document.getElementById('adminFilterCourse').addEventListener('change', renderAssignmentsTable);
    document.getElementById('adminFilterSem').addEventListener('change', renderAssignmentsTable);

    document.getElementById('adminAssignmentsBody').addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const id = Number(btn.getAttribute('data-id'));
        const item = allAssignments.find((a) => Number(a.assignment_id) === id);
        if (!item) return;
        const action = btn.getAttribute('data-action');
        if (action === 'edit') openEditModal(item);
        else if (action === 'delete') deleteAssignment(item);
        else if (action === 'submissions') openSubmissionsModal(id);
    });

    document.getElementById('assignmentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errBox = document.getElementById('assignmentFormError');
        errBox.classList.add('d-none');

        const assignmentId = document.getElementById('formAssignmentId').value;
        const payload = {
            action: assignmentId ? 'update' : 'create',
            assignment_id: assignmentId ? Number(assignmentId) : undefined,
            course_id: Number(document.getElementById('formCourseId').value),
            title: document.getElementById('formTitle').value.trim(),
            description: document.getElementById('formDescription').value.trim(),
            due_date: document.getElementById('formDueDate').value,
            max_marks: Number(document.getElementById('formMaxMarks').value),
            priority: document.getElementById('formPriority').value,
        };

        try {
            const res = await fetch(API_MANAGE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.success) {
                errBox.textContent = data.message || 'Failed to save assignment.';
                errBox.classList.remove('d-none');
                return;
            }
            bootstrap.Modal.getInstance(formModalEl).hide();
            showAlert('adminAssignAlert', data.message || 'Assignment saved.', 'success');
            loadAssignments();
        } catch (err) {
            errBox.textContent = 'Network error while saving assignment.';
            errBox.classList.remove('d-none');
        }
    });

    document.getElementById('submissionsTableBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.save-grade-btn');
        if (!btn || !activeAssignment) return;
        const row = btn.closest('tr');
        const studentId = Number(btn.getAttribute('data-student-id'));
        const status = row.querySelector('.sub-status').value;
        const marks = row.querySelector('.sub-marks').value.trim();
        const feedback = row.querySelector('.sub-feedback').value.trim();

        if (status === 'graded' && marks === '') {
            showAlert('subsAlert', 'Please enter marks_obtained when setting status to Graded.', 'danger');
            return;
        }

        try {
            const res = await fetch(API_GRADE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    assignment_id: Number(activeAssignment.assignment_id),
                    student_id: studentId,
                    status,
                    marks_obtained: marks !== '' ? Number(marks) : null,
                    feedback,
                }),
            });
            const data = await res.json();
            if (!data.success) {
                showAlert('subsAlert', data.message || 'Failed to save grade/status.', 'danger');
                return;
            }
            showAlert('subsAlert', data.message || 'Updated successfully.', 'success');
            loadAssignments();
            openSubmissionsModal(activeAssignment.assignment_id);
        } catch (err) {
            showAlert('subsAlert', 'Network error while saving grade/status.', 'danger');
        }
    });

    window.addEventListener('DOMContentLoaded', loadAssignments);
})();

