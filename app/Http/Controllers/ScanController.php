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

        // call the Python API running on localhost:5000
        $response = Http::post(config('services.python.url') . '/predict-url', [
            'url' => $request->input('url'),
        ]);

        // if python is down, throw an exception that JS can catch
        if ($response->failed()) {
            return response()->json(['error' => 'Python service unreachable'], 502);
        }

        return response()->json($response->json());
    }
}
