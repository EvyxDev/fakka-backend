<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Models\Fee;
use App\Models\User;
use App\Models\QrCode;
use App\Models\Vendor;
use App\Models\Transaction;
use App\Traits\ApiResponse;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\API\UserResource;
use App\Http\Resources\API\VendorResource;

class TransactionController extends Controller
{

    use ApiResponse;

    public function generateQrCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse(422, 'Validation errors', $validator->errors());
        }
        if (auth()->guard('vendor')->check()) {
            $user = auth()->guard('vendor')->user();
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
        } else {
            return $this->errorResponse(400, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        try {
            $amount = $request->input('amount');
            $qrCode = Str::random(20);
            $Table = QrCode::create([
                'user_id' => $user instanceof User ? $user->id : null,
                'vendor_id' => $user instanceof Vendor ? $user->id : null,
                'qr_code' => $qrCode,
                'amount' => $amount,
            ]);
            return $this->successResponse(200, __('word.qr_code_generated_successfully'), [
                'Qr Code' => $Table,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(400, __('word.something_went_wrong') . $e->getMessage());
        }
    }
    public function scanQrCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, 'Validation errors', $validator->errors());
        }

        $qrCode = $request->input('qr_code');
        $qrCodeData = QrCode::where('qr_code', $qrCode)->first();

        if (!$qrCodeData) {
            return $this->errorResponse(400, __('word.qr_code_not_found'));
        }

        if ($qrCodeData->status !== 'active') {
            return $this->errorResponse(400, __('word.qr_code_already_used'));
        }

        if (auth()->guard('vendor')->check()) {
            $receiver = auth()->guard('vendor')->user();
        } elseif (auth()->guard('user')->check()) {
            $receiver = auth()->guard('user')->user();
        } else {
            return $this->errorResponse(400, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        if ($qrCodeData->user_id) {
            $sender = User::find($qrCodeData->user_id);
        } else {
            $sender = Vendor::find($qrCodeData->vendor_id);
        }

        if (!$sender) {
            return $this->errorResponse(400, __('word.sender_not_found'));
        }

        if ($sender->balance < $qrCodeData->amount) {
            return $this->errorResponse(400, __('word.insufficient_balance'));
        }

        if ($sender instanceof User && $receiver instanceof User) {
            return $this->errorResponse(400, __('word.a_user_cannot_send_money_to_another_user'));
        }

        if ($sender instanceof Vendor && $receiver instanceof Vendor) {
            return $this->errorResponse(400, __('word.a_vendor_cannot_send_money_to_another_vendor'));
        }

        DB::beginTransaction();
        try {
            $feeAmount = 0;
            if ($sender instanceof Vendor) {
                $feeAmount = $qrCodeData->amount * 0.10;
            }

            $transactionAmount = $qrCodeData->amount - $feeAmount;

            $sender->balance -= $qrCodeData->amount;
            $sender->save();

            $receiver->balance += $transactionAmount;
            $receiver->save();

            $transaction = Transaction::create([
                'sender_id' => $sender->id,
                'sender_type' => $sender instanceof User ? 'user' : 'vendor',
                'receiver_id' => $receiver->id,
                'receiver_type' => $receiver instanceof User ? 'user' : 'vendor',
                'amount' => $qrCodeData->amount,
                'status' => 'completed',
            ]);

            if ($feeAmount > 0) {
                Fee::create([
                    'transaction_id' => $transaction->id,
                    'amount' => $feeAmount,
                ]);
            }

            Notification::create([
                'user_id' => $sender instanceof User ? $sender->id : null,
                'vendor_id' => $sender instanceof Vendor ? $sender->id : null,
                'type' => 'transaction_sent',
                'message' => 'You sent ' . $qrCodeData->amount . ' to ' . $receiver->name,
            ]);

            Notification::create([
                'user_id' => $receiver instanceof User ? $receiver->id : null,
                'vendor_id' => $receiver instanceof Vendor ? $receiver->id : null,
                'type' => 'transaction_received',
                'message' => 'You received ' . $qrCodeData->amount . ' from ' . $sender->name,
            ]);

            $qrCodeData->status = 'used';
            $qrCodeData->save();

            DB::commit();

            // Return sender and receiver as resources
            $senderResource = $sender instanceof User ? new UserResource($sender) : new VendorResource($sender);
            $receiverResource = $receiver instanceof User ? new UserResource($receiver) : new VendorResource($receiver);

            return $this->successResponse(200, __('word.transaction_successful'), [
                'sender' => $senderResource,
                'receiver' => $receiverResource,
                'amount' => $transactionAmount,
                'fee' => $feeAmount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(400, 'An error occurred while processing the transaction: ' . $e->getMessage());
        }
    }
    public function transactionHistory(Request $request)
    {
        // Get the authenticated user (User or Vendor)
        if (auth()->guard('vendor')->check()) {
            $user = auth()->guard('vendor')->user();
            $userType = 'vendor';
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $userType = 'user';
        } else {
            return $this->errorResponse(400, __('word.you_are_not_authorized_to_perform_this_action'));
        }
        $perPage = $validated['per_page'] ?? 5;

        // Fetch transactions where the user is either the sender or receiver
        $transactions = Transaction::where(function ($query) use ($user, $userType) {
            $query->where('sender_id', $user->id)
                ->where('sender_type', $userType);
        })->orWhere(function ($query) use ($user, $userType) {
            $query->where('receiver_id', $user->id)
                ->where('receiver_type', $userType);
        })->orderBy('created_at', 'desc')->paginate($perPage);

        // Format the transactions
        $formattedTransactions = $transactions->map(function ($transaction) use ($user, $userType) {
            $sender = $transaction->sender;
            $receiver = $transaction->receiver;

            return [
                'id' => $transaction->id,
                'type' => $transaction->sender_id === $user->id ? 'sent' : 'received',
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'sender' => [
                    'id' => $sender->id,
                    'type' => $transaction->sender_type,
                    'name' => $sender->name,
                    'profile_image' => $sender->profile_image ? url('public/' . $sender->profile_image) : null,
                ],
                'receiver' => [
                    'id' => $receiver->id,
                    'type' => $transaction->receiver_type,
                    'name' => $receiver->name,
                    'profile_image' => $receiver->profile_image ? url('public/' . $receiver->profile_image) : null,
                ],
                'created_at' => $transaction->created_at,
            ];
        });

        return $this->successResponse(200, __('word.transaction_history'), [
            'transactions' => $formattedTransactions,
        ]);
    }

    public function getNotifications(Request $request)
    {
        if (auth()->guard('vendor')->check()) {
            $user = auth()->guard('vendor')->user();
            $notifications = Notification::where('vendor_id', $user->id)->orderBy('created_at', 'desc')->get();
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $notifications = Notification::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        } else {
            return $this->errorResponse(400, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        $formattedNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'message' => $notification->message,
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at->toDateTimeString(),
            ];
        });
        return $this->successResponse(200, __('word.notifications'), [
            'notifications' => $formattedNotifications,
        ]);
    }
}
