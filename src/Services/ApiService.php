<?php

namespace Vpgame\Signature\Services;

use App\Exceptions\InternalServerErrorException;
use App\Exceptions\NotImplementedHttpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Vpgame\Signature\Services\RollingCurl\RollingCurl;
use Vpgame\Signature\Services\RollingCurl\RollingCurlRequest;

class ApiService
{

    /**
     * curl 方法
     *
     * @param $url
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws \Exception
     * @throws NotImplementedHttpException
     */
    public static function curl($url, $method = 'get', $params = [], $headers = [])
    {
        try {
            $ch = curl_init();

            $raw = json_encode($params);

            if ($method === 'get') {
                if ($params) {
                    $query = http_build_query($params);
                    $url .= '?' . $query;
                }
            } elseif ($method === 'post') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
                $headers['Content-Length'] = strlen($raw);
            } elseif ($method === 'put') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
                $headers['Content-Length'] = strlen($raw);
            } elseif ($method === 'delete') {
                $headers['Content-Length'] = strlen($raw);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            } else {
                throw new NotImplementedHttpException('Api SDK ERROR: http method not implemented');
            }

            $headersArray = [];
            foreach ($headers as $k => $v) {
                $headersArray[] = sprintf('%s: %s', $k, $v);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersArray);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);

            //var_dump('CURL 请求异常! URL: '.$url.' 请求参数: '.$raw);
            //var_dump('头信息: ',$headersArray);
            //var_dump('响应: ',$response);
            $err_no = curl_errno($ch);
            if ($err_no) {
                //curl 异常, 记录日志
                $error = curl_error($ch);
                Log::error('CURL 请求异常! URL: '.$url.' 请求参数: '.$raw.' 错误码: '.$err_no.' 错误内容: '.$error);
            }

            $return = [];
            $return['statusCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 503;
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $return['rawHeader'] = substr($response, 0, $headerSize);
            $return['rawBody'] = substr($response, $headerSize);
            $return['body'] = json_decode($return['rawBody'], true) ?: [];

            curl_close($ch);
            return $return;
        } catch (\Exception $e) {
            $return = [];
            $return['statusCode'] = 503;
            return $return;
        }
    }

    /**
     * 其他请求方法
     *
     * @param $interface
     * @param string $version
     * @param string $method
     * @param array $params
     * @param string $bearerToken
     * @return array
     * @throws \Exception
     */
    public static function request($config, $version, $method, $interface, $params = [], $bearerToken = '',$headerArr=[])
    {
        $url = $config['endpoint'].$interface;
        $contentType= sprintf('application/vnd.vpgame.v%s+json', $version);
        $UserAgent  = sprintf('Client/Passport Version/%s', $version);
        $apiTime    = time();

        //设置API语言
        $lang = 'cn';
        if (stripos(app('translator')->getLocale(), 'en') !== false) {
            $lang = 'en';
        } elseif (stripos(app('translator')->getLocale(), 'ru') !== false) {
            $lang = 'ru';
        }

        $headers = [];
        $headers['Accept-Charset'] = 'utf-8';
        $headers['Accept-TimeZone'] = 'Asia/shanghai';
        $headers['Accept'] = $contentType;
        $headers['Content-Type'] = $contentType;
        $headers['Accept-ApiTime'] = $apiTime;
        $headers['Accept-Language'] = $lang;
        $headers['Accept-UserAgent'] = $UserAgent;

        $pathInfo = parse_url($url);
        $uri = trim($pathInfo['path'], '/');
        $headers['Accept-ApiKey'] = $config['key'];
        $sign = SignService::signatureV2Mix($method, $params, $uri, $config['secret'], $apiTime);
        $headers['Accept-ApiSign'] = $sign['signature'];

        //bearer token
        $bearerToken = $bearerToken ?: app('request')->bearerToken();
        if (!empty($bearerToken)) {
            $authorization = sprintf('Bearer %s', str_replace('Bearer ', '', $bearerToken));
            $headers['Authorization'] = $authorization;
        }

        //传递跟踪 ID
        if (defined("X_REQUEST_ID")) {
            $headers['X-Request-Id'] = X_REQUEST_ID;
        }
        

        //支持Api log
        if (class_exists(Cache::class)){
            $penetrateContext = Cache::store('array')->get('penetrateContext');
            if(is_array($penetrateContext)){
                $headers = array_merge($headers, $penetrateContext);
            }
        }

        if(false === empty($headerArr)){
            $headers = array_merge($headers, $headerArr);
        }

        $response = self::curl($url, $method, $params, $headers);

        $data = [
            'statusCode' => $response['statusCode'],
            'data'       => $response['body'],
            'raw'        => $response['rawBody']
        ];

        return $data;
    }

    public static function getConfig()
    {
        $config = config('api_sign');

        $key = $config['client_keys']['api_config']['key'];
        $secret = $config['available_keys'][$key]['secret'];
        $endpoint = $config['client_keys']['api_config']['endpoint'];

        return [
            'endpoint' => $endpoint,
            'key' => $key,
            'secret' => $secret
        ];
    }

    /**
     * 批量并发请求
     *
     * @param $config
     * @param $version
     * @param $method
     * @param array $muitl [ [$interface, $params = [], $bearerToken = '',$headerArr=[]] ]
     * @param $windowSize
     * @param $returnKeyField
     * @return array
     */
    public static function rollingCurl($config, $version, $method, array $muitl, $windowSize = 20, $returnKeyField = null)
    {
        $data = [];
        if ($muitl) {
            $rc = new RollingCurl(function ($response, $info, $request) use(&$data, $returnKeyField) {
                //解析响应
                $return = [];
                $return['statusCode'] = $info['http_code'] ?: 503;
                $headerSize = $info['header_size'];
                $return['rawHeader'] = substr($response, 0, $headerSize);
                $return['rawBody'] = substr($response, $headerSize);
                $return['body'] = json_decode($return['rawBody'], true) ?: [];

                $return['info'] = $info;
                $return['request'] = $request;

                if ($returnKeyField !== null) {
                    if (isset($request->post_data[$returnKeyField])
                        && $request->post_data[$returnKeyField]
                    ) {
                        $data[$request->post_data[$returnKeyField]] = $return;
                    } else {
                        $tempPathInfo = parse_url($request->url);
                        parse_str($tempPathInfo['query'] ?? '', $queryArr);
                        if (isset($queryArr[$returnKeyField]) && $queryArr[$returnKeyField]) {
                            $data[$queryArr[$returnKeyField]] = $return;
                        } else {
                            $data[] = $return;
                        }
                    }
                } else {
                    $data[] = $return;
                }
            });

            //设置并发数
            $rc->setWindowSize($windowSize);

            //公共参数
            $contentType= sprintf('application/vnd.vpgame.v%s+json', $version);
            $UserAgent  = sprintf('Client/PHP/Version/%s', $version);
            $apiTime    = time();

            $lang = 'cn';
            if (stripos(app('translator')->getLocale(), 'en') !== false) {
                $lang = 'en';
            } elseif (stripos(app('translator')->getLocale(), 'ru') !== false) {
                $lang = 'ru';
            }

            $headers = [];
            $headers['Accept-Charset'] = 'utf-8';
            $headers['Accept-TimeZone'] = 'Asia/shanghai';
            $headers['Accept'] = $contentType;
            $headers['Content-Type'] = $contentType;
            $headers['Accept-ApiTime'] = $apiTime;
            $headers['Accept-Language'] = $lang;
            $headers['Accept-UserAgent'] = $UserAgent;

            //传递跟踪 ID
            if (defined("X_REQUEST_ID")) {
                $headers['X-Request-Id'] = X_REQUEST_ID;
            }

            //支持Api log
            if (class_exists(Cache::class)){
                $penetrateContext = Cache::store('array')->get('penetrateContext');
                if(is_array($penetrateContext)){
                    $headers = array_merge($headers, $penetrateContext);
                }
            }

            $requestBearerToken = app('request')->bearerToken();

            foreach ($muitl as $key => $value) {
                if (!isset($value[0])) {
                    throw new InternalServerErrorException(999900);
                }
                $subHeaders = $headers;

                $subUrl =  $config['endpoint'].$value[0];
                $subParams = $value[1] ?? [];
                $subBearerToken = $value[2] ?? '';
                $subHeaderArr = $value[3] ?? [];

                //bearer token
                $bearerToken = $subBearerToken ?: $requestBearerToken;
                if (!empty($bearerToken)) {
                    $authorization = sprintf('Bearer %s', str_replace('Bearer ', '', $bearerToken));
                    $subHeaders['Authorization'] = $authorization;
                }

                if(false === empty($subHeaderArr)){
                    $subHeaders = array_merge($subHeaders, $subHeaderArr);
                }

                $pathInfo = parse_url($subUrl);
                $uri = trim($pathInfo['path'], '/');
                $subHeaders['Accept-ApiKey'] = $config['key'];
                $sign = SignService::signatureV2Mix($method, $subParams, $uri, $config['secret'], $apiTime);
                $subHeaders['Accept-ApiSign'] = $sign['signature'];

                $subHeadersArray = [];
                foreach ($subHeaders as $k => $v) {
                    $subHeadersArray[] = sprintf('%s: %s', $k, $v);
                }

                $request = new RollingCurlRequest(
                    $subUrl,
                    strtoupper($method),
                    $subParams,
                    $subHeadersArray
                );
                $rc->add($request);
            }
            $rc->execute();
        }

        return $data;
    }

    /**
     * curl 方法 发送二进制
     *
     * @param $url
     * @param byte $body
     * @param array $headers
     * @return array
     * @throws \Exception
     * @throws NotImplementedHttpException
     */
    public static function binaryCurl($config, $version, $method, $interface, $body = null, $headerArr = [])
    {
        try {
            $ch = curl_init();

            $url    = $config['endpoint'] . $interface;

            $lang   = 'cn';
            if (stripos(app('translator')->getLocale(), 'en') !== false) {
                $lang = 'en';
            } elseif (stripos(app('translator')->getLocale(), 'ru') !== false) {
                $lang = 'ru';
            }

            $accept         = sprintf('application/vnd.vpgame.v%s+json', $version);
            $contentType    = 'application/octet-stream';
            $UserAgent      = sprintf('Client/PHP/Version/%s', $version);
            $apiTime        = time();

            $headers        = [];

            $headers['Content-Type']        = $contentType;
            $headers['Accept-Charset']      = 'utf-8';
            $headers['Accept-TimeZone']     = 'Asia/shanghai';
            $headers['Accept']              = $accept;
            $headers['Accept-ApiTime']      = $apiTime;
            $headers['Accept-Language']     = $lang;
            $headers['Accept-UserAgent']    = $UserAgent;

            $pathInfo = parse_url($url);
            $uri = trim($pathInfo['path'], '/');
            $headers['Accept-ApiKey'] = $config['key'];
            $sign = SignService::signatureV2Mix($method, [], $uri, $config['secret'], $apiTime);
            $headers['Accept-ApiSign'] = $sign['signature'];

            //传递跟踪 ID
            if (defined("X_REQUEST_ID")) {
                $headers['X-Request-Id'] = X_REQUEST_ID;
            }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $headers['Content-Length'] = strlen($body);

            $headersArray = [];
            foreach ($headers as $k => $v) {
                $headersArray[] = sprintf('%s: %s', $k, $v);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersArray);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);

            //var_dump('CURL 请求异常! URL: '.$url.' 请求参数: '.$body);
            //var_dump('头信息: ',$headersArray);
            //var_dump('响应: ',$response);
            $err_no = curl_errno($ch);
            if ($err_no) {
                //curl 异常, 记录日志
                $error = curl_error($ch);
                Log::error('CURL 请求异常! URL: '.$url.' 请求参数: '.$body.' 错误码: '.$err_no.' 错误内容: '.$error);
            }

            $return                 = [];
            $return['statusCode']   = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 503;
            $headerSize             = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $return['rawHeader']    = substr($response, 0, $headerSize);
            $return['rawBody']      = substr($response, $headerSize);
            $return['body']         = json_decode($return['rawBody'], true) ?: [];

            curl_close($ch);
            return $return;
        } catch (\Exception $e) {
            $return                 = [];
            $return['statusCode']   = 503;
            return $return;
        }
    }

    /**
     * 批量并发请求
     *
     * @param $config
     * @param $version
     * @param $method
     * @param array $muitl [ [$interface, $params = [], $bearerToken = '',$headerArr=[]] ]
     * @param $windowSize
     * @param $returnKeyField
     * @return array
     */
    public static function rollingBinaryCurl($config, $version, $method, array $muitl, $windowSize = 20, $returnKeyField = null)
    {
        $data = [];
        if ($muitl) {
            $rc = new RollingCurl(function ($response, $info, $request) use(&$data, $returnKeyField) {
                //解析响应
                $return = [];
                $return['statusCode'] = $info['http_code'] ?: 503;
                $headerSize = $info['header_size'];
                $return['rawHeader'] = substr($response, 0, $headerSize);
                $return['rawBody'] = substr($response, $headerSize);
                $return['body'] = json_decode($return['rawBody'], true) ?: [];

                $return['info'] = $info;
                $return['request'] = $request;

                if ($returnKeyField !== null) {
                    if (isset($request->post_data[$returnKeyField])
                        && $request->post_data[$returnKeyField]
                    ) {
                        $data[$request->post_data[$returnKeyField]] = $return;
                    } else {
                        $tempPathInfo = parse_url($request->url);
                        parse_str($tempPathInfo['query'] ?? '', $queryArr);
                        if (isset($queryArr[$returnKeyField]) && $queryArr[$returnKeyField]) {
                            $data[$queryArr[$returnKeyField]] = $return;
                        } else {
                            $data[] = $return;
                        }
                    }
                } else {
                    $data[] = $return;
                }
            });

            //设置并发数
            $rc->setWindowSize($windowSize);

            //公共参数
            $accept         = sprintf('application/vnd.vpgame.v%s+json', $version);
            $contentType    = 'application/octet-stream';
            $UserAgent      = sprintf('Client/PHP/Version/%s', $version);
            $apiTime        = time();

            $lang = 'cn';
            if (stripos(app('translator')->getLocale(), 'en') !== false) {
                $lang = 'en';
            } elseif (stripos(app('translator')->getLocale(), 'ru') !== false) {
                $lang = 'ru';
            }

            $headers                        = [];
            $headers['Content-Type']        = $contentType;
            $headers['Accept-Charset']      = 'utf-8';
            $headers['Accept-TimeZone']     = 'Asia/shanghai';
            $headers['Accept']              = $accept;
            $headers['Accept-ApiTime']      = $apiTime;
            $headers['Accept-Language']     = $lang;
            $headers['Accept-UserAgent']    = $UserAgent;
            $headers['Expect']              = '';

            //传递跟踪 ID
            if (defined("X_REQUEST_ID")) {
                $headers['X-Request-Id'] = X_REQUEST_ID;
            }

            //支持Api log
            if (class_exists(Cache::class)){
                $penetrateContext = Cache::store('array')->get('penetrateContext');
                if(is_array($penetrateContext)){
                    $headers = array_merge($headers, $penetrateContext);
                }
            }

            $requestBearerToken = app('request')->bearerToken();

            foreach ($muitl as $key => $value) {
                if (!isset($value[0])) {
                    throw new InternalServerErrorException(999900);
                }
                $subHeaders = $headers;

                $subUrl =  $config['endpoint'].$value[0];
                $subParams = $value[1] ?? [];
                $subBearerToken = $value[2] ?? '';
                $subHeaderArr = $value[3] ?? [];

                //bearer token
                $bearerToken = $subBearerToken ?: $requestBearerToken;
                if (!empty($bearerToken)) {
                    $authorization = sprintf('Bearer %s', str_replace('Bearer ', '', $bearerToken));
                    $subHeaders['Authorization'] = $authorization;
                }

                if(false === empty($subHeaderArr)){
                    $subHeaders = array_merge($subHeaders, $subHeaderArr);
                }

                $pathInfo = parse_url($subUrl);
                $uri = trim($pathInfo['path'], '/');
                $subHeaders['Accept-ApiKey'] = $config['key'];
                $sign = SignService::signatureV2Mix($method, [], $uri, $config['secret'], $apiTime);
                $subHeaders['Accept-ApiSign'] = $sign['signature'];

                $subHeadersArray = [];
                foreach ($subHeaders as $k => $v) {
                    $subHeadersArray[] = sprintf('%s: %s', $k, $v);
                }

                $request = new RollingCurlRequest(
                    $subUrl,
                    strtoupper($method),
                    $subParams,
                    $subHeadersArray
                );
                $rc->add($request);
            }
            $rc->execute();
        }

        return $data;
    }

    /**
     * 使用方法:
     * ServiceApi::postV1('/signup/check', ['account' => '13800138000']);
     * ServiceApi::getV1(...);
     * ServiceApi::getV2(...);
     * ServiceApi::putV3(...);
     *
     *      并发
     *      $request = [
     *          ['/sso'],
     *          ['/market/list', ['page_size' => 10, 'current_page' => 1]],
     *          ['/common/specials', ['hello' => 'world']],
     *          ];
     *      $r = ApiService::rollingGetV1($request, 10);
     *      print_r($r);
     *
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws NotImplementedHttpException
     */
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
