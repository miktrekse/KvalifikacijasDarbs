<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CourseController extends Controller
{
    public function index()
    {
        return view('courses.index');
    }

    public function data(Request $request)
    {
        $country = strtoupper((string) $request->query('country', 'GB'));
        $limit = min(max((int) $request->query('limit', 500), 1), 500);

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('https://io.discgolfapi.com/v1/courses', [
                    'country' => $country,
                    'limit' => $limit,
                ])
                ->throw();

            return response()->json($response->json());
        } catch (RequestException $exception) {
            return response()->json([
                'message' => 'Course data is temporarily unavailable.',
            ], 502);
        }
    }
}