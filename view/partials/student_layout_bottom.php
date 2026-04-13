            </main>
        </div>
    </div>

    <script>
        // Optional: Auto-logout after 1 hour of inactivity
        let inactivityTimer;

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                alert('Your session has expired. Redirecting to login...');
                window.location.href = 'index.php?page=logout';
            }, 3600000); // 1 hour
        }

        window.addEventListener('mousemove', resetInactivityTimer);
        window.addEventListener('keypress', resetInactivityTimer);
        window.addEventListener('click', resetInactivityTimer);

        resetInactivityTimer();
    </script>

    <script src="assets/js/sidebar.js"></script>
    <!-- Include Student Appointments Script -->
    <script src="assets/js/student_appointments.js"></script>
</body>

</html>