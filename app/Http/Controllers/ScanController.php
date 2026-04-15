<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScanController extends Controller
{
    /**
     * Proxy a job URL to the Python fraud detection service and
     * return its JSON response.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        // call the Python FastAPI service running on localhost:8001
        $response = Http::timeout(25)->post(config('services.python.url') . '/analyze', [
            'url' => $request->input('url'),
        ]);

        // if python is down, throw an exception that JS can catch
        if ($response->failed()) {
            return response()->json(['error' => 'Python service unreachable'], 502);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $payload = [];
        }

        return response()->json($payload);
    }

}
