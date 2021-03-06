<?php

namespace api\modules\v1\models;

use api\common\models\User;
use api\modules\v1\models\Venue;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

class Lesson extends ActiveRecord
{

    public static function tableName()
    {
        return 'lesson';
    }

    public function fields() {
        $fields = parent::fields();
        unset($fields['created_at'], $fields['updated_at']);
        return $fields;
    }

    public function extraFields() {
        $fields = parent::fields();
        return array_merge($fields, ['venue']);
    }

    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function getVenue() {
        return self::hasOne(Venue::className(), ['id' => 'venue_id']);
    }
}
