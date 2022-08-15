<?php namespace Winter\Translate\Tests\Unit;

use Event;
use Backend\Widgets\Form;
use Winter\Storm\Database\Model;

class EventRegistryTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{
    public function testRegisterModelTranslation()
    {
        FormTestModel::extend(function ($model) {
            $model->translatable = array_merge($model->translatable, ['testField', 'tabTestField', 'secondaryTabTestField']);
        });

        Event::listen('backend.form.extendFieldsBefore', function($widget) {
            $widget->tabs['fields']['tabTestField'] = [
                'label'   => 'Tab Test Field',
                'type'    => 'text',
                'tab'     => 'New Tab',
            ];
            $widget->secondaryTabs['fields']['secondaryTabTestField'] = [
                'label'   => 'Secondary Tab Test Field',
                'type'    => 'text',
                'tab'     => 'Another Tab',
            ];
        });

        $form = new Form(new \Backend\Classes\Controller(), [
            'model' => new FormTestModel,
            'arrayName' => 'array',
            'fields' => [
                'testField' => [
                    'type' => 'text',
                    'label' => 'Test 1'
                ],
            ]
        ]);
        $form->bindToController();
        $this->assertInstanceOf(Form::class, $form);

        $this->assertEquals('mltext', $form->fields['testField']['type']);
        $this->assertEquals('mltext', $form->tabs['fields']['tabTestField']['type']);
        $this->assertEquals('mltext', $form->secondaryTabs['fields']['secondaryTabTestField']['type']);
    }
}

class FormTestModel extends Model
{
    public $implement = [
        'Winter.Translate.Behaviors.TranslatableModel',
    ];

    public $translatable = [];
}
