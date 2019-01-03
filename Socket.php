<?php
/**
 * Created by PhpStorm.
 * User: Zeno
 * Date: 2018/11/5
 * Time: 15:24
 */

namespace Union\Utils;


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

/**
 * 设置选项失败
 */
define('ERROR_SOCKET_SETUP_FAIL', 5006);

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
     * @var string
     */
    protected $service_sn = '';


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
        //set send data timeout at 5s
        if(!socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0])){
            throw new \Exception('socket set option failed', ERROR_SOCKET_SETUP_FAIL);
        }

        if(false === socket_connect($this->socket, $config->host, $config->port)){
            throw new \Exception(socket_strerror(socket_last_error()), ERROR_SOCKET_CONNECT_FAIL);
        }

        $this->setServiceSn();
    }

    /**
     * 设置序列
     */
    protected function setServiceSn(){
        $this->service_sn = '1590204000'. date('m') .'9' . self::getServiceSNSuffix();
    }

    /**
     * 报文头
     *
     * @param $serviceId
     * @return $this
     */
    protected function setHeader($serviceId){
        $this->header = [
            'service_sn' => $this->service_sn,
            'requester_id' => '8110204',
            'channel_id' => '49',
            'service_time' => date('YmdHis'),
            'version_id' => '01',
            'service_id' => $serviceId,
            'branch_id' => '811777777'
        ];

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
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Service>';
        $xml .= '<Service_Header>';
        foreach ($this->header as $key => $value) {
            $xml .= '<' . $key . '>' . $value . '</' . $key . '>';
        }
        $xml .= '</Service_Header>';
        $xml .= '<Service_Body>';
        $xml .= '<request>';

        foreach ($this->body as $key => $value) {
            $xml .= '<' . $key . '>' . $value . '</' . $key . '>';
        }
        $xml .= '</request>';
        $xml .= '</Service_Body>';
        $xml .= '</Service>';

        $this->xml = $xml;

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
        \Functions::log('request: ', 'socket.log');
        \Functions::log($buffer, 'socket.log');
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
     * @param $serviceId
     * @param array $body
     * @throws \Exception
     */
    public function send($serviceId, $body = array()){
        $this->setHeader($serviceId)->setBody($body)->makeXML()->write();
    }

    /**
     * 获取原生结果
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
     * 获取解析后的结果
     *
     * @return array
     * @throws \Exception
     */
    public function getParseResult(){
        if(empty($this->result)){
            $this->read();
        }

        return Helper::parse(substr($this->result, 7));
    }

    /**
     * 获取序号
     *
     * @return string
     */
    public function getServiceSN(){
        return $this->service_sn;
    }

    /**
     * 获取序列号
     *
     * @return string
     */
    private static function getServiceSNSuffix(){
        $redis = \Phalcon\Di::getDefault()->get('redis');
        if(!$redis->exists('t:service_sn')){
            $redis->incr('t:service_sn');

            return '000000';
        }

        $sn = $redis->get('t:service_sn');
        if($sn > 999999){
            $redis->set('t:service_sn', 1);

            return '000000';
        }

        $redis->incr('t:service_sn');
        $sn = str_pad($sn,6,'0',STR_PAD_LEFT);

        return $sn;
    }

    /**
     * 关闭链接
     */
    public function __destruct(){
        socket_close($this->socket);
    }
}