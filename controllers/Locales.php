<?php

namespace Winter\Translate\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use Winter\Translate\Models\Locale as LocaleModel;

/**
 * Locales Backend Controller
 */
class Locales extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $requiredPermissions = ['winter.translate.manage_locales'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Winter.System', 'system', 'settings');
        SettingsManager::setContext('Winter.Translate', 'locales');

        $this->addJs('/plugins/winter/translate/assets/js/locales.js');
    }

    /**
     * {@inheritDoc}
     */
    public function listInjectRowClass($record, $definition = null)
    {
        if (!$record->is_enabled) {
            return 'safe disabled';
        }
    }

    public function onCreateForm()
    {
        $this->asExtension('FormController')->create();

        return $this->makePartial('create_form');
    }

    public function onCreate()
    {
        LocaleModel::clearCache();
        $this->asExtension('FormController')->create_onSave();

        return $this->listRefresh();
    }

    public function onUpdateForm()
    {
        $this->asExtension('FormController')->update(post('record_id'));
        $this->vars['recordId'] = post('record_id');

        return $this->makePartial('update_form');
    }

    public function onUpdate()
    {
        LocaleModel::clearCache();
        $this->asExtension('FormController')->update_onSave(post('record_id'));

        return $this->listRefresh();
    }

    public function onDelete()
    {
        LocaleModel::clearCache();
        $this->asExtension('FormController')->update_onDelete(post('record_id'));

        return $this->listRefresh();
    }

    public function onReorder()
    {
        LocaleModel::clearCache();
        $this->asExtension('ReorderController')->onReorder();
    }
}
