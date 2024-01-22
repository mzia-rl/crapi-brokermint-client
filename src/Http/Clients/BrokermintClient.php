<?php

namespace Canzell\Http\Clients;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Sentry;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

class BrokermintClient extends HttpClient
{
    public $config;

    private ?Response $lastResponse = null;
    private int $throttleMaxWait;

    public function __construct()
    {
        $this->config = config('brokermint-client');
        $this->throttleMaxWait = $this->config['throttling']['max_wait'];
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->config['base_uri'],
            'query' => [
                'api_key' => $this->config['api_key']
            ]
        ]);
        parent::__construct(
            $client,
            shouldParseJsonResponse: false
        );
    }

    public function __call($name, $args)
    {
        $url = &$args[0];
        $config = &$args[1];
        
        // Make sure api_key query param isn't overwritten
        if (isset($config['query'])) {
            $isArray = is_array($config['query']);
            $config['query'] = $isArray
                ? array_merge($config['query'], [
                    'api_key' => $this->config['api_key']
                ])
                : "{$config['query']}&api_key={$this->config['api_key']}";
        }

        // $args[1] will be set to null if it is undefined and set by reference, so it has to be unset so Guzzle doesn't throw a fit
        if (empty($config)) unset($args[1]);

        // Check for throttle limits
        $this->checkQuota();

        // Make request
        try {
            $response = parent::__call($name, $args);
            $this->lastResponse = $response;
        } catch (RequestException $e) {
            $req = $e->getRequest();
            $res = $e->getResponse();
            $code = $res->getStatusCode();

            $mes = "Failed request to Brokermint! \n" . $code . ' ' . $req->getUri();
            $sentryException = new Exception($mes, $e->getCode(), $e);
            Sentry\captureException($sentryException);

            if ($code == 429) $this->cacheQuota($res);

            throw $e;
        }
        
        // Store updated throttle data
        $this->cacheQuota($response);
        
        // Return response
        return json_decode($response->getBody());
    }
    
    private function checkQuota(): void
    {
        $errorRemaining = Cache::get('crapi-brokermint-client:throttle:errors-remaining', 150);
        $burstRemaining = Cache::get('crapi-brokermint-client:throttle:burst-remaining', 100);
        $hourRemaining = Cache::get('crapi-brokermint-client:throttle:hour-remaining', 2000);

        $throttleException = new Exception('Pre-emptive 429 response error; throttle limits will be exceeded if request is made to Brokermint.', 429);
        if ($errorRemaining < 1) {
            throw new Exception('Would exceed hour limit for errors', 429, $throttleException);
        } else if ($hourRemaining < 1) {
            throw new Exception('Would exceed hour limit for requests', 429, $throttleException);
        } else if ($burstRemaining < 1) {
            $maxWaitTime = ceil($this->throttleMaxWait / 1000); // sleep function uses seconds, so we must convert to seconds
            $waitTime = 10;
            if ($waitTime <= $maxWaitTime) sleep($waitTime);
            else throw new Exception('Would exceed burst limit for requests', 429, $throttleException);
        }
    }

    private function cacheQuota(Response $response)
    {
        $errorRemaining = $response->getHeaderLine('X-Brokermint-Errors-Remaining');
        $burstRemaining = $response->getHeaderLine('X-Brokermint-Burst-Remaining');
        $hourRemaining = $response->getHeaderLine('X-Brokermint-Hour-Remaining');
        $hourReset = $response->getHeaderLine('X-Brokermint-Hour-Reset');
        
        $hourReset = Carbon::parse((int) $hourReset / 1000);

        Cache::put('crapi-brokermint-client:throttle:errors-remaining', $errorRemaining, $hourReset);
        Cache::put('crapi-brokermint-client:throttle:burst-remaining', $burstRemaining, now()->addSeconds(10));
        Cache::put('crapi-brokermint-client:throttle:hour-remaining', $hourRemaining, $hourReset);
    }

}