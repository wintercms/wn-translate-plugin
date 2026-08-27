<?php namespace Winter\Translate\Tests\Feature\Console;

use Artisan;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Winter\Translate\Console\ScaffoldCommand;
use Winter\Translate\Models\Locale;
use Winter\Translate\Models\Message;
use Winter\Translate\Tests\TranslatePluginTestCase;

class ScaffoldCommandTest extends TranslatePluginTestCase
{
    /** Locale codes this scaffold manages (English is deliberately excluded). */
    const MANAGED_LOCALES = ['fr', 'es', 'de', 'ar', 'it'];

    public function setUp(): void
    {
        parent::setUp();

        // Plugin console commands are registered via ConsoleApplication::starting, which has
        // already fired by the time the test harness boots the plugin — so the command isn't
        // resolvable through Artisan here. Register it directly with the kernel for the test.
        $this->app->make(ConsoleKernel::class)->registerCommand(new ScaffoldCommand());
    }

    protected function managedLocaleCount(): int
    {
        return Locale::whereIn('code', self::MANAGED_LOCALES)->count();
    }

    public function testCreatesLocalesAndMessages()
    {
        $this->assertSame(0, Message::count(), 'No messages should exist beforehand.');

        $exitCode = Artisan::call('scaffold:winter.translate');

        $this->assertSame(0, $exitCode);
        $this->assertSame(5, $this->managedLocaleCount());
        $this->assertSame(72, Message::count());

        // Italian is intentionally seeded disabled; Arabic (RTL) enabled.
        $this->assertFalse((bool) Locale::where('code', 'it')->first()->is_enabled);
        $this->assertTrue((bool) Locale::where('code', 'ar')->first()->is_enabled);
    }

    public function testIsIdempotentWithoutFresh()
    {
        Artisan::call('scaffold:winter.translate');

        $exitCode = Artisan::call('scaffold:winter.translate');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('already exists', Artisan::output());
        $this->assertSame(5, $this->managedLocaleCount(), 'A second run must not duplicate locales.');
        $this->assertSame(72, Message::count(), 'A second run must not duplicate messages.');
    }

    public function testFreshRecreatesTheData()
    {
        Artisan::call('scaffold:winter.translate');

        $exitCode = Artisan::call('scaffold:winter.translate', ['--fresh' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(5, $this->managedLocaleCount());
        $this->assertSame(72, Message::count());
    }

    public function testPreservesUserManagedMessageWithCollidingCode()
    {
        // A pre-existing, user-managed message whose derived code collides with a
        // scaffold source string ("Home"), but carrying the developer's own value.
        $user = new Message();
        $user->code = Message::makeMessageCode('Home');
        $user->message_data = [Message::DEFAULT_LOCALE => 'Home', 'fr' => 'MON PROPRE ACCUEIL'];
        $user->found = true;
        $user->save();

        // Scaffolding must still run fully (the collision must not short-circuit
        // it) and must not overwrite the user's translation.
        $this->assertSame(0, Artisan::call('scaffold:winter.translate'));
        $this->assertSame(72, Message::count());
        $this->assertSame('MON PROPRE ACCUEIL', $user->fresh()->message_data['fr']);

        // --fresh must remove scaffold messages but preserve the user's message.
        $this->assertSame(0, Artisan::call('scaffold:winter.translate', ['--fresh' => true]));
        $survivor = Message::where('code', Message::makeMessageCode('Home'))->first();
        $this->assertNotNull($survivor, 'A user-managed message must survive --fresh.');
        $this->assertSame('MON PROPRE ACCUEIL', $survivor->message_data['fr']);
    }

    public function testPreservesSeededMessageExtendedWithAnExtraLocale()
    {
        Artisan::call('scaffold:winter.translate');

        // Developer adds an Italian translation (disabled by default, so not seeded)
        // to an otherwise scaffold-seeded message.
        $home = Message::where('code', Message::makeMessageCode('Home'))->first();
        $this->assertNotNull($home);
        $data = $home->message_data;
        $data['it'] = 'Casa';
        $home->message_data = $data;
        $home->save();

        // --fresh must now treat that message as user-managed and leave it intact.
        $this->assertSame(0, Artisan::call('scaffold:winter.translate', ['--fresh' => true]));
        $survivor = Message::where('code', Message::makeMessageCode('Home'))->first();
        $this->assertNotNull($survivor, 'A seeded message the developer extended must survive --fresh.');
        $this->assertSame('Casa', $survivor->message_data['it'] ?? null);
    }

    public function testRefusesToRunInProduction()
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('scaffold:winter.translate');

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, Message::count(), 'Nothing should be created in production.');

        $this->app['env'] = 'testing';
    }
}
