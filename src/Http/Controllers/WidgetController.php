<?php

namespace FifteenPeas\Support\Http\Controllers;

use FifteenPeas\Support\Widget;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the widget straight from the package, so an app never ships a stale
 * published copy: composer update is the whole upgrade.
 */
class WidgetController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        // The URL carries the file's hash, so a matching request can be cached
        // for good; anything else revalidates.
        $immutable = $request->query('v') === Widget::version();

        return response()->file(Widget::path(), [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => $immutable ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
        ]);
    }
}
