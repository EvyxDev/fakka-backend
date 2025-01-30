<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Models\{Fee, QrCode, Transaction, User, Vendor};
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
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
            $model_of_sender = 'vendor'; 
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $model_of_sender = 'user'; 
        } else {
            return $this->errorResponse(401, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        if ($request->amount > $user->balance) {
            return $this->errorResponse(400, __('word.insufficient_balance'));
        }

        try {
            $amount = $request->input('amount');
            $qrCode = Str::random(20);

            $qrCodeRecord = QrCode::create([
                'sender_id' => $user->id,
                'model_of_sender' => $model_of_sender,
                'qr_code' => $qrCode,
                'amount' => $amount,
                'status' => 'active', 
            ]);

            return $this->successResponse(200, __('word.qr_code_generated_successfully'), [
                'Qr Code' => $qrCodeRecord,
                'Remark' => 'We will take %10 of the amount',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, __('word.something_went_wrong') . $e->getMessage());
        }
    }

    // convert this to web sokcet
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
            return $this->errorResponse(401, __('word.qr_code_not_found'));
        }

        if ($qrCodeData->status !== 'active') {
            return $this->errorResponse(401, __('word.qr_code_already_used'));
        }

        if (auth()->guard('vendor')->check()) {
            $receiver = auth()->guard('vendor')->user();
        } elseif (auth()->guard('user')->check()) {
            $receiver = auth()->guard('user')->user();
        } else {
            return $this->errorResponse(401, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        if ($qrCodeData->user_id) {
            $sender = User::find($qrCodeData->user_id);
        } else {
            $sender = Vendor::find($qrCodeData->vendor_id);
        }

        if (!$sender) {
            return $this->errorResponse(401, __('word.sender_not_found'));
        }

        if ($sender->balance < $qrCodeData->amount) {
            return $this->errorResponse(401, __('word.insufficient_balance'));
        }
        // if ($sender instanceof User && $receiver instanceof User) {
        //     return $this->errorResponse(401, __('word.a_user_cannot_send_money_to_another_user'));
        // }
        // if ($sender instanceof Vendor && $receiver instanceof Vendor) {
        //     return $this->errorResponse(401, __('word.a_vendor_cannot_send_money_to_another_vendor'));
        // }
        if ($sender->id == $receiver->id) {
            return $this->errorResponse(401, __('word.you_cannot_send_money_to_yourself'));
        }

        DB::beginTransaction();
        try {
            $feeAmount = 0;
            if ($sender instanceof Vendor || $sender instanceof User) {
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
            return $this->errorResponse(401, 'An error occurred while processing the transaction: ' . $e->getMessage());
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
            return $this->errorResponse(401, __('word.you_are_not_authorized_to_perform_this_action'));
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

        return $this->successResponse(200, __('word.transaction_history'), [
            'transactions' => $formattedTransactions,
        ]);
    }

    public function getNotifications(Request $request)
    {
        if (auth()->guard('vendor')->check()) {
            $user = auth()->guard('vendor')->user();
            $notifications = Notification::where('vendor_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            return $this->errorResponse(401, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        $nowNotifications = [];
        $previousNotifications = [];

        foreach ($notifications as $notification) {
            $createdAt = Carbon::parse($notification->created_at); // Explicitly parse the date

            $formattedNotification = [
                'id' => $notification->id,
                'type' => $notification->type,
                'message' => $notification->message,
                'is_read' => $notification->is_read,
                'created_at' => $createdAt->toDateTimeString(), // Optionally format the date
            ];

            if ($createdAt->isToday()) {
                $nowNotifications[] = $formattedNotification;
            } else {
                $previousNotifications[] = $formattedNotification;
            }
        }

        return $this->successResponse(200, __('word.notifications'), [
            'notifications' => [
                'now' => $nowNotifications,
                'previous' => $previousNotifications,
            ],
        ]);
    }

    public function getUserTransactions(Request $request)
    {
        if (auth()->guard('vendor')->check()) {
            $user = auth()->guard('vendor')->user();
            $userType = 'vendor';
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $userType = 'user';
        } else {
            return $this->errorResponse(401, __('word.you_are_not_authorized_to_perform_this_action'));
        }
    
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);
    
        $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
        $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
    
        if ($userType === 'vendor') {
            $transactions = Transaction::where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                      ->where('sender_type', 'vendor');
                })->orWhere(function($q) use ($user) {
                    $q->where('receiver_id', $user->id)
                      ->where('receiver_type', 'vendor');
                });
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();
        } else {
            $transactions = Transaction::where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                      ->where('sender_type', 'user');
                })->orWhere(function($q) use ($user) {
                    $q->where('receiver_id', $user->id)
                      ->where('receiver_type', 'user');
                });
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();
        }
    
        $received = $transactions->where('receiver_id', $user->id)->values();
        $sent = $transactions->where('sender_id', $user->id)->values();
    
        // إنشاء قائمة بالأيام بين from_date و to_date
        $days = collect(CarbonPeriod::create($fromDate, $toDate))->map(function ($date) {
            return $date->format('Y-m-d');
        });
    
        // تجميع الـ received لكل يوم
        $groupedReceived = $days->map(function ($day) use ($received) {
            return $received->filter(function ($transaction) use ($day) {
                return Carbon::parse($transaction->created_at)->format('Y-m-d') === $day;
            })->sum('amount');
        });
    
        // تجميع الـ sent لكل يوم
        $groupedSent = $days->map(function ($day) use ($sent) {
            return $sent->filter(function ($transaction) use ($day) {
                return Carbon::parse($transaction->created_at)->format('Y-m-d') === $day;
            })->sum('amount');
        });
    
        return $this->successResponse(200, __('word.transactions'), [
            'transactions' => [
                'received' => $groupedReceived->values(),
                'sent' => $groupedSent->values(),
            ],
        ]);
    }
    

    public function getUserTransactionsByMonth(Request $request)
    {
        if (auth()->guard('vendor')->check()) {
            $user = auth()->guard('vendor')->user();
            $userType = 'vendor';
        } elseif (auth()->guard('user')->check()) {
            $user = auth()->guard('user')->user();
            $userType = 'user';
        } else {
            return $this->errorResponse(401, __('word.you_are_not_authorized_to_perform_this_action'));
        }

        $request->validate([
            'year' => 'required|date_format:Y',
        ]);

        $year = $request->input('year');

        if ($userType === 'vendor') {
            $transactions = Transaction::where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                    ->where('sender_type', 'vendor');
                })->orWhere(function($q) use ($user) {
                    $q->where('receiver_id', $user->id)
                    ->where('receiver_type', 'vendor');
                });
            })
            ->whereYear('created_at', $year)
            ->get();
        } else {
            $transactions = Transaction::where(function($query) use ($user) {
                $query->where(function($q) use ($user) {
                    $q->where('sender_id', $user->id)
                    ->where('sender_type', 'user');
                })->orWhere(function($q) use ($user) {
                    $q->where('receiver_id', $user->id)
                    ->where('receiver_type', 'user');
                });
            })
            ->whereYear('created_at', $year)
            ->get();
        }

        $received = $transactions->where('receiver_id', $user->id)->values();
        $sent = $transactions->where('sender_id', $user->id)->values();

        // إنشاء قائمة بالأشهر للسنة المحددة
        $months = collect(range(1, 12))->map(function ($month) use ($year) {
            return Carbon::createFromDate($year, $month, 1)->format('Y-m');
        });

        // تجميع الـ received لكل شهر
        $groupedReceived = $months->map(function ($month) use ($received) {
            return $received->filter(function ($transaction) use ($month) {
                return Carbon::parse($transaction->created_at)->format('Y-m') === $month;
            })->sum('amount');
        });

        // تجميع الـ sent لكل شهر
        $groupedSent = $months->map(function ($month) use ($sent) {
            return $sent->filter(function ($transaction) use ($month) {
                return Carbon::parse($transaction->created_at)->format('Y-m') === $month;
            })->sum('amount');
        });

        return $this->successResponse(200, __('word.transactions_by_month'), [
            'transactions' => [
                'received' => $groupedReceived->values(),
                'sent' => $groupedSent->values(),
            ],
        ]);
    }
}