<?php

namespace App\Http\Controllers;

use App\Services\Moderation\ModerationScanService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class SightengineModerationCallbackController extends Controller
{
    public function __invoke(Request $request, int $item, ModerationScanService $scanner): Response
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return response('Invalid callback payload.', 400);
        }

        try {
            $scanner->receiveAsyncResult($item, $payload);
        } catch (DomainException $exception) {
            return response($exception->getMessage(), 409);
        } catch (Throwable $exception) {
            report($exception);

            return response('The callback could not be processed.', 500);
        }

        return response()->noContent();
    }
}
