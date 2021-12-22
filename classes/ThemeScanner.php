<?php namespace Winter\Translate\Classes;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\Layout;
use Cms\Classes\Partial;
use Winter\Translate\Models\Message;
use Winter\Translate\Classes\Translator;
use System\Models\MailTemplate;
use Event;

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
    protected function parseContent($content)
    {
        $messages = [];
        $messages = array_merge($messages, $this->processStandardTags($content));

        return $messages;
    }

    /**
     * Get an array of standard language filter tags
     * @return array
     */
    protected function getFilters()
    {
        return [
            '_',
            '__',
            'transRaw',
            'transRawPlural',
            'localeUrl'
        ];
    }

    /**
     * Get an array of Twig tokens
     * @param  string $string
     * @return array
     */
    protected function findTwigTokensInString($string)
    {
        $loader = new \Twig\Loader\ArrayLoader();
        $env = new \Twig\Environment($loader);
        $source = new \Twig\Source($string, 'test');

        try {
            $stream = $env->tokenize($source);
        }
        catch (\Exception $e) {
            return [];
        }

        $tokens = [];
        while (!$stream->isEOF()) {
            $token = $stream->next();
            $token->typeString = $token->typeToString($token->getType(), true);
            $tokens[] = $token;
        }
        return $tokens;
    }

    /**
     * Searches for strings to be translated within a given Twig string
     * @param  string $content
     * @return array
     */
    protected function processStandardTags($content)
    {
        $tokens = $this->findTwigTokensInString($content);

        $translatable_strings = [];
        $var_token_started = false;
        for ($i = 0; $i < count($tokens); $i++) {
            switch ($tokens[$i]->typeString) {
                case 'VAR_START_TYPE':
                    $var_token_started = true;
                    continue 2;
                case 'VAR_END_TYPE':
                    $var_token_started = false;
                    continue 2;
            }
            if (
                $var_token_started
                && $tokens[$i]->typeString === 'STRING_TYPE'
                && $tokens[$i+1]->typeString === 'PUNCTUATION_TYPE'
                && $tokens[$i+1]->getValue() === '|'
                && $tokens[$i+2]->typeString === 'NAME_TYPE'
                && in_array($tokens[$i+2]->getValue(), $this->getFilters())
            ) {
                $translatable_strings[] = stripslashes($tokens[$i]->getValue());
                $i += 2;
            }
        }

        return $translatable_strings;
    }
}
