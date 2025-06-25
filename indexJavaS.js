document.addEventListener('DOMContentLoaded', function() {
    // Function to toggle password visibility
    const togglePasswordVisibility = (inputId, toggleBtn) => {
        const input = document.getElementById(inputId);
        if (!input || !toggleBtn) return;

        toggleBtn.addEventListener('click', function() {
            // Toggle password visibility
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            // Toggle the visibility state
            this.classList.toggle('password-visible');
            
            // Update aria-label for accessibility
            const label = isPassword ? 'Hide password' : 'Show password';
            this.setAttribute('aria-label', label);
            
            // Focus back on the input for better UX
            input.focus();
        });
    };

    // Handle main password field on login page
    togglePasswordVisibility('password', document.querySelector('.toggle-password'));
    
    // Handle confirm password field on signup page (if exists)
    togglePasswordVisibility('confirm-password', document.querySelector('#confirm-password + .toggle-password'));

    // Add animation to password toggle button
    const passwordToggles = document.querySelectorAll('.toggle-password');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('mousedown', (e) => {
            e.preventDefault(); // Prevent focus loss
            toggle.style.transform = 'scale(0.9)';
        });
        
        toggle.addEventListener('mouseup', () => {
            toggle.style.transform = '';
        });
        
        toggle.addEventListener('mouseleave', () => {
            toggle.style.transform = '';
        });
        
        // Keyboard accessibility
        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle.click();
            }
        });
    });

    // Add basic form validation for login form
    const loginForm = document.querySelector('.signin-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = this.querySelector('input[type="email"]');
            const password = this.querySelector('input[type="password"]');
            
            if (!email.value || !password.value) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Basic email validation
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                email.focus();
                return false;
            }
        });
    }
});