<?php

namespace TheShop\Src\Controllers;

use App\Helpers\Configuration;
use App\Http\Controllers\Controller;

class ConfigurationController extends Controller
{
    /**
     * Get Configuration from sharedSettings
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfiguration()
    {
        $allSettings = Configuration::getConfiguration();

        if ($allSettings === false) {
            $this->jsonError(['Empty settings list.'], 404);
        }

        return $this->jsonSuccess($allSettings);
    }
}
