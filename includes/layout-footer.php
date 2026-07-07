<?php
declare(strict_types=1);
/**
 * includes/layout-footer.php — içerik/main/layout kapanışı + ortak JS.
 */
?>
    </main>
    <footer class="footer">
        <span class="muted small">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
    </footer>
</div><!-- /.main -->
</div><!-- /.layout -->
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
