<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\log;

use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;

class LogTarget extends MonologTarget
{
    /**
     * @inheritdoc
     */
    public bool $logContext = false;

    /**
     * @inheritdoc
     */
    protected bool $allowLineBreaks = false;

    /**
     * @inheritdoc
     */
    protected string $level = LogLevel::INFO;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->categories = [$this->name];

        /**
         * Keep the format simple, loveable and complete.
         * @see LineFormatter::SIMPLE_FORMAT
         */
        $this->formatter = new LineFormatter(
            format: "[%datetime%] %message%\n",
            dateFormat: 'Y-m-d H:i:s',
            allowInlineLineBreaks: false,
            ignoreEmptyContextAndExtra: true,
        );

        parent::init();
    }
}
