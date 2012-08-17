<?php error_reporting(E_ERROR);
function autoload($class) {
	include('includes/'. $class. '.php');
}

spl_autoload_register('autoload');

################### SETTINGS #########################

// IMPORTANT: CONFIGURE MYSQL LOGIN IN INCLUDES/MYSQLOBJ.PHP

$mysql = new mysqlobj;
$mysql->table = 'job_board';

// Create table if doesn't exist
$mysql->query("CREATE TABLE IF NOT EXISTS `". $mysql->table. "` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('job','date') NOT NULL DEFAULT 'job',
  `group_name` varchar(255) NOT NULL DEFAULT 'Group Name',
  `name` varchar(255) NOT NULL DEFAULT 'Project Name',
  `description` varchar(255) NOT NULL DEFAULT 'Description',
  `complete` tinyint(1) NOT NULL DEFAULT '0',
  `prospective` tinyint(1) NOT NULL DEFAULT '0',
  `due` datetime NOT NULL,
  `finished` datetime NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `select` (`type`,`complete`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;");

################# POST ACTIONS #######################

// weird time zone conversion with strtotime. Really don't want to deal with this.
date_default_timezone_set('UTC');
$timeoffset = 18000;

switch($_POST['action']) {
	case 'load' :
		$active = $mysql->select('*', 'type = "job" AND complete = 0 AND prospective = 0', array('orderby' => 'due, name', 'sort' => 'ASC'));
		foreach($active as $index => $value) {
			$active[$index]['due'] = strtotime($value['due'])+$timeoffset;
			$active[$index]['finished'] = strtotime($value['finished'])+$timeoffset;
		}
		$completed = $mysql->select('*', 'type = "job" AND complete = 1', array('orderby' => 'finished', 'sort' => 'DESC'));
		foreach($completed as $index => $value) {
			$completed[$index]['due'] = strtotime($value['due'])+$timeoffset;
			$completed[$index]['finished'] = strtotime($value['finished'])+$timeoffset;
		}
		$prospective = $mysql->select('*', 'type="job" AND complete = 0 AND prospective = 1', array('orderby' => 'name', 'sort' => 'ASC'));
		$dates = $mysql->select('*', 'type="date" AND complete = 0');
		$date_sort = array(); // Try to sort dates by description
		$dates_sorted = array(); // Note: this doesn't use a date field to support easy date ranges, and time difference calculations aren't necessary
		foreach($dates as $index => $value) {
			$date_sort[$index] = strtotime($value['description']);
		}
		asort($date_sort);
		foreach($date_sort as $index => $value) {
			$dates_sorted[] = $dates[$index];
		}
		$data = array('active' => $active, 'completed' => $completed, 'prospective' => $prospective, 'dates' => $dates_sorted);
		echo json_encode($data);
		break;

	case 'complete' :
		if($mysql->update(array('complete' => 1, 'finished' => date('Y-m-d H:i:s')), 'ID = '. $_POST['id'])) {
			echo 'Success';
		}
		break;

	case 'uncomplete' :
		if($mysql->update(array('complete' => 0), 'ID = '. $_POST['id'])) {
			echo 'Success';
		}
		break;
		
	case 'delete' :
		if($mysql->delete('ID = '. $_POST['id'])) {
			echo 'Success';
		}
		break;
		
	case 'update' :
		$action = $_POST['action'];
		$id = $_POST['id'];
		unset($_POST['action'], $_POST['id']);
		$values = array();
		foreach($_POST as $field => $value) {
			if(!empty($value) || $value === '0') {
				switch($field) {
					case 'due' : // int value will be > 10 mil if unix time; else, evaluate
						$value = ((int)$value > 10000000) ? date('Y-m-d H:i:s', $value) : date('Y-m-d H:i:s', strtotime($value));
						break;
				}
				$values[$field] = $value;
			}
		}
		if($mysql->update($values, 'ID = '. $id)) {
			echo 'Success';
		}
		break;

	case 'add' :
		$type = $_POST['type'];
		switch($type) {
			case 'prospective' :
				$id = $mysql->insert(array('prospective' => 1, 'group_name' => 'Group Name', 'name' => 'Project Name', 'description' => 'Description', 'due' => date('Y-m-d H:i:s')));
				if($id > 0) {
					$data = $mysql->select('*', "ID = '". $id. "'", array('limit' => 1));
					$data[0]['due'] = strtotime($data[0]['due']);
					echo json_encode($data[0]);
				}
				break;
			case 'date' : 
				$today = date('M j');
				$id = $mysql->insert(array('type' => 'date', 'group_name' => 'Group Name', 'name' => 'Event', 'description' => $today, 'due' => date('Y-m-d H:i:s')));
				if($id > 0) {
					$data = $mysql->select('*', "ID = '". $id. "'", array('limit' => 1));
					$data[0]['due'] = strtotime($data[0]['due']);
					echo json_encode($data[0]);
				}
				break;
			default :
				$id = $mysql->insert(array('group_name' => 'Group Name', 'name' => 'Project Name', 'description' => 'Description', 'due' => date('Y-m-d H:i:s')));
				if($id > 0) {
					$data = $mysql->select('*', "ID = '". $id. "'", array('limit' => 1));
					$data[0]['due'] = strtotime($data[0]['due']);
					echo json_encode($data[0]);
				}
				break;
		}
		break;

	default :
		if(!empty($_GET['csv'])) {
			header("Content-type: application/csv");
			header("Content-Disposition: attachment; filename=".$mysql->table."-".date('Y-m-d').".csv");
			header("Pragma: no-cache");
			header("Expires: 0");
			$columns = $mysql->columns();
			$fields = array();
			foreach($columns as $column) {$fields[] = $column['Field'];}echo implode($_GET['csv'], $fields), "\n";
			$rows = $mysql->select();
			foreach($rows as $row) {echo implode($_GET['csv'], $row);}echo "\n";
		} else {

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>KMDG Job Board</title>
<link rel="stylesheet" type="text/css" href="job-board.css" />
<link rel="stylesheet" type="text/css" href="js/jquery-ui-1.8.21.css" />
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.21.js"></script>
<script type="text/javascript" src="http://use.typekit.com/gho6qsk.js"></script>
<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
</head>
<body>
<div id="main">
<nav><ul id="tabs"><li><a id="active" class="selected" href="#">Active</a></li><li><a id="completed" href="#">Completed</a></li></ul></nav>
<a class="new" href="#">+ New Job</a>
<div id="jobs"><div id="jobs-active" class="selected"></div><div id="jobs-completed"></div></div>
<div id="prospective"></div>
<a class="new" href="#">+ New Job</a>
</div>
<aside id="extras"><a href="#" class="new-prospective">+ New Prospective Job</a>
  <section id="jobs-prospective"></section>
  <a href="#" class="new-date">+ New Miscellaneous</a>
  <section id="dates"></section>
</aside>
<script type="text/javascript" src="js/global.js"></script>
</body>
</html>
<?php	}
	break;
}
?>