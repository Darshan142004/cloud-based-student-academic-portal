// Sidebar toggle for mobile
const toggleBtn = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 991) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Performance Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
let cachedGradesData = null;

let performanceChart = new Chart(performanceCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'GPA',
            data: [],
            borderColor: '#0F52BA',
            backgroundColor: 'rgba(15, 82, 186, 0.15)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#0F52BA',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleColor: '#fff',
                bodyColor: '#fff',
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: false,
                min: 5,
                max: 10,
                ticks: {
                    stepSize: 0.5
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

function renderPerformanceLineChart(data) {
    if (!data || !data.grades) return;
    const labels = [];
    const sgpaValues = [];
    const semesters = Object.keys(data.grades).sort((a, b) => Number(a) - Number(b));
    semesters.forEach(sem => {
        const semData = data.grades[sem];
        if (semData && semData.sgpa > 0) {
            labels.push(`Sem ${sem}`);
            sgpaValues.push(parseFloat(semData.sgpa));
        }
    });
    if (labels.length > 0) {
        performanceChart.data.labels = labels;
        performanceChart.data.datasets[0].data = sgpaValues;
        const minSgpa = Math.max(0, Math.floor(Math.min(...sgpaValues) - 0.5));
        const maxSgpa = Math.min(10, Math.ceil(Math.max(...sgpaValues) + 0.5));
        if (performanceChart.options.scales && performanceChart.options.scales.y) {
            performanceChart.options.scales.y.min = minSgpa;
            performanceChart.options.scales.y.max = maxSgpa;
        }
        performanceChart.update();
    }
}

function renderGradeDistributionBarChart(data) {
    if (!data || !data.grades) return;
    let aPlus = 0, a = 0, bPlus = 0, b = 0, others = 0;
    Object.values(data.grades).forEach(sem => {
        if (sem.courses) {
            sem.courses.forEach(course => {
                if (course.grade_points !== null && course.grade_points !== undefined) {
                    const gp = parseFloat(course.grade_points);
                    if (!isNaN(gp)) {
                        if (gp >= 9) aPlus++;
                        else if (gp >= 8) a++;
                        else if (gp >= 7) bPlus++;
                        else if (gp >= 6) b++;
                        else others++;
                    }
                }
            });
        }
    });
    performanceChart.data.labels = ['A+', 'A', 'B+', 'B', 'Other'];
    performanceChart.data.datasets[0].data = [aPlus, a, bPlus, b, others];
    performanceChart.update();
}

// Chart Filter
const chartFilter = document.getElementById('chartFilter');
if (chartFilter) {
    chartFilter.addEventListener('change', (e) => {
        if (e.target.value === 'grades') {
            performanceChart.destroy();
            performanceChart = new Chart(performanceCtx, {
                type: 'bar',
                data: {
                    labels: ['A+', 'A', 'B+', 'B', 'Other'],
                    datasets: [{
                        label: 'Number of Subjects',
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: [
                            '#0F52BA',
                            '#4AB7E0',
                            '#3B82F6',
                            '#F59E0B',
                            '#ff6b6b'
                        ],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            if (cachedGradesData) {
                renderGradeDistributionBarChart(cachedGradesData);
            }
        } else {
            performanceChart.destroy();
            performanceChart = new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'GPA',
                        data: [],
                        borderColor: '#0F52BA',
                        backgroundColor: 'rgba(15, 82, 186, 0.15)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#0F52BA',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 5,
                            max: 10,
                            ticks: {
                                stepSize: 0.5
                            }
                        }
                    }
                }
            });
            if (cachedGradesData) {
                renderPerformanceLineChart(cachedGradesData);
            }
        }
    });
}

// Auto-fetch real grades data for performanceChart
fetch('./backend/api/get_grades.php')
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data) {
            cachedGradesData = res.data;
            renderPerformanceLineChart(cachedGradesData);
        }
    })
    .catch(err => console.error('Error fetching grades for performance chart:', err));

// GPA Calculator
function calculateGPA() {
    const expectedGradeEl = document.getElementById('expectedGrade');
    const creditsEl = document.getElementById('credits');
    if (!expectedGradeEl || !creditsEl) return;
    
    const expectedGrade = parseFloat(expectedGradeEl.value);
    const credits = parseFloat(creditsEl.value);
    
    let currentCGPA = 8.5;
    let totalCredits = 80;

    if (window.allGradesData && window.allGradesData.cgpa) {
        currentCGPA = parseFloat(window.allGradesData.cgpa);
        totalCredits = parseFloat(window.allGradesData.total_credits || 80);
    }
    
    // Calculate new CGPA
    const totalPoints = currentCGPA * totalCredits;
    const newTotalPoints = totalPoints + (expectedGrade * credits);
    const newTotalCredits = totalCredits + credits;
    const predictedCGPA = (newTotalPoints / newTotalCredits).toFixed(2);
    
    // Display result with animation
    const resultElement = document.getElementById('predictedGPA');
    if (resultElement) {
        resultElement.style.transform = 'scale(1.15)';
        resultElement.style.transition = 'transform 0.3s ease';
        
        setTimeout(() => {
            resultElement.textContent = predictedCGPA;
            resultElement.style.transform = 'scale(1)';
        }, 150);
    }
}

// Semester Filter for Table
const semesterSelect = document.getElementById('semesterSelect');
if (semesterSelect) {
    semesterSelect.addEventListener('change', (e) => {
        const semester = e.target.value;
        console.log(`Semester select changed to Semester ${semester}`);
    });
}

// Export functionality
const exportBtn = document.querySelector('.btn-outline-primary');
exportBtn.addEventListener('click', () => {
    // In real application, this would generate PDF/Excel
    alert('Exporting grades report...\n\nIn production, this will download a PDF or Excel file.');
});

// Add animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe elements
document.querySelectorAll('.gpa-card, .card').forEach(element => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(20px)';
    element.style.transition = 'all 0.6s ease';
    observer.observe(element);
});