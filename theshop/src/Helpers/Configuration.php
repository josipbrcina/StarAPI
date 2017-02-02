<?php

namespace TheShop\Src\Helpers;

use App\Http\Controllers\Controller;

class Configuration extends Controller
{

    public static function getConfiguration($email = null)
    {
        $internalSettings = \Config::get('sharedSettings.internalConfiguration', []);
        $internalDynamicSettings = \Config::get('sharedSettings.internalDynamicConfiguration', []);
        $externalSettings = \Config::get('sharedSettings.externalConfiguration', []);
        $allSettings = [];
        $allSettings['internal'] = $internalSettings;

        if (empty($internalSettings) && empty($externalSettings)) {
            return false;
        }

        foreach ($internalDynamicSettings as $settingName => $settings) {
            foreach ($settings as $setting) {
                if (!key_exists('resolver', $setting)) {
                    continue;
                }
                try {
                    $resolved = call_user_func([$setting['resolver']['class'], $setting['resolver']['method']]);
                } catch (\Exception $e) {
                    continue;
                }
                $allSettings['internal'][$setting['settingName']] = $resolved;
            }
        }

        foreach ($externalSettings as $name => $configs) {
            foreach ($configs as $config) {
                if (!key_exists('resolver', $config)) {
                    continue;
                }
                try {
                    $value = call_user_func([$config['resolver']['class'], $config['resolver']['method']]);
                } catch (\Exception $e) {
                    continue;
                }
                $allSettings[$name][$config['settingName']] = $value;
            }
        }

        // return slack company fields for email upon new user registration
        if ($email !== null) {
            $response = [];
            $response['teamName'] = $allSettings['slack']['teamInfo']->team->name;
            $response['teamDomain'] = $allSettings['slack']['teamInfo']->team->domain;
            $response['emailDomain'] = $allSettings['slack']['teamInfo']->team->email_domain;

            return $response;
        }

        return $allSettings;
    }
}
