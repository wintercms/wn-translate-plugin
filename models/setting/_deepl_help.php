<?php
    /** @var \Winter\Translate\Models\Setting $formModel */
    $fromForm = trim((string) ($formModel->deepl_api_key ?? '')) !== '';
    $fromConfig = trim((string) config('winter.translate::providers.deepl.key', '')) !== '';
    $configured = $fromForm || $fromConfig;
    $viaEnv = $configured && !$fromForm;
?>
<div class="callout callout-<?= $configured ? 'success' : 'info' ?> no-subheader">
    <div class="header">
        <i class="icon-<?= $configured ? 'circle-check' : 'language' ?>"></i>
        <h3><?= e(trans('winter.translate::lang.settings.deepl_title')) ?></h3>
    </div>
    <div class="content">
        <?php if ($configured): ?>
            <p><strong><?= e(trans('winter.translate::lang.settings.' . ($viaEnv ? 'status_configured_env' : 'status_configured'))) ?></strong></p>
        <?php endif ?>
        <p><?= e(trans('winter.translate::lang.settings.deepl_intro')) ?></p>
        <ol>
            <li>
                <?= trans('winter.translate::lang.settings.deepl_step_signup', [
                    'link' => '<a href="https://www.deepl.com/pro-api" rel="nofollow" target="_blank">deepl.com/pro-api</a>',
                ]) ?>
            </li>
            <li>
                <?= trans('winter.translate::lang.settings.deepl_step_keys', [
                    'link' => '<a href="https://www.deepl.com/your-account/keys" rel="nofollow" target="_blank">'
                        . e(trans('winter.translate::lang.settings.deepl_keys_page')) . '</a>',
                ]) ?>
            </li>
            <li><?= e(trans('winter.translate::lang.settings.deepl_step_plan')) ?></li>
            <li><?= e(trans('winter.translate::lang.settings.deepl_step_paste')) ?></li>
        </ol>
        <p class="text-muted">
            <i class="icon-circle-info"></i>
            <?= e(trans('winter.translate::lang.settings.deepl_plan_hint')) ?>
        </p>
    </div>
</div>
