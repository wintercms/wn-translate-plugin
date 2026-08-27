<?php

namespace Winter\Translate\Console;

use Backend;
use Illuminate\Console\Command;
use Winter\Translate\Models\Locale;
use Winter\Translate\Models\Message;

/**
 * Scaffolds Winter.Translate demo data for local development and testing.
 *
 * Enables a spread of locales — English (kept as the default), French, Spanish
 * and German, plus one deliberately-disabled locale (Italian) and one
 * right-to-left locale (Arabic) — and seeds a batch of front-end translation
 * Messages with translations across those locales, so the "Translate messages"
 * editor is populated well past a single screen and the Locales list/reorder,
 * the enabled/disabled row styling and the RTL badge can all be exercised.
 *
 * Mirrors the env-guarded, idempotent `scaffold:*` pattern used elsewhere: a
 * fixed set of managed locale codes and deterministic message codes lets
 * `--fresh` scope its cleanup precisely. The default English locale is never
 * created, enabled/disabled-toggled or deleted by this command.
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'scaffold:winter.translate
        {--fresh : Delete any existing scaffold data before recreating it}';

    protected $description = 'Scaffold Winter.Translate demo data (extra locales + translated front-end messages) for local development/testing.';

    /**
     * Source-string ("default"/`x`) locale key used by the Message model.
     */
    const SOURCE_LOCALE = Message::DEFAULT_LOCALE;

    /**
     * The locales this command manages. `en` is intentionally absent: it is the
     * seeded default and must never be created, toggled or deleted here.
     *
     * code => [name, is_enabled]
     */
    protected $locales = [
        'fr' => ['Français', true],
        'es' => ['Español', true],
        'de' => ['Deutsch', true],
        'ar' => ['العربية (Arabic, RTL)', true],
        'it' => ['Italiano (disabled)', false],
    ];

    public function handle(): int
    {
        // Never inject demo content into a production install.
        if ($this->getLaravel()->environment('production')) {
            $this->error('scaffold:winter.translate cannot run in the production environment.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->deleteExisting();
        }

        $messages = $this->messageSeed();

        // Only treat the data as present when the *complete* managed set exists
        // and still matches what we seeded — a single pre-existing message (e.g.
        // a user's own "Home") must not short-circuit seeding the rest.
        if ($this->isFullyScaffolded($messages)) {
            $this->warn('Translate scaffold data already exists. Use --fresh to recreate it.');

            return self::SUCCESS;
        }

        $localeCount = $this->createLocales();
        $this->info("Enabled/created {$localeCount} scaffold locale(s) (English default left untouched).");

        $messageCount = $this->createMessages($messages);
        $this->info("Seeded {$messageCount} translation message(s).");

        Locale::clearCache();

        $this->newLine();
        $this->line('Locales:            ' . Backend::url('winter/translate/locales'));
        $this->line('Translate messages: ' . Backend::url('winter/translate/messages'));

        return self::SUCCESS;
    }

    /**
     * Remove previously scaffolded messages. Only messages this command created
     * (their code matches and their stored data is still exactly what we seeded)
     * are removed, so a user-managed translation that happens to share a code is
     * never destroyed.
     *
     * Locales are intentionally left in place: a Message code derives from the
     * source string alone and a locale carries no ownership marker, so there is
     * no reliable way to tell a scaffold-enabled locale (e.g. 'fr') from one the
     * developer configured themselves. Deleting them could wipe genuine locale
     * configuration, so we don't — a re-run reconciles them idempotently.
     */
    protected function deleteExisting(): void
    {
        $messages = $this->messageSeed();

        $deletedMessages = 0;
        foreach ($messages as $source => $translations) {
            $message = Message::where('code', Message::makeMessageCode($source))->first();

            if ($message && $this->isScaffoldOwned($message, $this->scaffoldMessageData($source, $translations))) {
                $message->delete();
                $deletedMessages++;
            }
        }

        Locale::clearCache();

        if ($deletedMessages) {
            $this->info("Removed {$deletedMessages} scaffold message(s). Scaffold locales are left in place.");
        }
    }

    /**
     * Whether the full managed message set already exists and still matches what
     * this command seeds (used to keep the command idempotent without treating a
     * single colliding message as proof the scaffold is complete).
     */
    protected function isFullyScaffolded(array $messages): bool
    {
        foreach ($messages as $source => $translations) {
            $message = Message::where('code', Message::makeMessageCode($source))->first();

            if (!$message || !$this->isScaffoldOwned($message, $this->scaffoldMessageData($source, $translations))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a stored message still carries exactly the values this command
     * seeded for it (i.e. it is scaffold-owned and untouched). If the developer
     * edited any of the seeded translations, it is treated as user-managed.
     */
    protected function isScaffoldOwned(Message $message, array $expected): bool
    {
        $actual = (array) $message->message_data;

        foreach ($expected as $locale => $value) {
            if (($actual[$locale] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create (or enable) the managed locales. English is deliberately excluded.
     * Existing rows are updated in place so the command stays idempotent.
     */
    protected function createLocales(): int
    {
        $default = Locale::getDefault();
        $defaultCode = $default ? $default->code : 'en';
        $sortBase = (int) Locale::max('sort_order');
        $count = 0;

        foreach ($this->locales as $code => [$name, $isEnabled]) {
            // Guard: never touch the default (English) locale.
            if ($code === $defaultCode || $code === 'en') {
                continue;
            }

            $locale = Locale::firstOrNew(['code' => $code]);
            $locale->name = $name;
            $locale->is_enabled = $isEnabled;
            if (!$locale->exists) {
                $locale->sort_order = ++$sortBase;
            }
            $locale->save();
            $count++;
        }

        return $count;
    }

    /**
     * Seed the translation messages. Each is keyed by its source (English)
     * string and carries translations for the enabled non-default locales.
     *
     * Idempotent and non-destructive: an existing message that is not scaffold
     * -owned (a user-managed translation sharing the derived code) is left
     * untouched rather than overwritten, and re-running repairs any missing
     * scaffold records.
     */
    protected function createMessages(array $messages): int
    {
        $count = 0;

        foreach ($messages as $source => $translations) {
            $expected = $this->scaffoldMessageData($source, $translations);
            $message = Message::firstOrNew(['code' => Message::makeMessageCode($source)]);

            // Never clobber a pre-existing, user-managed message with this code.
            if ($message->exists && !$this->isScaffoldOwned($message, $expected)) {
                continue;
            }

            $message->message_data = $expected;
            $message->found = true;
            $message->save();
            $count++;
        }

        return $count;
    }

    /**
     * Build the exact message_data this command seeds for a source string: the
     * source under the default ("x") locale plus its translations for the
     * scaffold-enabled locales only.
     */
    protected function scaffoldMessageData(string $source, array $translations): array
    {
        $enabledCodes = array_keys(array_filter($this->locales, fn ($l) => $l[1]));

        $data = [self::SOURCE_LOCALE => $source];
        foreach ($translations as $localeCode => $value) {
            if (in_array($localeCode, $enabledCodes, true)) {
                $data[$localeCode] = $value;
            }
        }

        return $data;
    }

    /**
     * The batch of front-end strings to seed, keyed by the English source
     * string. Values are per-locale translations (fr/es/de/ar); Italian is
     * disabled so it is intentionally left without translations. A mix of fully
     * and partially translated rows exercises the "hide translated" filter, and
     * the volume (70+ rows) pushes the editor past a single screen.
     */
    protected function messageSeed(): array
    {
        // A core set of hand-translated UI strings (fully translated where sensible).
        $core = [
            'Home'        => ['fr' => 'Accueil',     'es' => 'Inicio',        'de' => 'Startseite',   'ar' => 'الرئيسية'],
            'About us'    => ['fr' => 'À propos',     'es' => 'Sobre nosotros', 'de' => 'Über uns',    'ar' => 'من نحن'],
            'Contact'     => ['fr' => 'Contact',      'es' => 'Contacto',      'de' => 'Kontakt',      'ar' => 'اتصل بنا'],
            'Blog'        => ['fr' => 'Blogue',       'es' => 'Blog',          'de' => 'Blog',         'ar' => 'مدونة'],
            'Products'    => ['fr' => 'Produits',     'es' => 'Productos',     'de' => 'Produkte',     'ar' => 'المنتجات'],
            'Services'    => ['fr' => 'Services',     'es' => 'Servicios',     'de' => 'Leistungen',   'ar' => 'الخدمات'],
            'Search'      => ['fr' => 'Rechercher',   'es' => 'Buscar',        'de' => 'Suchen',       'ar' => 'بحث'],
            'Sign in'     => ['fr' => 'Se connecter', 'es' => 'Iniciar sesión', 'de' => 'Anmelden',   'ar' => 'تسجيل الدخول'],
            'Sign out'    => ['fr' => 'Se déconnecter', 'es' => 'Cerrar sesión', 'de' => 'Abmelden',  'ar' => 'تسجيل الخروج'],
            'Register'    => ['fr' => 'S’inscrire',   'es' => 'Registrarse',   'de' => 'Registrieren', 'ar' => 'تسجيل'],
            'My account'  => ['fr' => 'Mon compte',   'es' => 'Mi cuenta',     'de' => 'Mein Konto',   'ar' => 'حسابي'],
            'Cart'        => ['fr' => 'Panier',       'es' => 'Carrito',       'de' => 'Warenkorb',    'ar' => 'عربة التسوق'],
            'Checkout'    => ['fr' => 'Commander',    'es' => 'Pagar',         'de' => 'Zur Kasse',    'ar' => 'الدفع'],
            'Add to cart' => ['fr' => 'Ajouter au panier', 'es' => 'Añadir al carrito', 'de' => 'In den Warenkorb', 'ar' => 'أضف إلى العربة'],
            'Read more'   => ['fr' => 'Lire la suite', 'es' => 'Leer más',     'de' => 'Weiterlesen',  'ar' => 'اقرأ المزيد'],
            'Submit'      => ['fr' => 'Envoyer',      'es' => 'Enviar',        'de' => 'Absenden',     'ar' => 'إرسال'],
            'Cancel'      => ['fr' => 'Annuler',      'es' => 'Cancelar',      'de' => 'Abbrechen',    'ar' => 'إلغاء'],
            'Save'        => ['fr' => 'Enregistrer',  'es' => 'Guardar',       'de' => 'Speichern',    'ar' => 'حفظ'],
            'Delete'      => ['fr' => 'Supprimer',    'es' => 'Eliminar',      'de' => 'Löschen',      'ar' => 'حذف'],
            'Edit'        => ['fr' => 'Modifier',     'es' => 'Editar',        'de' => 'Bearbeiten',   'ar' => 'تعديل'],
            'Next'        => ['fr' => 'Suivant',      'es' => 'Siguiente',     'de' => 'Weiter',       'ar' => 'التالي'],
            'Previous'    => ['fr' => 'Précédent',    'es' => 'Anterior',      'de' => 'Zurück',       'ar' => 'السابق'],
            'Loading...'  => ['fr' => 'Chargement…',  'es' => 'Cargando…',     'de' => 'Wird geladen…', 'ar' => 'جارٍ التحميل…'],
            'Welcome back' => ['fr' => 'Bon retour',  'es' => 'Bienvenido de nuevo', 'de' => 'Willkommen zurück', 'ar' => 'مرحبًا بعودتك'],
            'Password'    => ['fr' => 'Mot de passe', 'es' => 'Contraseña',    'de' => 'Passwort',     'ar' => 'كلمة المرور'],
            'Email address' => ['fr' => 'Adresse e-mail', 'es' => 'Correo electrónico', 'de' => 'E-Mail-Adresse', 'ar' => 'البريد الإلكتروني'],
            'Forgot your password?' => ['fr' => 'Mot de passe oublié ?', 'es' => '¿Olvidó su contraseña?', 'de' => 'Passwort vergessen?', 'ar' => 'هل نسيت كلمة المرور؟'],
            // A deliberately long string to test wrapping in the editor cells.
            'By continuing you agree to our terms of service and privacy policy, and consent to receiving occasional updates by email.'
                => [
                    'fr' => 'En continuant, vous acceptez nos conditions d’utilisation et notre politique de confidentialité, et consentez à recevoir occasionnellement des mises à jour par e-mail.',
                    'es' => 'Al continuar, acepta nuestros términos de servicio y política de privacidad, y consiente recibir actualizaciones ocasionales por correo electrónico.',
                    'de' => 'Indem Sie fortfahren, stimmen Sie unseren Nutzungsbedingungen und der Datenschutzerklärung zu und willigen ein, gelegentlich Updates per E-Mail zu erhalten.',
                    'ar' => 'بالمتابعة فإنك توافق على شروط الخدمة وسياسة الخصوصية الخاصة بنا، وتوافق على تلقي تحديثات عرضية عبر البريد الإلكتروني.',
                ],
            // Partially translated (French only) — exercises "hide translated" + mixed states.
            'Newsletter'  => ['fr' => 'Infolettre'],
            'Follow us'   => ['fr' => 'Suivez-nous'],
            // Untranslated entirely — shows an empty "to" cell.
            'Terms of service' => [],
            'Privacy policy'   => [],
        ];

        // Filler strings to push the editor comfortably past one page. Each gets
        // fr/es/de translations so most rows look "done" while a few above don't.
        for ($i = 1; $i <= 40; $i++) {
            $core["Sample front-end string number {$i}"] = [
                'fr' => "Chaîne d’exemple numéro {$i}",
                'es' => "Cadena de ejemplo número {$i}",
                'de' => "Beispielzeichenkette Nummer {$i}",
                'ar' => "سلسلة نصية تجريبية رقم {$i}",
            ];
        }

        return $core;
    }
}
