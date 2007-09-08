<?PHP
/*******************************************************************
 | 						Raha TinyAdmin Var 1.5.0
 | 			------------------------------------------------
 |	@copyright: 		(C) 2007-2008 Raha Group, All Rights Reserved
 |	@license:		CC-BY-SA-4.0 <https://creativecommons.org/licenses/by-sa/4.0>
 |	@author: 		Mahdi NezaratiZadeh <HTTPS://Raha.Group>
 |	@since:			2007-09-08 23:07:15 GMT+0330 - 2008-10-20 18:16:23 GMT+0330
********************************************************************/
header('Content-Type: Text/HTML; Charset=UTF-8');
session_start();
set_time_limit(0);
ini_set("error_reporting", 0);
!extension_loaded("zlib") || ob_start("ob_gzhandler");
define("Time", time());
define("PerPage", 30);
define("File", "DB");
get_magic_quotes_gpc() && ($_REQUEST = killmq($_REQUEST)) && ($_COOKIE = killmq($_COOKIE)) && ($_POST = killmq($_POST)) && ($_GET = killmq($_GET));
$Cookie = array("host", "user", "pass", "db", "save");
function killmq($v) {
	return is_array($v) ? array_map("killmq", $v) : stripslashes($v);
}
function size($w) {
	for ($s=array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"), $i=0; $w/1024>1; $i++)
		$w /= 1024;
	return round($w, 3)." ".$s[$i];
}
class DataBase {
	public $connected = 0, $selected = 0;
	private $host, $database, $affected_rows = 0, $error = array(), $handle, $inserted_id = 0, $query, $SQL, $time;
	protected $functions = array("CHAR" => array("ASCII", "CHAR", "SOUNDEX", "LCASE", "UCASE", "PASSWORD", "OLD_PASSWORD", "MD5", "SHA1", "ENCRYPT", "LAST_INSERT_ID", "USER", "CONCAT"), "DATE" => array("NOW", "CURDATE", "CURTIME", "FROM_DAYS", "FROM_UNIXTIME", "PERIOD_ADD", "PERIOD_DIFF", "TO_DAYS", "UNIX_TIMESTAMP", "WEEKDAY"), "NUMBER" => array("ASCII", "CHAR", "MD5", "SHA1", "ENCRYPT", "RAND", "LAST_INSERT_ID", "UNIX_TIMESTAMP", "COUNT", "AVG", "SUM"));
	public function __construct($h, $u, $p, $n) {
		if (empty($_COOKIE[File]["host"]) && empty($_SESSION[File]["host"]))
			return;
		($this->handle = @mysql_pconnect($h, $u, $p)) ? $this->connected = 1 : $this->bug("Connection Failed!", "Error");
		if ($this->connected)
			@mysql_select_db($n, $this->handle) ? $this->selected = 1 : $this->bug("Can not select Data base!", "Error");
		$this->host = $h;
		$this->database = $n;
	}
	public function query($q, $h=0) {
		if (trim($q) == '')
			return;
		$h or $this->SQL = $q;
		$m = microtime();
		$Q = @mysql_query($q, $this->handle);
		$m = explode(" ", $m);
		$M = explode(" ", microtime());
		$h or $this->time = ($M[1]+$M[0])-($m[1]+$m[0]);
		if (!$h && preg_match("'^\s*(insert|delete|update|replace) 'i", $q)) {
			$this->affected_rows = mysql_affected_rows($this->handle);
			if (preg_match("'^\s*(insert|replace) 'i", $q))
				$this->inserted_id = mysql_insert_id($this->handle);
		}
		$h || $this->query = $Q;
		return $Q || $h ? $Q : $this->bug($q, "SQL");
	}
	public function num_rows($q=null) {
		return @mysql_num_rows(isset($q) ? $q : $this->query);
	}
	public function fetch_array($q=null) {
		return @mysql_fetch_assoc(isset($q) ? $q : $this->query);
	}
	public function field_name($q=null, $c=0) {
		return @mysql_field_name(isset($q) ? $q : $this->query, $c);
	}
	public function num_fields($q=null) {
		return @mysql_num_fields(isset($q) ? $q : $this->query);
	}
	public function affected_rows() {
		return mysql_affected_rows($this->handle);
	}
	public function result($q=null, $r=0, $f=0) {
		return @mysql_result(isset($q) ? $q : $this->query, $r, $f);
	}
	public function escape($c) {
		return function_exists("mysql_real_escape_string") ? mysql_real_escape_string($c, $this->handle) : mysql_escape_string($c);
	}
	public function backup($n) {
		$Q = mysql_query("show tables like '$n%'");
		while ($r=mysql_fetch_row($Q)) {
			for ($q=mysql_query("select * from `$r[0]`"), $R=mysql_num_rows($q), $i=0, $c=$d=""; $i<$R; $d .= "$c(\n\t$C\n)", $c=", ", $i++)
				for ($j=0, $C=""; $j<mysql_num_fields($q); $j++)
					$C .= ($j ? ", " : "").(is_numeric($v=mysql_result($q, $i, $j)) ? $v : "'".str_replace(array("\x00", "\x0a", "\x0d", "\x1a"), array('\0', '\n', '\r', '\Z'), ($this->escape($v)))."'");
			!$R or $d = "\n\n# --------------------------------------------------------\n# Data contents of table `$r[0]`\n#\nINSERT INTO `$r[0]` VALUES $d;\n\n#\n# End of data contents of table `$r[0]`\n# --------------------------------------------------------\n";
			$D .= "\n# --------------------------------------------------------\n# Table: `$r[0]`\n# --------------------------------------------------------\n\nDROP TABLE IF EXISTS `$r[0]`;\n".mysql_result(mysql_query("show create table `$r[0]`"), 0, 1).";$d";
		}
		return "# Raha MySQL database backup\n#\n# Generated: ".date("l j. F Y H:i T")."\n# Hostname: $this->host\n# Database: $this->database\n# --------------------------------------------------------\n$D";
	}
	public function getUvaCondition($h, $c, $m, $r) {
		$primary_key = $unique_key = $uva_nonprimary_condition = '';
		for ($i=0; $i<$c; ++$i) {
			$field_flags = mysql_field_flags($h, $i);
			$meta = $m[$i];
			$condition = ($meta->type == "real" ? " CONCAT(`$meta->name`) " : (MYSQL_VERSION >= 40100 && ($meta->type == "string" || $meta->type == "blob") ? " CONVERT(`$meta->name` USING utf8) " : " `$meta->name` ")).(!isset($r[$i]) || is_null($r[$i]) ? "IS NULL AND" : ($meta->numeric && $meta->type != "timestamp" ? "= $r[$i] AND" : ($meta->type == "blob" && stristr($field_flags, "BINARY") && !empty($r[$i]) ? (MYSQL_VERSION < 40002 ? "LIKE 0x".bin2hex($r[$i])." AND" : "= CAST(0x".bin2hex($r[$i])." AS BINARY) AND") : "= '".$this->escape($r[$i])."' AND")));
			if ($meta->primary_key > 0)
				$primary_key .= $condition;
			elseif ($meta->unique_key > 0)
				$unique_key .= $condition;
			$uva_nonprimary_condition .= $condition;
		}
		return preg_replace("'\s?AND$'", "", $primary_key ? $primary_key : ($unique_key ? $unique_key : $uva_nonprimary_condition));
	}
	public function functions($n) {
		foreach ($this->functions as $q => $v) {
			$c .= "<optgroup label=$q>";
			foreach ($v as $v)
				$c .= "<option>$v</option>";
			$c .= "</optgroup>";
		}
		return '<select name="function['.$n.']"><option></option>'.$c.'</select>';
	}
	public function details() {
		for ($v=ceil($this->num_rows()/PerPage), $i=1; $i<=$v; $p.="<option ".($_GET["p"] == $i ? " selected" : "")." value=".$i.">$i</option>", $i++);
		return "<table class=table><tr><th>Details</th></tr><tr><td>SQL query: ".htmlentities($this->SQL).($this->affected_rows ? "<br />Affected rows: $this->affected_rows" : "").($this->inserted_id ? "<br />Inserted row id: $this->inserted_id" : "")."<br />Total rows: ".$this->num_rows()."<br />Query took: $this->time sec".(strtolower(trim($this->SQL)) != "show tables" && $this->num_rows()>PerPage && !preg_match("'limit\s*\d+(\s*,\s*\d+)?$'i", $this->SQL) ? "<br />Page: <select onchange=".'"return request(\''.preg_replace("'&p=\d+$'", "", $_SERVER["QUERY_STRING"]).'&p=\'+this.options[this.selectedIndex].value)"'.">$p</select>" : "")."</td></tr></table>";
	}
	public function SQL() {
		foreach (array("", "SHOW CREATE TABLE ", "SHOW VARIABLES LIKE '%'", "SHOW STATUS LIKE '%'", "SHOW FULL COLUMNS FROM ", "DESCRIBE ", "EXPLAIN ", "SHOW CREATE DATABASE ", "SHOW TABLE STATUS LIKE ''", "CREATE TABLE ", "SHOW TABLES", "SHOW DATABASES", "SELECT * FROM ", "INSERT INTO ", "TRUNCATE ", "DELETE FROM ", "DROP TABLE ", "DROP DATABASE ", "CREATE DATABASE ", "RENAME TABLE ", "DROP INDEX ", "CREATE INDEX ", "UPDATE ", "SHOW PROCESSLIST", "DROP USER ", "GRANT ", "SHOW FULL PROCESSLIST", "REVOKE ", "SET PASSWORD ", "FLUSH PRIVILEGES", "DROP USER ", "ANALYZE TABLE ", "CHECK TABLE ", "CHECKSUM TABLE ", "OPTIMIZE TABLE ", "REPAIR ", "RESTORE TABLE ", "SHOW GRANTS FOR ", "SHOW OPEN TABLES ", "SHOW PRIVILEGES", "SHOW WARNINGS", "SHOW ERRORS") as $v)
			$_ .= '<option value="'.$v.'">'.trim($v)."</option>";
		return '<form method=get enctype=multipart/form-data><textarea name=q id=q style="width: 100%" rows=2>'.htmlentities($this->SQL).'</textarea><br /><select onChange="v = document.getElementById(\'q\'); v.value += (v.value != \'\' ? \'\n\' : \'\')+this.options[this.selectedIndex].value">'.$_.'</select><br /><input type="file" name=import /> <input type=reset onclick="document.getElementById(\'q\').innerHTML=\'\'" value=Clear /> <input type=submit value=Submit /> <input type=button onclick="return request()" value=Index /></form>';
	}
	public function tables() {
		for ($c="<p><table class=table><tr><th>Table</th><th>Action</th><th>Record(s)</th><th>Size</th></tr>", $S=0, $R=0, $q=$this->query("show tables"), $n=$this->num_rows(), $i=0; $i<$n; $i++) {
			$t = $this->result($q, $i);
			$s = $this->fetch_array($this->query("show table status like '$t'"));
			$w = $s["Data_length"]+$s["Index_length"];
			$r = $this->result($this->query("select count(*) from ".$t));
			$S += $w;
			$R += $r;
			$c .= "<tr bgcolor=#".($i%2 ? "ffffff" : "f8f8f8")."><td>$t".'</td><td><ul class=img><li class='.($r ? "browse" : '"browse disable"').' title=Browse '.($r ? 'onclick="return request(\'q=select * from `'.$t.'`\')")' : 'style="cursor: default"').'></li><li class=explain title=Explain onclick="return request(\'q=explain `'.$t.'`\')"></li><li class=index title=index onclick="return request(\'q=show index from `'.$t.'`\')"></li><li class=export title=Export onclick="return request(\'do=backup&t='.$t.'\')"></li><li class=insert title=Insert onclick="return request(\'do=inserting&t='.$t.'\')"></li><li class=trash title=Truncate onclick="return request(\'q=truncate table `'.$t.'`\', \'Do you really want to truncate table '.$t.' ?\')"></li><li class=drop title=Drop onclick="return request(\'q=drop table `'.$t.'`\', \'Do you really want to drop table '.$t.' ?\')"></li></ul></td><td>'.$r."</td><td>".size($w)."</td></tr>";
		}
		return $c.'<tr><td style="color: 003366">Total</td><td><ul class=img><li class=backup title=Backup onclick="return request(\'do=backup\')"></li><li class=drop title=Drop onclick="return request(\'q=drop database `'.$_SESSION[File]["db"].'`\', \'Do you really want to drop database '.$_SESSION[File]["db"].' ?\')"></li></ul></td><td>'.$R.'</td><td>'.size($S).'</td></tr></table></p>';
	}
	public function publish($m) {
		if (preg_match("'^select\s+'i", $this->SQL) && !preg_match("'limit\s*\d+(\s*,\s*\d+)?$'i", $this->SQL)) {
			$this->SQL .= " limit ".(is_numeric($_GET["p"]) ? ($_GET["p"]*PerPage)-PerPage : 0).", ".PerPage;
			$this->query($this->SQL);
		}
		for ($c="<p><table class=table><tr>".($m ? '<th style="color: 003366">Action</th>' : ""), $q=$this->query, $n=$this->num_fields(), $i=0; $i<$n; $c.="<th>".$this->field_name($q, $i)."</th>", $i++);
		for ($C=mysql_num_fields($q), $M=array(), $num_fields=mysql_num_fields($q), $i=0; $i<$num_fields; $M[]=mysql_fetch_field($q, $i), $i++);
		if ($m) {
			$Q = preg_replace("'(where\s*.*)?'i", "", $_REQUEST["q"]);
			preg_match("'select\s*.*?\s*from\s*([`A-z0-9_ ]*)(\s*.*?)?'i", $Q, $t);
			$t = trim(str_replace("`", "", $t[1]));
		}
		for ($c.="</tr><tr>", $N=$this->num_rows(), $i=0; $i<$N, $r=mysql_fetch_row($q); $i++) {
			$w = urlencode($this->getUvaCondition($q, $C, $M, $r));
			$c .= "</tr><tr bgcolor=#".($i%2 ? "ffffff" : "f8f8f8").">".($m ? '<td><ul class=img><li class=edit title=Edit onclick="return request(\'do=edit&t='.$t."&w=$w".'\')"></li><li class=drop title=Delete onclick="return request(\'do=delete&t='.$t.'&w='.$w.'\', \'Do you really want to delete this item ?\')"></li></ul></td>' : "");
			for ($j=0; $j<$n; $c.="<td>".substr(htmlspecialchars($this->result($q, $i, $j)), 0, 300)."</td>", $j++);
		}
		return "$c</table></p>";
	}
	public function bug($e, $t) {
		$this->error[$t][$e] = mysql_errno($this->handle).": ".mysql_error($this->handle);
	}
	public function error() {
		foreach ($this->error as $T => $E)
			foreach ($E as $q => $e)
				$r .= "<p>$T [$e]<pre><code>$q</code></pre></p>";
		return $r;
	}
}
class Request {
	public $result;
	public function __construct() {
		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["do"]) && method_exists($this, $_POST["do"]))
			$this->result = $this->$_POST["do"]($_POST);
		elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["do"]) && method_exists($this, $_GET["do"]))
			$this->result = $this->$_GET["do"]($_GET);
	}
	public function login($_=array()) {
		global $Cookie;
		foreach ($Cookie as $k)
			setcookie(File."[$k]", $_SESSION[File][$k] = $_[$k], $_["save"] ? Time+2592000 : 0);
		die(header("location: ?"));
	}
	public function insert($_) {
		global $DB;
		foreach ($_["field"] as $c => $v) {
			$F .= "$C`$c`";
			$V.= $C.(empty($_["function"][$c]) ? "'".$DB->escape($v)."'" : $_["function"][$c]."('".$DB->escape($v)."')");
			$C = ", ";
		}
		$DB->query("insert into $_[t] ($F) values ($V);");
	}
	public function update($_) {
		global $DB;
		foreach ($_["field"] as $c => $v) {
			$q .= "$C`$c`=".(empty($_["function"][$c]) ? "'".$DB->escape($v)."'": $_["function"][$c]."('".$DB->escape($v)."')");
			$C = ", ";
		}
		empty($_["w"]) or $q .= " where $_[w]";
		$DB->query("update $_[t] set $q;");
	}
	public function delete($_) {
		global $DB;
		$DB->query("delete from $_[t] where $_[w]");
	}
	public function backup($_) {
		global $DB;
		$n = (isset($_["t"]) ? "$_[t](TBL)" : $_SESSION[File][db]."(DB)").".sql";
		header("Content-Type: text/x-delimtext; name=$n");
		header("Content-disposition: attachment; filename=$n");
		die($DB->backup(isset($_["t"]) ? $_["t"] : ""));
	}
	public function inserting($_) {
		global $DB;
		for ($DB->query("show fields from $_[t]"), $i=0; $r=$DB->fetch_array(); $c.="<tr bgcolor=#".($i%2 ? "ffffff" : "f8f8f8")."><td>$r[Field]</td><td>$r[Type]</td><td>".$DB->functions($r["Field"])."</td><td>".(stristr($r["Type"], "longtext") || stristr($r["Type"], "text") ? '<textarea name="field['.$r["Field"].']" cols=40 rows=5>'.htmlspecialchars($r["Default"]).'</textarea>' : '<input name="field['.$r["Field"].']" value="'.htmlspecialchars($r["Default"]).'" size=40 />')."</td></tr>", $i++);
		return "<form method=post action=\"".(preg_match("/^select/i", $_SESSION[File]["q"]) ? "?q=".$_SESSION[File]["q"] : "")."\"><input type=hidden name=do value=insert /><input type=hidden name=t value=".'"'.$_["t"].'"'." /><table class=table><tr><th width=15%>Field</th><th width=15%>Type</th><th width=15%>Function</th><th>Value</th></tr>$c<tr><td colspan=4 align=center><input type=submit value=Insert /></td></tr></table></form>";
	}
	public function edit($_) {
		global $DB;
		for ($DB->query("select * from `$_[t]` where $_[w]"); $r=$DB->fetch_array(); $R[]=$r);
		for ($q=$DB->query("show fields from $_[t]"), $i=0; $r=$DB->fetch_array(); $c.="<tr bgcolor=#".($i%2 ? "ffffff" : "f8f8f8")."><td>$r[Field]</td><td>$r[Type]</td><td>".$DB->functions($r["Field"])."</td><td>".(stristr($r["Type"], "longtext") || stristr($r["Type"], "text") ? '<textarea name="field['.$r["Field"].']" cols=40 rows=5>'.htmlspecialchars($R[0][$r["Field"]]).'</textarea>' : '<input name="field['.$r["Field"].']" value="'.htmlspecialchars($R[0][$r["Field"]]).'" size=40 />')."</td></tr>", $i++);
		return "<form method=post action=\"".(preg_match("/^select/i", $_SESSION[File]["q"]) ? "?q=".$_SESSION[File]["q"] : "")."\"><input type=hidden name=do value=update /><input type=hidden name=t value=".'"'.$_["t"].'"'." /><input type=hidden name=w value=".'"'.htmlspecialchars($_["w"]).'"'." /><table class=table><tr><th width=15%>Field</th><th width=15%>Type</th><th width=15%>Function</th><th>Value</th></tr>$c<tr><td colspan=4 align=center><input type=submit value=Update /></td></tr></table></form>";
	}
	public function img() {
		header('content-type: image/gif');
		die(base64_decode('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'));
	}
}
if ($_COOKIE[File]["save"]) {
	if ($_COOKIE[File]["db"] != $_SESSION[File]["db"])
		unset($Cookie["db"]);
	foreach ($Cookie as $k)
		$_SESSION[File][$k] = $_COOKIE[File][$k];
}
$DB = new DataBase($_SESSION[File]["host"], $_SESSION[File]["user"], $_SESSION[File]["pass"], $_SESSION[File]["db"]);
$DB->query("set names 'UTF8'");
if ($DB->connected && $DB->selected) {
	if ($DB->query("select version()") && $DB->num_rows() > 0) {
		$r = mysql_fetch_row($q);
		$m = explode(".", $r[0]);
	}
	define("MYSQL_VERSION", isset($r) ? (int)sprintf("%d%02d%02d", $m[0], $m[1], intval($m[2])) : 32332);
	unset($r, $m);
}
$RQE = new Request();
$_GET["do"] != "delete" or $_REQUEST["q"] = "select * from ".trim($_GET['t']);
$m = preg_match("'^(update|insert|replace|delete|drop|truncate|alter|create)'i", $_REQUEST["q"]);
$Q = empty($_REQUEST["q"]) || $m;
if ($DB->connected && $DB->selected) {
	if (isset($_FILES['import']['tmp_name']))
		foreach (preg_split('/;[\r|\n]+/', file_get_contents($_FILES['import']['tmp_name'])) as $q)
			$DB->query($q);
	#if ($m)
	#	$DB->query($_REQUEST["q"]);
	$c = 1;
	if (count($e=explode(";\r\n", $_REQUEST["q"]))) {
		foreach ($e as $q)
			if ($q != "") {
				$DB->query($q);
				$_SESSION[File]["q"] = $q;
				$c = 0;
			}
	}
	if ($c)
		$DB->query($Q ? "show tables" : $_REQUEST["q"]);
}
ob_start();
?>
<html>
<head>
	<title>.:|TinyAdmin|:.</title>
	<style>
		body {
			font-family: tahoma; font-size: 8pt; scrollbar-face-color: #dee3e7; scrollbar-light-color: #ffffff; scrollbar-shadow-color: #dee3e7; Sscrollbar-3dlight-color: #d1d7dc; scrollbar-arrow-color: #006699; scrollbar-track-color: #efefef; scrollbar-darkshadow-color: #98aab1
		}
		code {
			display: block; background-color: #f9f9f9; direction: ltr; padding: 5px; margin-top: 2px; margin-bottom: 2px; border: 1px solid #CCCCCC; font-family: verdana; font-size: 10pt
		}
		legend {
			color: #0046D5
		}
		p {
			font-family: tahoma; font-size: 10pt
		}
		fieldset {
			background: #ffffff; color: #000000; border: #D0D0BF 1px solid; font: 11px Tahoma, Verdana, sans-serif; -moz-border-radius: 5px
		}
		.table {
			font-family: tahoma; font-size: 8pt; padding: 5px; width: 100%; border-spacing: 0px 0px; border-collapse: collapse; border-bottom: solid 1px #b6c0c3; border-right: solid 1px #b6c0c3; background-color: #ffffff
		}
		.table th, .table td {
			border-top: solid 1px #b6c0c3; border-left: solid 1px #b6c0c3
		}
		.table th {
			background-color: #d6dbdf
		}
		.img {
			position: relative; margin: 0px; display: inline
		}
		.img li {
			display: inline; float: left; background: url(<?=$_SERVER['PHP_SELF'].'?do=img'?>) no-repeat; width: 16px; height: 16px; cursor: pointer; margin: 3px
		}
		.img .delete {
			background-position: 0 0
		}
		.img .browse {
			background-position: -16px 0
		}
		.img .explain {
			background-position: -32px 0
		}
		.img .index {
			background-position: -48px 0
		}
		.img .export {
			background-position: -64px 0
		}
		.img .insert {
			background-position: -80px 0
		}
		.img .trash {
			background-position: -96px 0
		}
		.img .drop {
			background-position: -112px 0
		}
		.img .edit {
			background-position: -128px 0
		}
		.disable {
			filter:progid:DXImageTransform.Microsoft.Alpha(opacity=30); opacity: 0.3;
		}
	</style>
	<script>
		function request(q, c) {
			if (typeof c == "undefined" || confirm(c))
				document.location.href = "?"+(typeof q != "undefined" ? q : "");
			return false;
		}
	</script>
