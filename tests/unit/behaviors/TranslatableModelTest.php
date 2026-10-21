<?php namespace Winter\Translate\Tests\Unit\Behaviors;

use Db;
use Model;
use Schema;
use Winter\Storm\Database\MemoryCache;
use Winter\Translate\Classes\Translator;
use Winter\Translate\Tests\Fixtures\Models\Country as CountryModel;
use Winter\Translate\Tests\Fixtures\Models\ValidatedCountry as ValidatedCountryModel;
use Winter\Translate\Models\Locale as LocaleModel;
use Winter\Storm\Database\Relations\Relation;

class TranslatableModelTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->seedSampleTableAndData();
    }

    protected function seedSampleTableAndData()
    {
        if (Schema::hasTable('translate_test_countries')) {
            return;
        }

        Model::unguard();

        Schema::create('translate_test_countries', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->text('states')->nullable();
            $table->timestamps();
        });

        LocaleModel::firstOrCreate([
            'code' => 'fr',
            'name' => 'French',
            'is_enabled' => 1
        ]);

        $this->recycleSampleData();

        Model::reguard();
    }

    protected function recycleSampleData()
    {
        CountryModel::truncate();

        CountryModel::create([
            'name' => 'Australia',
            'code' => 'AU',
            'states' => ['NSW', 'ACT', 'QLD'],
        ]);
    }

    public function testGetTranslationValue()
    {
        $obj = CountryModel::first();

        $this->assertEquals('Australia', $obj->name);
        $this->assertEquals(['NSW', 'ACT', 'QLD'], $obj->states);

        $obj->translateContext('fr');

        $this->assertEquals('Australia', $obj->name);
    }

    /**
     * @since 2.1.5
     */
    public function testGetTranslationValueUseFallbackFalse()
    {
        $obj = CountryModel::first();

        $this->assertEquals('Australia', $obj->name);

        $obj->setTranslatableUseFallback(false)->translateContext('fr');
        
        $this->assertEquals(null, $obj->name);
    }
    
    /**
     * @deprecated 2.1.5
     * @see testGetTranslationValueUseFallbackFalse()
     */
    public function testGetTranslationValueNoFallback()
    {
        $obj = CountryModel::first();

        $this->assertEquals('Australia', $obj->name);

        $obj->noFallbackLocale()->translateContext('fr');

        $this->assertEquals(null, $obj->name);
    }

    public function testSetTranslationValue()
    {
        $this->recycleSampleData();

        $obj = CountryModel::first();
        $obj->name = 'Aussie';
        $obj->states = ['VIC', 'SA', 'NT'];
        $obj->save();

        $obj->translateContext('fr');
        $obj->name = 'Australie';
        $obj->states = ['a', 'b', 'c'];
        $obj->save();

        $obj = CountryModel::first();
        $this->assertEquals('Aussie', $obj->name);
        $this->assertEquals(['VIC', 'SA', 'NT'], $obj->states);

        $obj->translateContext('fr');
        $this->assertEquals('Australie', $obj->name);
        $this->assertEquals(['a', 'b', 'c'], $obj->states);
    }

    public function testSaveRestoresTranslateContextAfterValidation()
    {
        $this->recycleSampleData();

        $obj = ValidatedCountryModel::first();
        $obj->setAttributeTranslated('name', 'Australie', 'fr');

        $nameBeforeSave = null;
        $obj->bindEvent('model.beforeSave', function () use ($obj, &$nameBeforeSave) {
            $nameBeforeSave = $obj->name;
        });

        $obj->save();

        $this->assertEquals('Australia', $nameBeforeSave);
        $this->assertEquals(Translator::instance()->getDefaultLocale(), $obj->translateContext());

        $obj = ValidatedCountryModel::first();
        $this->assertEquals('Australia', $obj->name);
        $obj->translateContext('fr');
        $this->assertEquals('Australie', $obj->name);
    }

    public function testGetTranslationValueEagerLoading()
    {
        $this->recycleSampleData();

        $obj = CountryModel::first();
        $obj->translateContext('fr');
        $obj->name = 'Australie';
        $obj->states = ['a', 'b', 'c'];
        $obj->save();

        $objList = CountryModel::with([
          'translations'
        ])->get();

        $obj = $objList[0];
        $this->assertEquals('Australia', $obj->name);
        $this->assertEquals(['NSW', 'ACT', 'QLD'], $obj->states);

        $obj->translateContext('fr');
        $this->assertEquals('Australie', $obj->name);
        $this->assertEquals(['a', 'b', 'c'], $obj->states);
    }

    public function testTranslateWhere()
    {
        $this->recycleSampleData();

        $obj = CountryModel::first();

        $obj->translateContext('fr');
        $obj->name = 'Australie';
        $obj->save();

        $this->assertEquals(0, CountryModel::transWhere('name', 'Australie')->count());

        Translator::instance()->setLocale('fr');
        $this->assertEquals(1, CountryModel::transWhere('name', 'Australie')->count());

        Translator::instance()->setLocale('en');
    }

    public function testTranslateOrderBy()
    {
        $this->recycleSampleData();

        $obj = CountryModel::first();

        $obj->translateContext('fr');
        $obj->name = 'Australie';
        $obj->save();

        $obj = CountryModel::create([
            'name' => 'Germany',
            'code' => 'DE'
        ]);

        $obj->translateContext('fr');
        $obj->name = 'Allemagne';
        $obj->save();

        $res = CountryModel::transOrderBy('name')->get()->pluck('name');
        $this->assertEquals(['Australia', 'Germany'], $res->toArray());

        Translator::instance()->setLocale('fr');
        $res = CountryModel::transOrderBy('name')->get()->pluck('name');
        $this->assertEquals(['Allemagne', 'Australie'], $res->toArray());

        Translator::instance()->setLocale('en');
    }

    public function testGetTranslationValueEagerLoadingWithMorphMap()
    {
        Relation::morphMap([
            'morph.key' => CountryModel::class,
        ]);

        $this->recycleSampleData();

        $obj = CountryModel::first();
        $obj->translateContext('fr');
        $obj->name = 'Australie';
        $obj->states = ['a', 'b', 'c'];
        $obj->save();

        $objList = CountryModel::with([
          'translations'
        ])->get();

        $obj = $objList[0];
        $this->assertEquals('Australia', $obj->name);
        $this->assertEquals(['NSW', 'ACT', 'QLD'], $obj->states);

        $obj->translateContext('fr');
        $this->assertEquals('Australie', $obj->name);
        $this->assertEquals(['a', 'b', 'c'], $obj->states);
    }

    public function testTranslationsAreEagerLoadedOnNonDefaultLocale()
    {
        $this->recycleSampleData();

        LocaleModel::firstOrCreate(['code' => 'fr', 'name' => 'French', 'is_enabled' => 1]);
        LocaleModel::clearCache();

        foreach (['Germany' => 'Allemagne', 'Spain' => 'Espagne'] as $name => $translation) {
            $obj = CountryModel::create(['name' => $name]);
            $obj->translateContext('fr');
            $obj->name = $translation;
            $obj->save();
        }

        $this->assertTrue(Translator::instance()->setLocale('fr'));
        $names = $this->pluckNamesCountingQueries($queries);
        $this->assertEquals(['Australia', 'Allemagne', 'Espagne'], $names);
        $this->assertEquals(2, $queries);

        Translator::instance()->setLocale('en');
        $names = $this->pluckNamesCountingQueries($queries);
        $this->assertEquals(['Australia', 'Germany', 'Spain'], $names);
        $this->assertEquals(1, $queries);

        Translator::instance()->setLocale('fr');
        $this->pluckNamesCountingQueries($queries, fn ($query) => $query->withoutGlobalScope('translatableEagerLoad'));
        $this->assertEquals(4, $queries);
        Translator::instance()->setLocale('en');
    }

    protected function pluckNamesCountingQueries(&$count, ?callable $scope = null): array
    {
        MemoryCache::instance()->flush();
        Db::enableQueryLog();
        Db::flushQueryLog();

        $query = CountryModel::query();
        if ($scope) {
            $scope($query);
        }
        $names = $query->get()->map(fn ($obj) => $obj->name)->all();

        $count = count(Db::getQueryLog());
        Db::disableQueryLog();

        return $names;
    }

    public function testAddTranslatableAttributes()
    {
        $country = new CountryModel;
        $country->translatable = [];
        $country->addTranslatableAttributes('attr1');
        $this->assertEquals($country->getTranslatableAttributes(), ['attr1']);

        $country->translatable = [];
        $country->addTranslatableAttributes('attr1', 'attr2');
        $this->assertEquals($country->getTranslatableAttributes(), ['attr1', 'attr2']);

        $country->translatable = [];
        $country->addTranslatableAttributes(['attr1', 'attr2']);
        $this->assertEquals($country->getTranslatableAttributes(), ['attr1', 'attr2']);
    }
}
