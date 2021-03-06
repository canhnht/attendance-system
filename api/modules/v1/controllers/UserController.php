<?php

namespace api\modules\v1\controllers;

use api\common\controllers\CustomActiveController;
use api\common\helpers\TokenHelper;
use api\common\models\UserToken;
use api\common\models\User;
use api\common\components\AccessRule;
use api\common\components\Facepp;

use Yii;
use api\common\models\SignupModel;
use api\common\models\SignupStudentModel;
use api\common\models\SignupLecturerModel;
use api\common\models\LoginModel;
use api\common\models\LecturerLoginModel;
use api\common\models\StudentLoginModel;
use api\common\models\ChangePasswordModel;
use api\common\models\PasswordResetModel;
use api\common\models\RegisterDeviceModel;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\rest\ActiveController;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

class UserController extends CustomActiveController
{
    public $uploadPath = '/upload/';
    public $modelClass = '';

    const CODE_INCORRECT_USERNAME = 0;
    const CODE_INCORRECT_PASSWORD = 1;
    const CODE_INCORRECT_DEVICE = 2;
    const CODE_UNVERIFIED_EMAIL = 3;
    const CODE_UNVERIFIED_DEVICE = 4;
    const CODE_UNVERIFIED_EMAIL_DEVICE = 5;
    const CODE_INVALID_ACCOUNT = 6;
    const CODE_DUPLICATE_DEVICE = 7;
    const CODE_INVALID_PASSWORD = 8;
    
    public function behaviors() {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::className(),
            'except' => ['login', 'signup', 'confirm-email', 'register-device',
                'signup-student', 'signup-lecturer', 'reset-password', 'lecturer-login', 'student-login'],
        ];

        $behaviors['access'] = [
            'class' => AccessControl::className(),
            'ruleConfig' => [
                'class' => AccessRule::className(),
            ],
            'rules' => [
                [   
                    'actions' => ['login', 'signup', 'confirm-email', 'register-device',
                        'signup-student', 'signup-lecturer', 'reset-password', 'lecturer-login', 'student-login'],
                    'allow' => true,
                    'roles' => ['?'],
                ],
                [
                    'actions' => ['logout', 'person-id', 'face-id', 'set-person-id', 'set-face-id',
                        'change-password', 'train-face'],
                    'allow' => true,
                    'roles' => ['@'],
                ]
            ],
            'denyCallback' => function ($rule, $action) {
                throw new UnauthorizedHttpException('You are not authorized');
            },
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::className(),
            'actions' => [
                'login' => ['post'],
                'signup' => ['post'],
                'logout' => ['get'],
                'register-device' => ['post'],
            ],
        ];

