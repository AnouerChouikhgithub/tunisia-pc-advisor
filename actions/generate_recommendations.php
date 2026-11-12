<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

Security::requirePost();
Security::requireCsrf();

$activityRepo = new ActivityRepository();
$productRepo = new ProductRepository();
$validator = new QuestionnaireValidator($activityRepo, $productRepo);
$result = $validator->validate($_POST);

if ($result['request'] === null) {
    $_SESSION['_errors'] = $result['errors'];
    $_SESSION['_old'] = QuestionnaireValidator::oldInput($_POST);
    flash('error', 'Some answers need attention before we can rank the catalogue.');
    redirect('questionnaire.php');
}

try {
    $engine = new RecommendationEngine(
        $activityRepo,
        $productRepo,
        new RecommendationExplanationGenerator()
    );
    $service = new RecommendationService($engine);
    $generated = $service->generate($result['request']);
    redirect('results.php?token=' . urlencode($generated['token']));
} catch (Throwable $exception) {
    error_log('Recommendation generation failed: ' . $exception->getMessage());
    http_response_code(500);
    $pageTitle = 'Recommendation unavailable';
    require APP_ROOT . '/includes/header.php';
    ?>
    <main id="main-content" class="section">
        <div class="container">
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="server-crash"></i></div>
                <h2>We could not generate the recommendation.</h2>
                <p>The catalogue database may be unavailable. Please try again in a moment. If the problem persists, check the database import and the server error log.</p>
                <a class="btn btn-primary" href="<?= e(url('questionnaire.php')) ?>">Return to the questionnaire</a>
            </div>
        </div>
    </main>
    <?php
    require APP_ROOT . '/includes/footer.php';
    exit;
}
