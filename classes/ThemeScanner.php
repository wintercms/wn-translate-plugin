<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Partial;
use Cms\Classes\Theme;
use Event;
use System\Models\MailTemplate;
use Twig\Token;
use Winter\Translate\Models\Message;

/**
 * Theme scanner class
 *
 * @package winter\translate
 * @author Alexey Bobkov, Samuel Georges
 */
class ThemeScanner
{
    /**
     * Cached list of translatable filters
     */
    private static ?array $translatableFilters = null;

    /**
     * Helper method for scanForMessages()
     * @return void
     */
    public static function scan()
    {
        $obj = new static;

        $obj->scanForMessages();

        /**
         * @event winter.translate.themeScanner.afterScan
         * Fires after theme scanning.
         *
         * Example usage:
         *
         *     Event::listen('winter.translate.themeScanner.afterScan', function (ThemeScanner $scanner) {
         *         // added an extra scan. Add generation files...
         *     });
         *
         */
        Event::fire('winter.translate.themeScanner.afterScan', [$obj]);
    }

    /**
     * Scans theme templates and config for messages.
     * @return void
     */
    public function scanForMessages()
    {
        // Set all messages initially as being not found. The scanner later
        // sets the entries it finds as found.
        Message::query()->update(['found' => false]);

        $this->scanThemeConfigForMessages();
        $this->scanThemeTemplatesForMessages();
        $this->scanMailTemplatesForMessages();
    }

    /**
     * Scans the theme configuration for defined messages
     * @return void
     */
    public function scanThemeConfigForMessages()
    {
        $theme = Theme::getActiveTheme();
        if (!$theme) {
            return;
        }

        $config = $theme->getConfigArray('translate');

        if (!count($config)) {
            return;
        }

        $translator = Translator::instance();
        $keys = [];

        foreach ($config as $locale => $messages) {
            if (is_string($messages)) {
                // $message is a yaml filename, load the yaml file
                $messages = $theme->getConfigArray('translate.'.$locale);
            }
            $keys = array_merge($keys, array_keys($messages));
        }

        Message::importMessages($keys);

        foreach ($config as $locale => $messages) {
            if (is_string($messages)) {
                // $message is a yaml filename, load the yaml file
                $messages = $theme->getConfigArray('translate.'.$locale);
            }
            Message::importMessageCodes($messages, $locale);
        }
    }

    /**
     * Scans the theme templates for message references.
     * @return void
     */
    public function scanThemeTemplatesForMessages()
    {
        $messages = [];

        foreach (Layout::all() as $layout) {
            $messages = array_merge($messages, $this->parseContent($layout->markup));
        }

        foreach (Page::all() as $page) {
            $messages = array_merge($messages, $this->parseContent($page->markup));
        }

        foreach (Partial::all() as $partial) {
            $messages = array_merge($messages, $this->parseContent($partial->markup));
        }

        Message::importMessages($messages);
    }

    /**
     * Scans the mail templates for message references.
     * @return void
     */
    public function scanMailTemplatesForMessages()
    {
        $messages = [];

        foreach (MailTemplate::allTemplates() as $mailTemplate) {
            $messages = array_merge($messages, $this->parseContent($mailTemplate->subject));
            $messages = array_merge($messages, $this->parseContent($mailTemplate->content_html));
        }

        Message::importMessages($messages);
    }

    /**
     * Parse the known language tag types into messages.
     * @param string $content
     * @return array
     */
    public function parseContent(?string $content): array
    {
        if ($content === null) {
            return [];
        }
        return $this->processStandardTags($content);
    }

    /**
     * Searches for strings to be translated within a given Twig string
     *
     * Looks for patterns like {{ 'string' | translatableFilter }} where 'translatableFilter' is a registered filter.
     *
     * @param string $content The Twig template content
     * @return array List of translatable strings found
     */
    public function processStandardTags(string $content): array
    {
        // Early return for empty or whitespace-only content to avoid unnecessary processing
        if (trim($content) === '') {
            return [];
        }

        $arrayLoader = new \Twig\Loader\ArrayLoader();
        $twigEnvironment = new \Twig\Environment($arrayLoader);
        $source = new \Twig\Source($content, 'translator');

        try {
            $stream = $twigEnvironment->tokenize($source);
        } catch (\Exception $exception) {
            // If tokenization fails, log and return empty array
            \Log::debug('ThemeScanner: Twig tokenization failed', ['error' => $exception->getMessage()]);
            return [];
        }

        // Collect all tokens
        $tokens = [];
        while (! $stream->isEOF()) {
            $tokens[] = $stream->next();
        }

        $totalTokens = count($tokens);
        $translatableStrings = [];

        // Fetch registered filters once for performance (cached statically)
        if (self::$translatableFilters === null) {
            self::$translatableFilters = array_keys(
                \System\Classes\PluginManager::instance()
                    ->findByIdentifier('Winter.Translate')
                    ->registerMarkupTags()['filters']
            );
        }

        // Iterate through tokens to find translatable strings
        for ($i = 0; $i < $totalTokens; $i++) {
            // Check for translatable string pattern: string | filter
            if (
                $i + 2 < $totalTokens
                && $tokens[$i]->test(Token::STRING_TYPE)
                && $tokens[$i + 1]->test(Token::OPERATOR_TYPE, '|')
                && $tokens[$i + 2]->test(Token::NAME_TYPE, self::$translatableFilters)
            ) {
                $translatableStrings[] = $tokens[$i]->getValue();
                $i += 2; // Skip the | and filter tokens
            }
        }

        return $translatableStrings;
    }
}
