<?php
require('config.php');

class db {
	protected
		$pdo;
	
	protected function __construct(){
		$this->pdo = new PDO(DSN, DB_USER, DB_PASS);
		$this->pdo->query("set names utf8")->execute();
	}
	
	/**
	 * @staticvar null $self
	 * @return db 
	 */
	public static function Instance(){
		static $self = null;
		if ($self) {
			return $self;
		}
		return $self = new self();
	}
	
	/**
	 * @param type $sql 
	 * 
	 */
	public function FetchAll($sql, $cache = false){
		if ($cache) {
			$filename = "cache/".md5($sql).".txt";
			if (file_exists($filename)) {
				return unserialize(file_get_contents($filename));
			}
		}
		
		$result = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		
		if ($cache) {
			file_put_contents($filename, serialize($result));
		}
		return $result;
	}
	
	/**
	 * return PDO 
	 */
	public function PDO(){
		return $this->pdo;
	}
}

function issetdef(&$var, $default = null){
	if (isset($var)) {
		return $var;
	}
	
	return $var = $default;
}

function mergeTrees($treeList = array())
{
	if (count($treeList) > 5) {
		list($half1, $half2) = array_chunk($treeList, (int)(count($treeList)/2) + count($treeList) % 2);
		$halfTree1 = mergeTrees($half1);
		$halfTree2 = mergeTrees($half2);
		return mergeTrees(array($halfTree1, $halfTree2));
	}
	$result = array();
	foreach ($treeList as $tree) {
		foreach ($tree as $branch) {
			$resultExists = false;
			foreach ($result as &$val) {
				if ($val['tags'] == $branch['tags']) {
					$resultExists = true;
					break;
				}
			}
			if ($resultExists) {
				$val['value'] = (float)$branch['value'] + (float)$val['value'];
				$val['count'] += $branch['count'];
				
				if (!empty($branch['children'])) {
					$val['children'] = mergeTrees(array($val['children'], $branch['children']));
				}
			} else {
				$result[] = $branch;
			}
			unset($val);
			
		}
	}
	return $result;
}

function buildTree($data, $level = 0) {
	foreach ($data as $key => &$val) {
		$id = $val['tree_id'];
		
		if (!empty($_GET['debug'])) {
			echo str_repeat("   ", $level),$id,"\n";
		}
		
		$sql = "SELECT value, tree_id, tree_parent_id, tags, 1 as count FROM timer_tag_tree WHERE tree_parent_id='".addslashes($id)."'";
		$new = db::Instance()->FetchAll($sql);
		$subtree = buildTree($new, $level + 1);
		$val['children'] = $subtree;
	}
	
	return $data;
}

$scripts = array();

$sql = "
SELECT distinct script_name
FROM timer_tag_tree AS root
join request on request.id=root.request_id
join timer_tag_tree as child on root.tree_id=child.tree_parent_id
join timer_tag_tree as child2 on child.tree_id=child2.tree_parent_id
WHERE root.tree_parent_id='root'";

$temp = db::Instance()->FetchAll($sql, true);

foreach ($temp as $val) {
	$scripts[] = $val['script_name'];
}

$script_name = issetdef($_GET['script_name']);

if (!empty($script_name)) {
	$filename = "cache/$script_name.txt";
	if (file_exists($filename)) {
		$tree = unserialize(file_get_contents($filename));
	} else {
		$sql = "SELECT root.value, root.tree_id, root.tree_parent_id, root.tags, 1 as count
				FROM timer_tag_tree AS root
				join request on request.id=root.request_id
				join timer_tag_tree as child on root.tree_id=child.tree_parent_id
				join timer_tag_tree as child2 on child.tree_id=child2.tree_parent_id

				WHERE root.tree_parent_id='root'
				and request.script_name='".addslashes($script_name)."'
				ORDER BY rand() desc
				LIMIT 1000";

		$list = db::Instance()->FetchAll($sql);
		$treeList = array();
		foreach($list as $data) {
			$tree = buildTree(array($data), 0);

			$treeList[] = $tree;
		}

		$tree = mergeTrees($treeList);
		file_put_contents($filename, serialize($tree));
	}
}


