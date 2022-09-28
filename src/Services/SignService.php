<?php

namespace U9\Signature\Services;

class SignService
{

    /**
     * V2版本参数签名混合
     *
     * @param $method
     * @param $params
     * @param $uri
     * @param $private
     * @param $apiTime
     * @return array|string
     */
    public static function signatureV2Mix($method, $params, $uri, $private, $apiTime)
    {
        if (strtolower($method) === 'get') {
            return self::signature($params, $uri, $private, $apiTime);
        } else {
            return self::signatureV2(json_encode($params), $uri, $private, $apiTime);
        }
    }

    /**
     * V2版本参数签名生成
     *
     * @param string $params
     * @param $uri
     * @param $private
     * @param $apiTime
     * @return array
     */
    public static function signatureV2(string $params, $uri, $private, $apiTime)
    {
        $signature = $params.$uri.$private.$apiTime;
        return [
            'signature' => md5($signature),
            'raw_string' => $signature
        ];
    }

    /**
     * 参数签名
     *
     * @param $params
     * @param $uri
     * @param $private
     * @param $apiTime
     * @return string
     */
    public static function signature($params, $uri, $private, $apiTime)
    {
        $signature = '';
        if (!empty($params)) {
            $params = self::dimensionalityReduction($params);

            ksort($params);
            foreach ($params as $k => $v) {
                $signature .= $v;
            }
        }

        $signature .= $uri.$private.$apiTime;

        return [
            'signature' => md5($signature),
            'raw_string' => $signature
        ];
    }

    /**
     * 将参数进行降维
     *
     * @param $params
     * @return array
     */
    private static function dimensionalityReduction($params)
    {
        $array = [];
        foreach ($params as $k => $v) {
            if (is_object($v) || is_array($v)) {
                $array[$k] = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } elseif ($v === true) {
                $array[$k] = 'true';
            } elseif ($v === false) {
                $array[$k] = 'false';
            } else {
                $array[$k] = $v;
            }
        }

        return $array;
    }
}
