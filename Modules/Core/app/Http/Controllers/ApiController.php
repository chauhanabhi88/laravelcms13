<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ApiController extends Controller
{

    protected function getSuccessReturn($data = [], $changeKeyToIndex = false)
    {
        if ($data) {
            $code = config('passport.http_success.http_ok');
            if($changeKeyToIndex) {
                $data = changeKeyToIndex($data);
            }
            $data['code'] = $code;
            return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        } else {
            return response()->json(['type' => 'success'])->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        }
    }

    protected function getFailureReturn($message, $code = null)
    {
        if (!$code) {
            $code = config('passport.http_fail.bad_request');
        }
        if ($message) {
            return response()->json(['type' => 'failure', 'message' => $message, "code" => $code], $code)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        } else {
            return response()->json(['type' => 'failure', 'message' => '', "code" => $code], $code)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        }
    }

    protected function getFailureWithData($data = [], $changeKeyToIndex = false, $code = null)
    {
        if ($data) {
            $code = isset($code) && !empty($code) ? $code : config('passport.http_fail.bad_request');
            $data['type'] = 'failure'; 

            if($changeKeyToIndex) {
                $data = changeKeyToIndex($data);
            }
            $data['code'] = $code;
            return response()->json($data, $code)->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        } else {
            return response()->json(['type' => 'failure'])->header('Cache-Control', 'no-store, no-cache, must- revalidate, max-age=0');
        }
    }
}