<?php

namespace App\Http\Middleware;

use App\GenericModel;
use Closure;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class OutputAdapters
 * @package App\Http\Middleware
 */
class OutputAdapters
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // If already JSON just return it, this should be removed after Controller::jsonError is replaced with
        // exception handlers
        if (!$response instanceof Response) {
            return $response;
        }

        $responseBody = $response->getOriginalContent();

        $out = [];
        if ($responseBody instanceof \Traversable) {
            foreach ($responseBody as $item) {
                $adapterConfig = $this->getAdapterConfig(GenericModel::getCollection());
                $out[] = $this->processWithAdapter($adapterConfig, $item);
            }
        } else {
            $adapterConfig = $this->getAdapterConfig(GenericModel::getCollection());
            $out = $this->processWithAdapter($adapterConfig, $responseBody);
        }

        return response()->json($out, 200);
    }

    /**
     * @param GenericModel|null $adapterConfig
     * @param GenericModel $model
     * @return GenericModel|mixed
     */
    private function processWithAdapter(GenericModel $adapterConfig = null, GenericModel $model)
    {
        if ($adapterConfig) {
            $adapterModel = new $adapterConfig->resolver['class']($model);
            return call_user_func([$adapterModel, $adapterConfig->resolver['method']]);
        }

        return $model;
    }

    /**
     * @param $resource
     * @return mixed
     */
    private function getAdapterConfig($resource)
    {
        $preSetCollection = GenericModel::getCollection();
        $out = GenericModel::whereTo('adapter-rules')
            ->where('resource', '=', $resource)
            ->first();
        GenericModel::setCollection($preSetCollection);

        return $out;
    }
}
