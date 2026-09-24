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
let performanceChart = new Chart(performanceCtx, {
    type: 'line',
    data: {
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'],
        datasets: [{
            label: 'GPA',
            data: [8.2, 8.5, 8.3, 8.7, 8.5],
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

// Chart Filter
const chartFilter = document.getElementById('chartFilter');
chartFilter.addEventListener('change', (e) => {
    if (e.target.value === 'grades') {
        // Change to bar chart for grade distribution
        performanceChart.destroy();
        performanceChart = new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['A+', 'A', 'B+', 'B', 'C'],
                datasets: [{
                    label: 'Number of Subjects',
                    data: [8, 6, 3, 1, 0],
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
                            stepSize: 2
                        }
                    }
                }
            }
        });
    } else {
        // Switch back to line chart
        performanceChart.destroy();
        performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'],
                datasets: [{
                    label: 'GPA',
                    data: [8.2, 8.5, 8.3, 8.7, 8.5],
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
                        min: 7,
                        max: 10,
                        ticks: {
                            stepSize: 0.5
                        }
                    }
                }
            }
        });
    }
});

// GPA Calculator
function calculateGPA() {
    const expectedGrade = parseFloat(document.getElementById('expectedGrade').value);
    const credits = parseFloat(document.getElementById('credits').value);
    
    // Current CGPA calculation (simplified)
    // In real app, this would fetch from database
    const currentCGPA = 8.5;
    const totalCredits = 80; // Completed credits
    
    // Calculate new CGPA
    const totalPoints = currentCGPA * totalCredits;
    const newTotalPoints = totalPoints + (expectedGrade * credits);
    const newTotalCredits = totalCredits + credits;
    const predictedCGPA = (newTotalPoints / newTotalCredits).toFixed(2);
    
    // Display result with animation
    const resultElement = document.getElementById('predictedGPA');
    resultElement.style.transform = 'scale(1.2)';
    resultElement.style.transition = 'transform 0.3s ease';
    
    setTimeout(() => {
        resultElement.textContent = predictedCGPA;
        resultElement.style.transform = 'scale(1)';
    }, 150);
}

// Semester Filter for Table
const semesterSelect = document.getElementById('semesterSelect');
semesterSelect.addEventListener('change', (e) => {
    const semester = e.target.value;
    console.log(`Loading grades for Semester ${semester}`);
    
    // In real application, this would:
    // 1. Make an API call to PHP backend
    // 2. Fetch grades for selected semester
    // 3. Update the table dynamically
    
    // For now, show alert
    alert(`Loading Semester ${semester} grades...`);
});

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