</head>
<body>
<p>
<table align=center width=90%>
	<tr>
		<td width=50%>
			<form method=post style="margin: 0px">
				<input type=hidden name=do value=login />
				<fieldset style="width: 85%">
					<legend>Settings</legend>
						<table>
							<tr>
								<td width=20%>Host:</td>
								<td width=35%><input type=text name=host value="<?=empty($_SESSION[File]["host"]) ? "localhost" : $_SESSION[File]["host"]?>" /></td>
								<td width=15%>Database:</td>
								<td width=30%>
								<?if ($DB->connected && ($q=$DB->query("show databases", 1))) {?>
									<select name=db>
									<?for ($n=$DB->num_rows($q), $i=0; $i<$n; $i++) {?>
										<option value="<?=$DB->result($q, $i)?>"<?=@$_SESSION[File]["db"] == $DB->result($q, $i) ? " selected" : ""?>><?=$DB->result($q, $i)?></option>
									<?}?>
									</select>
								<?} else {?>
										<input type=text name=db value="<?=@$_SESSION[File]["db"]?>" />
								<?}?>
								</td>
							</tr>
							<tr>
								<td>User name:</td>
								<td><input type=text name=user value="<?=@$_SESSION[File]["user"]?>" /></td>
								<td colspan=2><input type=checkbox name=save value=true<?=@$_SESSION[File]["save"] ? " checked" : ""?> /><label for=save>Remember in cookies</label></td>
							</tr>
							<tr>
								<td>Password:</td>
								<td><input type=password name=pass value="<?=@$_SESSION[File]["pass"]?>" /></td>
								<td align=center colspan=2><input type=submit value=Login /></td>
							</tr>
						</table>
					</fieldset>
			</form>
		</td>
		<td width=50% valign=bottom><?=$DB->connected ? $DB->SQL() : ""?></td>
	</tr>
	<tr>
		<td colspan=2><?=empty($RQE->result) ? ($DB->connected && $DB->selected && $DB->error() == "" ? $DB->details() : $DB->error()) : ""?></td>
	</tr>
<?if ($DB->connected && $DB->selected) {?>
	<tr>
		<td colspan=2><?=empty($RQE->result) ? ($Q ? $DB->tables() : $DB->publish(preg_match("'^select'i", $_REQUEST["q"]))) : $RQE->result?></td>
	</tr>
<?}?>
</table>
</p>
</body>
</html>
<?="<!--\n|\tThis Program has written By MAHDI NEZARATIZADEH\n|\tWEB : HTTPS://Raha.Group\n-->\n".str_replace("\r", "", ob_get_clean())."\n".'<span style="color: #c0c0c0; font-size: 8pt">Programmed By <a href="//raha.group" style="color: #c0c0c0; font-size: 8pt" target="_blank">Raha.Group</a></span>'."\n<!-- Powered By WWW.Raha.Group -->"?>
