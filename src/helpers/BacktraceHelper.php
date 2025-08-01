<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use ReflectionClass;
use Twig\Template;

/**
 * @since 5.12.0
 */
class BacktraceHelper
{
    /**
     * @var string|null
     */
    private static ?string $templateClassFilename = null;

    /**
     * Returns the most recently rendered template code from the backtrace.
     */
    public static function getOriginTemplateCode(): array
    {
        $traces = debug_backtrace();

        foreach ($traces as $key => $trace) {
            $template = self::getTraceTemplate($trace);
            if ($template) {
                $templatePath = self::getTemplateShortPath($template);
                $templateCodeLine = $traces[$key - 1]['line'] ?? null;
                $line = self::findTemplateLine($template, $templateCodeLine);

                if ($templatePath && $line) {
                    $code = self::getTemplateCode($template, $line);

                    return [$templatePath . ':' . $line, $code];
                }
            }
        }

        return [null, null];
    }

    /**
     * Returns the template class filename.
     */
    private static function getTemplateClassFilename(): string
    {
        if (self::$templateClassFilename !== null) {
            return self::$templateClassFilename;
        }

        $reflector = new ReflectionClass(Template::class);
        self::$templateClassFilename = $reflector->getFileName();

        return self::$templateClassFilename;
    }

    /**
     * Returns a template from the trace.
     */
    private static function getTraceTemplate(array $trace): ?Template
    {
        // Ensure this is a template class file.
        if (empty($trace['file']) || $trace['file'] != self::getTemplateClassFilename()) {
            return null;
        }

        // Ensure this is a compiled template and not a dynamic one.
        if (empty($trace['class']) || $trace['class'] == 'Twig\\Template') {
            return null;
        }

        $template = $trace['object'] ?? null;

        if (!($template instanceof Template)) {
            return null;
        }

        return $template;
    }

    /**
     * Returns a template’s path.
     */
    private static function getTemplatePath(Template $template): string
    {
        return $template->getSourceContext()->getPath();
    }

    /**
     * Returns a template’s short path.
     */
    private static function getTemplateShortPath(Template $template): string
    {
        $path = self::getTemplatePath($template);

        return str_replace(Craft::getAlias('@templates/'), '', $path);
    }

    /**
     * Returns the template line number.
     *
     * @see Deprecator::_findTemplateLine()
     */
    private static function findTemplateLine(Template $template, ?int $actualCodeLine = null): ?int
    {
        if ($actualCodeLine === null) {
            return null;
        }

        // getDebugInfo() goes upward, so the first code line that's <= the trace line will be the match
        foreach ($template->getDebugInfo() as $codeLine => $templateLine) {
            if ($codeLine <= $actualCodeLine) {
                return $templateLine;
            }
        }

        return null;
    }

    private static function getTemplateCode(Template $template, int $line): string
    {
        $sourceContext = $template->getSourceContext();
        $sourceCode = $sourceContext->getCode();

        // If `devMode` is enabled, the source code will be empty, so we attempt to read the file.
        if (empty($sourceCode)) {
            $templatePath = $sourceContext->getPath();
            if (file_exists($templatePath)) {
                $sourceCode = file_get_contents($templatePath);
            }
        }

        $lines = array_slice(explode(PHP_EOL, $sourceCode), $line - 1);
        if (empty($lines)) {
            return '';
        }

        $code = $lines[0];
        if (str_contains($code, '{%')) {
            $code = self::getCodeBlock($lines, '%}');
        } else {
            $code = self::getCodeBlock($lines, '}}');
        }

        return $code;
    }

    private static function getCodeBlock(array $lines, string $closingTag): string
    {
        $code = '';

        for ($i = 0; $i < count($lines); $i++) {
            $code .= $lines[$i] . PHP_EOL;
            if (str_contains($lines[$i], $closingTag)) {
                break;
            }
        }

        // Unindent each line based on first line’s indentation.
        $firstLine = $lines[0];
        $indentation = strspn($firstLine, ' ');
        $code = preg_replace('/^ {0,' . $indentation . '}/m', '', $code);

        return trim($code, PHP_EOL);
    }
}
