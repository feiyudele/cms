<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-03-15 21:16
 */

namespace common\models;

use common\libs\Constants;
use Yii;
use common\helpers\FileDependencyHelper;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * This is the model class for table "{{%options}}".
 *
 * @property integer $id
 * @property integer $type
 * @property string $name
 * @property string $value
 * @property integer $input_type
 * @property string $tips
 * @property integer $autoload
 * @property integer $sort
 */
class Options extends \yii\db\ActiveRecord
{

    const TYPE_SYSTEM = 0;
    const TYPE_CUSTOM = 1;
    const TYPE_BANNER = 2;

    const CUNSTOM_AUTOLOAD_NO = 0;
    const CUSTOM_AUTOLOAD_YES = 1;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%options}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'input_type', 'autoload', 'sort'], 'integer'],
            [['name', 'input_type', 'autoload'], 'required'],
            [['name'], 'unique'],
            [
                ['name'],
                'match',
                'pattern' => '/^[a-zA-Z][0-9_]*/',
                'message' => yii::t('app', 'Must begin with alphabet and can only includes alphabet,_,and number')
            ],
            [['value'], 'string'],
            [['name', 'tips'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'type' => Yii::t('app', 'Type'),
            'name' => Yii::t('app', 'Name'),
            'value' => Yii::t('app', 'Value'),
            'input_type' => Yii::t('app', 'Input Type'),
            'tips' => Yii::t('app', 'Tips'),
            'autoload' => Yii::t('app', 'Autoload'),
            'sort' => Yii::t('app', 'Sort'),
        ];
    }

    /**
     * @return array
     */
    public function getNames()
    {
        return array_keys($this->attributeLabels());
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        $object = yii::createObject([
            'class' => FileDependencyHelper::className(),
            'fileName' => 'options.txt',
        ]);
        $object->updateFile();
        parent::afterSave($insert, $changedAttributes);
    }

    public function beforeSave($insert)
    {
        if(!$insert){
            if( $this->input_type == Constants::INPUT_IMG ) {
                $temp = explode('\\', self::className());
                $modelName = end( $temp );
                $key = "{$modelName}[{$this->id}][value]";
                $upload = UploadedFile::getInstanceByName($key);
                $old = Options::findOne($this->id);
                if($upload !== null){
                    $uploadPath = yii::getAlias('@admin/uploads/custom-setting/');
                    if (! FileHelper::createDirectory($uploadPath)) {
                        $this->addError($key, "Create directory failed " . $uploadPath);
                        return false;
                    }
                    $fullName = $uploadPath . uniqid() . '_' . $upload->baseName . '.' . $upload->extension;
                    if (! $upload->saveAs($fullName)) {
                        $this->addError($key, yii::t('app', 'Upload {attribute} error: ' . $upload->error, ['attribute' => yii::t('app', 'Picture')]) . ': ' . $fullName);
                        return false;
                    }
                    $this->value = str_replace(yii::getAlias('@frontend/web'), '', $fullName);
                    if( $old !== null ){
                        $file = yii::getAlias('@frontend/web') . $old->value;
                        if( file_exists($file) && is_file($file) ) unlink($file);
                    }
                }else{
                    if( $this->value !== '' ){
                        $file = yii::getAlias('@frontend/web') . $old->value;
                        if( file_exists($file) && is_file($file) ) unlink($file);
                        $this->value = '';
                    }else {
                        $this->value = $old->value;
                    }
                }
            }
        }
        return true;
    }

    public static function getBannersByType($name)
    {
        $model = Options::findOne(['type'=>self::TYPE_BANNER, 'name'=>$name]);
        if( $model == null ) throw new NotFoundHttpException("None banner type named $name");
        $banners = json_decode($model->value, true);
        ArrayHelper::multisort($banners, 'sort');
        foreach ($banners as $k => $banner){
            if( $banner['status'] == Constants::Status_Desable ) unset($banners[$k]);
        }
        return $banners;
    }

}
