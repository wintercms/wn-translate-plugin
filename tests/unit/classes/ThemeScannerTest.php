<?php

declare(strict_types=1);

namespace Winter\Translate\Tests\Unit\Classes;

use Winter\Translate\Classes\ThemeScanner;

/**
 * Test suite for the ThemeScanner class
 *
 * Tests Twig template parsing, message extraction, and filter caching functionality.
 */
class ThemeScannerTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{
    /**
     * Set up test environment before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        // Pre-populate the translatable filters cache with known filters
        $reflectionClass = new \ReflectionClass(ThemeScanner::class);
        $reflectionProperty = $reflectionClass->getProperty('translatableFilters');
        $reflectionProperty->setValue(null, ['_', '__', 'transRaw', 'transRawPlural', 'localeUrl']);
    }

    /**
     * Clean up test environment after each test
     */
    public function tearDown(): void
    {
        // Reset the cache after each test
        $reflectionClass = new \ReflectionClass(ThemeScanner::class);
        $reflectionProperty = $reflectionClass->getProperty('translatableFilters');
        $reflectionProperty->setValue(null, null);
        parent::tearDown();
    }

    /**
     * Test parsing content with null content
     *
     * Verifies that parseContent() handles null input gracefully.
     */
    public function testParseContentWithNullContent(): void
    {
        $themeScanner = new ThemeScanner();

        $result = $themeScanner->parseContent(null);

        $this->assertEquals([], $result);
    }

    /**
     * Test parsing content with valid content
     *
     * Verifies that parseContent() processes valid Twig content correctly.
     */
    public function testParseContentWithValidContent(): void
    {
        $themeScanner = new ThemeScanner();

        $content = "{{ 'Test message' | _ }}";

        $result = $themeScanner->parseContent($content);

        $this->assertContains('Test message', $result);
    }

    /**
     * Test static scan method accessibility
     *
     * Verifies that the static scan() method exists and is publicly accessible.
     */
    public function testScanStaticMethod(): void
    {
        // Test that the static scan method can be called without errors
        // This is mainly to ensure the method exists and can be invoked
        try {
            // We can't actually run the scan in tests as it requires theme setup
            // But we can verify the method exists
            $reflectionClass = new \ReflectionClass(ThemeScanner::class);
            $method = $reflectionClass->getMethod('scan');
            $this->assertTrue($method->isStatic());
            $this->assertTrue($method->isPublic());
        } catch (\Exception $exception) {
            $this->fail('Static scan method should be accessible');
        }
    }

    /**
     * Test translatable filters caching mechanism
     *
     * Verifies that translatable filters are cached statically for performance.
     */
    public function testTranslatableFiltersCaching(): void
    {
        // Test that translatable filters are cached statically
        $reflectionClass = new \ReflectionClass(ThemeScanner::class);
        $reflectionProperty = $reflectionClass->getProperty('translatableFilters');

        // Reset the cache
        $reflectionProperty->setValue(null, null);

        // Pre-populate with test filters
        $reflectionProperty->setValue(null, ['_', '__']);

        // First call should use the cache
        $themeScanner = new ThemeScanner();
        $result = $themeScanner->processStandardTags("{{ 'test' | _ }}");

        $this->assertContains('test', $result);

        $cachedFilters = $reflectionProperty->getValue();
        $this->assertEquals(['_', '__'], $cachedFilters);
    }

