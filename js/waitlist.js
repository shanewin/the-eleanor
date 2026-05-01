// Handles the waitlist modal: fetches a CSRF token, posts to api/form-handler.php,
// and swaps the form with a thank-you message on success.
document.addEventListener('DOMContentLoaded', function() {
    const waitlistForm = document.getElementById('waitlistForm');
    const waitlistThankYou = document.getElementById('waitlistThankYou');
    const csrfTokenInput = document.getElementById('csrf_token');
    
    // Fetch CSRF token on page load
    fetch('api/get-csrf-token.php')
        .then(response => response.json())
        .then(data => {
            if (data.csrf_token) {
                csrfTokenInput.value = data.csrf_token;
            }
        })
        .catch(error => console.error('Error fetching CSRF token:', error));
    
    if (waitlistForm) {
        waitlistForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = waitlistForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;

            // Clear any previous error
            const prevError = document.getElementById('waitlistError');
            if (prevError) prevError.style.display = 'none';
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Joining Wait List...';
            
            // Get form data
            const formData = new FormData(waitlistForm);
            
            // Submit form
            fetch('api/form-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide form and show thank you message
                    waitlistForm.style.display = 'none';
                    waitlistThankYou.style.display = 'block';
                    
                    // Scroll to thank you message
                    waitlistThankYou.scrollIntoView({ behavior: 'smooth' });
                } else {
                    throw new Error(data.error || 'Something went wrong');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                let errorEl = document.getElementById('waitlistError');
                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.id = 'waitlistError';
                    errorEl.className = 'alert alert-danger mt-3 text-center';
                    waitlistForm.prepend(errorEl);
                }
                errorEl.textContent = error.message || 'There was an error submitting your information. Please try again.';
                errorEl.style.display = 'block';
            })
            .finally(() => {
                // Reset button
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }
}); 
