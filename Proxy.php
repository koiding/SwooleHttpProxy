<?php

class Proxy
{
    protected $server = null;
    protected $http_methods = [
        'GET', 'CONNECT'
    ];
    protected $http_clients = [];
    protected $debug = true;

    public function __construct(string $ip, int $port)
    {
        $this->server = new Swoole\Server($ip, $port, SWOOLE_BASE);
        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->start();
    }

    public function onConnect(Swoole\Server $server, int $fd, int $from_id)
    {
        $this->log($fd, 'onConnect');
    }

    public function onReceive(Swoole\Server $server, int $fd, int $reactor_id, string $data)
    {
        $this->log($fd, 'onReceive');
        
        if($ret = $this->parseHeader($data))
        {
            $host = $ret['host'];
            $port = $ret['port'];
            $method = $ret['method'];

            $client = new Swoole\Client(SWOOLE_SOCK_TCP);
            if (!$client->connect($host, $port, -1))
            {
                exit("connect failed. Error: {$client->errCode}\n");
            }
            $this->http_clients[$fd] = $client;

            if($method == 'CONNECT')
            {
                $str = "HTTP/1.1 200 Connection Established\r\n\r\n";
                $server->send($fd, $str);
                $this->log($fd, ' https connect success');
                return;
            }

            $client->send($data);
            $server->send($fd, $client->recv());
        }

        if($client = ($this->http_clients[$fd] ?? null))
        {
            $client->send($data);
            $response = $client->recv();
            $server->send($fd, $response);
        }
    }

    public function onClose(Swoole\Server $server, int $fd, int $reactorId)
    {
        $this->log($fd, ' onClose ');
        $this->http_clients[$fd]->close();
        unset($this->http_clients[$fd]);
    }

    protected function parseHeader(string $data)
    {
        $header_arr = explode("\n", $data);
        $first_line = $header_arr[0] ?? '';
        preg_match("/^[a-zA-Z]+\b/", $first_line, $match);
        $method = $match[0] ?? '';
        if(in_array($method, $this->http_methods))
        {
            preg_match("/\S+(?=\sHTTP)/", $first_line, $match);
            $target = parse_url($match[0] ?? '');
            $host = $target['host'];
            $port = $target['port'] ?? 80;
            return ['host' => $host, 'port' => $port, 'method' => $method];
        }
        return false;
    }

    protected function log(int $fd, $data)
    {
        if($this->debug)
        {
            echo sprintf("[fd: %d] %s\r\n", $fd, $data);        
        }
    }
}