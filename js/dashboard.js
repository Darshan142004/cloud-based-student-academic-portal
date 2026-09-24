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

// Academic Progress Chart
const progressCtx = document.getElementById('progressChart').getContext('2d');
const progressChart = new Chart(progressCtx, {
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
            pointHoverRadius: 7
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

// Grade Distribution Chart
const gradeCtx = document.getElementById('gradeChart').getContext('2d');
const gradeChart = new Chart(gradeCtx, {
    type: 'doughnut',
    data: {
        labels: ['A+', 'A', 'B+', 'B'],
        datasets: [{
            data: [0, 0, 0, 0],
            backgroundColor: [
                '#0F52BA',
                '#4AB7E0',
                '#3B82F6',
                '#F59E0B'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                displayColors: true
            }
        }
    }
});

// Update Progress Chart with real dynamic SGPA trend data
function updateProgressChart(gradesData) {
    if (!progressChart || !gradesData || !gradesData.grades) return;
    
    const labels = [];
    const sgpaValues = [];
    
    const semesters = Object.keys(gradesData.grades).sort((a, b) => Number(a) - Number(b));
    
    semesters.forEach(sem => {
        const semData = gradesData.grades[sem];
        if (semData && semData.sgpa > 0) {
            labels.push(`Sem ${sem}`);
            sgpaValues.push(parseFloat(semData.sgpa));
        }
    });

    if (labels.length > 0) {
        progressChart.data.labels = labels;
        progressChart.data.datasets[0].data = sgpaValues;
        
        const minSgpa = Math.max(0, Math.floor(Math.min(...sgpaValues) - 0.5));
        const maxSgpa = Math.min(10, Math.ceil(Math.max(...sgpaValues) + 0.5));
        if (progressChart.options.scales && progressChart.options.scales.y) {
            progressChart.options.scales.y.min = minSgpa;
            progressChart.options.scales.y.max = maxSgpa;
        }
        
        progressChart.update();
    }
}

// Update Grade Distribution Chart dynamically from real grades data
function updateGradeDistributionChart(gradesData) {
    if (!gradeChart || !gradesData || !gradesData.grades) return;
    
    let aPlus = 0, a = 0, bPlus = 0, b = 0;
    
    Object.values(gradesData.grades).forEach(sem => {
        if (sem.courses) {
            sem.courses.forEach(course => {
                if (course.grade_points !== null && course.grade_points !== undefined) {
                    const gp = parseFloat(course.grade_points);
                    if (!isNaN(gp)) {
                        if (gp >= 9) aPlus++;
                        else if (gp >= 8) a++;
                        else if (gp >= 7) bPlus++;
                        else b++;
                    }
                }
            });
        }
    });
    
    gradeChart.data.datasets[0].data = [aPlus, a, bPlus, b];
    gradeChart.update();
}

// Auto-fetch real grades data to update progress and grade distribution charts
fetch('./backend/api/get_grades.php')
    .then(response => response.json())
    .then(res => {
        if (res.success && res.data) {
            updateProgressChart(res.data);
            updateGradeDistributionChart(res.data);
        }
    })
    .catch(err => console.error('Error fetching grades for dashboard charts:', err));

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

// Observe all cards
document.querySelectorAll('.stat-card, .card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.6s ease';
    observer.observe(card);
});

// Simulate real-time updates (for demo purposes)
function updateStats() {
    // This will be replaced with actual API calls to PHP backend later
    console.log('Stats updated');
}

// Update stats every 30 seconds
setInterval(updateStats, 30000);

// Smooth scroll for sidebar links
document.querySelectorAll('.nav-menu a').forEach(link => {
    link.addEventListener('click', (e) => {
        // Remove active class from all
        document.querySelectorAll('.nav-menu li').forEach(li => {
            li.classList.remove('active');
        });
        
        // Add active class to clicked
        e.target.closest('li').classList.add('active');
    });
});