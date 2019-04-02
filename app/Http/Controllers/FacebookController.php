<?php

namespace App\Http\Controllers;

use App\Helpers\FbPersistentDataHelper;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FacebookController extends Controller
{
    public function index() {
        $fb = $this->getFb();

        if (!Storage::exists('facebook_access_token.txt')) {
            $helper = $fb->getRedirectLoginHelper();
            $permissions = ['manage_pages'];
            $loginUrl = $helper->getLoginUrl(config('app.url') . '/fb/callback', $permissions);

            return view('fb_not_loggedin', ['fb_login_url' => $loginUrl]);
        }

        $accessToken = unserialize(Storage::get('facebook_access_token.txt'));


        $response = $fb->get('/me/accounts', $accessToken);

        $posts = [];

        foreach($response->getGraphEdge() as $page) {
            $data = $page->asArray();

            $response = $fb->get('/' . $data['id'] . '/feed?fields=full_picture,message,link&limit=5', $data['access_token']);

            $ps = $response->getGraphEdge()->asArray();

            if (count($ps) > 0) {
                foreach($ps as $p) {
                    $p['page'] = $data['name'];
                    $posts[] = $p;
                }
            }
        }

        return view('fb_posts', ['posts' => $posts]);
    }

    public function callback() {
        $fb = $this->getFb();

        if(session()->has('access_token')) {
            $accessToken = session()->get('access_token');
            Storage::put('facebook_access_token.txt', serialize($accessToken));
        } else {

            $helper = $fb->getRedirectLoginHelper();

            try {
                $accessToken = $helper->getAccessToken();
            } catch(FacebookResponseException $e) {
                // When Graph returns an error
                echo 'Graph returned an error: ' . $e->getMessage();
                exit;
            } catch(FacebookSDKException $e) {
                // When validation fails or other local issues
                echo 'Facebook SDK returned an error: ' . $e->getMessage();
                exit;
            }

            if (! isset($accessToken)) {
                if ($helper->getError()) {
                    header('HTTP/1.0 401 Unauthorized');
                    echo "Error: " . $helper->getError() . "\n";
                    echo "Error Code: " . $helper->getErrorCode() . "\n";
                    echo "Error Reason: " . $helper->getErrorReason() . "\n";
                    echo "Error Description: " . $helper->getErrorDescription() . "\n";
                } else {
                    header('HTTP/1.0 400 Bad Request');
                    echo 'Bad request';
                }
                exit;
            }

            // Logged in

            // The OAuth 2.0 client handler helps us manage access tokens
            $oAuth2Client = $fb->getOAuth2Client();

            // Get the access token metadata from /debug_token
            $tokenMetadata = $oAuth2Client->debugToken($accessToken);

            // Validation (these will throw FacebookSDKException's when they fail)
            $tokenMetadata->validateAppId(config('facebook.app_id')); // Replace {app-id} with your app id
            // If you know the user ID this access token belongs to, you can validate it here
            //$tokenMetadata->validateUserId('123');
            $tokenMetadata->validateExpiration();

            if (! $accessToken->isLongLived()) {
                // Exchanges a short-lived access token for a long-lived one
                try {
                    $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
                } catch (FacebookSDKException $e) {
                    echo "<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n";
                    exit;
                }
            }

            session()->put('access_token', $accessToken);
            Storage::put('facebook_access_token.txt', serialize($accessToken));
        }

        return redirect(action('FacebookController@index'));
    }

    private function getFb() {
        return new Facebook([
            'app_id' => config('facebook.app_id'),
            'app_secret' => config('facebook.app_secret'),
            'persistent_data_handler' => new FbPersistentDataHelper()
        ]);
    }
}
