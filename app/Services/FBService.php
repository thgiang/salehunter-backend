<?php

namespace App\Services;

use App\Models\PageAccessToken;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Support\Facades\Log;

class FBService
{
    public static $fb;

    public function __construct()
    {
        if (self::$fb == null) {
            self::$fb = new Facebook([
                'app_id' => config('fb.app_id', '431091941000101'),
                'app_secret' => config('fb.app_secret', 'de69843e371ea7cc898bcb698c05f393'),
                'default_graph_version' => config('fb.version', 'v8.0')
            ]);
        }
    }

    public function requestFb($method, $url, $accessToken, $params = [], $getAvatar = false)
    {
        if ($url[0] != '/') {
            $url = '/' . $url;
        }

        try {
            if ($method == 'get') {
                $response = self::$fb->get($url, $accessToken);
            } else if ($method == 'post') {
                $response = self::$fb->post($url, $params, $accessToken);
            } else if ($method == 'delete') {
                $response = self::$fb->delete($url, $params, $accessToken);
            } else {
                return [
                    'success' => false,
                    'message' => 'Lỗi Facebook trả về #000: Không hỗ trợ loại phương thức ' . $method . ' này',
                    'code' => 0
                ];
            }

            if ($getAvatar == false) {
                $resData = json_decode($response->getBody(), true);
            } else {
                Log::info(json_encode($response->getHeaders()));
                $resData = $response->getHeaders()['Location'];
            }
        } catch (FacebookResponseException $e) {
            $code = $e->getCode();
            if (in_array($code, [190, 467, 463, 460, 459, 458, 464, 492])) {
//                $this->deleteErrorAccessToken($accessToken);
                $code = 999;
            }

            $message = $this->_translateError($code);

            if (empty($message)) {
                $message = $e->getMessage();
            }

            return [
                'success' => false,
                'message' => 'Lỗi Facebook trả về #' . $e->getCode() . ': ' . $message,
                'code' => $code
            ];
        } catch (FacebookSDKException $e) {
            // When validation fails or other local issues
            $message = $this->_translateError($e->getCode());

            if (empty($message)) {
                $message = $e->getMessage();
            }

            return [
                'success' => false,
                'message' => 'Lỗi Facebook trả về #' . $e->getCode() . ': ' . $message,
                'code' => $e->getCode()
            ];
        } catch (\Exception $e) {
            $message = $this->_translateError($e->getCode());

            if (empty($message)) {
                $message = $e->getMessage();
            }

            return [
                'success' => false,
                'message' => 'Lỗi Facebook trả về #' . $e->getCode() . ': ' . $message,
                'code' => $e->getCode()
            ];
        }

        return [
            'success' => true,
            'data' => $resData
        ];
    }

    private function _translateError($code)
    {
        if ($code == 999) {
            return 'Phiên làm việc của page đã hết hạn trên ứng dụng, vui lòng liên kết lại Facebook với ứng dụng để tiếp tục sử dụng';
        } else if ($code == 551) {
            return 'Người này hiện không có mặt để nhận tin nhắn, hãy nhắn thông qua inbox trên bình luận của người dùng';
        } else if ($code == 10 or ($code >= 200 and $code <= 299)) {
            return 'Bạn vui lòng liên kết lại Facebook cho ứng dụng và chấp nhận đủ quyền để thực hiện hành động này';
        } else if ($code == 10901) {
            return 'Bạn không thể gửi tin nhắn phản hồi bình luận từ hơn 7 ngày trước do chính sách Facebook';
        } else if ($code == 10900) {
            return 'Bình luận này đã được gửi tin nhắn phản hồi, vui lòng kiểm tra trong mục Tin nhắn';
        } else if ($code == 1) {
            return 'Lỗi không xác định';
        }

        return '';
    }

    public function deleteErrorAccessToken($accessToken)
    {
        PageAccessToken::where('fb_access_token', $accessToken)->delete();

        return true;
    }

    public function generateLongLivedToken($shortToken)
    {
        $urlRequest = 'oauth/access_token?grant_type=fb_exchange_token&fb_exchange_token=' . $shortToken
            . '&client_id=' . config('fb.app_id')
            . '&client_secret=' .config('fb.app_secret');

        $response = $this->requestFb('get', $urlRequest, $shortToken);

        return $response;
    }

    public function getInfoUserFb($fid, $accessToken)
    {
        $urlRequest = '/' . $fid . '?fields=name,picture.width(320).height(320)';

        $response = $this->requestFb('get', $urlRequest, $accessToken);

        if ($response['success'] == false) {
            $response['data'] = [
                'profile_pic' => '',
                'name' => 'Người dùng Facebook'
            ];
        } else {
            $responseData = $response['data'];
            $response['data'] = [
                'profile_pic' => $responseData['picture']['data']['url'],
                'name' => $responseData['name']
            ];
        }

        return $response;
    }

    public function getInfoUserCommentFb($fid, $accessToken)
    {
        $urlRequest = '/' . $fid . '/picture?type=normal';

        $response = $this->requestFb('get', $urlRequest, $accessToken, [], true);

        if ($response['success'] == false) {
            $response['data'] = [
                'profile_pic' => '',
                'name' => 'Người dùng Facebook'
            ];
        }

        $response['data'] = [
            'profile_pic' => $response['data']
        ];

        return $response;
    }

