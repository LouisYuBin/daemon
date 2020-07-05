<?php
/**
 * Project ArrowWorker
 * User:   louis
 * Date:   18-1-1
 */

declare(strict_types=1);


namespace ArrowWorker\Web\Request;

use ArrowWorker\Library\Context;
use ArrowWorker\Log\Log;
use Swoole\Http\Request as SwRequest;
use Swoole\Http\Response as SwResponse;
use ArrowWorker\Web\Upload;
use ArrowWorker\Web\RequestInterface;


/**
 * Class Request
 * @package ArrowWorker
 */
class Request implements RequestInterface
{

    const LOG_NAME = 'Http';

    private $class;

    private SwRequest $swRequest;

    public function __construct(SwRequest $request)
    {
        $this->class = __CLASS__;
        $this->swRequest = $request;
        $this->initUrlPostParams($request);
    }


    private function initUrlPostParams(SwRequest $request) :void
    {
        if (!is_null($request->post)) {
            return;
        }

        $raw = $request->rawContent();
        if (empty($raw)) {
            return;
        }

        $postDataArray = json_decode($raw, true);
        if (null===$postDataArray) {
            // normal x-www-form-urlencoded data
            parse_str($raw, $postParam);
            $request->post = $postParam;
        } else {
            $request->post = $postDataArray;
        }
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->swRequest->server['request_method'];
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->swRequest->server['request_uri'];
    }

    /**
     * @return string
     */
    public function getRaw(): string
    {
        return $this->swRequest->rawContent();
    }

    /**
     * @return string
     */
    public function getRouteType(): string
    {
        return Context::Get('routerType') ?? '';
    }

    /**
     * @return string
     */
    public function getQueryString(): string
    {
        return $this->swRequest->server['query_string'];
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->swRequest->header['user-agent'];
    }


    /**
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->swRequest->server['remote_addr'];
    }

    /**
     * @param string $key
     * @param string $default
     * @return string|bool
     */
    public function Get(string $key, string $default = ''): string
    {
        return $this->swRequest->get[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param string $default
     * @return string
     */
    public function Post(string $key, string $default = ''): string
    {
        return $this->swRequest->post[$key] ?? $default;
    }

    public function Cookie(string $key, string $default = ''): string
    {
        return $this->swRequest->cookie[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getParam(string $key, string $default = ''): string
    {
        return Context::Get('urlParameters')[$key] ?? $default;
    }

    /**
     * Params : return specified post data
     * @return array
     */
    public function getParams(): array
    {
        return Context::Get('urlParameters') ?? [];
    }

    /**
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getHeader(string $key, string $default = ''): string
    {
        return $this->swRequest->header[$key] ?? $default;
    }

    public function getHost(): string
    {
        return $this->swRequest->header['host'] ?? '';
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return (array)$this->swRequest->header;
    }

    /**
     * Gets : return all get data
     * @return array
     */
    public function Gets(): array
    {
        return (array)$this->swRequest->get;
    }

    /**
     * @return array
     */
    public function Posts(): array
    {
        return (array)$this->swRequest->post;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getServer(string $key): string
    {
        return $this->swRequest->server[$key] ?? '';
    }

    /**
     * @return array
     */
    public function getServers():array
    {
        return (array)$this->swRequest->server;
    }

    /**
     * @param string $name
     * @return Upload|false
     */
    public function getFile(string $name): ?Upload
    {
        $file = $this->swRequest->files[$name]??null;
        if( null===$file) {
            return $file;
        }
        return new Upload((array)$file);
    }

    /**
     * @return array
     */
    public function getFiles(): array
    {
        return (array)$this->swRequest->files;
    }

    /**
     * @param array $params
     * @param string $routeType path/rest
     */
    public function SetParams(array $params, string $routeType = 'path'):void
    {
        Context::Set('urlParameters', $params);
        Context::Set('routerType', $routeType);
        $this->log();
    }

    private function log():void
    {
        $request = $this->swRequest;

        Log::Debug(' Request : {uri}[{method}], {request}',
            [
                'uri'     => $request->server['request_uri'],
                'method'  => $request->server['request_method'],
                'request' => json_encode($request, JSON_UNESCAPED_UNICODE),
            ]
            , __METHOD__);
    }

}