        return $behaviors;
    }

    public function actionLogin() {
    	$request = Yii::$app->request;
    	$bodyParams = $request->bodyParams;
        $username = $bodyParams['username'];
        $password = $bodyParams['password'];
        $device_hash = $bodyParams['device_hash'];
        if (!$device_hash) throw new BadRequestHttpException('Invalid data');

    	$model = new LoginModel();
    	$model->username = $username;
    	$model->password = $password;
        $model->device_hash = $device_hash;
    	if ($user = $model->login()) {
            if ($user->status == User::STATUS_WAIT_EMAIL_DEVICE)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_EMAIL_DEVICE);
            if ($user->status == User::STATUS_WAIT_EMAIL)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_EMAIL);
            if ($user->status == User::STATUS_WAIT_DEVICE)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_DEVICE);
            if ($user->status == User::STATUS_ACTIVE) {
                UserToken::deleteAll(['user_id' => $user->id, 'action' => TokenHelper::TOKEN_ACTION_ACCESS]);
                $token = TokenHelper::createUserToken($user->id);
                return [
                    'token' => $token->token,
                ];
            } else throw new BadRequestHttpException(null, self::CODE_INVALID_ACCOUNT);
    	} else {
            if (isset($model->errors['username']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_USERNAME);
            if (isset($model->errors['password']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_PASSWORD);
            if (isset($model->errors['device_hash']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_DEVICE);
        }
        throw new BadRequestHttpException('Invalid data');
    }

    public function actionLecturerLogin() {
        $request = Yii::$app->request;
        $bodyParams = $request->bodyParams;
        $username = $bodyParams['username'];
        $password = $bodyParams['password'];

        $model = new LecturerLoginModel();
        $model->username = $username;
        $model->password = $password;
        if ($user = $model->login()) {
            if ($user->status == User::STATUS_WAIT_EMAIL_DEVICE)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_EMAIL_DEVICE);
            if ($user->status == User::STATUS_WAIT_EMAIL)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_EMAIL);
            if ($user->role != User::ROLE_LECTURER)
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_USERNAME);
            if ($user->status == User::STATUS_ACTIVE) {
                UserToken::deleteAll(['user_id' => $user->id, 'action' => TokenHelper::TOKEN_ACTION_ACCESS]);
                $token = TokenHelper::createUserToken($user->id);
                return [
                    'token' => $token->token,
                ];
            } else throw new BadRequestHttpException(null, self::CODE_INVALID_ACCOUNT);
        } else {
            if (isset($model->errors['username']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_USERNAME);
            if (isset($model->errors['password']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_PASSWORD);
        }
        throw new BadRequestHttpException('Invalid data');
    }

    public function actionStudentLogin() {
        $request = Yii::$app->request;
        $bodyParams = $request->bodyParams;
        $username = $bodyParams['username'];
        $password = $bodyParams['password'];
        $device_hash = $bodyParams['device_hash'];
        if (!$device_hash) throw new BadRequestHttpException('Invalid data');

        $model = new StudentLoginModel();
        $model->username = $username;
        $model->password = $password;
        $model->device_hash = $device_hash;
        if ($user = $model->login()) {
            if ($user->status == User::STATUS_WAIT_EMAIL_DEVICE)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_EMAIL_DEVICE);
            if ($user->status == User::STATUS_WAIT_EMAIL)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_EMAIL);
            if ($user->role != User::ROLE_STUDENT)
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_USERNAME);
            if ($user->status == User::STATUS_WAIT_DEVICE)
                throw new BadRequestHttpException(null, self::CODE_UNVERIFIED_DEVICE);
            if ($user->status == User::STATUS_ACTIVE) {
                UserToken::deleteAll(['user_id' => $user->id, 'action' => TokenHelper::TOKEN_ACTION_ACCESS]);
                $token = TokenHelper::createUserToken($user->id);
                return [
                    'token' => $token->token,
                ];
            } else throw new BadRequestHttpException(null, self::CODE_INVALID_ACCOUNT);
        } else {
            if (isset($model->errors['username']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_USERNAME);
            if (isset($model->errors['password']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_PASSWORD);
            if (isset($model->errors['device_hash']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_DEVICE);
        }
        throw new BadRequestHttpException('Invalid data');
    }

    public function actionSignup() {
    	$bodyParams = Yii::$app->request->bodyParams;

    	$model = new SignupModel();
    	$model->username = $bodyParams['username'];
    	$model->email = $bodyParams['email'];
    	$model->password = $bodyParams['password'];
        $model->role = isset($bodyParams['role']) ? $bodyParams['role'] : User::ROLE_STUDENT;
        $model->device_hash = $bodyParams['device_hash'];
		if ($user = $model->signup()) {
			$token = TokenHelper::createUserToken($user->id);
			return [
                'token' => $token->token,
            ];
		}
        throw new BadRequestHttpException('Invalid data');
    }

    public function actionSignupStudent() {
        $bodyParams = Yii::$app->request->bodyParams;

        $model = new SignupStudentModel();
        $model->username = $bodyParams['username'];
        $model->email = $bodyParams['email'];
        $model->password = $bodyParams['password'];
        $model->role = isset($bodyParams['role']) ? $bodyParams['role'] : User::ROLE_STUDENT;
        $model->device_hash = $bodyParams['device_hash'];
        if ($user = $model->signup()) {
            $token = TokenHelper::createUserToken($user->id);
            return [
                'token' => $token->token,
            ];
        }
        throw new BadRequestHttpException('Invalid data');   
    }

    public function actionSignupLecturer() {
        $bodyParams = Yii::$app->request->bodyParams;

        $model = new SignupLecturerModel();
        $model->username = $bodyParams['username'];
        $model->email = $bodyParams['email'];
        $model->password = $bodyParams['password'];
        $model->role = isset($bodyParams['role']) ? $bodyParams['role'] : User::ROLE_LECTURER;
        
        if ($user = $model->signup()) {
            $token = TokenHelper::createUserToken($user->id);
            return [
                'token' => $token->token,
            ];
        }
        throw new BadRequestHttpException('Invalid data');   
    }

    public function actionLogout() {
    	$id = Yii::$app->user->identity->id;
    	UserToken::deleteAll(['user_id' => $id, 'action' => TokenHelper::TOKEN_ACTION_ACCESS]);
		return 'logout successfully';
    }

    public function actionChangePassword() {
        $user = Yii::$app->user->identity;
        $bodyParams = Yii::$app->request->bodyParams;

        $model = new ChangePasswordModel();
        $model->user = $user;
        $model->oldPassword = $bodyParams['oldPassword'];
        $model->newPassword = $bodyParams['newPassword'];
        if ($model->changePassword())
            return 'change password successfully';
        else {
            if (isset($model->errors['oldPassword']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_PASSWORD);
            if (isset($model->errors['newPassword']))
                throw new BadRequestHttpException(null, self::CODE_INVALID_PASSWORD);
        }
        throw new BadRequestHttpException('Invalid data');
    }

    public function actionResetPassword() {
        $bodyParams = Yii::$app->request->bodyParams;
        $email = $bodyParams['email'];

        $model = new PasswordResetModel();
        $model->email = $email;
        if ($model->sendEmail()) {
            return 'reset password successfully';
        }
        throw new BadRequestHttpException('Invalid data');
    }

    public function actionConfirmEmail($token = null) {
        if (empty($token) || !is_string($token)) {
            return $this->redirect(Yii::$app->params['WEB_BASEURL'].'site/confirmation-error');
        }
        $userId = TokenHelper::authenticateToken($token, true, TokenHelper::TOKEN_ACTION_ACTIVATE_ACCOUNT);
        $user = User::findOne([
            'id' => $userId, 
            'status' => [User::STATUS_WAIT_EMAIL_DEVICE, User::STATUS_WAIT_EMAIL],
        ]);
        if (!$user)
            return $this->redirect(Yii::$app->params['WEB_BASEURL'].'site/confirmation-error');

        if ($user->status == User::STATUS_WAIT_EMAIL_DEVICE)
            $user->status = User::STATUS_WAIT_DEVICE;
        else if ($user->status == User::STATUS_WAIT_EMAIL)
            $user->status = User::STATUS_ACTIVE;
        
        UserToken::removeEmailConfirmToken($user->id);
        if ($user->save()) {
            return $this->redirect(Yii::$app->params['WEB_BASEURL'].'site/confirmation-success');
        }
        return $this->redirect(Yii::$app->params['WEB_BASEURL'].'site/confirmation-error');
    }

    public function actionRegisterDevice() {
        $request = Yii::$app->request;
        $bodyParams = $request->bodyParams;
        $username = $bodyParams['username'];
        $password = $bodyParams['password'];
        $device_hash = $bodyParams['device_hash'];

        $model = new RegisterDeviceModel();
        $model->username = $username;
        $model->password = $password;
        $model->device_hash = $device_hash;
        if ($user = $model->registerDevice()) {
            if ($user->status == User::STATUS_BLOCKED || $user->status == User::STATUS_DELETED)
                throw new BadRequestHttpException(null, self::CODE_INVALID_ACCOUNT);
            else {
                $user->device_hash = $device_hash;
                if ($user->status == User::STATUS_WAIT_EMAIL)
                    $user->status = User::STATUS_WAIT_EMAIL_DEVICE;
                else if ($user->status == User::STATUS_ACTIVE)
                    $user->status = User::STATUS_WAIT_DEVICE;
                if ($user->save()) {
                    UserToken::deleteAll(['user_id' => $user->id, 'action' => TokenHelper::TOKEN_ACTION_ACCESS]);
                    return $user;
                }
            }
        } else {
            if (isset($model->errors['username']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_USERNAME);
            if (isset($model->errors['password']))
                throw new BadRequestHttpException(null, self::CODE_INCORRECT_PASSWORD);
            if (isset($model->errors['device_hash']))
                throw new BadRequestHttpException(null, self::CODE_DUPLICATE_DEVICE);
        }
    }

    public function actionPersonId() {
        $userId = Yii::$app->user->identity->id;
        $query = Yii::$app->db->createCommand('
            select id as user_id,
                   person_id 
             from user 
             where id = :user_id
        ')
        ->bindValue(':user_id', $userId);
        return $query->queryOne();
    }

    public function actionFaceId() {
        $userId = Yii::$app->user->identity->id;
        $query = Yii::$app->db->createCommand('
            select id as user_id,
                   face_id 
             from user 
             where id = :user_id
        ')
        ->bindValue(':user_id', $userId);
        $result = $query->queryOne();
        if ($result['face_id'])
            $result['face_id'] = json_decode($result['face_id']);
        return $result;
    }

    public function actionSetPersonId() {
        $userId = Yii::$app->user->identity->id;
        $request = Yii::$app->request;
        $bodyParams = $request->bodyParams;
        $person_id = $bodyParams;
        $query = Yii::$app->db->createCommand('
            update user 
             set person_id = :person_id 
             where id = :user_id
        ')
        ->bindValue(':person_id', $person_id)
        ->bindValue(':user_id', $userId);
        return [
            'result' => $query->execute(),
        ];
    }

    public function actionSetFaceId() {
        $userId = Yii::$app->user->identity->id;
        $request = Yii::$app->request;
        $bodyParams = $request->bodyParams;
        $face_id = json_encode($bodyParams);
        $query = Yii::$app->db->createCommand('
            update user 
             set face_id = :face_id 
             where id = :user_id
        ')
        ->bindValue(':face_id', $face_id)
        ->bindValue(':user_id', $userId);
        return [
            'result' => $query->execute(),
        ];
    }

    public function actionTrainFace() {
        $bodyParams = $request->bodyParams;
        $newFaceId = $bodyParams;

        $facepp = new Facepp();
        $facepp->api_key = Yii::$app->params['FACEPP_API_KEY'];
        $facepp->api_secret = Yii::$app->params['FACEPP_API_SECRET'];

        $user = Yii::$app->user->identity;
        $personId = $user->person_id;
        $listFaceId = $user->face_id;
        if ($listFaceId) $listFaceId = json_decode($listFaceId);
        else $listFaceId = [];
        if (count($listFaceId) == 5) {
            $params['person_id'] = $personId;
            $params['face_id'] = $listFaceId[0];
            $response = $facepp->execute('/person/remove_face', $params);
            // $result = json_decode($response['body']);
            array_splice($listFaceId, 0, 1);
        }
        $listFaceId[] = $newFaceId;
        $params['person_id'] = $personId;
        $params['face_id'] = $newFaceId;
        $response = $facepp->execute('/person/add_face', $params);
        // $result = json_decode($response['body']);

        $paramsVerify['person_id'] = $personId;
        $response = $facepp->execute('/train/verify', $paramsVerify);
        $result = json_decode($response['body']);
        return $result;
    }

    // public function afterAction($action, $result)
    // {
    //     $result = parent::afterAction($action, $result);
    //     // your custom code here
    //     return [
    //         'status' => '200',
    //         'data' => $result,
    //     ];
    // }
}