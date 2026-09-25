</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand footer-brand" href="<?= e($rootPrefix ?? '') ?>index.php"><span class="brand-mark" aria-hidden="true">M</span><span class="brand-word">MOLEQRA</span></a>
            <p class="muted">Independent research materials with batch-level documentation.</p>
        </div>
        <div>
            <h2 class="footer-heading">Company</h2>
            <a href="<?= e($rootPrefix ?? '') ?>about.php">About</a>
            <a href="<?= e($rootPrefix ?? '') ?>suppliers.php">Supplier partnerships</a>
            <a href="<?= e($rootPrefix ?? '') ?>contact.php">Contact</a>
        </div>
        <div>
            <h2 class="footer-heading">Information</h2>
            <a href="<?= e($rootPrefix ?? '') ?>quality.php">Quality framework</a>
            <a href="<?= e($rootPrefix ?? '') ?>coa.php">COA library</a>
            <a href="<?= e($rootPrefix ?? '') ?>terms.php">Terms</a>
            <a href="<?= e($rootPrefix ?? '') ?>privacy.php">Privacy</a>
        </div>
        <div>
            <h2 class="footer-heading">Research use only</h2>
            <p class="muted small">Materials presented by Moleqra are intended exclusively for laboratory research. They are not medicines, foods, supplements, cosmetics, or products for human or veterinary administration.</p>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= e(config('company_name')) ?>.</span>
        <span><?= e(config('launch_status')) ?></span>
    </div>
</footer>
<script src="<?= e($rootPrefix ?? '') ?>assets/js/site.js" defer></script>
</body>
</html>
