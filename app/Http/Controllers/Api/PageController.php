<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Services\FBService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function addPages(Request $request) {
        $fb = new FBService();
        $longLiveAccessToken = $fb->generateLongLivedToken($request->access_token);
        if ($longLiveAccessToken && isset($longLiveAccessToken['success']) && $longLiveAccessToken['success'] == 1) {
            $pages = $fb->getPages($longLiveAccessToken['data']['access_token']);
            return response()->json($pages);
        }
    }
}
