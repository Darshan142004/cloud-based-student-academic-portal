// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }
    });
}

// Restore remembered ID on page load
window.addEventListener('DOMContentLoaded', () => {
    const rememberedId = localStorage.getItem('studentId');
    const idInput = document.getElementById('studentId');
    const rememberBox = document.getElementById('rememberMe');
    if (rememberedId && idInput) {
        idInput.value = rememberedId;
        if (rememberBox) rememberBox.checked = true;
    }
});
