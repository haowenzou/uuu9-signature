<?php

namespace Uuu9\Signature\Services;

use App\Exceptions\NotImplementedHttpException;

class GatewayService extends ApiService
{

    public static function getConfig()
    {
        $config = config('api_sign');

        $key = $config['client_keys']['gateway_config']['key'];
        $secret = $config['available_keys'][$key]['secret'];
        $endpoint = $config['client_keys']['gateway_config']['endpoint'];

        return [
            'endpoint' => $endpoint,
            'key' => $key,
            'secret' => $secret
        ];
    }

    public static function __callStatic($name, $arguments)
    {
        preg_match('/(rollingGet|rollingPost|rollingPostBinary|get|post|postBinary|put|patch|delete|head|connect|options|trace)V(\d)/', $name, $matches);
        $method = $matches[1] ?? null;
        $version = $matches[2] ?? null;

        $config = self::getConfig();

        array_unshift($arguments, $method);
        array_unshift($arguments, $version);
        array_unshift($arguments, $config);

        if ($method && $version) {
            //rolling curl
            if (\in_array($method, ['rollingGet', 'rollingPost'], true)) {
                $method = strtolower(trim($method, 'rolling'));
                $arguments[2] = $method;
                return call_user_func_array(['self', 'rollingCurl'], $arguments);
            }

            if ($method === 'postBinary') {
                $arguments[2] = 'post';
                return call_user_func_array(['self', 'binaryCurl'], $arguments);
            }

            if ($method === 'rollingPostBinary') {
                $arguments[2] = 'post';
                return call_user_func_array(['self', 'rollingBinaryCurl'], $arguments);
            }

            return call_user_func_array(['self', 'request'], $arguments);
        } else {
            throw new NotImplementedHttpException('Api SDK ERROR: http method not exist');
        }
    }
}
