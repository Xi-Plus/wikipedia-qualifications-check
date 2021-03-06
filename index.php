<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>zhwiki qualifications check</title>
</head>
<body>
<?php
date_default_timezone_set('UTC');
echo "現在時間: ".date("Y/m/d H:i")."<br>";
$api = 'https://zh.wikipedia.org/w/api.php';
$user = (isset($_GET["user"]) ? $_GET["user"] : "");
$type = (isset($_GET["type"]) ? $_GET["type"] : "");
$require = [
	"patroller" => [
		"registration" => false,
		"editcount" => 250,
		"firstedit" => strtotime("-30 days"),
		"active" => true
	],
	"rollbacker" => [
		"registration" => false,
		"editcount" => 1000,
		"firstedit" => strtotime("-90 days"),
		"active" => true
	],
	"autoconfirmed" => [
		"registration" => strtotime("-7 days"),
		"editcount" => 50,
		"firstedit" => false,
		"active" => false
	],
	"autoconfirmed_tor" => [
		"registration" => strtotime("-90 days"),
		"editcount" => 100,
		"firstedit" => false,
		"active" => false
	]
];
$rightname = [
	"patroller" => "巡查",
	"rollbacker" => "回退",
	"autoconfirmed" => "自動確認",
	"autoconfirmed_tor" => "自動確認（透過Tor編輯）"
];
?>
<form>
	<table>
		<tr>
			<td>Username:</td>
			<td>
				<input type="text" name="user" value="<?=htmlspecialchars($user)?>" required autofocus>
			</td>
		</tr>
		<tr>
			<td>right:</td>
			<td>
				<select name="type">
					<option value="patroller" <?=($type=="patroller"?"selected":"")?>><?=$rightname["patroller"]?></option>
					<option value="rollbacker" <?=($type=="rollbacker"?"selected":"")?>><?=$rightname["rollbacker"]?></option>
					<option value="autoconfirmed" <?=($type=="autoconfirmed"?"selected":"")?>><?=$rightname["autoconfirmed"]?></option>
					<option value="autoconfirmed_tor" <?=($type=="autoconfirmed_tor"?"selected":"")?>><?=$rightname["autoconfirmed_tor"]?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td></td>
			<td><button type="submit">check</button></td>
		</tr>
	</table>
</form>
<?php
if ($user === "") {
	exit();
}

$url = $api.'?action=query&format=json&list=users&usprop=editcount%7Cregistration&ususers='.urlencode($user);
$res = file_get_contents($url);
if ($res === false) {
	exit("get user info fail");
}
$uinfo = json_decode($res, true);
$uinfo = $uinfo["query"]["users"][0];
if (isset($uinfo["missing"])) {
	exit("user not found");
}

if (!array_key_exists($type, $require)) {
	exit("type error");
}

$registration = strtotime($uinfo["registration"]);
$editcount = $uinfo["editcount"];

$url = $api.'?action=query&format=json&list=usercontribs&uclimit=1&ucdir=newer&ucuser='.urlencode($user);
$res = file_get_contents($url);
if ($res === false) {
	exit("get firstedit fail");
}

$firstedit = json_decode($res, true);
if (count($firstedit["query"]["usercontribs"]) === 0) {
	$firstedit = 0;
} else {
	$firstedit = $firstedit["query"]["usercontribs"][0];
	$firstedit = strtotime($firstedit["timestamp"]);
}

if ($registration > strtotime("-3 months")) {
	$activedays = floor((time()-$registration)/86400);
} else {
	$activedays = floor((time()-strtotime("-3 months"))/86400);
	$url = $api.'?action=query&format=json&list=usercontribs&uclimit='.$activedays.'&ucdir=older&ucuser='.urlencode($user);
	$res = file_get_contents($url);
	if ($res === false) {
		exit("get usercontribs fail");
	}
	$activeedit = json_decode($res, true);
	$activeedit = end($activeedit["query"]["usercontribs"]);
	$activeedit = strtotime($activeedit["timestamp"]);
}
echo "檢查 ".htmlspecialchars($user)." 的 ".$rightname[$type]." 資格如下";
?>
<table>
	<tr>
		<th>資格</th>
		<th>用戶</th>
		<th>要求</th>
		<th>檢查結果</th>
	</tr>
	<tr>
		<td>註冊日期</td>
		<td><?=date("Y/m/d H:i", $registration)?></td>
		<td><?php
		if ($require[$type]["registration"]) {
			echo "< ".date("Y/m/d H:i", $require[$type]["registration"]);
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["registration"] && $registration > $require[$type]["registration"]) {
			echo "fail";
		} else {
			echo "pass";
		}
		?></td>
	</tr>
	<tr>
		<td>編輯次數</td>
		<td><?=$editcount?></td>
		<td><?php
		if ($require[$type]["editcount"]) {
			echo ">= ".$require[$type]["editcount"];
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["editcount"] && $editcount < $require[$type]["editcount"]) {
			echo "fail";
		} else {
			echo "pass";
		}
		?></td>
	</tr>
	<tr>
		<td>首次編輯</td>
		<td><?php
		if ($firstedit === 0) {
			echo "從未編輯";
		} else {
			echo date("Y/m/d H:i", $firstedit);
		}
		?></td>
		<td><?php
		if ($require[$type]["firstedit"]) {
			echo "< ".date("Y/m/d H:i", $require[$type]["firstedit"]);
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["firstedit"] && ($firstedit === 0 || $firstedit > $require[$type]["firstedit"])) {
			echo "fail";
		} else {
			echo "pass";
		}
		?></td>
		<td></td>
	</tr>
	<tr>
		<td>活躍程度</td>
		<td><?php
		if ($registration > strtotime("-3 months")) {
			echo "註冊以來 ".$editcount."編輯";
		} else {
			echo "最新第".$activedays."筆編輯在".date("Y/m/d H:i", $activeedit);;
		}
		?></td>
		<td><?php
		if ($require[$type]["active"]) {
			echo "3個月內或註冊以來(".$activedays."天)平均每日1編輯<br>";
			if ($registration > strtotime("-3 months")) {
				echo "註冊以來 > ".$activedays."編輯";
			} else {
				echo "最新第".$activedays."筆編輯 >= ".date("Y/m/d H:i", strtotime("-3 months"));
			}
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["active"] === false) {
			echo "pass";
		} else if ($registration > strtotime("-3 months")) {
			if ($editcount > $activedays) {
				echo "pass";
			} else {
				echo "fail";
			}
		} else {
			if ($activeedit > strtotime("-3 months")) {
				echo "pass";
			} else {
				echo "fail";
			}
		}
		?></td>
		<td></td>
	</tr>
</table>
</body>
</html>
