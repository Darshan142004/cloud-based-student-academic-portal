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

// Search functionality
const searchInput = document.getElementById('searchCourse');
const courseCards = document.querySelectorAll('.course-card');

searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    
    courseCards.forEach(card => {
        const courseName = card.querySelector('.course-title-section h5').textContent.toLowerCase();
        const courseCode = card.querySelector('.course-code').textContent.toLowerCase();
        const instructor = card.querySelector('.info-item span').textContent.toLowerCase();
        
        if (courseName.includes(searchTerm) || 
            courseCode.includes(searchTerm) || 
            instructor.includes(searchTerm)) {
            card.closest('.col-lg-6').style.display = 'block';
        } else {
            card.closest('.col-lg-6').style.display = 'none';
        }
    });
});

// Semester filter
const semesterFilter = document.getElementById('semesterFilter');

semesterFilter.addEventListener('change', (e) => {
    const selectedSemester = e.target.value;
    
    // In real application, this would filter courses based on semester
    // For now, we'll just show an alert
    if (selectedSemester !== 'all') {
        console.log(`Filtering courses for Semester ${selectedSemester}`);
        // Later, this will make an API call to PHP backend
    } else {
        console.log('Showing all courses');
    }
});

// View Details button functionality
const viewDetailsButtons = document.querySelectorAll('.btn-outline-primary');

viewDetailsButtons.forEach(button => {
    button.addEventListener('click', (e) => {
        const courseCard = e.target.closest('.course-card');
        const courseName = courseCard.querySelector('.course-title-section h5').textContent;
        
        // Show alert for now, later will open a modal or redirect
        alert(`Viewing details for: ${courseName}`);
        
        // In actual implementation, this will:
        // 1. Fetch course details from database
        // 2. Display in a modal or separate page
    });
});

// Course Materials button functionality
const materialButtons = document.querySelectorAll('.btn-primary');

materialButtons.forEach(button => {
    button.addEventListener('click', (e) => {
        const courseCard = e.target.closest('.course-card');
        const courseName = courseCard.querySelector('.course-title-section h5').textContent;
        
        // Show alert for now
        alert(`Opening materials for: ${courseName}`);
        
        // In actual implementation, this will:
        // 1. Fetch course materials from database
        // 2. Display downloadable files, lecture notes, etc.
    });
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

// Observe all course cards
document.querySelectorAll('.course-card, .course-stat-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.6s ease';
    observer.observe(card);
});

// Progress bar animation
window.addEventListener('load', () => {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        
        setTimeout(() => {
            bar.style.width = width;
        }, 300);
    });
});