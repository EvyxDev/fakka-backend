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
use App\Http\Resources\Api\UserResource;
use App\Http\Resources\Api\VendorResource;

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
            return $this->errorResponse(400, 'You are not authorized to perform this action');
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
            return $this->successResponse(200, 'QR code generated successfully', [
                'Qr Code' => $Table,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(400, 'An error occurred while generating QR code' . $e->getMessage());
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
            return $this->errorResponse(400, 'QR code not found');
        }
    
        if ($qrCodeData->status !== 'active') {
            return $this->errorResponse(400, 'QR code is not active');
        }
    
        if (auth()->guard('vendor')->check()) {
            $receiver = auth()->guard('vendor')->user();
        } elseif (auth()->guard('user')->check()) {
            $receiver = auth()->guard('user')->user();
        } else {
            return $this->errorResponse(400, 'You are not authorized to perform this action');
        }
    
        if ($qrCodeData->user_id) {
            $sender = User::find($qrCodeData->user_id);
        } else {
            $sender = Vendor::find($qrCodeData->vendor_id);
        }
    
        if (!$sender) {
            return $this->errorResponse(400, 'Sender not found');
        }
    
        if ($sender->balance < $qrCodeData->amount) {
            return $this->errorResponse(400, 'Insufficient balance');
        }
    
        if ($sender instanceof User && $receiver instanceof User) {
            return $this->errorResponse(400, 'A user cannot send money to another user');
        }
    
        if ($sender instanceof Vendor && $receiver instanceof Vendor) {
            return $this->errorResponse(400, 'A vendor cannot send money to another vendor');
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
    
            $qrCodeData->status = 'used';
            $qrCodeData->save();
    
            DB::commit();
    
            // Return sender and receiver as resources
            $senderResource = $sender instanceof User ? new UserResource($sender) : new VendorResource($sender);
            $receiverResource = $receiver instanceof User ? new UserResource($receiver) : new VendorResource($receiver);
    
            return $this->successResponse(200, 'Transaction successful', [
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
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action',
            ], 401);
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
                    'phone'=>$sender->phone,
                    'phonecode'=>$sender->phonecode,
                    'profile_image' => $sender->profile_image ? url('public/' . $sender->profile_image) : null,
                ],
                'receiver' => [
                    'id' => $receiver->id,
                    'type' => $transaction->receiver_type,
                    'name' => $receiver->name,
                    'phone'=>$receiver->phone,
                    'phonecode'=>$receiver->phonecode,

                    'profile_image' => $receiver->profile_image ? url('public/' . $receiver->profile_image) : null,
                ],
                'created_at' => $transaction->created_at,
            ];
        });
    
        return response()->json([
            'status' => 'success',
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
            return $this->errorResponse(400, 'You are not authorized to perform this action');
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
        return $this->successResponse(200, 'Notifications retrieved successfully', [
            'notifications' => $formattedNotifications,
        ]);
    }
}
