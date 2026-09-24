// Admin Dashboard JS - EduPulse Academic Analytics
window.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    loadAnalyticsData();
});

// Load Summary Stats & Recent Students
function loadDashboardData() {
    fetch('../backend/api/admin/get_dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalStudents').textContent = data.data.total_students;
                document.getElementById('totalActivities').textContent = data.data.total_activities;
                document.getElementById('totalCourses').textContent = data.data.total_courses;
                document.getElementById('avgAttendance').textContent = data.data.avg_attendance + '%';
                
                if (data.data.admin_name) {
                    const nameElements = document.querySelectorAll('#adminName');
                    nameElements.forEach(el => el.textContent = data.data.admin_name);
                }
                
                displayRecentStudents(data.data.recent_students || []);
            } else {
                console.error('Failed to load dashboard stats:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard stats:', error);
        });
}

function displayRecentStudents(students) {
    const tbody = document.getElementById('recentStudents');
    if (!tbody) return;
    
    if (!students || students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No students found</td></tr>';
        return;
    }
    
    tbody.innerHTML = students.map(s => `
        <tr>
            <td><strong>${s.enrollment_id}</strong></td>
            <td>${s.first_name} ${s.last_name}</td>
            <td>${s.email}</td>
            <td><span class="badge bg-primary">Sem ${s.current_semester}</span></td>
            <td>${s.program}</td>
        </tr>
    `).join('');
}

// Load Academic Analytics & Charts
function loadAnalyticsData() {
    fetch('../backend/api/admin/get_analytics.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const analytics = data.data;
                renderAttendanceDistributionChart(analytics.attendance_ranges);
                renderSemesterDistributionChart(analytics.semester_distribution);
                renderCourseAttendanceChart(analytics.course_attendance);
                renderGradePerformanceChart(analytics.performance);
            } else {
                console.warn('Analytics unavailable or empty data');
            }
        })
        .catch(error => {
            console.error('Error fetching analytics:', error);
        });
}

// 1. Attendance Distribution (Doughnut Chart)
let attendanceChartInstance = null;
function renderAttendanceDistributionChart(ranges) {
    const ctx = document.getElementById('attendanceDistributionChart');
    
    const safe = ranges?.safe || 0;
    const warning = ranges?.warning || 0;
    const critical = ranges?.critical || 0;

    // Dynamically update Attendance Risk Summary counts
    const safeElem = document.getElementById('riskSafeCount');
    const warningElem = document.getElementById('riskWarningCount');
    const criticalElem = document.getElementById('riskCriticalCount');

    if (safeElem) safeElem.innerHTML = `${safe} <span class="fs-6 text-muted fw-normal">students</span>`;
    if (warningElem) warningElem.innerHTML = `${warning} <span class="fs-6 text-muted fw-normal">students</span>`;
    if (criticalElem) criticalElem.innerHTML = `${critical} <span class="fs-6 text-muted fw-normal">students</span>`;

    if (!ctx) return;

    if (attendanceChartInstance) attendanceChartInstance.destroy();
    
    attendanceChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Safe (>= 85%)', 'Warning (75-84.9%)', 'Critical (< 75%)'],
            datasets: [{
                data: [safe, warning, critical],
                backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 8, font: { size: 10 }, usePointStyle: true }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.label}: ${context.raw} Students`;
                        }
                    }
                }
            }
        }
    });
}

// 2. Semester Distribution (Bar Chart)
let semesterChartInstance = null;
function renderSemesterDistributionChart(semData) {
    const ctx = document.getElementById('semesterDistributionChart');
    if (!ctx) return;
    
    const labels = (semData || []).map(s => `Sem ${s.semester}`);
    const values = (semData || []).map(s => s.total_students);

    if (semesterChartInstance) semesterChartInstance.destroy();
    
    semesterChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['No Data'],
            datasets: [{
                label: 'Enrolled Students',
                data: values.length > 0 ? values : [0],
                backgroundColor: '#0F52BA',
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
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

// 3. Course Attendance (Horizontal Bar Chart)
let courseAttChartInstance = null;
function renderCourseAttendanceChart(courses) {
    const ctx = document.getElementById('courseAttendanceChart');
    if (!ctx) return;
    
    const labels = (courses || []).map(c => c.course_code);
    const values = (courses || []).map(c => c.avg_attendance);

    if (courseAttChartInstance) courseAttChartInstance.destroy();

    courseAttChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['No Courses'],
            datasets: [{
                label: 'Avg Attendance (%)',
                data: values.length > 0 ? values : [0],
                backgroundColor: '#2575FC',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: v => v + '%' }
                }
            }
        }
    });
}

// 4. Grade Performance Analytics (Bar Chart & System CGPA Badge)
let gradeChartInstance = null;
function renderGradePerformanceChart(performance) {
    const ctx = document.getElementById('gradePerformanceChart');
    const cgpaElem = document.getElementById('systemCgpaValue');
    
    if (cgpaElem && performance?.system_cgpa !== undefined) {
        cgpaElem.textContent = parseFloat(performance.system_cgpa).toFixed(2);
    }

    if (!ctx) return;

    const gradesObj = performance?.grade_distribution || {};
    const labels = Object.keys(gradesObj);
    const values = Object.values(gradesObj);

    if (gradeChartInstance) gradeChartInstance.destroy();

    gradeChartInstance = new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['A+', 'A', 'B+'],
            datasets: [{
                label: 'Grade Count',
                data: values.length > 0 ? values : [0, 0, 0],
                backgroundColor: ['#0F52BA', '#4AB7E0', '#3B82F6', '#F59E0B', '#10B981', '#EF4444'],
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
                    beginAtZero: true,
                    ticks: { stepSize: 5 }
                }
            }
        }
    });
}
