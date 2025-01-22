<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Models\User;
use App\Models\QrCode;
use App\Models\Vendor;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{


    public function generateQrCode(Request $request)
    {
        if(auth()->guard('vendor')->check()){
            $user = auth()->guard('vendor')->user();
        }elseif(auth()->guard('user')->check()){
            $user = auth()->guard('user')->user();
        }else{
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action',
            ]);
        } 
        $amount = $request->input('amount');

        $qrCode = Str::random(20);

        $Table = QrCode::create([
            'user_id' => $user instanceof User ? $user->id : null,
            'vendor_id' => $user instanceof Vendor ? $user->id : null,
            'qr_code' => $qrCode,
            'amount' => $amount,
        ]);

        return response()->json([
            'status' => 'success',
            'qr_code' => $qrCode,
            'amount' => $amount,
            'table' => $Table,
        ]);
    }
    public function scanQrCode(Request $request)
    {
        
        $qrCode = $request->input('qr_code');
        $qrCodeData = QrCode::where('qr_code', $qrCode)->first();
        
        if (!$qrCodeData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid QR code',
            ], 400);
        }
        if ($qrCodeData->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'QR code is not active , You can not use this QR code, Please generate new one',
            ], 400);
        }
        if(auth()->guard('vendor')->check()){
            $reciever = auth()->guard('vendor')->user();
        }elseif(auth()->guard('user')->check()){
            $reciever = auth()->guard('user')->user();
        }else{
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action',
            ]);
        }
        // Deduct amount from the sender
        if ($qrCodeData->user_id) {
            $sender = User::find($qrCodeData->user_id);
        } else {
            $sender = Vendor::find($qrCodeData->vendor_id);
        }

        if ($sender->balance < $qrCodeData->amount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance',
            ], 400);
        }

        $sender->balance -= $qrCodeData->amount;
        
        $reciever->balance += $qrCodeData->amount;
        
        Transaction::create([
            'sender_id' => $sender->id,
            'sender_type' => $sender instanceof User ? 'user' : 'vendor',
            'receiver_id' => $reciever->id,
            'receiver_type' => $reciever instanceof User ? 'user' : 'vendor',
            'amount' => $qrCodeData->amount,
            'status' => 'completed',
        ]);

        $sender->save();
        $reciever->save();
        
        $qrCodeData->status = 'used';
        $qrCodeData->save();
        
        
        return response()->json([
            'status' => 'success',
            'message' => 'Transaction completed successfully',
            'data'=>[
                'sender' => $sender,
                'receiver' => $reciever,
                'amount' => $qrCodeData->amount,
                'sender_balance' => $sender->balance,
                'reciever_balance' => $reciever->balance
            ]
        ]);
    }
    public function transactionHistory(Request $request)
    {
        // Get the authenticated user or vendor
        if(auth()->guard('vendor')->check()){
            $user = auth()->guard('vendor')->user();
        }elseif(auth()->guard('user')->check()){
            $user = auth()->guard('user')->user();
        }else{
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action',
            ]);
        }

        $transactions = Transaction::where(function ($query) use ($user) {
            $query->where('sender_id', $user->id)
                  ->where('sender_type', get_class($user));
        })->orWhere(function ($query) use ($user) {
            $query->where('receiver_id', $user->id)
                  ->where('receiver_type', get_class($user));
        })->orderBy('created_at', 'desc')->get();

        $formattedTransactions = $transactions->map(function ($transaction) use ($user) {
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
                ],
                'receiver' => [
                    'id' => $receiver->id,
                    'type' => $transaction->receiver_type,
                    'name' => $receiver->name,
                ],
                'created_at' => $transaction->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'transactions' => $formattedTransactions,
        ]);
    }

}
