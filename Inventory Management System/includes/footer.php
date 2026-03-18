            </main>
        </div>
        
        <!-- Bootstrap Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Custom Scripts -->
        <script>
            // Add active class to current nav item
            document.addEventListener('DOMContentLoaded', function() {
                const currentPage = window.location.pathname.split('/').pop();
                const navLinks = document.querySelectorAll('.nav-link');
                
                navLinks.forEach(link => {
                    const href = link.getAttribute('href');
                    if (href === currentPage) {
                        link.classList.add('active');
                    }
                });

                // Initialize confirmation modal functionality
                const confirmationModal = document.getElementById('confirmationModal');
                if (confirmationModal) {
                    const modal = new bootstrap.Modal(confirmationModal);
                    const modalTitle = confirmationModal.querySelector('.modal-title');
                    const modalBody = confirmationModal.querySelector('.modal-body');
                    const confirmButton = confirmationModal.querySelector('.btn-danger');
                    
                    // Handle logout confirmation
                    document.querySelectorAll('a[href="logout.php"]').forEach(link => {
                        if (!link.closest('.modal-footer')) {
                            link.addEventListener('click', function(e) {
                                e.preventDefault();
                                modalTitle.textContent = 'Confirm Logout';
                                modalBody.textContent = 'Are you sure you want to logout?';
                                confirmButton.textContent = 'Yes, Logout';
                                confirmButton.href = 'logout.php';
                                modal.show();
                            });
                        }
                    });

                    // Handle delete confirmations
                    document.querySelectorAll('[data-confirm]').forEach(element => {
                        element.addEventListener('click', function(e) {
                            e.preventDefault();
                            const message = this.getAttribute('data-confirm');
                            const title = this.getAttribute('data-confirm-title') || 'Confirm Action';
                            const buttonText = this.getAttribute('data-confirm-button') || 'Yes, Continue';
                            const href = this.getAttribute('href') || this.form?.action;

                            modalTitle.textContent = title;
                            modalBody.textContent = message;
                            confirmButton.textContent = buttonText;
                            confirmButton.href = href;
                            modal.show();
                        });
                    });
                }
            });
        </script>
    </body>
</html> 