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

// Change Profile Photo
const changePhotoBtn = document.getElementById('changePhotoBtn');
changePhotoBtn.addEventListener('click', () => {
    alert('Change Profile Photo\n\nIn production, this will open a file picker to upload a new profile picture.');
});

// Edit Profile Button
const editProfileBtn = document.getElementById('editProfileBtn');
const formButtons = document.getElementById('formButtons');
const formInputs = document.querySelectorAll('#personalInfoForm input, #personalInfoForm select, #personalInfoForm textarea');

editProfileBtn.addEventListener('click', () => {
    // Enable all form fields
    formInputs.forEach(input => {
        input.disabled = false;
    });
    
    // Show form buttons
    formButtons.style.display = 'flex';
    
    // Change button text
    editProfileBtn.innerHTML = '<i class="fas fa-times me-2"></i>Cancel Edit';
    editProfileBtn.classList.remove('btn-primary');
    editProfileBtn.classList.add('btn-secondary');
});

// Cancel Edit Button
const cancelEditBtn = document.getElementById('cancelEditBtn');
cancelEditBtn.addEventListener('click', () => {
    // Disable all form fields
    formInputs.forEach(input => {
        input.disabled = true;
    });
    
    // Hide form buttons
    formButtons.style.display = 'none';
    
    // Reset button
    editProfileBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Profile';
    editProfileBtn.classList.remove('btn-secondary');
    editProfileBtn.classList.add('btn-primary');
});

// Save Profile Changes
const personalInfoForm = document.getElementById('personalInfoForm');
personalInfoForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Get form data
    const formData = {
        fullName: document.getElementById('fullName').value,
        dob: document.getElementById('dob').value,
        gender: document.getElementById('gender').value,
        bloodGroup: document.getElementById('bloodGroup').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        address: document.getElementById('address').value
    };
    
    // In production, this will send data to PHP backend
    console.log('Saving profile data:', formData);
    
    // Show success message
    alert('Profile updated successfully!');
    
    // Disable fields and hide buttons
    formInputs.forEach(input => {
        input.disabled = true;
    });
    formButtons.style.display = 'none';
    editProfileBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Profile';
    editProfileBtn.classList.remove('btn-secondary');
    editProfileBtn.classList.add('btn-primary');
});

// Change Password Modal
const changePasswordBtn = document.getElementById('changePasswordBtn');
const passwordModal = new bootstrap.Modal(document.getElementById('passwordModal'));

changePasswordBtn.addEventListener('click', () => {
    passwordModal.show();
});

// Save Password
const savePasswordBtn = document.getElementById('savePasswordBtn');
const passwordForm = document.getElementById('passwordForm');

savePasswordBtn.addEventListener('click', () => {
    const currentPassword = passwordForm.querySelector('input[type="password"]:nth-of-type(1)').value;
    const newPassword = passwordForm.querySelector('input[type="password"]:nth-of-type(2)').value;
    const confirmPassword = passwordForm.querySelector('input[type="password"]:nth-of-type(3)').value;
    
    // Validation
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill in all password fields');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert('New passwords do not match!');
        return;
    }
    
    if (newPassword.length < 8) {
        alert('Password must be at least 8 characters long');
        return;
    }
    
    // In production, this will send to PHP backend
    console.log('Changing password...');
    
    // Show success and close modal
    alert('Password changed successfully!');
    passwordModal.hide();
    passwordForm.reset();
});

// Notification Toggles
const emailNotif = document.getElementById('emailNotif');
const smsNotif = document.getElementById('smsNotif');

emailNotif.addEventListener('change', (e) => {
    const status = e.target.checked ? 'enabled' : 'disabled';
    console.log(`Email notifications ${status}`);
    
    // In production, save to database
    alert(`Email notifications ${status}`);
});

smsNotif.addEventListener('change', (e) => {
    const status = e.target.checked ? 'enabled' : 'disabled';
    console.log(`SMS notifications ${status}`);
    
    // In production, save to database
    alert(`SMS notifications ${status}`);
});

// Two-Factor Authentication
const twoFactorBtn = document.querySelector('.setting-item:last-child .btn');
twoFactorBtn.addEventListener('click', () => {
    alert('Enable Two-Factor Authentication\n\nIn production, this will guide you through setting up 2FA with QR code or SMS verification.');
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
document.querySelectorAll('.card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.6s ease';
    observer.observe(card);
});