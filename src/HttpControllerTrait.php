<?php

declare(strict_types=1);

namespace Ziphp\Recipes;

use yii\base\InvalidConfigException;
use yii\base\Model;
use Yii;
use yii\filters\AccessRule;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\helpers\ArrayHelper;

trait HttpControllerTrait
{
    public array $behaviorRules = [];

    protected ?Model $validator = null;

    private function resolveBehaviorRules(string $module, string $controller, string $action): array
    {
        $rules = [];
        foreach ($this->behaviorRules as $key => $value) {
            if (is_int($key)) {
                if (!ArrayHelper::isAssociative($value, true)) {
                    throw new InvalidConfigException("value of $key must be associative");
                }
                $rule = $value;
            } else if (is_string($key)) {
                $rule = [
                    'class' => AccessRule::class,
                    'controllers' => ["$module/$controller"],
                    'actions' => [$key],
                    'allow' => true,
                    'roles' => (array)$value,
                ];
            } else {
                throw new InvalidConfigException("value of $key must be int or string");
            }

            $rules[] = $rule;
        }

        // If the access permission corresponding to the current action is not listed,
        // an exception is thrown (except for Options requests)
        $_is_listed = false;
        foreach ($rules as $_rule) {
            foreach ($_rule['controllers'] as $rule_controller) {
                foreach ($_rule['actions'] as $rule_action) {
                    if ("$rule_controller/$rule_action" === "$module/$controller/$action") {
                        $_is_listed = true;
                        break 3;
                    }
                }
            }
        }

        if (!$_is_listed && !Yii::$app->getRequest()->getIsOptions()) {
            throw new InvalidConfigException("Current action [$action] does not listed in behavior rules");
        }

        // The access permissions array cannot be empty
        foreach ($rules as $_rule) {
            if (empty($_rule['roles'])) {
                throw new InvalidConfigException("The access permissions array cannot be empty");
            }
        }

        return $rules;
    }

    private function resolveAuthorizationMethods(): array
    {
        $bearerAuthorization = $this->extractAuthorizationTokenFromHeader();
        if ($bearerAuthorization !== null) {
            if (method_exists($this, 'beforeAuthorizationMethodResolved')) {
                return $this->beforeAuthorizationMethodResolved(HttpBearerAuth::class, $bearerAuthorization);
            }

            return [['class' => HttpBearerAuth::class]];
        }

        $queryAccessToken = $this->extractAuthorizationTokenFromQuery();
        if ($queryAccessToken !== null) {
            if (method_exists($this, 'beforeAuthorizationMethodResolved')) {
                return $this->beforeAuthorizationMethodResolved(QueryParamAuth::class, $queryAccessToken);
            }

            return [['class' => QueryParamAuth::class, 'tokenParam' => 'AccessToken']];
        }

        return [];
    }

    private function extractAuthorizationTokenFromHeader(): ?string
    {
        $authorization = Yii::$app->getRequest()->getHeaders()->get('Authorization');

        if (!pf_is_string_filled($authorization)) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.*?)$/', $authorization, $matches)) {
            if (is_string($matches[1] ?? null)) {
                $token = trim($matches[1]);
                if (!empty($token)) {
                    return $token;
                }
            }
        }

        return null;
    }

    private function extractAuthorizationTokenFromQuery(): ?string
    {
        $queryAccessToken = Yii::$app->getRequest()->get('AccessToken');

        if (!pf_is_string_filled($queryAccessToken)) {
            return null;
        }

        $queryAccessToken = trim($queryAccessToken);
        if (!empty($queryAccessToken)) {
            return $queryAccessToken;
        }

        return null;
    }
}
