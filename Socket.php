<?php

/**
 * socket 创建失败
 */
define('ERROR_SOCKET_CREATE_FAIL', 5002);

/**
 * 连接失败
 */
define('ERROR_SOCKET_CONNECT_FAIL', 5003);

/**
 * 数据发送失败
 */
define('ERROR_SOCKET_WRITE_FAIL', 5004);

/**
 * 读取数据失败
 */
define('ERROR_SOCKET_READ_FAIL', 5005);


class Socket {

    /**
     * @var bool|resource
     */
    protected $socket = false;

    /**
     * @var array
     */
    protected $header = [];

    /**
     * @var array
     */
    protected $body = [];

    /**
     * @var string
     */
    protected $xml = '';

    /**
     * @var string
     */
    protected $result = '';

    /**
     * Socket  constructor.
     * @param  $config
     * @throws \Exception
     */
    public function __construct(Config $config) {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if($this->socket === false){
            throw new \Exception(socket_strerror(socket_last_error()), ERROR_SOCKET_CREATE_FAIL);
        }

        if(false === socket_connect($this->socket, $config->host, $config->port)){
            throw new \Exception(socket_strerror(socket_last_error()), ERROR_SOCKET_CONNECT_FAIL);
        }
    }


    /**
     * 报文头
     *
     * @param  array  $header
     * @return $this
     */
    protected function setHeader($header){
        $this->header = $header;

        return $this;
    }

    /**
     * 报文主体
     *
     * @param $body
     * @return $this
     */
    protected function setBody($body){
        $this->body = $body;

        return $this;
    }

    /**
     * 拼接xml
     *
     * @return $this
     */
    protected function makeXML(){
        $this->xml = '<?xml version="1.0" encoding="UTF-8"?><Service>';
        $this->xml .= '<Service_Header>';
        foreach ($this->header as $key => $value) {
            $this->xml .= '<' . $key . '>' . $value . '</' . $key . '>';
        }
        $this->xml .= '</Service_Header>';
        $this->xml .= '<Service_Body>';
        $this->xml .= '<request>';

        foreach ($this->body as $key => $value) {
            $this->xml .= '<' . $key . '>' . $value . '</' . $key . '>';
        }
        $this->xml .= '</request>';
        $this->xml .= '</Service_Body>';
        $this->xml .= '</Service>';

        return $this;
    }

    /**
     * 发送报文
     *
     * @return $this
     * @throws \Exception
     */
    protected function write(){
        $buffer = str_pad(strlen($this->xml), 7, '0', STR_PAD_LEFT) . $this->xml;
        $ret = socket_write($this->socket, $buffer, strlen($buffer));
        if($ret === false){
            throw new \Exception(socket_strerror(socket_last_error($this->socket)), ERROR_SOCKET_WRITE_FAIL);
        }

        return $this;
    }

    /**
     * 读取结果
     *
     * @return string
     * @throws \Exception
     */
    protected function read(){
        $this->result = '';
        while ($res = socket_read($this->socket, 8192)){
            if($res === false){
                throw new \Exception(socket_strerror(socket_last_error($this->socket)), ERROR_SOCKET_READ_FAIL);
            }

            $this->result .= $res;
        }
    }

    /**
     * 发送请求
     *
     * @param array $header
     * @param array $body
     * @throws \Exception
     */
    public function send($header = array(), $body = array()){
        $this->setHeader($header)->setBody($body)->makeXML()->write();
    }

    /**
     * 获取结果
     *
     * @return string
     * @throws \Exception
     */
    public function getResult(){
        if(empty($this->result)){
            $this->read();
        }

        return $this->result;
    }


    /**
     * 关闭链接
     */
    public function __destruct(){
        socket_close($this->socket);
    }
}