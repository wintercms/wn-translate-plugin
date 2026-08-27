<?php namespace Winter\Translate\Tests\Unit;

use Event;
use Backend\Classes\Controller;
use Backend\Classes\WidgetManager;
use Backend\FormWidgets\FieldSet;
use Backend\FormWidgets\Repeater;
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

    public function testFieldsInANestedFormAreNotTranslated()
    {
        // A repeater's or nested form's fields belong to their own data scope and do
        // not map to the model's translatable attributes.
        $form = $this->makeScopeTestForm(Form::class, ['isNested' => true]);

        $this->assertEquals('text', $form->fields['testField']['type']);
    }

    public function testFieldsInAModelScopedNestedFormAreTranslated()
    {
        $form = $this->makeScopeTestForm(ModelScopedFormStub::class, ['isNested' => true]);

        $this->assertEquals('mltext', $form->fields['testField']['type']);
    }

    public function testFieldSetFieldsAreTranslated()
    {
        if (!property_exists(Form::class, 'sharesModelScope')) {
            $this->markTestSkipped('Requires Form::$sharesModelScope, see wintercms/winter#1529.');
        }

        WidgetManager::instance()->registerFormWidget(FieldSet::class, 'fieldset');

        $form = $this->makeScopeTestForm(Form::class, [
            'fields' => [
                'group' => [
                    'type' => 'fieldset',
                    'label' => 'Grouped Fields',
                    'fields' => [
                        'testField' => ['type' => 'text'],
                    ],
                ],
            ],
        ]);

        $fieldSet = $form->getFormWidget('group');
        $this->assertInstanceOf(FieldSet::class, $fieldSet);

        $inner = \Closure::bind(fn () => $this->formWidget, $fieldSet, FieldSet::class)();

        $this->assertEquals('mltext', $inner->fields['testField']['type']);
    }

    public function testFieldSetFieldsInsideARepeaterAreNotTranslated()
    {
        if (!property_exists(Form::class, 'sharesModelScope')) {
            $this->markTestSkipped('Requires Form::$sharesModelScope, see wintercms/winter#1529.');
        }

        WidgetManager::instance()->registerFormWidget(FieldSet::class, 'fieldset');
        WidgetManager::instance()->registerFormWidget(Repeater::class, 'repeater');

        $model = new ScopeTestModel;
        $model->items = [['testField' => 'first']];

        $form = $this->makeScopeTestForm(Form::class, [
            'model' => $model,
            'fields' => [
                'items' => [
                    'type' => 'repeater',
                    'form' => [
                        'fields' => [
                            'group' => [
                                'type' => 'fieldset',
                                'fields' => [
                                    // Deliberately collides with a translatable attribute.
                                    'testField' => ['type' => 'text'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $repeater = $form->getFormWidget('items');
        $itemForms = \Closure::bind(fn () => $this->formWidgets, $repeater, Repeater::class)();

        $fieldSet = $itemForms[0]->getFormWidget('group');
        $inner = \Closure::bind(fn () => $this->formWidget, $fieldSet, FieldSet::class)();

        // The fieldset inherits the repeater item's scope, so its fields hold repeater
        // data rather than the model's translatable attribute of the same name.
        $this->assertEquals('text', $inner->fields['testField']['type']);
    }

    protected function makeScopeTestForm(string $class, array $config = []): Form
    {
        $form = new $class(new Controller, array_merge([
            'model' => new ScopeTestModel,
            'arrayName' => 'array',
            'fields' => [
                'testField' => [
                    'type' => 'text',
                    'label' => 'Test 1',
                ],
            ],
        ], $config));

        $form->bindToController();

        return $form;
    }
}

/**
 * A nested form resolving against the model's own attributes, as the fieldset form widget
 * does. The property is declared here so that these tests also run against core releases
 * that predate Form::$sharesModelScope.
 */
class ModelScopedFormStub extends Form
{
    public $sharesModelScope = true;
}

class ScopeTestModel extends Model
{
    public $implement = [
        'Winter.Translate.Behaviors.TranslatableModel',
    ];

    protected $jsonable = ['items'];

    public $translatable = ['testField'];
}

class FormTestModel extends Model
{
    public $implement = [
        'Winter.Translate.Behaviors.TranslatableModel',
    ];

    public $translatable = [];
}
