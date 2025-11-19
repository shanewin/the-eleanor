// Manages the email list modal: posts to api/email-list.php, shows a thank-you state,
// and resets the form each time the modal is closed.
document.addEventListener('DOMContentLoaded', function() {
    const emailForm = document.getElementById('emailListForm');
    const emailThankYou = document.getElementById('emailThankYou');
    
    emailForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitButton = emailForm.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        
        // Show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Joining...';
        
        // Get form data
        const formData = new FormData(emailForm);
        
        // Simulate API call (replace with actual endpoint)
        fetch('api/email-list.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide form and show thank you message
                emailForm.style.display = 'none';
                document.getElementById('emailDescription').style.display = 'none';
                emailThankYou.style.display = 'block';
            } else {
                throw new Error(data.message || 'Something went wrong');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('There was an error submitting your information. Please try again.');
        })
        .finally(() => {
            // Reset button
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        });
    });
    
    // Reset form when modal is closed
    document.getElementById('emailListModal').addEventListener('hidden.bs.modal', function() {
        emailForm.style.display = 'block';
        document.getElementById('emailDescription').style.display = 'block';
        emailThankYou.style.display = 'none';
        emailForm.reset();
    });
}); 
