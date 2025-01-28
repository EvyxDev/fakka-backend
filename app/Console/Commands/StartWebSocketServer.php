<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use App\WebSocket\QrCodeHandler;

class StartWebSocketServer extends Command
{
    protected $signature = 'websocket:start';
    protected $description = 'Start the WebSocket server';

    public function handle()
    {
        $this->info('Starting WebSocket server...');

        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new QrCodeHandler() // Handler responsible for WebSocket events
                )
            ),
            8080 // Port to run the server on
        );

        $server->run();
    }
}