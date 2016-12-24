<?php

namespace mjm\filemanager\models;

use Yii;
use yii\web\UploadedFile;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\imagine\Image;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\helpers\Inflector;
use mjm\filemanager\Module;
use mjm\filemanager\models\Owners;
use Imagine\Image\ImageInterface;

/**
 *
 */
class MediafileSearch extends Mediafile
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['tagIds'], 'safe'],
        ];
    }

    /**
     * Creates data provider instance with search query applied
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params, $type)
    {
        $query = self::find()->orderBy('created_at DESC');
        
        if($type) {
        	$query->filterWhere(['like', 'type', $type]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->tagIds) {
            $query->joinWith('tags')->andFilterWhere(['in', Tag::tableName() . '.id', $this->tagIds]);
        }

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     * @param array $params
     * @param int $userId
     * @return ActiveDataProvider
     */
    public function searchByUser($params, $userId, $type)
    {
        $query = self::find()->orderBy('created_at DESC');
        
        if($type) {
        	$query->filterWhere(['like', 'type', $type]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

    		$query->joinWith('owners')->andWhere([Owners::tableName() . '.owner_id' => $userId, Owners::tableName() . '.owner' => 'user']);

        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->tagIds) {
            $query->joinWith('tags')->andFilterWhere(['in', Tag::tableName() . '.id', $this->tagIds]);
        }

        return $dataProvider;
    }
}
