<?php

namespace mjm\filemanager\controllers;

use mjm\filemanager\models\MediafileSearch;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use mjm\filemanager\Module;
use mjm\filemanager\models\Mediafile;
use mjm\filemanager\assets\FilemanagerAsset;
use yii\helpers\Url;
use mjm\filemanager\models\Owners;

class FileController extends Controller
{
  public $enableCsrfValidation = false;

  public function behaviors()
  {
    return [
      'access' => [
        'class' => \yii\filters\AccessControl::className(),
        'only' => ['index', 'filemanager', 'uploadmanager', 'upload', 'update', 'delete','resize','info'],
        'rules' => [
          [
            'actions' => ['index', 'filemanager', 'uploadmanager', 'upload', 'update', 'delete','resize','info'],
            'allow' => true,
            'roles' => ['@'],
          ],
        ],
      ],
      'verbs' => [
        'class' => VerbFilter::className(),
        'actions' => [
          'delete' => ['post'],
          'update' => ['post'],
        ],
      ],
    ];
  }

  public function beforeAction($action)
  {
    if (defined('YII_DEBUG') && YII_DEBUG) {
      Yii::$app->assetManager->forceCopy = true;
    }

    return parent::beforeAction($action);
  }

  public function actionIndex()
  {
    return $this->render('index');
  }

  public function actionFilemanager()
  {
    $this->layout = '@vendor/mjm/yii2-filemanager/views/layouts/main';
    $model = new MediafileSearch();

    if($this->module->useUserOwner) {
      $userId = Yii::$app->user->id;
      $dataProvider = $model->searchByUser(Yii::$app->request->queryParams, $userId, Yii::$app->request->get('type'));
    }
    else {
      $dataProvider = $model->search(Yii::$app->request->queryParams, Yii::$app->request->get('type'));
    }

    $dataProvider->pagination->defaultPageSize = 15;

    return $this->render('filemanager', [
      'model' => $model,
      'dataProvider' => $dataProvider,
    ]);
  }

  public function actionUploadmanager()
  {
    $this->layout = '@vendor/mjm/yii2-filemanager/views/layouts/main';
    return $this->render('uploadmanager', ['model' => new Mediafile()]);
  }

  /**
  * Provides upload file
  * @return mixed
  */
  public function actionUpload()
  {
    Yii::$app->response->format = Response::FORMAT_JSON;

    $model = new Mediafile();
    $routes = $this->module->routes;
    $rename = $this->module->rename;
    $tagIds = Yii::$app->request->post('tagIds');

    if ($tagIds !== 'undefined') {
      $model->setTagIds(explode(',', $tagIds));
    }

    if($this->module->useUserOwner) {
      $userId = Yii::$app->user->id;
      $model->saveUploadedFile($routes, $rename, $userId);
    }
    else {
      $model->saveUploadedFile($routes, $rename);
    }

    $bundle = FilemanagerAsset::register($this->view);

    if ($model->isImage()) {
      $model->createThumbs($routes, $this->module->thumbs);
    }

    if($this->module->useUserOwner) {
      $userId = Yii::$app->user->id;
      $model->addOwner($userId, 'user', '.');
    }

    $response['files'][] = [
      'url'           => Url::base().$model->url,
      'thumbnailUrl'  => $model->getDefaultThumbUrl($bundle->baseUrl),
      'name'          => $model->filename,
      'type'          => $model->type,
      'size'          => $model->file->size,
      'deleteUrl'     => Url::to(['file/delete', 'id' => $model->id]),
      'deleteType'    => 'POST',
    ];

    return $response;
  }

  /**
  * Updated mediafile by id
  * @param $id
  * @return array
  */
  public function actionUpdate($id)
  {
    if($this->module->useUserOwner) {
      $query = Mediafile::find();
      $query->andWhere([Mediafile::tableName() . '.id' => $id]);
      $query->joinWith('owners')->andWhere([Owners::tableName() . '.owner_id' => Yii::$app->user->id, Owners::tableName() . '.owner' => 'user']);
      $model = $query->one();
    }
    else {
      $model = Mediafile::findOne($id);
    }

    $message = Module::t('main', 'Changes not saved.');

    if ($model->load(Yii::$app->request->post()) && $model->save()) {
      $message = Module::t('main', 'Changes saved!');
    }

    Yii::$app->session->setFlash('mediafileUpdateResult', $message);

    Yii::$app->assetManager->bundles = false;
    return $this->renderAjax('info', [
      'model' => $model,
      'strictThumb' => null,
    ]);
  }

  /**
  * Delete model with files
  * @param $id
  * @return array
  */
  public function actionDelete($id)
  {
    Yii::$app->response->format = Response::FORMAT_JSON;
    $routes = $this->module->routes;

    if($this->module->useUserOwner) {
      $query = Mediafile::find();
      $query->andWhere([Mediafile::tableName() . '.id' => $id]);
      $query->joinWith('owners')->andWhere([Owners::tableName() . '.owner_id' => Yii::$app->user->id, Owners::tableName() . '.owner' => 'user']);
      $model = $query->one();
    }
    else {
      $model = Mediafile::findOne($id);
    }

    if ($model->isImage()) {
      $model->deleteThumbs($routes);
    }

    $model->deleteFile($routes);
    $model->delete();

    return ['success' => 'true'];
  }

  /**
  * Resize all thumbnails
  */
  public function actionResize()
  {
    if($this->module->useUserOwner) {
      $models = Mediafile::findByTypesAndUser(Mediafile::$imageFileTypes, Yii::$app->user->id);
    }
    else {
      $models = Mediafile::findByTypes(Mediafile::$imageFileTypes);
    }

    $routes = $this->module->routes;
    foreach ($models as $model) {
      if ($model->isImage()) {
        $model->deleteThumbs($routes);
        $model->createThumbs($routes, $this->module->thumbs);
      }
    }

    Yii::$app->session->setFlash('successResize');
    $this->redirect(Url::to(['default/settings']));
  }

  /** Render model info
  * @param int $id
  * @param string $strictThumb only this thumb will be selected
  * @return string
  */
  public function actionInfo($id, $strictThumb = null)
  {
    if($this->module->useUserOwner) {
      $query = Mediafile::find();
      $query->andWhere([Mediafile::tableName() . '.id' => $id]);
      $query->joinWith('owners')->andWhere([Owners::tableName() . '.owner_id' => Yii::$app->user->id, Owners::tableName() . '.owner' => 'user']);
      $model = $query->one();
    }
    else {
      $model = Mediafile::findOne($id);
    }

    Yii::$app->assetManager->bundles = false;
    return $this->renderAjax('info', [
      'model' => $model,
      'strictThumb' => $strictThumb,
    ]);
  }
}
