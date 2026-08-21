<?php
    /** @var \Winter\Translate\Models\Setting $formModel */
    $fromForm = trim((string) ($formModel->google_api_key ?? '')) !== '';
    $fromConfig = trim((string) config('winter.translate::providers.google.key', '')) !== '';
    $configured = $fromForm || $fromConfig;
    $viaEnv = $configured && !$fromForm;
?>
<div class="callout callout-<?= $configured ? 'success' : 'info' ?> no-subheader">
    <div class="header">
        <i class="icon-<?= $configured ? 'circle-check' : 'language' ?>"></i>
        <h3><?= e(trans('winter.translate::lang.settings.google_title')) ?></h3>
    </div>
    <div class="content">
        <?php if ($configured): ?>
            <p><strong><?= e(trans('winter.translate::lang.settings.' . ($viaEnv ? 'status_configured_env' : 'status_configured'))) ?></strong></p>
        <?php endif ?>
        <p><?= e(trans('winter.translate::lang.settings.google_intro')) ?></p>
        <ol>
            <li>
                <?= trans('winter.translate::lang.settings.google_step_console', [
                    'link' => '<a href="https://console.cloud.google.com/projectcreate" rel="nofollow" target="_blank">console.cloud.google.com</a>',
                ]) ?>
            </li>
            <li>
                <?= trans('winter.translate::lang.settings.google_step_enable', [
                    'link' => '<a href="https://console.cloud.google.com/apis/library/translate.googleapis.com" rel="nofollow" target="_blank">'
                        . e(trans('winter.translate::lang.settings.google_api_name')) . '</a>',
                ]) ?>
            </li>
            <li>
                <?= trans('winter.translate::lang.settings.google_step_credentials', [
                    'link' => '<a href="https://console.cloud.google.com/apis/credentials" rel="nofollow" target="_blank">'
                        . e(trans('winter.translate::lang.settings.google_credentials_page')) . '</a>',
                ]) ?>
            </li>
            <li><?= e(trans('winter.translate::lang.settings.google_step_restrict')) ?></li>
            <li><?= e(trans('winter.translate::lang.settings.google_step_paste')) ?></li>
        </ol>
        <p class="text-muted">
            <i class="icon-circle-info"></i>
            <?= trans('winter.translate::lang.settings.google_billing', [
                'link' => '<a href="https://cloud.google.com/translate/pricing" rel="nofollow" target="_blank">'
                    . e(trans('winter.translate::lang.settings.pricing')) . '</a>',
            ]) ?>
        </p>
    </div>
</div>
