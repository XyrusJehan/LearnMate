document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality
    const passwordToggles = document.querySelectorAll('.toggle-password');
    
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            // Find the associated input (previous sibling)
            const input = this.parentElement.querySelector('input');
            if (!input) return;
            
            // Toggle password visibility
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            // Toggle the visibility state class
            this.classList.toggle('password-visible');
            
            // Update aria-label
            const label = isPassword ? 'Hide password' : 'Show password';
            this.setAttribute('aria-label', label);
        });
    });

    // Form submission handling
    const authForm = document.querySelector('.auth-form');
    if (authForm) {
        authForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            // Check if passwords match
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            // If everything is valid, you can submit the form
            console.log('Form submitted successfully');
            // Here you would typically send data to your server
        });
        
        
    }
});

