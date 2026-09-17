<?php
$pageTitle = 'Moleqra | Research Materials';
$pageDescription = 'Moleqra is building a documented, research-use-only peptide catalogue for laboratories and qualified research buyers.';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <div class="eyebrow">Research use only · South Africa</div>
            <h1>Research materials with <span>documentation first.</span></h1>
            <p class="lead">Moleqra is building a focused catalogue of peptide research materials supported by batch-specific analytical documentation, controlled supplier onboarding and clear traceability.</p>
            <div class="actions">
                <a class="btn primary" href="suppliers.php">Supplier partnerships</a>
                <a class="btn" href="quality.php">View quality framework</a>
            </div>
        </div>
        <aside class="panel hero-card" aria-label="Launch status">
            <span class="tag">Supplier onboarding</span>
            <div class="metric"><span class="metric-label">Current phase</span><span class="metric-value">Qualification &amp; documentation</span></div>
            <div class="metric"><span class="metric-label">Catalogue model</span><span class="metric-value">Batch-linked research materials</span></div>
            <div class="metric"><span class="metric-label">Quality target</span><span class="metric-value">Identity + purity documentation</span></div>
            <div class="metric"><span class="metric-label">Commercial availability</span><span class="metric-value">Pending supplier approval</span></div>
        </aside>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head"><div><div class="eyebrow">Built for traceability</div><h2>Simple standards, consistently applied.</h2></div><p>Before a material is made available, Moleqra intends to review supplier documentation, batch identity and purity evidence, and catalogue traceability.</p></div>
        <div class="grid-3">
            <article class="card"><div class="icon-chip">01</div><h3>Batch-linked COAs</h3><p>Each listed batch is intended to carry its own certificate or analytical record rather than relying on generic product-level claims.</p></article>
            <article class="card"><div class="icon-chip">02</div><h3>Supplier qualification</h3><p>Suppliers are reviewed for documentation quality, fulfilment reliability, traceability and consistency before onboarding.</p></article>
            <article class="card"><div class="icon-chip">03</div><h3>Clear research scope</h3><p>Catalogue content is written for laboratory research contexts without therapeutic, diagnostic or human-use claims.</p></article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container grid-2">
        <div>
            <div class="eyebrow">Initial catalogue</div>
            <h2>A narrow launch range, not an endless list.</h2>
            <p class="lead">The first catalogue is being kept intentionally small while supplier documentation and batch controls are established.</p>
            <div class="actions"><a class="btn" href="catalog.php">Preview catalogue</a></div>
        </div>
        <div class="card">
            <span class="kicker">Planned research materials</span>
            <ul class="list-clean">
                <li>BPC-157 research peptide</li>
                <li>TB-500 research peptide</li>
                <li>GHK-Cu research peptide</li>
                <li>Additional materials after supplier qualification</li>
            </ul>
        </div>
    </div>
</section>

<section class="section">
    <div class="container callout">
        <div class="grid-2">
            <div><div class="eyebrow">For manufacturers &amp; distributors</div><h2>Supply to Moleqra.</h2><p class="muted">We are currently evaluating South African stockists, manufacturers and private-label partners with batch-specific analytical documentation.</p></div>
            <div class="actions" style="align-items:center;justify-content:flex-end"><a class="btn primary" href="suppliers.php">View supplier requirements</a></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
