<?php

namespace App\Http\Controllers;

use App\Events\GenericModelArchive;
use App\Events\GenericModelCreate;
use App\Events\GenericModelDelete;
use App\Events\GenericModelUpdate;
use App\GenericModel;
use Illuminate\Http\Request;

/**
 * Class GenericResourceController
 * @package App\Http\Controllers
 */
class GenericResourceController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Http\JsonResponse|static[]
     */
    public function index(Request $request)
    {
        //if request route is archive, set archived collection for query
        $this->checkArchivedCollection($request);

        $query = GenericModel::query();

        //default query params values
        $orderBy = '_id';
        $orderDirection = 'desc';
        $offset = 0;
        $limit = 100;

        $errors = [];

        // Validate query params based on request params
        if (!empty($request->all())) {
            $allParams = $request->all();
            $skipParams = [
                'orderBy',
                'orderDirection',
                'offset',
                'limit',
                'looseSearch'
            ];

            $operator = '=';
            if ($request->has('looseSearch')) {
                $operator = 'like';
            }

            foreach ($allParams as $key => $value) {
                if (in_array($key, $skipParams)) {
                    continue;
                }

                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    if ($request->has('looseSearch')) {
                        $value = '%' . $value . '%';
                    }

                    if ($value === 'false') {
                        $value = false;
                    } elseif ($value === 'true') {
                        $value = true;
                    } elseif ($value === 'null') {
                        $value = null;
                    }

                    $query->where($key, $operator, $value);
                }
            }
        }

        if ($request->has('orderBy')) {
            $orderBy = $request->get('orderBy');
        }

        if ($request->has('orderDirection')) {
            if (strtolower(substr($request->get('orderDirection'), 0, 3)) === 'asc' ||
                strtolower(substr($request->get('orderDirection'), 0, 4)) === 'desc'
            ) {
                $orderDirection = $request->get('orderDirection');
            } else {
                $errors[] = 'Invalid orderDirection input.';
            }
        }

        if ($request->has('offset')) {
            if (ctype_digit($request->get('offset')) && $request->get('offset') >= 0) {
                $offset = (int)$request->get('offset');
            } else {
                $errors[] = 'Invalid offset input.';
            }
        }

        if ($request->has('limit')) {
            if (ctype_digit($request->get('limit')) && $request->get('limit') >= 0) {
                $limit = (int)$request->get('limit');
            } else {
                $errors[] = 'Invalid limit input.';
            }
        }

        if (count($errors) > 0) {
            return $this->jsonError($errors, 400);
        }

        return $query->orderBy($orderBy, $orderDirection)
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $model = GenericModel::find($request->route('id'));

        if (!$model instanceof GenericModel) {
            return $this->jsonError(['Model not found.'], 404);
        }

        $fields = $request->all();
        if ($this->validateInputsForResource($fields, $request->route('resource'), $model) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        return $model;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|static
     */
    public function store(Request $request)
    {
        $fields = $request->all();
        if ($this->validateInputsForResource($fields, $request->route('resource')) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        $model = new GenericModel($fields);

        event(new GenericModelCreate($model));

        if ($model->save()) {
            return $model;
        }
        return $this->jsonError('Issue with saving resource.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $model = GenericModel::find($request->route('id'));

        if (!$model instanceof GenericModel) {
            return $this->jsonError(['Model not found.'], 404);
        }

        $updateFields = $request->all();

        if ($this->validateInputsForResource($updateFields, $request->route('resource'), $model) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        $model->fill($updateFields);

        event(new GenericModelUpdate($model));

        if ($model->save()) {
            return $model;
        }

        return $this->jsonError('Issue with updating resource.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $model = GenericModel::find($request->route('id'));

        if (!$model instanceof GenericModel) {
            return $this->jsonError(['Model not found.'], 404);
        }

        $fields = $request->all();
        if ($this->validateInputsForResource($fields, $request->route('resource'), $model) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        $deletedModel = $model->delete();
        if ($deletedModel) {
            event(new GenericModelDelete($deletedModel));
            return $deletedModel;
        }

        return $this->jsonError('Issue with deleting resource.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request)
    {
        $modelCollection = GenericModel::getCollection();

        GenericModel::setCollection($modelCollection . '_deleted');
        $model = GenericModel::find($request->route('id'));

        if (!$model instanceof GenericModel) {
            return $this->jsonError(['Model not found.'], 404);
        }

        $fields = $request->all();
        if ($this->validateInputsForResource($fields, $request->route('resource'), $model) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        $restoredModel = $model->restore();
        if ($restoredModel) {
            event(new GenericModelDelete($restoredModel));
            return $restoredModel;
        }

        return $this->jsonError('Issue with restoring resource.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Http\JsonResponse
     */
    public function archive(Request $request)
    {
        $model = GenericModel::find($request->route('id'));

        if (!$model instanceof GenericModel) {
            return $this->jsonError(['Model not found.'], 404);
        }

        $fields = $request->all();
        if ($this->validateInputsForResource($fields, $request->route('resource'), $model) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        $archivedModel = $model->archive();
        if ($archivedModel) {
            event(new GenericModelArchive($archivedModel));
            return $archivedModel;
        }

        return $this->jsonError('Issue with archiving resource.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unArchive(Request $request)
    {
        $modelCollection = GenericModel::getCollection();

        GenericModel::setCollection($modelCollection . '_archived');
        $model = GenericModel::find($request->route('id'));

        if (!$model instanceof GenericModel) {
            return $this->jsonError(['Model not found.'], 404);
        }

        $fields = $request->all();
        if ($this->validateInputsForResource($fields, $request->route('resource'), $model) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        $unArchivedModel = $model->unArchive();
        if ($unArchivedModel) {
            event(new GenericModelArchive($unArchivedModel));
            return $unArchivedModel;
        }

        return $this->jsonError('Issue with unarchiving resource.');
    }

    /**
     * @param Request $request
     * @return bool|Request
     */
    private function checkArchivedCollection(Request $request)
    {
        $uri = $request->path();

        if (strpos($uri, '/archive')) {
            GenericModel::setCollection($request->route('resource') . '_archived');

            return $request;
        }

        return false;
    }
}
