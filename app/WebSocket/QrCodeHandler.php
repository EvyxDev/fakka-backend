<?php

namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\QrCode;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class QrCodeHandler implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
    
        if (isset($data['user_id'])) {
            $this->userConnections[$data['user_id']] = $from;
            $from->send(json_encode([
                'success' => true,
                'message' => 'User registered successfully!',
                'user_id' => $data['user_id'],
            ]));
            return;
        }
    
        if (isset($data['qr_code'])) {
            $qrCode = $data['qr_code'];
            $qrCodeData = QrCode::where('qr_code', $qrCode)->first();
    
            if (!$qrCodeData) {
                $from->send(json_encode(['success' => false, 'message' => 'QR Code not found!']));
                return;
            }
    
            if ($qrCodeData->status !== 'active') {
                $from->send(json_encode(['success' => false, 'message' => 'QR Code already used!']));
                return;
            }
    
            try {
                DB::beginTransaction();
    
                $receiverId = $data['receiver_id'];
                $receiver = $this->getUserOrVendor($receiverId);
    
                if (!$receiver) {
                    $from->send(json_encode(['success' => false, 'message' => 'Receiver not authorized!']));
                    return;
                }
    
                // Determine the sender based on model_of_sender
                $sender = $this->getSenderFromQrCodeData($qrCodeData);
    
                if (!$sender) {
                    $from->send(json_encode(['success' => false, 'message' => 'Sender not found!']));
                    return;
                }
    
                if ($sender->id == $receiver->id) {
                    $from->send(json_encode(['success' => false, 'message' => 'Cannot send money to yourself!']));
                    return;
                }
    
                if ($sender->balance < $qrCodeData->amount) {
                    $from->send(json_encode(['success' => false, 'message' => 'Insufficient balance!']));
                    return;
                }
    
                $feeAmount = $qrCodeData->amount * 0.10;
                $transactionAmount = $qrCodeData->amount - $feeAmount;
    
                $sender->balance -= $qrCodeData->amount;
                $sender->save();
    
                $receiver->balance += $transactionAmount;
                $receiver->save();
    
                $transaction = Transaction::create([
                    'sender_id' => $sender->id,
                    'sender_type' => $qrCodeData->model_of_sender, // Using the model_of_sender
                    'receiver_id' => $receiver->id,
                    'receiver_type' => $receiver instanceof User ? 'user' : 'vendor',
                    'amount' => $qrCodeData->amount,
                    'status' => 'completed',
                ]);
    
                if ($feeAmount > 0) {
                    Fee::create(['transaction_id' => $transaction->id, 'amount' => $feeAmount]);
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
    
                $qrCodeData->receiver_id = $receiver->id; 
                $qrCodeData->model_of_receiver = $receiver instanceof User ? 'user' : 'vendor';
                $qrCodeData->status = 'used';
                $qrCodeData->save();
    
                // Notify sender
                if (isset($this->userConnections[$sender->id])) {
                    $this->userConnections[$sender->id]->send(json_encode([
                        'success' => true,
                        'message' => 'Your QR code has been used successfully.',
                        'qr_code' => $qrCode,
                        'amount' => $qrCodeData->amount,
                    ]));
                }
    
                DB::commit();
    
                $from->send(json_encode([
                    'success' => true,
                    'message' => 'Transaction successful!',
                    'amount' => $transactionAmount,
                    'fee' => $feeAmount,
                ]));
            } catch (\Exception $e) {
                DB::rollBack();
                $from->send(json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]));
            }
            return;
        }
    
        $from->send(json_encode(['success' => false, 'message' => 'Invalid request!']));
    }
    

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function getSenderFromQrCodeData($qrCodeData)
    {
        if ($qrCodeData->model_of_sender == 'user') {
            return User::find($qrCodeData->sender_id);
        } elseif ($qrCodeData->model_of_sender == 'vendor') {
            return Vendor::find($qrCodeData->sender_id);
        }
    
        return null;
    }

    private function getUserOrVendor($receiverId)
    {
        $user = User::find($receiverId);
        if ($user) {
            return $user;
        }

        $vendor = Vendor::find($receiverId);
        if ($vendor) {
            return $vendor;
        }
        return null;
    }

}
