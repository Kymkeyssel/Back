<?php

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class TranslationService
{
    private const SUPPORTED_LOCALES = ['fr', 'en', 'ewo', 'dua', 'bam', 'ful'];
    
    private const DEFAULT_LOCALE = 'fr';

    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Get supported locales
     */
    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Get default locale
     */
    public function getDefaultLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }

    /**
     * Check if locale is supported
     */
    public function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES);
    }

    /**
     * Translate a key
     */
    public function translate(string $key, array $parameters = [], string $locale = null): string
    {
        return $this->translator->trans($key, $parameters, null, $locale);
    }

    /**
     * Get translations for a specific domain and locale
     */
    public function getTranslations(string $domain, string $locale): array
    {
        $translations = [];
        
        // Load translations from files
        $translationFile = sprintf(
            '%s/../translations/%s.%s.yaml',
            $this->getParameter('kernel.project_dir'),
            $domain,
            $locale
        );

        if (file_exists($translationFile)) {
            $translations = yaml_parse_file($translationFile);
        }

        return $translations;
    }

    /**
     * Get all translations for a locale
     */
    public function getAllTranslations(string $locale): array
    {
        $translations = [];
        
        $translationDir = sprintf(
            '%s/../translations',
            $this->getParameter('kernel.project_dir')
        );

        $files = glob($translationDir . '/*.' . $locale . '.yaml');

        foreach ($files as $file) {
            $domain = basename($file, '.' . $locale . '.yaml');
            $translations[$domain] = yaml_parse_file($file);
        }

        return $translations;
    }

    /**
     * Detect user locale from request
     */
    public function detectLocale(string $acceptLanguageHeader): string
    {
        $languages = explode(',', $acceptLanguageHeader);
        
        foreach ($languages as $lang) {
            $locale = trim(explode(';', $lang)[0]);
            $locale = substr($locale, 0, 2);
            
            if ($this->isLocaleSupported($locale)) {
                return $locale;
            }
        }

        return self::DEFAULT_LOCALE;
    }
}
