<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Layout;
use Cms\Classes\Page;
use Cms\Classes\Partial;
use Cms\Classes\Theme;
use Event;
use System\Models\MailTemplate;
use Winter\Translate\Classes\Translator;
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
     * Parse the known language tag types in to messages.
     * @param  string $content
     * @return array
     */
    public function parseContent($content)
    {
        $messages = [];
        if ($content) {
            $messages = array_merge($messages, $this->processStandardTags($content));
        }

        return $messages;
    }

    /**
     * Process standard language filter tag (_|)
     * @param  string $content
     * @return array
     */
    public function processStandardTags($content)
    {
        $messages = [];

        $messages = self::getMessages(preg_quote("'"), $content);

        return array_merge($messages, self::getMessages(preg_quote('"'), $content));
    }

    /**
     * Apply regex on string to extract value to translate
     * @param  string $quoteChar
     * @return array
     */
    protected static function getMessages($quoteChar, $content)
    {
        preg_match_all('/\{\{\s*'.$quoteChar.'([^'.$quoteChar.']+)'.$quoteChar.'\s*\|\s*(?:localeUrl|transRaw|transRawPlural|_{1,2})(?:\(.*\)){0,1}\s*(?:\|[^|\s\}]+){0,}\s*\}\}/x', $content, $match);

        return $match[1] ?? [];
    }
}
