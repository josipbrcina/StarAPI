<?php

namespace App\Http\Controllers;

use App\Events\GenericModelArchive;
use App\Events\GenericModelCreate;
use App\Events\GenericModelDelete;
use App\Events\GenericModelUpdate;
use App\GenericModel;
use App\Profile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Helpers\InputHandler;

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
        // If request route is archive, set archived collection for query
        $this->checkArchivedCollection($request);

        $query = GenericModel::query();

        // Default query params values
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

            // Set operator like if request has looseSearch
            $operator = '=';
            if ($request->has('looseSearch')) {
                $operator = 'like';
            }

            foreach ($allParams as $key => $value) {
                if (in_array($key, $skipParams)) {
                    continue;
                }

                // Check if value has "range" delimiter and set query
                if (!is_array($value) && strpos($value, '>=<')) {
                    $values = explode('>=<', $value);
                    $trimmedValues = array_map('trim', $values);
                    $query->where(
                        $key,
                        '>=',
                        ctype_digit($trimmedValues[0]) ? (int) $trimmedValues[0] : $trimmedValues[0]
                    );
                    $query->where(
                        $key,
                        '<=',
                        ctype_digit($trimmedValues[1]) ? (int) $trimmedValues[1] : $trimmedValues[1]
                    );

                    if (count($trimmedValues) > 2) {
                        $errors[] = 'Range search must be between two values.';
                    }
                    continue;
                }

                // Check if value is array
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

        // Check if request has orderBy, orderDirection, offset or limit field and set query
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

    /**
     * Create new record for profile vacations
     * @param Request $request
     * @return GenericModel|\Illuminate\Http\JsonResponse
     */
    public function vacation(Request $request)
    {
        if (GenericModel::getCollection() !== 'vacations') {
            return $this->jsonError(['Wrong collection.'], 403);
        }

        $model = GenericModel::find($request->route('id'));
        if ($model) {
            return $this->jsonError(['Method not allowed. Model already exists.'], 403);
        }

        $profile = Profile::find($request->route('id'));
        if (!$profile) {
            return $this->jsonError(['Profile ID not found.'], 404);
        }

        $requestFields = $request->all();
        if (empty($requestFields)) {
            $requestFields = [];
        }

        // Validate request fields
        $allowedFields = [
            'dateFrom',
            'dateTo'
        ];

        $validateFields = array_diff_key($requestFields, array_flip($allowedFields));

        if (!empty($validateFields)
            || !key_exists('dateFrom', $requestFields)
            || !key_exists('dateTo', $requestFields)
        ) {
            return $this->jsonError('Invalid input. Request must have two fields - dateFrom and dateTo');
        }

        $errors = [];

        if (is_array($requestFields['dateFrom'])) {
            $errors[] = 'Invalid input format. dateFrom field must not be type of array.';
        }

        if (is_array($requestFields['dateTo'])) {
            $errors[] = 'Invalid input format. dateTo field must not be type of array';
        }

        if (count($errors) > 0) {
            return $this->jsonError($errors);
        }

        // Validate dateFrom field timestamp format
        try {
            InputHandler::getUnixTimestamp($requestFields['dateFrom']);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage() . ' on dateFrom field.');
        }

        // Validate dateTo field timestamp format
        try {
            InputHandler::getUnixTimestamp($requestFields['dateTo']);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage() . ' on dateTo field.');
        }

        $fields = [
            'records' => [
                [
                'dateFrom' => $requestFields['dateFrom'],
                'dateTo' => $requestFields['dateTo'],
                'recordTimestamp' => (int) Carbon::now()->format('U')
                ]
            ]
        ];

        if ($this->validateInputsForResource($fields, $request->route('resource')) === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        // Set model id same as profile id
        $model = new GenericModel($fields);
        $model->_id = $profile->_id;

        if ($model->save()) {
            return $model;
        }

        return $this->jsonError('Issue with saving resource.');
    }
}
