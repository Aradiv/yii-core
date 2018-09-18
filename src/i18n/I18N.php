<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\i18n;

use NumberFormatter;
use yii\base\Application;
use yii\base\Component;
use yii\exceptions\InvalidConfigException;

/**
 * I18N provides features related with internationalization (I18N) and localization (L10N).
 *
 * I18N is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `$this->app->i18n`.
 *
 * @property MessageFormatter $messageFormatter The message formatter to be used to format message via ICU
 * message format. Note that the type of this property differs in getter and setter. See
 * [[getMessageFormatter()]] and [[setMessageFormatter()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class I18N extends Component
{
    /**
     * @var array list of [[MessageSource]] configurations or objects. The array keys are message
     * category patterns, and the array values are the corresponding [[MessageSource]] objects or the configurations
     * for creating the [[MessageSource]] objects.
     *
     * The message category patterns can contain the wildcard `*` at the end to match multiple categories with the same prefix.
     * For example, `app/*` matches both `app/cat1` and `app/cat2`.
     *
     * The `*` category pattern will match all categories that do not match any other category patterns.
     *
     * This property may be modified on the fly by extensions who want to have their own message sources
     * registered under their own namespaces.
     *
     * The category `yii` and `app` are always defined. The former refers to the messages used in the Yii core
     * framework code, while the latter refers to the default message category for custom application code.
     * By default, both of these categories use [[PhpMessageSource]] and the corresponding message files are
     * stored under `@yii/messages` and `@app/messages`, respectively.
     *
     * You may override the configuration of both categories.
     */
    public $translations;

    /**
     * @var Application
     */
    protected $app;

    private $_locale;

    /**
     * @param Application $app
     * @param LocaleInterface $locale
     */
    public function __construct(Application $app, LocaleInterface $locale)
    {
        $this->app = $app;
        $this->_locale = $locale;
    }

    /**
     * Translates a message to the specified language.
     *
     * After translation the message will be formatted using [[MessageFormatter]] if it contains
     * ICU message format and `$params` are not empty.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the translated and formatted message.
     */
    public function translate($category, $message, $params, $language)
    {
        $messageSource = $this->getMessageSource($category);
        $translation = $messageSource->translate($category, $message, $language);
        if ($translation === false) {
            return $this->format($message, $params, $messageSource->sourceLanguage);
        }

        return $this->format($translation, $params, $language);
    }

    /**
     * Formats a message using [[MessageFormatter]].
     *
     * @param string $message the message to be formatted.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the formatted message.
     */
    public function format($message, $params, $language)
    {
        $params = (array) $params;
        if ($params === []) {
            return $message;
        }

        if (preg_match('~{\s*[\w.]+\s*,~u', $message)) {
            $formatter = $this->getMessageFormatter();
            $result = $formatter->format($message, $params, $language);
            if ($result === false) {
                $errorMessage = $formatter->getErrorMessage();
                $this->app->warning("Formatting message for language '$language' failed with error: $errorMessage. The message being formatted was: $message.", __METHOD__);

                return $message;
            }

            return $result;
        }

        return static::substitute($message, $params);
    }

    public static function substitute($message, array $params)
    {
        $placeholders = [];
        foreach ($params as $name => $value) {
            $placeholders['{' . $name . '}'] = $value;
        }

        return empty($placeholders) ? $message : strtr($message, $placeholders);
    }

    /**
     * @var string|array|MessageFormatter
     */
    private $_messageFormatter;

    /**
     * Returns the message formatter instance.
     * @return MessageFormatter the message formatter to be used to format message via ICU message format.
     */
    public function getMessageFormatter()
    {
        if ($this->_messageFormatter === null) {
            $this->_messageFormatter = new MessageFormatter();
        } elseif (is_array($this->_messageFormatter) || is_string($this->_messageFormatter)) {
            $this->_messageFormatter = $this->app->createObject($this->_messageFormatter);
        }

        return $this->_messageFormatter;
    }

    /**
     * @param string|array|MessageFormatter $value the message formatter to be used to format message via ICU message format.
     * Can be given as array or string configuration that will be given to [[$this->app->createObject]] to create an instance
     * or a [[MessageFormatter]] instance.
     */
    public function setMessageFormatter($value)
    {
        $this->_messageFormatter = $value;
    }

    /**
     * Returns the message source for the given category.
     * @param string $category the category name.
     * @return MessageSource the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function getMessageSource($category)
    {
        if (isset($this->translations[$category])) {
            $source = $this->translations[$category];
            if ($source instanceof MessageSource) {
                return $source;
            }

            return $this->translations[$category] = $this->app->createObject($source);
        }
        // try wildcard matching
        foreach ($this->translations as $pattern => $source) {
            if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                if ($source instanceof MessageSource) {
                    return $source;
                }

                return $this->translations[$category] = $this->translations[$pattern] = $this->app->createObject($source);
            }
        }

        // match '*' in the last
        if (isset($this->translations['*'])) {
            $source = $this->translations['*'];
            if ($source instanceof MessageSource) {
                return $source;
            }

            return $this->translations[$category] = $this->translations['*'] = $this->app->createObject($source);
        }

        throw new InvalidConfigException("Unable to locate message source for category '$category'.");
    }

    /**
     * Returns a currency symbol
     *
     * @param string $currencyCode the 3-letter ISO 4217 currency code to get symbol for. If null,
     * method will attempt using currency code from current locale.
     * @return string
     * @throws InvalidConfigException
     */
    public function getCurrencySymbol($currencyCode = null)
    {
        if (!extension_loaded('intl')) {
            throw new InvalidConfigException('Locale component requires PHP intl extension to be installed.');
        }

        $locale = $this->_locale;

        if ($currencyCode !== null) {
            $locale = $locale->withCurrency($currencyCode);
        }

        $formatter = new NumberFormatter((string)$locale, NumberFormatter::CURRENCY);
        return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }
}
