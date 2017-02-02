<?php

namespace TheShop\Src\Controllers;

use App\Validation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Class ValidationController
 * @package App\Http\Controllers
 */
class ValidationController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $models = Validation::all();
        return $this->jsonSuccess($models);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $model = Validation::create();

        $model->fields = json_decode($request->get('fields'), true);
        $model->resource = $request->get('resource');

        $model->save();

        return $this->jsonSuccess($model);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $model = Validation::find($request->route('id'));
        if (!$model) {
            return $this->jsonError('Model not found.', 404);
        }
        return $this->jsonSuccess($model);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $model = Validation::find($request->route('id'));

        if (!$model) {
            return $this->jsonError('Model not found.', 404);
        }

        $model->fields = json_decode($request->get('fields'), true);
        $model->resource = $request->get('resource');

        $model->save();

        return $this->jsonSuccess($model);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $model = Validation::find($request->route('id'));

        if (!$model) {
            return $this->jsonError('Model not found.', 404);
        }

        $model->delete();
        return $this->jsonSuccess(['id' => $model->id]);
    }
}
