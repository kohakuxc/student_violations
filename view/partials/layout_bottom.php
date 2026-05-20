<?php
// view/partials/layout_bottom.php
?>
</main>
</div>
</div>

<script>
    (function () {
        var btn = document.getElementById('sidebarToggle');
        var backdrop = document.getElementById('sidebarBackdrop');
        var body = document.body;
        var sidebarLinks = document.querySelectorAll('.sidebar a');
        var closeBtn = document.getElementById('sidebarClose');

        function toggleSidebar() {
            body.classList.toggle('sidebar-open');
        }
        function closeSidebar() {
            body.classList.remove('sidebar-open');
        }

        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (btn) btn.addEventListener('click', toggleSidebar);
        if (backdrop) backdrop.addEventListener('click', closeSidebar);

        sidebarLinks.forEach(function (link) {
            link.addEventListener('click', closeSidebar);
        });

    })();
</script>

<script>
    (function () {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var message = form.getAttribute('data-confirm');
                if (message && !window.confirm(message)) {
                    e.preventDefault();
                    return;
                }
                if (form.dataset.submitted === 'true') {
                    e.preventDefault();
                    return;
                }
                form.dataset.submitted = 'true';
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
            });
        });
    })();
</script>

</body>

</html>
