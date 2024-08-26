<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

/**
 * @noinspection PhpInternalEntityUsedInspection
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\services\Elements;
use putyourlightson\blitz\helpers\ElementQueryHelper;
use putyourlightson\blitz\models\HintModel;
use putyourlightson\blitz\records\HintRecord;
use ReflectionClass as ReflectionClassAlias;
use Twig\Template;

class HintsService extends Component
{
    /**
     * @var HintModel[] The hints to be saved for the current request.
     */
    private array $hints = [];

    /**
     * @var string|null
     */
    private ?string $templateClassFilename = null;

    /**
     * Clears all hints.
     */
    public function clearAll(): void
    {
        HintRecord::deleteAll();
    }

    /**
     * Clears a hint.
     */
    public function clear(int $id): void
    {
        HintRecord::deleteAll([
            'id' => $id,
        ]);
    }

    /**
     * Checks for opportunities to eager-load elements.
     */
    public function checkElementQuery(ElementQuery $elementQuery): void
    {
        if (ElementQueryHelper::isClone($elementQuery)) {
            return;
        }

        if ($this->isEagerLoading($elementQuery) || $this->isParsingReferenceTags($elementQuery)) {
            return;
        }

        if (ElementQueryHelper::isNestedEntryQuery($elementQuery)) {
            $this->addFieldHint($elementQuery->fieldId ?? null);

            return;
        }

        // Required as of Craft 5.3.0.
        if (ElementQueryHelper::hasNumericElementIds($elementQuery)) {
            $this->addFieldHint($elementQuery->fieldId ?? null);

            return;
        }

        // Required to support relations saved prior to Craft 5.3.0.
        if (!ElementQueryHelper::isRelationFieldQuery($elementQuery)) {
            return;
        }

        /** @see ElementQuery::wasEagerLoaded() */
        $planHandle = $elementQuery->eagerLoadHandle;
        if (str_contains($planHandle, ':')) {
            $planHandle = explode(':', $planHandle, 2)[1];
        }

        $field = Craft::$app->getFields()->getFieldByHandle($planHandle);
        if ($field === null) {
            return;
        }

        $this->addFieldHint($field->id);
    }

    /**
     * Saves any hints that have been prepared.
     */
    public function save(): void
    {
        $db = Craft::$app->getDb();

        foreach ($this->hints as $hint) {
            $db->createCommand()
                ->upsert(
                    HintRecord::tableName(),
                    [
                        'fieldId' => $hint->fieldId,
                        'template' => $hint->template,
                        'line' => $hint->line,
                        'stackTrace' => implode(',', $hint->stackTrace),
                    ],
                    [
                        'line' => $hint->line,
                        'stackTrace' => implode(',', $hint->stackTrace),
                    ])
                ->execute();
        }

        $this->hints = [];
    }

    /**
     * Returns whether the element query is being eager-loaded.
     */
    private function isEagerLoading(ElementQuery $elementQuery): bool
    {
        if ($elementQuery->eagerly || $elementQuery->wasEagerLoaded()) {
            return true;
        }

        $traces = debug_backtrace();
        foreach ($traces as $trace) {
            $class = $trace['class'] ?? null;
            $function = $trace['function'] ?? null;

            // Detect eager-loading of matrix fields.
            if ($class === Elements::class && $function === 'eagerLoadElements') {
                return true;
            }

            // Detect eager-loading of relation fields.
            if ($class === Element::class && $function === 'getFieldValue') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the element query is parsing reference tags.
     */
    private function isParsingReferenceTags(ElementQuery $elementQuery): bool
    {
        if ($elementQuery->ref !== null) {
            return true;
        }

        // If the reference tag was an ID then the `ref` property will not be set, so we need to check the stack trace for the presence of `Elements::parseRefs()`.
        $traces = debug_backtrace();
        foreach ($traces as $trace) {
            $class = $trace['class'] ?? null;
            $function = $trace['function'] ?? null;
            if ($class === Elements::class && $function === 'parseRefs') {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a field hint. As of Craft 5.3.0, we may not be able to detect the field ID from the element query, if the relation field value is stored in the `content` column. In this case we set a field ID of zero, so we can still store it, maintaining unique keys.
     */
    private function addFieldHint(array|int|null $fieldId = null): void
    {
        if (is_array($fieldId)) {
            $fieldId = $fieldId[0] ?? null;
        }

        $fieldId = $fieldId ?? 0;
        $fieldHandle = null;

        if ($fieldId > 0) {
            $field = Craft::$app->getFields()->getFieldById($fieldId);
            $fieldHandle = $field->handle ?? null;
        }

        $hint = $this->createHintWithTemplateLine($fieldId, $fieldHandle);

        if ($hint === null) {
            return;
        }

        // Don’t continue if the template path is in the vendor folder path.
        // https://github.com/putyourlightson/craft-blitz/issues/574
        $vendorFolderPath = Craft::getAlias('@vendor');
        if (str_contains($hint->template, $vendorFolderPath)) {
            return;
        }

        $key = $fieldId . '-' . $hint->template;

        // Don’t continue if a hint with the key already exists.
        if (!empty($this->hints[$key])) {
            return;
        }

        $this->hints[$key] = $hint;
    }

    /**
     * Returns a new hint with the template and line number of the rendered template.
     */
    protected function createHintWithTemplateLine(int $fieldId, ?string $fieldHandle = null): ?HintModel
    {
        $hint = null;
        $traces = debug_backtrace();

        foreach ($traces as $key => $trace) {
            $template = $this->getTraceTemplate($trace);
            if ($template) {
                $templatePath = $this->getTemplateShortPath($template);
                $templateCodeLine = $traces[$key - 1]['line'] ?? null;
                $line = $this->findTemplateLine($template, $templateCodeLine);

                if ($templatePath && $line) {
                    if ($hint === null) {
                        $hint = new HintModel([
                            'fieldId' => $fieldId,
                            'template' => $templatePath,
                            'line' => $line,
                            'stackTrace' => [$templatePath . ':' . $line],
                        ]);
                    } else {
                        $hint->stackTrace[] = $templatePath . ':' . $line;

                        continue;
                    }

                    if ($fieldHandle !== null) {
                        // Read the contents of the template file, since the code cannot be retrieved from the source context with `devMode` disabled.
                        $templateCode = file($this->getTemplatePath($template));
                        $code = $templateCode[$line - 1] ?? '';
                        preg_match('/(\w+?)\.' . $fieldHandle . '/', $code, $matches);
                        $routeVariable = $matches[1] ?? null;

                        // Don’t continue if the route variable is set.
                        if ($routeVariable && !empty($trace['args'][0]['variables'][$routeVariable])) {
                            return null;
                        }
                    }
                }
            }
        }

        return $hint;
    }

    /**
     * Returns the template class filename.
     */
    private function getTemplateClassFilename(): string
    {
        if ($this->templateClassFilename !== null) {
            return $this->templateClassFilename;
        }

        $reflector = new ReflectionClassAlias(Template::class);
        $this->templateClassFilename = $reflector->getFileName();

        return $this->templateClassFilename;
    }

    /**
     * Returns a template from the trace.
     */
    private function getTraceTemplate(array $trace): ?Template
    {
        // Ensure this is a template class file.
        if (empty($trace['file']) || $trace['file'] != $this->getTemplateClassFilename()) {
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
    private function getTemplatePath(Template $template): string
    {
        return $template->getSourceContext()->getPath();
    }

    /**
     * Returns a template’s short path.
     */
    private function getTemplateShortPath(Template $template): string
    {
        $path = $this->getTemplatePath($template);

        return str_replace(Craft::getAlias('@templates/'), '', $path);
    }

    /**
     * Returns the template line number.
     *
     * @see craft\services\Deprecator::_findTemplateLine()
     */
    private function findTemplateLine(Template $template, int $actualCodeLine = null): ?int
    {
        if ($actualCodeLine === null) {
            return null;
        }

        // `getDebugInfo()` goes upward, so the first code line that is `<=` the trace line is the match.
        foreach ($template->getDebugInfo() as $codeLine => $templateLine) {
            if ($codeLine <= $actualCodeLine) {
                return $templateLine;
            }
        }

        return null;
    }
}
