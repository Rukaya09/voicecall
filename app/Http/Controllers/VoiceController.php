<?php
namespace App\Http\Controllers;

use Twilio\Rest\Client;
use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse; // Correct namespace for Validator
use Twilio\Exceptions\RestException;
use Illuminate\Support\Facades\Validator;

class VoiceController extends Controller
{
    public function __construct() {
        // Twilio credentials
        $this->account_sid = env('ACCOUNT_SID');
        $this->auth_token = env('AUTH_TOKEN');
        // the Twilio number you purchased
        $this->from = env('TWILIO_PHONE_NUMBER');

        // Initialize the Programmable Voice API
        $this->client = new Client($this->account_sid, $this->auth_token);
    }

    public function initiateCall(Request $request) {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $phone_number = $this->client->lookups->v1->phoneNumbers($request->phone_number)->fetch();

            if ($phone_number) {
                // URL to your TwiML endpoint, passing the phone number as a query parameter
                $twimlUrl = route('twiml.response', ['phone_number' => $request->phone_number]);

                $call = $this->client->account->calls->create(
                    $request->phone_number,
                    $this->from,
                    [
                        "record" => true,
                        "url" => $twimlUrl,
                    ]
                );

                if ($call) {
                    return response()->json(['message' => 'Call initiated successfully'], 200);
                } else {
                    return response()->json(['message' => 'Call failed!'], 500);
                }
            }
        } catch (RestException $rest) {
            return response()->json(['error' => $rest->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function twimlResponse(Request $request) {
        $response = new VoiceResponse();

        // Use the Dial verb to connect the caller to another phone number
        $dial = $response->dial();
        $dial->number($request->input('phone_number'));

        return response($response, 200)
            ->header('Content-Type', 'text/xml');
    }
}
