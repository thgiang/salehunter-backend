<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FBService;
use Illuminate\Http\Request;
use App\Services\ProducerService;

class FacebookController extends Controller
{
    public function webhook(Request $request) {
		// Parse the query params
		$mode = $request->get('hub_mode');
		$token = $request->get('hub_verify_token');
		$challenge = $request->get('hub_challenge');


		// Checks if a token and mode is in the query string of the request
		if ($mode && $token) {
			// Checks the mode and token sent is correct
			if ($mode === 'subscribe' && $token === env('FACEBOOK_VERIFY_TOKEN', 'GHTVTHV1')) {
			  return $challenge;

			} else {
				return response()->json(['error' => 'Not authorized.'], 403);
			}
		}

		// Receive other webhook
		$data = $request->all();

		// Bóc tách lấy pid để đẩy vào cùng queue
		if (!empty($data['entry'][0]['id'])) {
			$key = $data['entry'][0]['id'];
		} else {
			return response([]);
		}

		file_put_contents('request.txt', json_encode($request->all())."\n", FILE_APPEND);
		try {
			ProducerService::getInstance(env('KAFKA_WEBHOOK_BROKER'))->setTopic(env('KAFKA_WEBHOOK_TOPIC'))->send($data, $key);
			file_put_contents('request.txt', date("d/m/Y H:i:s") . ' Luu vao Kaka thanh cong'."\n", FILE_APPEND);
		} catch (\Exception $ex) {
			file_put_contents('request.txt', date("d/m/Y H:i:s") . ' ERROR WEBHOOK KAFKA'. $ex->getMessage() . "\n", FILE_APPEND);
		}

		return response([]);
	}
}
