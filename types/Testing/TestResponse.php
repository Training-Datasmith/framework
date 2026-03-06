<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

use function PHPStan\Testing\assertType;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Symfony\Component\HttpFoundation\StreamedResponse;

$response = TestResponse::fromBaseResponse(response('Laravel', 200));
assertType(Response::class, $response->baseResponse);

$response = TestResponse::fromBaseResponse(response()->redirectTo(''));
assertType(RedirectResponse::class, $response->baseResponse);

$response = TestResponse::fromBaseResponse(response()->download(''));
assertType(BinaryFileResponse::class, $response->baseResponse);

$response = TestResponse::fromBaseResponse(response()->json());
assertType(JsonResponse::class, $response->baseResponse);

$response = TestResponse::fromBaseResponse(response()->streamDownload(fn () => 1));
assertType(StreamedResponse::class, $response->baseResponse);
