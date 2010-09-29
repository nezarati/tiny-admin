<?PHP
/*******************************************************************
 | 						Raha TinyAdmin Var 2.0.0
 | 			------------------------------------------------
 |	@copyright: 		(C) 2010 Raha Group, All Rights Reserved
 |	@license:		CC-BY-SA-4.0 <https://creativecommons.org/licenses/by-sa/4.0>
 |	@author: 		Mahdi NezaratiZadeh <HTTPS://Raha.Group>
 |	@since:			2010-09-29 09:20:23 GMT+0330 - 2010-11-29 09:20:23 GMT+0330
********************************************************************/
ini_set('error_reporting', E_ALL ^ E_NOTICE);
session_start();
#ob_start('ob_gzhandler');
define("GMT", time());
define("PER_PAGE", 20);
$Cookie = array("host", "user", "pass", "db", "save");
function size($w) {
	for ($s=array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"), $i=0; $w/1024>1; $i++)
		$w /= 1024;
	return round($w, 3)." ".$s[$i];
}

# http://ir.php.net/manual/en/function.array-merge-recursive.php#92195
function array_merge_recursive_distinct(array $array1, array $array2) {
  $merged = $array1;

  foreach ( $array2 as $key => &$value )
  {
    if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
    {
      $merged [$key] = array_merge_recursive_distinct ( $merged [$key], $value );
    }
    else
    {
      $merged [$key] = $value;
    }
  }

  return $merged;
}
class DB {
	public $connected = 0, $selected = 0;
	private $host, $database, $affected_rows = 0, $error = array(), $inserted_id = 0, $SQL, $time;
	public static $handle, $ns, $query;
	public function __construct($h, $u, $p, $n) {
		if (empty($_COOKIE["host"]) && empty($_SESSION["host"]))
			return;
		$h = 'localhost';
		$u = '';
		$p = '';
		(self::$handle = new Mongo('mongodb://'.trim($u.':'.$p.'@', ':@').$h)) ? $this->connected = 1 : $this->bug("Connection Failed!", "Error");
		if ($this->connected = 1)
			(self::$ns = self::$handle->$n) ? $this->selected = 1 : $this->bug("Can not select Data base!", "Error");
		$this->selected = 1;
		$this->host = $h;
		$this->database = $n;
	}
	public function query($q, $h=0) {
		$h or $this->SQL = $q;
		$m = microtime();
		$Q = @mysql_query($q, self::$handle);
		$m = explode(" ", $m);
		$M = explode(" ", microtime());
		$h or $this->time = ($M[1]+$M[0])-($m[1]+$m[0]);
		if (!$h && preg_match("'^\s*(insert|delete|update|replace) 'i", $q)) {
			$this->affected_rows = mysql_affected_rows();
			if (preg_match("'^\s*(insert|replace) 'i", $q))
				$this->inserted_id = mysql_insert_id();
		}
		$h or $this->query = $Q;
		return $Q ? $Q : $this->bug($q, "SQL");
	}
	public function details() {
		$arg = $_GET;
		$explain = self::$query->explain();
		unset($arg['page']);
		foreach ((array)$_GET['field'] as $field => $status)
			$fields .= '<label><input type="checkbox" onchange="request(\''.http_build_query(array_merge_recursive_distinct($arg, array('field' => array($field => $status ? 0 : NULL)))).'\')" '.($status ? 'checked="checked"' : '').' />'.$field.'</label>';
		return '
<div id="detail">
	<small>
	<div style="float: left">
		Databases » '.$_SESSION['db'].' » '.$_GET['table'].' ('.($count = self::$query->count()).' Documents)<sup>Query took: '.($explain['millis']/1000).' sec</sup>
		<br />
			'.ceil($count/PER_PAGE).' pages. Go to page
			<input type="text" onchange="request(\''.http_build_query($arg).'&page=\' + this.value)" value="'.$_GET['page'].'" size="4" name="page">
			<input type="button" value="Go">
	</div>
	<div style="float: right">
		'.$fields.'
		<br />
		<label for="search_input">Search</label>
		<input type="text" size="36" name="filter" onchange="request(\''.http_build_query(array('filter' => NULL) + $arg).'&filter=\' + this.value)" id="search_input" value="'.htmlspecialchars($_GET['filter']).'">
		<input type="button" value="Search" />
	</div>
	</small>
</div>';
		#for ($v=ceil(self::$query->count()/PER_PAGE), $i=1; $i<=$v; $p.="<option ".($_GET["page"] == $i ? " selected" : "").">$i</option>", $i++);
		#return "<table class=table><tr><th>Details</th></tr><tr><td>SQL query: ".htmlentities($this->SQL).($this->affected_rows ? "<br />Affected rows: $this->affected_rows" : "").($this->inserted_id ? "<br />Inserted row id: $this->inserted_id" : "")."<br />Total rows: ".DB::$query->count()."<br />Query took: $this->time sec".(strtolower(trim($this->SQL)) != "show tables" && DB::$query->count()>PER_PAGE && !preg_match("'limit\s*\d+(\s*,\s*\d+)?$'i", $this->SQL) ? "<br />Page: <select onchange=".'"return request(\''.preg_replace("'&page=\d+$'", "", $_SERVER["QUERY_STRING"]).'&page=\'+this.options[this.selectedIndex].value)"'.">$p</select>" : "")."</td></tr></table>";
	}
	public function execute() {
		return '<form method="post"><textarea name=query id=query style="width: 100%" rows=3>'.htmlentities($_POST['query']).'</textarea><input type=reset onclick="document.getElementById(\'query\').innerHTML=\'\'" value=Clear /> <input type=submit value=Submit /><input type=hidden value="execute" name="do" /><input type=button onclick="return request()" value=Index /></form>';
	}
	public function bug($e, $t) {
		#$this->error[$t][$e] = mysql_errno($this->handle).": ".mysql_error($this->handle);
	}
	public function error() {
		foreach ($this->error as $T => $E)
			foreach ($E as $q => $e)
				$r .= "<p>$T [$e]<code>$q</code></p>";
		return $r;
	}
}
class Request {
	public function login() {
		global $Cookie;
		foreach ($Cookie as $k)
			setcookie($k, $_SESSION[$k] = $_POST[$k], $_POST["save"] ? GMT+2592000 : 0);
		die(header("location: ?"));
	}
	public function save() {
		if ((string)@eval('return TRUE; '.$_POST["doc"].';')) {
			$doc = eval('return '.$_POST["doc"].';');
			$doc['_id'] = new \MongoId($_POST['_id']);
			DB::$ns->$_POST['table']->save($doc);
		}
	}
	public function delete() {
		DB::$ns->$_GET['table']->remove(array('_id' => new \MongoId($_REQUEST['_id'])));
		header('location: '.$_SERVER['HTTP_REFERER']);
		die;
	}
	public function emptyTable() {
		header('location: '.$_SERVER['HTTP_REFERER']);
		DB::$ns->execute('db.'.$_GET['table'].'.remove()');
		die;
	}
	public function drop() {
		DB::$ns->execute(isset($_GET['table']) ? 'db.'.$_GET['table'].'.drop()' : 'db.dropDatabase()');
		header('location: '.$_SERVER['HTTP_REFERER']);
		die;
	}
	public function optimize() {
		DB::$ns->execute('db.repairDatabase()');
		header('location: '.$_SERVER['HTTP_REFERER']);
		die;
	}
	public function create() {
		return $this->_form('Insert', array('_id' => new \MongoId('')));
	}
	public function modify() {
		return $this->_form('Update', DB::$ns->$_GET['table']->findOne(array('_id' => new \MongoId($_GET['_id']))));
	}
	function _form($submit, $doc) {
		$_id = $doc['_id'];
		unset($doc['_id']);
		foreach (array('_id' => $_id, 'doc' => $doc) as $field => $obj)
			$out .= '<tr bgcolor=#'.($i++%2 ? 'f8f8f8' : 'ffffff').'><td>'.$field.'</td><td>'.($field == 'doc' ? '<textarea name="'.$field.'" cols=100% rows=10>'.htmlspecialchars(var_export($obj, 1)).'</textarea>' : '<input size="40" name="'.$field.'" value="'.$obj.'" />').'</td></tr>';
		return '<form method=post action='.$_SERVER['HTTP_REFERER'].'><input type=hidden name=do value=save /><input type=hidden name=table value="'.$_GET['table'].'" /><table class=table><tr><th width=15%>Field</th><th>Value</th></tr>'.$out.'<tr><td colspan=2 align=center><input type=submit value='.$submit.' /></td></tr></table></form>';
	}
	
	public function tables() {
		$c = "<p><table class=table><tr><th>Table</th><th>Action</th><th>Record(s)</th><th>Index(s)</th><th>Real Size</th><th>Storage Size</th><th>Index Size</th><th>Total Size</th><th>File Size</th></tr>";
		foreach (DB::$handle->{$_SESSION['db']}->listCollections() as $i => $collection) {
			$t = $collection->getName();
			$s = array_shift(DB::$handle->{$_SESSION['db']}->execute(new MongoCode('function(){return db.'.$t.'.stats()}')));
			$c .= "<tr bgcolor=#".($i%2 ? "ffffff" : "f8f8f8")."><td>$t".'</td><td><ul class=img><li class='.($s['count'] ? "browse" : '"noBrowse disabled"').' title=Browse '.($s['count'] ? 'onclick="return request(\'do=find&table='.$t.'&field[_id]=0\')")' : 'style="cursor: default"').'></li><li class="explain disabled" title=Explain onclick=""></li><li class=index title=index onclick=\'return request("do=find&table=system.indexes&filter={\"ns\":\"'.$_SESSION['db'].'.'.$t.'\"}")\'></li><li class="export disabled" title=Export onclick="return request(\'do=backup&t='.$t.'\')"></li><li class=insert title=Insert onclick="return request(\'do=create&table='.$t.'\')"></li><li class=trash title=Truncate onclick="return request(\'do=emptyTable&table='.$t.'\', \'Do you really want to truncate table '.$t.' ?\')"></li><li class=drop title=Drop onclick="return request(\'do=drop&table='.$t.'\', \'Do you really want to drop table '.$t.' ?\')"></li></ul></td><td>'.$s['count'].'</td><td>'.$s['nindexes']."</td><td>".size($s['size'])."</td><td>".size($s['storageSize'])."</td><td>".size($s['totalIndexSize'])."</td><td colspan=2>".size($s['storageSize']+$s['totalIndexSize'])."</td></tr>";
		}
		$s = array_shift(DB::$handle->{$_SESSION['db']}->execute(new MongoCode('function(){return db.stats()}')));
		return $c.'<tr><td style="color: 003366">Total</td><td><ul class=img><li class=optimize title=Optimize onclick="return request(\'do=optimize\')"></li><li class=profile title=Profile onclick="return request(\'do=find&table=system.profile&sort=-millis&action=0\')"></li><li class=namespace title=Namespaces onclick="return request(\'do=find&table=system.namespaces\')"></li><li class="backup disabled" title=Backup onclick="return request(\'do=backup\')"></li><li class=drop title=Drop onclick="return request(\'do=drop\', \'Do you really want to drop database '.$_SESSION["db"].' ?\')"></li></ul></td><td>'.$s['objects'].'</td><td>'.$s['indexes'].'</td><td>'.size($s['dataSize']).'</td><td>'.size($s['storageSize']).'</td><td>'.size($s['indexSize']).'</td><td>'.size($s['storageSize']+$s['indexSize']).'</td><td>'.size($s['fileSize']).'</td></tr></table></p>';
	}
	public function execute() {
		return '<pre>'.print_R(DB::$ns->execute(new MongoCode($_POST['query'])), 1).'<pre>';
	}
	public function find() {
		$_GET['sort'] || $_GET['sort'] = '_id';
		$_GET['length'] || $_GET['length'] = PER_PAGE;
		if (is_numeric($_GET['page']))
			$_GET['offset'] = ($_GET['page'] - 1)*$_GET['length'];
		else
			$_GET['page'] = ceil($_GET['offset']/PER_PAGE) ?: 1;
		$fields = array();
		$filter = json_decode($_GET['filter']);
		if ($_GET['field']['_id'] == '0')
			unset($_GET['field']['_id']);
		foreach (DB::$query = DB::$ns->$_GET['table']->find((array)$filter, array_map('intval', (array)$_GET['field']))->skip($_GET['offset'])->limit($_GET['length'])->sort($sort = array_reduce(explode(',', $_GET['sort']), function($out, $field) {return $out += array(ltrim($field, '-') => $field[0] != '-' ? 1 : -1);}, array())) as $_id => $doc) {
			$fields += array_fill_keys(array_keys($doc), 1);
			$tds = $_GET['action'] == '0' ? '' : array('<td><ul class=img><li class=edit title=Edit onclick="return request(\'do=modify&table='.$_GET['table'].'&_id='.$_id.'\')"></li><li class=drop title=Delete onclick="return request(\'do=delete&table='.$_GET['table'].'&_id='.$_id.'\', \'Do you really want to delete this item ?\')"></li></ul></td>');
			if ($_REQUEST['field']['_id'] == '0') {
				unset($fields['_id']);
				$_GET['field']['_id'] = 0;
			}
			foreach ($fields as $field => $key)
				$tds[] = '<td'.(isset($sort[$field]) ? ' class="active"' : '').'>'.(is_array($doc[$field]) ? '<pre>'.print_r($doc[$field], TRUE).'<pre>' : substr(htmlspecialchars($doc[$field]), 0, 500)).'</td>';
			$count = max(count($tds, 1), $count);
			$trs[] = array('<tr>', $tds, '</tr>');
		}
		return '<table class="table"><thead><tr>'.($_GET['action'] == '0' ? '' : '<th style="color: 003366; width: 50px">Action</th>').'<th style="text-transform: none">'.implode('</th><th style="text-transform: none">', array_map(function($field) use($sort) {return '<input type="checkbox" checked="checked" onclick=\'request("'.http_build_query(array_merge_recursive($_GET, array('field' => array($field => 0)))).'")\' /><a href="?'.http_build_query(array('sort' => ($sort[$field] == 1 ? '-' : '').$field)+$_GET).'" style="color: black">'.$field.'</a>'.($sort[$field] == 1 ? '▲' : ($sort[$field] == -1 ? '▼' : ''));}, array_keys($fields))).'</th></tr></thead><tbody>'.implode(
			array_map(
				function($row) use($count) {
					for ($i = count($row[1]); $i<$count; $i++)
						$row[1][] = '<td />';
					$row[1] = implode($row[1]);
					return implode($row);
				},
				$trs
			)
		).'</tbody></table>';
	}
}
if ($_COOKIE["save"]) {
	if ($_COOKIE["db"] != $_SESSION["db"])
		unset($Cookie["db"]);
	foreach ($Cookie as $k)
		$_SESSION[$k] = $_COOKIE[$k];
}
$RQE = new Request();
if ($_POST['do'] == 'login')
	$RQE->$_POST['do']();
$DB = new DB($_SESSION["host"], $_SESSION["user"], $_SESSION["pass"], $_SESSION["db"]);
if ($DB->connected && $DB->selected) {
if (method_exists($RQE, $_POST['do']))
	if ($ret = $RQE->$_POST['do']())
		$response = $ret;
	else if (method_exists($RQE, $_GET['do']))
		$response = $RQE->$_GET['do']();
	else
		$response = $RQE->tables();
else if (method_exists($RQE, $_GET['do']))
	 if ($ret = $RQE->$_GET['do']())
		$response = $ret;
	else
		$response = $RQE->tables();
else
	$response = $RQE->tables();
}
?>
<html>
<head>
	<title>.:|TinyMyAdmin|:.</title>
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
			background-color: #d6dbdf;
			text-transform: uppercase;
			white-space: nowrap
		}
		.table tr:nth-child(2n) {
			background-color: #FFF;
		}
		.table tr:nth-child(2n+1) {
			background-color: #F8F8F8;
		}
		.table td.active {
			background-color: #FFFFDD;
		}
		.table tr:hover td {
			background-color: #EEE;
		}
		.img {
			position: relative; margin: 0px; display: inline
		}
		.img li {
			display: inline; float: left; background: url(data:image/png;base64,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) no-repeat; width: 16px; height: 16px; cursor: pointer; margin: 3px
		}
		.img .delete {
			background-position: 0 0
		}
		.img .browse {
			background-position: -16px 0
		}
		.img .noBrowse {
			background-position: -192px 0
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
		.img .optimize {
			background-position: -144px 0
		}
		.img .profile {
			background-position: -160px 0
		}
		.img .namespace {
			background-position: -176px 0
		}
		.disabled {
			filter:progid:DXImageTransform.Microsoft.Alpha(opacity=30); opacity: 0.3;
		}
		
	#detail {
		-moz-border-radius: 10px;
		-webkit-border-radius: 10px;
		border-radius: 10px;
		background: #f5f5f5;
		border: 1px solid #ccc;
		padding: 8px;
		margin-bottom: 15px;
		width: 100%;
		float: left;
	}
	textarea {
		padding: 10px;
		-moz-border-radius: 10px;
		-webkit-border-radius: 10px;
		border-radius: 10px;
		border: 1px solid #ccc;
		margin-top: 10px;
		margin-bottom: 10px;
	}
	</style>
	<script>
		function request(q, c) {
			if (typeof c == "undefined" || confirm(c))
				document.location.href = "?"+(typeof q != "undefined" ? q : "");
			return false;
		}
	</script>
	 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
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
								<td width=35%><input type=text name=host value="<?=empty($_SESSION["host"]) ? "localhost" : $_SESSION["host"]?>" /></td>
								<td width=15%>Database:</td>
								<td width=30%>
								<?if ($DB->connected) {?>
									<select name=db>
									<?foreach (array_shift(DB::$handle->listDBs()) as $db) {?>
										<option value="<?=$db['name']?>"<?=@$_SESSION["db"] == $db['name'] ? " selected" : ""?> style="color: <?=$db['empty']?'black':'green'?>"><?=$db['name']?>(<?=size($db['sizeOnDisk'])?>)</option>
									<?}?>
									</select>
								<?} else {?>
										<input type=text name=db value="<?=@$_SESSION["db"]?>" />
								<?}?>
								</td>
							</tr>
							<tr>
								<td>User name:</td>
								<td><input type=text name=user value="<?=@$_SESSION["user"]?>" /></td>
								<td colspan=2><input type=checkbox name=save value=true<?=@$_SESSION["save"] ? " checked" : ""?> /><label for=save>Remember in cookies</label></td>
							</tr>
							<tr>
								<td>Password:</td>
								<td><input type=password name=pass value="<?=@$_SESSION["pass"]?>" /></td>
								<td align=center colspan=2><input type=submit value=Login /></td>
							</tr>
						</table>
					</fieldset>
			</form>
		</td>
		<td width=50%><?=$DB->connected ? $DB->execute() : ""?></td>
	</tr>
	<tr>
		<td colspan=2><?=DB::$query ? $DB->details() : ''?></td>
	</tr>
	<tr>
		<td colspan=2><?=$response?></td>
	</tr>
</table>
</p>
</body>
</html>
<?="<!--\n|\tThis Program has written By MAHDI NEZARATI ZADEH\n|\tWEB : HTTPS://Raha.Group\n-->\n".str_replace("\r", "", ob_get_clean())."\n".'<span style="color: #c0c0c0; font-size: 8pt">Programmed By <a href="//raha.group" style="color: #c0c0c0; font-size: 8pt" target="_blank">Raha.Group</a></span>'."\n<!-- Powered By WWW.Raha.Group -->"?>
