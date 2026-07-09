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

// Monthly Doughnut Chart
const monthCtx = document.getElementById('monthChart').getContext('2d');
const monthChart = new Chart(monthCtx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent'],
        datasets: [{
            data: [38, 2],
            backgroundColor: ['#43e97b', '#ff6b6b'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '75%',
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12
            }
        }
    }
});

// Generate Calendar
function generateCalendar(month, year) {
    const calendarGrid = document.getElementById('calendarGrid');
    calendarGrid.innerHTML = '';
    
    // Days of week headers
    const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    daysOfWeek.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
    });
    
    // Get first day of month and total days
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Sample attendance data (in real app, this comes from database)
    const attendanceData = {
        present: [1, 2, 3, 5, 6, 8, 9, 10, 12, 13, 15, 16, 17, 19, 20, 22, 23, 24, 26, 27],
        absent: [4, 11, 18],
        holidays: [7, 14, 21, 28]
    };
    
    // Add empty cells for days before month starts
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Add days of month
    const today = new Date();
    const currentDay = today.getDate();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        
        // Determine status
        let status = 'future';
        let statusText = '';
        
        if (year < currentYear || (year === currentYear && month < currentMonth) || 
            (year === currentYear && month === currentMonth && day < currentDay)) {
            
            if (attendanceData.holidays.includes(day)) {
                status = 'holiday';
                statusText = 'Holiday';
            } else if (attendanceData.present.includes(day)) {
                status = 'present';
                statusText = 'P';
            } else if (attendanceData.absent.includes(day)) {
                status = 'absent';
                statusText = 'A';
            }
        } else if (year === currentYear && month === currentMonth && day === currentDay) {
            status = 'present';
            statusText = 'Today';
        }
        
        dayElement.classList.add(status);
        dayElement.innerHTML = `
            <span class="day-number">${day}</span>
            ${statusText ? `<span class="day-status">${statusText}</span>` : ''}
        `;
        
        // Add click event
        dayElement.addEventListener('click', () => {
            if (status !== 'future') {
                showDayDetails(day, month, year, status);
            }
        });
        
        calendarGrid.appendChild(dayElement);
    }
}

// Show day details (to be implemented with modal later)
function showDayDetails(day, month, year, status) {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    alert(`Date: ${monthNames[month]} ${day}, ${year}\nStatus: ${status.toUpperCase()}\n\nIn production, this will show detailed attendance for all subjects on this day.`);
}

// Calendar navigation
let currentMonth = 11; // December (0-indexed)
let currentYear = 2024;

document.getElementById('prevMonth').addEventListener('click', () => {
    currentMonth--;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    updateCalendar();
});

document.getElementById('nextMonth').addEventListener('click', () => {
    currentMonth++;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    updateCalendar();
});

function updateCalendar() {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    document.getElementById('currentMonth').textContent = `${monthNames[currentMonth]} ${currentYear}`;
    generateCalendar(currentMonth, currentYear);
}

// Initialize calendar
generateCalendar(currentMonth, currentYear);

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
document.querySelectorAll('.attendance-card, .card').forEach(element => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(20px)';
    element.style.transition = 'all 0.6s ease';
    observer.observe(element);
});

// Quick Actions
document.querySelectorAll('.card-body .btn').forEach(button => {
    button.addEventListener('click', (e) => {
        const buttonText = e.target.textContent.trim();
        
        if (buttonText.includes('Download')) {
            alert('Downloading attendance report...\n\nIn production, this will generate and download a PDF report.');
        } else if (buttonText.includes('Reminders')) {
            alert('Setting up attendance reminders...\n\nIn production, this will allow you to set custom reminders.');
        } else if (buttonText.includes('History')) {
            alert('Loading attendance history...\n\nIn production, this will show previous semesters attendance.');
        }
    });
});