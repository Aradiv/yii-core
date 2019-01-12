<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\tests\data\base;

use yii\base\Model;

/**
 * Speaker.
 */
class Speaker extends Model
{
    public $firstName;
    public $lastName;

    public $customLabel;
    public $underscore_style;

    protected $protectedProperty;
    private $_privateProperty;

    public static $formName = 'Speaker';

    public function formName(): string
    {
        return static::$formName;
    }

    public function attributeLabels(): array
    {
        return [
            'customLabel' => 'This is the custom label',
        ];
    }

    public function rules(): array
    {
        return [];
    }

    public function scenarios(): array
    {
        return [
            'test' => ['firstName', 'lastName', '!underscore_style'],
            'duplicates' => ['firstName', 'firstName', '!underscore_style', '!underscore_style'],
        ];
    }
}
