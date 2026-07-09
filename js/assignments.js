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

// Tab Filter Functionality
const tabs = document.querySelectorAll('.assignment-tabs .nav-link');
const assignmentItems = document.querySelectorAll('.assignment-item');

tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        
        // Remove active class from all tabs
        tabs.forEach(t => t.classList.remove('active'));
        
        // Add active class to clicked tab
        tab.classList.add('active');
        
        // Get filter value
        const filter = tab.getAttribute('data-filter');
        
        // Filter assignments
        filterAssignments(filter);
    });
});

function filterAssignments(filter) {
    assignmentItems.forEach(item => {
        if (filter === 'all') {
            item.classList.remove('hidden');
            item.style.display = 'block';
        } else {
            const status = item.getAttribute('data-status');
            if (status === filter) {
                item.classList.remove('hidden');
                item.style.display = 'block';
            } else {
                item.classList.add('hidden');
                item.style.display = 'none';
            }
        }
    });
}

// Search Functionality
const searchInput = document.getElementById('searchAssignment');

searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    
    assignmentItems.forEach(item => {
        const card = item.querySelector('.assignment-card');
        const title = card.querySelector('.assignment-title-section h5').textContent.toLowerCase();
        const course = card.querySelector('.course-name').textContent.toLowerCase();
        const description = card.querySelector('.description').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || course.includes(searchTerm) || description.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// View Details Button
const viewDetailsButtons = document.querySelectorAll('.btn-outline-primary');

viewDetailsButtons.forEach(button => {
    if (button.textContent.includes('View Details')) {
        button.addEventListener('click', (e) => {
            const card = e.target.closest('.assignment-card');
            const title = card.querySelector('.assignment-title-section h5').textContent;
            const description = card.querySelector('.description').textContent;
            
            alert(`Assignment: ${title}\n\nDescription:\n${description}\n\nIn production, this will open a detailed modal with full requirements, attachments, and rubrics.`);
        });
    }
});

// Submit Button
const submitButtons = document.querySelectorAll('.btn-primary');

submitButtons.forEach(button => {
    if (button.textContent.includes('Submit')) {
        button.addEventListener('click', (e) => {
            const card = e.target.closest('.assignment-card');
            const title = card.querySelector('.assignment-title-section h5').textContent;
            
            // In real app, this will open file upload modal
            const confirmation = confirm(`Submit assignment: ${title}?\n\nIn production, this will open a file upload interface.`);
            
            if (confirmation) {
                alert('Assignment submitted successfully!\n\nYou will receive a confirmation email.');
            }
        });
    }
});

// Continue Button (for in-progress assignments)
const continueButtons = document.querySelectorAll('.btn-warning');

continueButtons.forEach(button => {
    if (button.textContent.includes('Continue')) {
        button.addEventListener('click', (e) => {
            const card = e.target.closest('.assignment-card');
            const title = card.querySelector('.assignment-title-section h5').textContent;
            
            alert(`Continuing work on: ${title}\n\nIn production, this will load your saved draft.`);
        });
    }
});

// Download Button (for completed assignments)
const downloadButtons = document.querySelectorAll('.btn-outline-primary');

downloadButtons.forEach(button => {
    if (button.textContent.includes('Download')) {
        button.addEventListener('click', (e) => {
            const card = e.target.closest('.assignment-card');
            const title = card.querySelector('.assignment-title-section h5').textContent;
            
            alert(`Downloading: ${title}\n\nIn production, this will download your submitted file.`);
        });
    }
});

// Feedback Button
const feedbackButtons = document.querySelectorAll('.btn-outline-success');

feedbackButtons.forEach(button => {
    if (button.textContent.includes('Feedback')) {
        button.addEventListener('click', (e) => {
            const card = e.target.closest('.assignment-card');
            const title = card.querySelector('.assignment-title-section h5').textContent;
            
            alert(`Viewing feedback for: ${title}\n\nIn production, this will show detailed feedback from instructor with comments and suggestions.`);
        });
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

// Observe all assignment cards
document.querySelectorAll('.assignment-card, .assignment-stat-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.6s ease';
    observer.observe(card);
});

// Update progress bars animation
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