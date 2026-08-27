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

    public function testRefusesToRunInProduction()
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('scaffold:winter.translate');

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, Message::count(), 'Nothing should be created in production.');

        $this->app['env'] = 'testing';
    }
}
