<?php

namespace Uuu9\Signature\Middleware;

use App\Exceptions\UnauthorizedHttpException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Closure;
use Uuu9\Signature\Services\SignService;
use Illuminate\Support\Facades\Log;

/**
 * Api鉴权
 */
class Authenticate
{
    
    const ERR = 851;

    /**
     * api鉴权处理
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws \App\Exceptions\UnauthorizedHttpException
     * @throws \LogicException
     */
    public function handle(Request $request, Closure $next)
    {
        $key  = strtolower($request->header('Accept-ApiKey'));
        //获取签名配置
        $apiAuthConfig = config('api_sign')['available_keys'];
        // 开发环境如果有 debug 参数, 则校验参数, 方便开发调试
        if (App::environment('develop') && $request->header('debug') === null) {
            $request->offsetSet('vp-request-client',  $key ? $apiAuthConfig[$key]['type'] : 100);
            return $next($request);
        }

        $signature = strtolower($request->header('Accept-ApiSign'));
        $apiTime = $request->header('Accept-ApiTime');
        $uri = $request->path();


        if (!$key || !$signature || empty($apiAuthConfig[$key])) {
            Log::info('鉴权失败! 签名参数不合法或缺失');
            throw new UnauthorizedHttpException(ecode(self::ERR, 0));
        }

        if (strtolower($request->method()) === 'get') {
            $params = $request->all();

            $orgSignArr = SignService::signature($params, $uri, $apiAuthConfig[$key]['secret'], $apiTime);
            $orgSignature = $orgSignArr['signature'];
            $signatureV2 = $orgSignature;
        } else {
            $params = json_decode($request->getContent());
            $paramsV2 = $request->getContent();

            $orgSignArr = SignService::signature($params, $uri, $apiAuthConfig[$key]['secret'], $apiTime);
            $orgSignature = $orgSignArr['signature'];

            $signArrV2 = SignService::signatureV2($paramsV2, $uri, $apiAuthConfig[$key]['secret'], $apiTime);
            $signatureV2 = $signArrV2['signature'];

            //安卓仍存在错误的请求方式, v4.0.0 版本中已经做彻底修改, 新版均使用 v2 版本签名
            //**但是** 发现前端也有错误使用请求的地方, 刚好被安卓兼容代码匹配, 导致之前没发现异常, 所以继续保持兼容
            $androidParams = $request->all();
        }

        $request->offsetSet('vp-request-client', $apiAuthConfig[$key]['type']);

        //为了记录具体错误, 逐个判断
        if ($orgSignature === $signature) {
            //旧版签名匹配
            return $next($request);
        }

        if ($signatureV2 === $signature) {
            //新版签名匹配
            return $next($request);
        }

        if (isset($androidParams)) {
            $androidSignArr = SignService::signature($androidParams, $uri, $apiAuthConfig[$key]['secret'], $apiTime);
            $androidSignature = $androidSignArr['signature'];
            if ($androidSignature === $signature) {
                //兼容代码
                //判断 安卓 或者 H5
                $appVersion = $this->getAppVersion($request);
                if ($appVersion['device_type'] === 'android') {
                    Log::info('安卓端 错误兼容代码仍在使用 URI: ' . $uri . ' 版本: ' . $appVersion['versions']);
                } else {
                    Log::info('其他端 错误兼容代码仍在使用 URI: ' . $uri);
                }
                return $next($request);
            }
        }

        //鉴权失败日志
        Log::info('鉴权失败! 客户端key: ' . $key . ' 客户端sign: ' . $signature);
        Log::info('服务端签名: sign: ' . $orgSignArr['signature'] . ' 原始字符串: ' . $orgSignArr['raw_string']);
        if (isset($signArrV2)) {
            Log::info('服务端V2版本签名: sign: ' . $signatureV2 . ' 原始字符串' . $signArrV2['raw_string']);
        }

        //都不匹配, 未通过
        throw new UnauthorizedHttpException(ecode(self::ERR, 0));
    }

    protected function getAppVersion($request)
    {
        $appVersion = strtolower($request->header('app-versions', ''));
        if (strpos($appVersion, 'android') !== false) {
            $device_type = 'android';
            $versions = str_replace('android', '', strtolower($appVersion));
        } else {
            $device_type = 'ios';
            $versions = str_replace('ios', '', strtolower($appVersion));
        }
        return ['device_type' => $device_type, 'versions' => $versions];
    }
}