    public function sendMessage($params, $pageAccessToken)
    {
        $urlRequest = '/me/messages';

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        if ($response['success'] == true) {
            $response['data']['mid'] = $response['data']['message_id'];
            unset($response['data']['message_id']);
        }

        return $response;
    }

    public function sendTyping($params, $pageAccessToken)
    {
        $urlRequest = '/me/messages';

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function me($accessToken)
    {
        $urlRequest = '/me';

        $response = $this->requestFb('get', $urlRequest, $accessToken);

        return $response;
    }

    public function getPages($accessToken)
    {
        $isNext = true;
        $after = null;

        $returnData = [];

        while ($isNext) {
            if (!empty($after)) {
                $urlRequest = '/me/accounts' . '?after=' . $after . '&limit=10';
            } else {
                $urlRequest = '/me/accounts?limit=10';
            }

            $response = $this->requestFb('get', $urlRequest, $accessToken);

            if (!$response['success']) {
                return $response;
            }

            $resData = $response['data'];

            $returnData = array_merge($returnData, $resData['data']);

            if (empty($resData['paging']['next'])) {
                $isNext = false;
            } else {
                $after = $resData['paging']['cursors']['after'];
            }
        }

        return [
            'success' => true,
            'data' => $returnData
        ];
    }

    public function checkSubscribeLivestream($pid, $pageAccessToken)
    {
        $urlRequest = '/' . $pid . '/subscribed_apps';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken, []);

        return $response;
    }

    public function subscribePages($pid, $pageAccessToken)
    {
        $urlRequest = '/' . $pid . '/subscribed_apps';
        $params = [
            'subscribed_fields' => ['messages', 'message_echoes', 'feed', 'messaging_postbacks', 'message_reads', 'live_videos']
        ];

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function unsubscribePage($pid, $pageAccessToken)
    {
        $urlRequest = '/' . $pid . '/subscribed_apps';
        $params = [
            'subscribed_fields' => ['messages', 'message_echoes', 'feed', 'messaging_postbacks', 'message_reads', 'live_videos']
        ];

        $response = $this->requestFb('delete', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function getPostDetail($postId, $pageAccessToken)
    {
        $urlRequest = '/' . $postId . '?fields=message,created_time,full_picture,picture,permalink_url,attachments';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function getCommentDetail($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId . '?fields=attachment,message,is_hidden,from,created_time';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function sendComment($commentId, $params, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId . '/comments';

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function checkComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId . '?fields=can_hide,can_like,can_reply_privately,can_remove,can_comment';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function likeComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId . '/likes';

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function unlikeComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId . '/likes';

        $response = $this->requestFb('delete', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function hideComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId;

        $params = [
            'is_hidden' => true
        ];

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function unhideComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId;

        $params = [
            'is_hidden' => false
        ];

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function deleteComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId;

        $response = $this->requestFb('delete', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function privateReply($commentId, $pageAccessToken, $params)
    {
        $urlRequest = '/' . $commentId . '/private_replies';

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        return $response;
    }

    public function getAttachmentComment($commentId, $pageAccessToken)
    {
        $urlRequest = '/' . $commentId . '?fields=attachment';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function removePermissionApp($fid, $accessToken)
    {
        $urlRequest = '/' . $fid . '/permissions';

        $response = $this->requestFb('delete', $urlRequest, $accessToken);

        return $response;
    }

    public function getConversationId($pid, $fid, $pageAccessToken)
    {
        $urlRequest = '/' . $pid . '/conversations?user_id=' . $fid;

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function sendMessageViaConversationId($conversationId, $params, $pageAccessToken)
    {
        $urlRequest = '/' . $conversationId . '/messages';

        $response = $this->requestFb('post', $urlRequest, $pageAccessToken, $params);

        if ($response['success'] == true) {
            $response['data']['mid'] = $response['data']['id'];
            unset($response['data']['uuid']);
        }

        return $response;
    }

    public function getConversationDetail($conversationId, $pageAccessToken, $skip = 0, $limit = 10)
    {
        if ($skip != 0) {
            $urlRequest = '/' . $conversationId . '/messages?limit=' . $skip . '&pretty=0';

            $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

            if (empty($response['data']['paging']['next'])) {
                return [
                    'success' => false,
                    'message' => 'Not have next page'
                ];
            }

            $urlRequest = '/' . $conversationId . '/messages?fields=message,attachments,from,created_time,sticker&limit='
                . $limit . '&pretty=0&after=' . $response['data']['paging']['cursors']['after'];
        } else {
            $urlRequest = '/' . $conversationId . '/messages?fields=message,attachments,from,created_time,sticker&limit='
                . $limit . '&pretty=0';
        }

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function checkTokenValid($id, $accessToken)
    {
        $urlRequest = '/' . $id . '/permissions';

        $response = $this->requestFb('get', $urlRequest, $accessToken);

        return $response;
    }

    public function getPostLiveStreamDetail($postId, $pageAccessToken)
    {
        $urlRequest = '/' . $postId . '?fields=description,live_views,status,permalink_url,creation_time,id';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }

    public function getViewsLivestream($liveId, $pageAccessToken)
    {
        $urlRequest = '/' . $liveId . '?fields=live_views';

        $response = $this->requestFb('get', $urlRequest, $pageAccessToken);

        return $response;
    }
}
