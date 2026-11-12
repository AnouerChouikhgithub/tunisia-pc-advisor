<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pageTitle = 'Privacy policy';
$pageDescription = 'What Tunisia PC Advisor stores, why it stores it, and how anonymous recommendation data is handled.';
$currentPage = 'privacy';

require APP_ROOT . '/includes/header.php';
?>
<main id="main-content">
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow">Privacy</span>
            <h1>A small amount of data, stored for a clear reason.</h1>
            <p>No account is required to receive recommendations. This policy describes the anonymous session data that makes the result link work, and the limits on its use.</p>
        </div>
    </section>

    <section class="section">
        <div class="container content-grid">
            <div class="prose">
                <h2>What we store</h2>
                <p>When you submit the questionnaire, the application stores an anonymous recommendation session containing:</p>
                <ul>
                    <li>the selected activity and intended workloads;</li>
                    <li>the device category, budget range or maximum, and optional minimum specifications;</li>
                    <li>optional preferred brands and importance settings;</li>
                    <li>the scored product IDs, compatibility scores, generated explanations and score breakdowns;</li>
                    <li>a random result token and the time of submission.</li>
                </ul>
                <p>We do <strong>not</strong> ask for a name, email address, phone number or account. This build does not store your IP address or browser user-agent in the recommendation tables. It does not use advertising pixels or third-party analytics.</p>

                <h2>Why we store it</h2>
                <p>The session record lets the results page survive a refresh and allows product and comparison links to retrieve the same ranking. It also makes the recommendation auditable: an administrator or examiner can inspect the exact answers, scores and explanation that produced the output.</p>

                <h2>Cookies and sessions</h2>
                <p>The application uses a PHP session cookie for CSRF protection, flash messages, form recovery and administrator authentication. It is HttpOnly and SameSite=Lax. It is not used for advertising or cross-site tracking. Blocking it may prevent the questionnaire and admin login from working correctly.</p>

                <h2>Retention</h2>
                <p>Anonymous recommendation sessions remain in the local database until the project owner removes them. They can be pruned with the maintenance command <code>php tools/prune_sessions.php</code> or removed individually through the database. Result links stop working when their session is removed. Price history and product records are retained separately as catalogue data.</p>

                <h2>Administrator data</h2>
                <p>The protected administration area stores a username, display name, a one-way password hash, an active flag and last-login timestamp. Passwords are hashed with PHP’s <code>password_hash()</code> and never stored in plain text. Administrator sessions expire after a period of inactivity, and login attempts are rate-limited in the session.</p>

                <h2>External links</h2>
                <p>“Buy from retailer” and source links open third-party retailer websites in a new tab. Once you leave this site, the retailer’s own privacy policy and cookies apply. Tunisia PC Advisor does not receive a commission and does not pass your questionnaire answers to the retailer.</p>

                <h2>Your choices</h2>
                <p>You can use the questionnaire without creating an account. If you do not want a session stored, do not submit the questionnaire. If you have access to the local installation, an administrator can delete the session and its results. Because the stored session is anonymous, we may be unable to identify a specific record without its unique result token.</p>

                <h2>Corrections and contact</h2>
                <p>For questions about this policy or to request removal of a record, use the contact details supplied with the project submission or contact the project owner through the associated course/institution channel. See <a href="<?= e(url('about.php#contact')) ?>">About → Contact</a>.</p>

                <p class="small muted" style="margin-top:30px;">Policy last updated: <?= e(format_date(app('research_date'))) ?>. This policy describes the local academic build in this repository; a production deployment would require a named data controller and a formal retention schedule.</p>
            </div>

            <aside class="side-card card">
                <h2>Summary</h2>
                <ul>
                    <li><i data-lucide="user-x"></i> No account or email required</li>
                    <li><i data-lucide="cookie"></i> Only a PHP session cookie</li>
                    <li><i data-lucide="eye-off"></i> No analytics or ad pixels</li>
                    <li><i data-lucide="database"></i> Anonymous sessions stored locally</li>
                    <li><i data-lucide="trash-2"></i> Removable with a maintenance command</li>
                </ul>
                <a class="btn btn-secondary btn-block" href="<?= e(url('questionnaire.php')) ?>">Back to the questionnaire</a>
            </aside>
        </div>
    </section>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
