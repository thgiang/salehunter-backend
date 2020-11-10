<?php

namespace App\Http\Controllers\Api;

use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FacebookController extends Controller
{
    public function loginUrl () {
        try {
            $fb = new Facebook([
                'app_id' => env('FB_APP_ID'),
                'app_secret' => env('FB_SECRET'),
                'default_graph_version' => env('FB_SDK_VERSION'),
            ]);
        } catch (FacebookSDKException $e) {
            exit('Error when init Facebook SDK');
        }

        $helper = $fb->getRedirectLoginHelper();

        $permissions = ['email']; // Optional permissions
        $loginUrl = $helper->getLoginUrl('https://example.com/fb-callback.php', $permissions);
        exit($loginUrl );
    }
}
