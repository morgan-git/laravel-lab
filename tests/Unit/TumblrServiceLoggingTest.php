<?php

use App\Services\TumblrService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['services.tumblr.key' => 'test-api-key']);

    $this->serviceReturning = function (Response $response): TumblrService {
        $mock = new MockHandler([$response]);

        return new TumblrService(new Client(['handler' => HandlerStack::create($mock)]));
    };
});

it('logs a short warning to the feed-sync channel when a Tumblr request fails', function () {
    $service = ($this->serviceReturning)(
        new Response(401, [], json_encode(['meta' => ['status' => 401, 'msg' => 'Unauthorized']]))
    );

    Log::shouldReceive('channel')->with('feed-sync')->once()->andReturnSelf();
    Log::shouldReceive('warning')->once()->with(
        'Tumblr page fetch failed',
        Mockery::on(fn (array $context) => $context['tag'] === 'foodporn'
            && $context['before'] === null
            && $context['exception'] === ClientException::class
            && $context['status'] === 401)
    );

    expect($service->fetch('foodporn'))->toBeEmpty();
});

it('never writes the api key into the log context', function () {
    $service = ($this->serviceReturning)(new Response(500));

    Log::shouldReceive('channel')->with('feed-sync')->once()->andReturnSelf();
    Log::shouldReceive('warning')->once()->with(
        'Tumblr page fetch failed',
        Mockery::on(fn (array $context) => ! str_contains(json_encode($context), 'test-api-key'))
    );

    $service->fetch('foodporn');
});

it('does not log anything when the request succeeds', function () {
    $service = ($this->serviceReturning)(
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['response' => []]))
    );

    Log::shouldReceive('channel')->never();

    expect($service->fetch('foodporn'))->toBeEmpty();
});
