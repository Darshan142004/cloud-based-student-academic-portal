// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    // Toggle eye icon
    const icon = this.querySelector('i');
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
});

// Form submission handler
const loginForm = document.getElementById('loginForm');

loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const studentId = document.getElementById('studentId').value;
    const password = document.getElementById('password').value;
    const rememberMe = document.getElementById('rememberMe').checked;
    
    // Basic validation
    if (studentId.trim() === '' || password.trim() === '') {
        showAlert('Please fill in all fields', 'danger');
        return;
    }
    
    // Show loading state
    const loginBtn = document.querySelector('.login-btn');
    const originalBtnText = loginBtn.innerHTML;
    loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
    loginBtn.disabled = true;
    
    // Simulate login process (replace with actual PHP backend later)
    setTimeout(() => {
        // For now, just show success message
        // Later, this will be replaced with actual PHP authentication
        
        // Demo credentials for testing
        if (studentId === '' && password === '') {
            showAlert('Login successful! Redirecting...', 'success');
            
            // Store in localStorage if remember me is checked
            if (rememberMe) {
                localStorage.setItem('studentId', studentId);
            }
            
            // Redirect to dashboard after 1.5 seconds
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 2000);
        }
         else {
           showAlert('Invalid credentials', 'danger');
            loginBtn.innerHTML = originalBtnText;
            loginBtn.disabled = false;
        }
    }, 2000);
});

// Function to show alert messages
function showAlert(message, type) {
    // Remove existing alerts
    const existingAlert = document.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.role = 'alert';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert alert before form
    const form = document.getElementById('loginForm');
    form.parentNode.insertBefore(alert, form);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Check if user was remembered
window.addEventListener('DOMContentLoaded', () => {
    const rememberedId = localStorage.getItem('studentId');
    if (rememberedId) {
        document.getElementById('studentId').value = rememberedId;
        document.getElementById('rememberMe').checked = true;
    }
});

// Add smooth animations on input focus
const inputs = document.querySelectorAll('.form-control');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
        this.parentElement.style.transition = 'transform 0.2s ease';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
    });
});