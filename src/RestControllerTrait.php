<?php

declare(strict_types=1);

namespace Ziphp\Recipes;

use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;
use yii\db\ActiveQueryInterface;

trait RestControllerTrait
{
    use StatelessControllerTrait;

    protected function verbs(): array
    {
        $verbs = parent::verbs();

        foreach ($verbs as $action => $methods) {
            if (is_array($methods) && !in_array('OPTIONS', $methods, true)) {
                $verbs[$action][] = 'OPTIONS';
            }
        }

        return $verbs;
    }

    public function actions(): array
    {
        $actions = parent::actions();

        /** @see IndexAction::$dataFilter */
        $actions['index']['dataFilter'] = $this->dataFilterCallback();

        /** @see IndexAction::$prepareDataProvider */
        $actions['index']['prepareDataProvider'] = $this->prepareDataProviderCallback();

        return $actions;
    }

    protected function dataFilterCallback(): ?array
    {
        return null;
    }

    protected function prepareDataProviderCallback(): ?callable
    {
        return null;
    }

    public function checkAccess($action, $model = null, $params = []): void
    {
        parent::checkAccess($action, $model, $params);
    }

    protected function makeActiveDataProviderConfig(ActiveQueryInterface $query, array $querySort = [], array $pageConfig = []): ActiveDataProvider
    {
        if (empty($querySort)) {
            $querySort = ['id' => SORT_DESC];
        }

        /** @var ActiveDataProvider $providerConfig */
        $providerConfig = Yii::createObject([
            'class' => ActiveDataProvider::class,
            'query' => $query,
            'pagination' => [
                'class' => Pagination::class,
                'params' => Yii::$app->getRequest()->getQueryParams(),
                'pageParam' => $pageConfig['pageParam'] ?? '_page',
                'pageSizeParam' => $pageConfig['pageSizeParam'] ?? '_perPage',
                'pageSizeLimit' => $pageConfig['pageSizeLimit'] ?? [1, 100],
            ],
            'sort' => [
                'class' => Sort::class,
                'params' => Yii::$app->getRequest()->getQueryParams(),
                'sortParam' => '_sort',
                'enableMultiSort' => true,
                'defaultOrder' => $querySort,
            ],
        ]);

        return $providerConfig;
    }
}
