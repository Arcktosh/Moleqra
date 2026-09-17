</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand footer-brand" href="index.php"><span class="brand-mark" aria-hidden="true">M</span><span class="brand-word">MOLEQRA</span></a>
            <p class="muted">Independent research materials with batch-level documentation.</p>
        </div>
        <div>
            <h2 class="footer-heading">Company</h2>
            <a href="about.php">About</a>
            <a href="suppliers.php">Supplier partnerships</a>
            <a href="contact.php">Contact</a>
        </div>
        <div>
            <h2 class="footer-heading">Information</h2>
            <a href="quality.php">Quality framework</a>
            <a href="coa.php">COA library</a>
            <a href="terms.php">Terms</a>
            <a href="privacy.php">Privacy</a>
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
<script src="assets/js/site.js" defer></script>
</body>
</html>
