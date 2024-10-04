<?php

declare(strict_types=1);

namespace Ziphp\Recipes;

use Yii;
use yii\base\Action;
use yii\base\Model;
use yii\base\Module;
use yii\base\UnknownClassException;
use yii\filters\AccessControl;
use yii\filters\Cors;
use yii\web\Response;
use function Symfony\Component\String\u;

/**
 * @property-read string $id the ID of this controller.
 * @property-read Module $module the module that this controller belongs to.
 * @property-read Action|null $action the action that is currently being executed. This property will be set
 * by [[run()]] when it is called by [[Application]] to run an action.
 */
trait StatelessControllerTrait
{
    use HttpControllerTrait;

    protected function prepareValidator(): void
    {
        $php_url_path = parse_url(Yii::$app->getRequest()->getAbsoluteUrl(), PHP_URL_PATH);
        $php_url_part = explode('/', $php_url_path);

        if (isset($php_url_part[3]) && !isset($this->actions()[$php_url_part[3]])) {
            $validatorClass = "\\Zpp\\Modules\\"
                . u($this->module->id)->camel()->title()->toString()
                . "\\Models\\"
                . u($this->id)->camel()->title()->toString() . 'Validator';

            if (!class_exists($validatorClass)) {
                throw new UnknownClassException("Class $validatorClass does not exist");
            }

            /** @var Model $validator */
            $validator = Yii::createObject($validatorClass);

            $this->validator = $validator;

            $this->validator->setScenario(u($php_url_part[3])->camel()->title()->toString());
            $this->validator->setAttributes(Yii::$app->getRequest()->get());

            if (
                Yii::$app->getRequest()->getIsPost()
                || Yii::$app->getRequest()->getIsPut()
                || Yii::$app->getRequest()->getIsPatch()
            ) {
                $this->validator->setAttributes(Yii::$app->getRequest()->post());
            }
        }
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['cors'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => [$_SERVER['HTTP_ORIGIN'] ?? '*'],
                'Access-Control-Request-Method' => ['GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'DELETE', 'PATCH'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => ['*'],
            ],
        ];

        // make sure put `authenticator` behavior after `cors` behavior
        // see issue: https://github.com/yiisoft/yii2/issues/14754
        if (isset($behaviors['authenticator'])) {
            $_behaviorCopy = $behaviors['authenticator'];
            unset($behaviors['authenticator']);
            $behaviors['authenticator'] = $_behaviorCopy;
        }

        $behaviors['authenticator']['authMethods'] = $this->resolveAuthorizationMethods();
        $behaviors['authenticator']['except'] = ['options'];

        $behaviors['contentNegotiator']['formats'] = [
            'application/json' => Response::FORMAT_JSON,
            'text/json' => Response::FORMAT_JSON,
            'application/json;charset=utf-8' => Response::FORMAT_JSON,
            'application/json; charset=utf-8' => Response::FORMAT_JSON,
            'application/json;charset=UTF-8' => Response::FORMAT_JSON,
            'application/json; charset=UTF-8' => Response::FORMAT_JSON,
        ];

        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => $this->resolveBehaviorRules($this->module->id, $this->id, $this->action->id),
        ];

        return $behaviors;
    }

    // Note: beforeAction()先后两次执行，behaviors()中间执行
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // for TableControllerTrait checkAccess()
        if (method_exists($this, 'checkAccess')) {
            if (!isset($this->actions()[$action->id])) {
                $this->checkAccess($action->id);
            }
        }

        $this->prepareValidator();
        if (($this->validator instanceof Model) && (!$this->validator->validate())) {
            Yii::$app->response->data = ['err' => $this->validator->getErrors()];
            return false;
        }

        return true;
    }
}
