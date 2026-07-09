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
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'],
        datasets: [{
            label: 'SGPA',
            data: [8.2, 8.5, 8.3, 8.7, 8.5],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#667eea',
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
                min: 7,
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
            data: [8, 10, 5, 2],
            backgroundColor: [
                '#43e97b',
                '#4facfe',
                '#fa709a',
                '#fee140'
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