    /**
     * Comprehensive test for processStandardTags with various Twig patterns
     *
     * Tests all edge cases, special characters, Unicode, and complex scenarios
     * including accented characters, newlines, tabs, quotes, and block tags.
     */
    public function testProcessStandardTagsComprehensive(): void
    {
        $themeScanner = new ThemeScanner();

        $check_strings = [
            // Should not match
            ['hello', []],
            ["'hello'|_", []],
            ["{ 'hello'|_ }", []],
            ['{{ var|_ }}', []],
            ["{{ ('Dynamic ' ~ variable) | _ }}", []],  // Complex expression
            ["{{ 'hello'|upper|_ }}", []],

            // Code is syntactically wrong
            ["{{ 'hello\"|_ }}", []],
            ["{{ \"hello'|_ }}", []],
            ["{{ 'Unclosed string | _", []],  // Invalid Twig syntax

            // Should find 1 match
            ["{{ 'hello1'|_ }}}}", ['hello1']],
            ["{{{{ 'hello2'|_ }}", ['hello2']],
            ["{{ 'hello3'|_ }}", ['hello3']],
            ['{{ "hello4"|_ }}', ['hello4']],
            ["{{ \"'hello5\"|_ }}", ["'hello5"]],
            ["{{ '\"hello6'|_ }}", ['"hello6']],
            ["{{ 'hello7'|__ }}", ['hello7']],
            ["{{ 'hello8a'|transRaw }}", ['hello8a']],
            ["{{ 'hello8b'|transRaw() }}", ['hello8b']],
            ["{{ 'hello9a'|transRawPlural }}", ['hello9a']],
            ["{{ 'hello9b'|transRawPlural() }}", ['hello9b']],
            ["{{ 'hello10a'|localeUrl }}", ['hello10a']],
            ["{{ 'hello10b'|localeUrl() }}", ['hello10b']],
            ["{{ 'hello11'|_() }}", ['hello11']],
            ["{{ 'hello12'|_(func()) }}", ['hello12']],
            ["{{ 'hello13'|_({var: val}) }}", ['hello13']],
            ["{{ 'hello14'|_({var: func(param)}) }}", ['hello14']],
            ["{{ 'hello15'|_({var: func(nestedFunc())}) }}", ['hello15']],
            ["{{ 'hello16'|_|filter }}", ['hello16']],
            ["{{ 'hello17'|_|filter|otherfilter }}", ['hello17']],
            ["{{ 'Hello & Welcome' | _ }}", ['Hello & Welcome']],  // Special characters
            ["{{ 'Hello 世界' | _ }}", ['Hello 世界']],  // Unicode characters

            // Should find 2 matches
            [
                "{{ 'hello18a'|_|filter|otherfilter }}{{ 'hello18b'|_|filter|otherfilter }}",
                ['hello18a', 'hello18b'],
            ],
            ["{{ \"Double quoted\" | _ }} {{ 'Single quoted' | __ }}", ['Double quoted', 'Single quoted']],
            [
                "<h1>{{ 'Title' | _ }}</h1><p>{{ 'Description' | upper }}</p>{{ 'Footer' | __ }}",
                ['Title', 'Footer'],
            ],

            // Special characters and edge cases
            ["{{ 'héllo19'|_ }}", ['héllo19']],  // 19. Accented characters
            ["{{ 'helloñ20'|_ }}", ['helloñ20']],  // 20. Special characters
            ["{{ '你好21'|_ }}", ['你好21']],  // 21. Unicode characters
            ["{{ 'hello@#$%22'|_ }}", ['hello@#$%22']],  // 22. Special symbols
            ["{{'hello world23'|_}}", ['hello world23']],  // 23. spaces
            ["{{ 'hello world24' | _ }}", ['hello world24']],  // 24. spaces
            ["{{ 'hello\nworld25'\n|\n_ }}", ["hello\nworld25"]],  // 25. Newline character (literal \n in Twig)
            ["{{ 'hello\tworld26'\t|\t_ }}", ["hello\tworld26"]],  // 26. Tab character (literal \t in Twig)
            ["{{ 'hello \"world\"27'|_ }}", ['hello "world"27']],  // 27. Double quotes inside single quotes
            ["{{ \"hello's world28\"|_ }}", ["hello's world28"]],  // 28. Single quotes inside double quotes
            ["{{ 'hello\\\\nworld29'|_ }}", ['hello\\nworld29']],  // 29. Escaped newline
            ["{{ 'hello\\\\tworld30'|_ }}", ['hello\\tworld30']],  // 30. Escaped tab
            ["{{ 'hello™©®31'|_ }}", ['hello™©®31']],  // 31. Trademark/copyright symbols
            ["{{ 'русский32'|_ }}", ['русский32']],  // 32. Cyrillic characters
            ["{{ 'العربية33'|_ }}", ['العربية33']],  // 33. Arabic characters
            ['{% set variable="hello\nworld34" | _ %}', ["hello\nworld34"]],  // 34. also inside block
        ];

        foreach ($check_strings as $check_string) {
            $this->assertEquals($themeScanner->processStandardTags($check_string[0]), $check_string[1]);
        }
    }
}
