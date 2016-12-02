<?php

namespace mjm\filemanager\controllers;

use Yii;
use yii\web\Controller;

class DefaultController extends Controller
{
  public function actionIndex()
  {
    if($this->module->useUserOwner AND Yii::$app->user->id != 1) {
      throw new \yii\web\ForbiddenHttpException()
    }

    return $this->render('index');
  }

  public function actionSettings()
  {
    if($this->module->useUserOwner AND Yii::$app->user->id != 1) {
      throw new \yii\web\ForbiddenHttpException()
    }

    return $this->render('settings');
  }
}