?>


<!DOCTYPE html>
<html>
	<head>
		<title>Профайлинг скриптов</title>
		<script src="http://code.jquery.com/jquery-1.7.2.min.js"></script>
	</head>
	<body>
		<form method="get" action="/" onchange="this.submit();">
			<table style="width: 800px; margin: auto; border: none">
				<tr>
					<td>Скрипт: </td>
					<td>
						<select name="script_name" onchange="this.form.submit()">
							<option> - chose - </option>
							<?foreach ($scripts as $script) {?>
								<option <?if ($script == $script_name) {?>selected="selected"<?}?>><?=$script?></option>
							<?}?>
						</select>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<h2>Профайлинг</h2>
						<?if (!empty($tree)) {?>
							<div style="width: 1000px; margin: auto">
								<?showTree($tree, $tree[0]['value'], 0, $tree[0]['value']);?>
							</div>
						<?}?>
					</td>
				</tr>
			</table>
		</form>
	</body>
</html>


<?
function showTree($tree, $all_time, $level=0, $global_time) {
	foreach ($tree as $branch) {?>
		<div class="row" style="border-left: solid 2px grey; padding-left: 5px; margin-left: 5px;">
			<div style="">
				<a onclick="$('#<?=str_replace(":", "_", $branch['tree_id'])?>').toggle('fast'); return false;" <?if (!empty($branch['children'])){?>href="#" style="cursor: pointer" <?}?>>
					<?=$branch['tags']?>
					(<?=sprintf("%.2f", 100 * $branch['value']/$global_time);?>%, <?=sprintf("%.2f", 1000*$branch['value']/$branch['count'])?>ms, <?=$branch['count']?> requests)
				</a>
				<?if (!empty($branch['children'])) {?>
					<button onclick="$('section.list, section.tree:first', $('#<?=str_replace(":", "_", $branch['tree_id'])?>')).toggle(); return false;">Списком/деревом</button>
				<?}?>
				<?//$branch['tree_id'];?>
			</div>
			<div style="width: 100px; float: right">
				<div style="border: solid 1px #ccc;">
					<div style="height: 20px;float: left; margin: 1px; padding: 1px; width: <?=(int)(100 * $branch['value']/$global_time)?>%; background: green;"></div>
				</div>
			</div>
			<div style="clear: both"></div>
			<?if (!empty($branch['children'])) {?>
				<div id="<?=str_replace(":", "_", $branch['tree_id'])?>" style="<?if ($branch['value']/$global_time < 0.20) {?>display: none<?}?>">
					<section class="tree">
						<?showTree($branch['children'], $branch['value'], $level+1, $global_time);?>
					</section>

					<section class="list" style="display: none; border-left: solid 1px blue; margin: 5px; padding: 5px;">
						<?showList($branch['children'], $branch['value'], $level+1, $global_time);?>
					</section>
				</div>
			<?}?>
		</div>
	<?}
}

function showList($tree, $all_time, $level=0, $global_time)
{
	$list = Tree2List($tree);
	
	foreach ($list as $val) {
		?>
			<div class="row">
				<?=$val['tags']?>	(<?=sprintf("%.2f", 100 * $val['value']/$all_time)?>%)
			</div>
		<?
	}
}


function Tree2List($tree)
{
	$list = array();
	
	foreach ($tree as $val) {
		if (!isset($list[$val['tags']])) {
			$list[$val['tags']] = $val;
		} else {
			$list[$val['tags']]['value'] = (float)$list[$val['tags']]['value'] + (float)$val['value'];
		}
		if (!empty($val['children'])) {
			$sublist = Tree2List($val['children']);
			foreach ($sublist as $val2) {
				if (!isset($list[$val2['tags']])) {
					$list[$val2['tags']] = $val2;
				} else {
					$list[$val2['tags']]['value'] = (float)$list[$val2['tags']]['value'] + (float)$val2['value'];
				}
			}
		}
	}
	
	usort($list, function($a, $b){return (float)$b['value'] - (float)$a['value'];});
	
	return $list;
}

?>
