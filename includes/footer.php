<?php
declare(strict_types=1);
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}
?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 44 44" fill="none">
                        <rect x="7" y="9" width="30" height="21" rx="4" stroke="currentColor" stroke-width="2.4"/>
                        <path d="M4 35h36" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" opacity=".65"/>
                        <path d="M22 30v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <circle cx="22" cy="19.5" r="4.5" fill="currentColor"/>
                    </svg>
                </span>
                <span class="brand-copy"><strong>Tunisia PC</strong><span>Advisor</span></span>
            </a>
            <p>Independent, rule-based computer recommendations for the Tunisian market. No sponsored ranking and no AI-generated product claims.</p>
            <div class="footer-badge"><i data-lucide="shield-check" aria-hidden="true"></i> Catalogue built from retailer product pages</div>
        </div>
        <div class="footer-col">
            <h2>Explore</h2>
            <a href="<?= e(url('index.php')) ?>">Home</a>
            <a href="<?= e(url('questionnaire.php')) ?>">Find a PC</a>
            <a href="<?= e(url('how-it-works.php')) ?>">How it works</a>
            <a href="<?= e(url('data-sources.php')) ?>">Data sources</a>
        </div>
        <div class="footer-col">
            <h2>Project</h2>
            <a href="<?= e(url('about.php')) ?>">About us</a>
            <a href="<?= e(url('about.php#contact')) ?>">Contact</a>
            <a href="<?= e(url('privacy.php')) ?>">Privacy policy</a>
            <a href="<?= e(url('admin/login.php')) ?>">Administration</a>
        </div>
        <div class="footer-col footer-note">
            <h2>Price note</h2>
            <p>Prices and availability are point-in-time snapshots from each retailer. They are not live feeds. Always confirm the final price and stock on the retailer’s product page before buying.</p>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> Tunisia PC Advisor. Academic project, Tunisia.</p>
        <p>Recommendations are deterministic and database-driven. <span class="dot-separator" aria-hidden="true">·</span> No ChatGPT, Gemini or external recommendation APIs.</p>
    </div>
</footer>
<script src="<?= e(asset('js/lucide.min.js')) ?>"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<?php if (!empty($extraJs)): foreach ((array) $extraJs as $js): ?>
    <script src="<?= e(asset('js/' . $js)) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
