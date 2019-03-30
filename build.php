<?php

chdir(__DIR__);

date_default_timezone_set('Asia/Shanghai');

is_dir('./data') || mkdir('./data');

confirm("继续操作将覆盖原数据,请确认") || exit;

////////////////////////////////////////////////////

//开始时间
$time1 = time();

echo("获取APNIC数据\n");
$apnic = get_apnic_data();

echo("获取中国IPv4地址\n");
get_country_ipv4('china', 'cn', $apnic);

echo("获取海外IPv4地址\n");
get_country_ipv4('oversea', '(?!cn)\w{2}', $apnic);

//计算耗时
$time2 = time() - $time1;
echo("\n\n[+] 转换过程消耗时长约为{$time2}秒.");

////////////////////////////////////////////////////

/**
 * 询问操作
 */
function confirm($text) {
    echo("{$text}[yes/no]: ");
    $stat = trim(fgets(STDIN));
    return $stat == 'y' || $stat == 'yes';
}

/**
 * 获取APNIC数据
 */
function get_apnic_data() {
	$file = './data/apnic.txt';
	if(is_file($file) && filectime($file) > strtotime('-1 day')) {
		return file_get_contents($file);
	}
	$site = 'http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest';
	file_put_contents($file, $data = file_get_contents($site));
	return $data;
}

/**
 * 获取指定国家IPv4地址
 */
function get_country_ipv4($name, $expr, &$data) {
	$expr = "/apnic\|{$expr}\|ipv4\|([0-9\.]+\|[0-9]+)\|[0-9]+\|a.*/i";
	preg_match_all($expr, $data, $match);
	$rest = array(array(), array());
	foreach($match[1] as $val) {
		list($net, $ips) = explode('|', $val);
		$rest[0][] = $net.'/'.(32 - log($ips, 2));
		$rest[1][] = $net.'/'.(long2ip(ip2long('255.255.255.255') << log($ips, 2)));
	}
	file_put_contents("./data/apnic_{$name}_0_v4.txt", implode("\n", $rest[0]));
	file_put_contents("./data/apnic_{$name}_1_v4.txt", implode("\n", $rest[1]));
	//生成Linux路由表
	$route = 'route add -net $1 netmask $2 gw ${gwip}';
	$route =  preg_replace('@([^/]+)/([^/]+)@', $route, $rest[1]);
	file_put_contents("./data/apnic_{$name}_v4_linux_route.add", implode("\n", $route));
}

///////////////////////////////////////////////////////////////////////////////////////////////

/**
 * IPv4地址转换类
 * $ip = new ipv4('192.168.2.1', 24);
 */

class ipv4 {
	//变量表
	private $address;
	private $netbits;
	//构造函数
	public function __construct($address, $netbits, $type = '') {
		$this->address = $address;
		$this->netbits = $netbits;
		if($type == 'netips') {
			$this->set_netbits_by_netips();
		}
	}
	//获取IP地址
	public function address() {
		return ($this->address);
	}
	//获取掩码位数
	public function netbits() {
		return ($this->netbits);
	}
	//获取网络掩码
	public function netmask() {
		return (long2ip(ip2long('255.255.255.255') << (32 - $this->netbits)));
	}
	//获取反掩码
	public function inverse() {
		return (long2ip( ~ (ip2long('255.255.255.255') << (32 - $this->netbits))));
	}
	//获取子网地址
	public function network() {
		return (long2ip((ip2long($this->address)) & (ip2long($this->netmask()))));
	}
	//获取广播地址
	public function broadcast() {
		return (long2ip(ip2long($this->network()) | ( ~ (ip2long($this->netmask())))));
	}
	//根据可用IP获取掩码位数
	private function set_netbits_by_netips() {
		$this->netbits = 32 - log($this->netbits, 2);
	}
}
