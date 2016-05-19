function refValues($arr){
	if (strnatcmp(phpversion(),'5.3') >= 0)
	{
		$refs = array();
		foreach($arr as $key => $value)
			$refs[$key] = &$arr[$key];
		return $refs;
	}
	return $arr;
}

function _bd(){
	global $_bd;
	$h="localhost";
	$l="deight";
	$p="pas";
	$b="base";
	$tmp=false;
	if(is_null($_bd)){
		$_bd = new mysqli($h,$l,$p,$b);
		$_bd->set_charset("cp1251");
	}elseif(is_null($_bd->stat)){
		$tmp = new mysqli($h,$l,$p,$b);
		$tmp->set_charset("cp1251");
	}
	return $tmp?:$_bd;
}

function _db($q,$s='',$v=[],$p=[]){
	if(strlen($s)!=count($v))
		return false;
	$stmt=_bd()->prepare($q);
	if(strlen($s)){
		array_unshift($v,$s);
		call_user_func_array(array($stmt, 'bind_param'),refValues($v));
	}
	$stmt->execute();
	if(count($p))
		call_user_func_array(array($stmt, 'bind_result'),$p);
	return $stmt;
}

function _dbq($s,$v=[],$o=false){
	$c=-1;
	$p=array();
	$pp=array();
	$n=array();
	$a='';
	$t=array();
	$se='';
	$tp=array();
	if($o){
		$o = new stdClass;
		$oo=array();
	}
	foreach(explode(';',$s) as $k=>$w)if($w){
		$r[$k]=explode(':',$w);
		$t[]=$r[$k][0];
		if($o)$o->{$r[$k][0]}=new stdClass;
		foreach(explode(',',$r[$k][1]) as $k1=>$w1){
			$c++;
			$q=explode('.',$w1);
			if($q[0])$se.=$r[$k][0].'.'.$q[0].',';
			if($o && $q[0]){
				$o->{$r[$k][0]}->{$q[0]}=null;
				$oo[]=&$o->{$r[$k][0]}->{$q[0]};
			}
			if(!is_null($v[$c])&&$q[0]){
				if(!$k)$tp[]=$q[0];
				if(is_numeric($q[1])){
					if($k)$pp[]=[$r[$k][0].'.'.$q[0],$q[1],$v[$c]];
					else $p[]=[$v[$c],$q[1]];
				}else{
					$n[]=$v[$c];
					$a.=$q[1]?$q[1]:'i';
					if($k){
						$pp[] = $r[$k][0] . '.' . $q[0] . '?';
					}else
						$p[]='?';
				}
			}
		}
	}
	foreach($p as $k=>$w)
		if(is_array($w))
			$p[$k]=$r[$w[1]][0].'.'.$w[0];
	foreach($pp as $k=>$w)
		if(is_array($w))
			$pp[$k]=$w[0].$r[$w[1]][0].'.'.$w[2];
	return [$t,$tp,$p,$a,$n,$pp,substr($se,0,-1),$oo,$o];
}

function _dbw($a,$t,$o='AND'){
	$r='';
	$s='';
	$p=array();
	foreach($a as $k=>$v)if(is_array($v)){
		if(is_array($v[0])){
			$db=_dbw($v,$t,$o=='AND'?'OR':'AND');
			if($db[0]){
				$r.='('.$db[0].') '.$o.' ';
				$s.=$db[1];
				foreach($db[2] as $x)
					$p[]=$x;
			}
		}elseif(!is_null($v[1])){
			$r.=($v[2]=='b'?'BINARY ':'').($t[(int)$v[3]]?$t[(int)$v[3]]:$t[0]).'.'.$v[0].($v[2]=='p'?$t[(int)$v[4]].'.'.$v[1]:'?').' '.$o.' ';
			if($v[2]!='p'){
				if($v[2]=='l'){
					$s.='s';
					$p[]='%'.$v[1].'%';
				}elseif($v[2]=='b'){
					$s.='s';
					$p[]=$v[1];
				}else{
					$s.=$v[2]?$v[2]:'i';
					$p[]=$v[1];
				}
			}
		}
	}
	return [substr($r,0,-strlen($o)-2),$s,$p];
}

function _dbs($q,$l=null){
	//'таблица[:столбцы,][;таблицы[:столбцы,]]',[true|object|string],[array('столбец[=|>|<| LIKE ]',значение,[i,s,b,p,l],[номер таблицы для столбца],[номер таблицы для значения])]
	$a=func_get_args();
	if(is_int($l))$l='LIMIT 0,'.$l;
	$j=[is_string($l),is_object($l),$l===true];
	if($j[0]||$j[2]||$j[1])
		unset($a[1]);
	unset($a[0]);
	$q=_dbq($q,null,true);
	$w=_dbw($a,$q[0]);
	$s='SELECT '.$q[6];
	if($j[1]){
		if($q[6])$s.=',';
		foreach($l as $k=>$v)if($k!='limit' && $k!='join'){
			$q[7][]=&$l->{$k};
			$s.=$l->{$k}.',';
		}
		$s=substr($s,0,-1);
	}
	$s.=' FROM '.implode(',',$q[0]);
	if($j[1])if($l->join)
		$s.=' '.$l->join;
	if($w[0])
		$s.=' WHERE '.$w[0];
	if($j[0]||$j[2])
		$s.=' '.($j[2]?'LIMIT 1':$l);
	elseif($j[1])
		if($l->limit)
			$s.=' '.$l->limit;
	$stmt=_db($s,$w[1],$w[2],$q[7]);
	$stmt->result=$q[8];
	if($j[2]){
		$stmt->fetch();
		$stmt->close();
		$stmt=$q[8];
	}
	return $stmt;
}

function _dbi($q,$w){
	$a=func_get_args();
	unset($a[1]);
	unset($a[0]);
	$q=_dbq($q,$w);
	$w=_dbw($a,array_slice($q[0],1));
	$s='INSERT INTO '.$q[0][0].'('.implode(',',$q[1]).') ';
	if(count($q[0])>1){
		$s.='SELECT '.implode(',',$q[2]).' FROM '.substr(implode(',',$q[0]),strlen($q[0][0])+1);
		if($w[0]||$q[5][0])
			$s.=' WHERE '.implode(' AND ',$q[5]).($w[0]&&$q[5][0]?' AND ':'').$w[0];
		$q[3].=$w[1];
		$q[4]=array_merge($q[4],$w[2]);
	}elseif(is_array($q[4][0])){
		$s.='VALUES ';
		$qq=[];
		$qs='';
		foreach($q[4] as $k=>$v){
			$s.='('.implode(',',$q[2]).'),';
			foreach($v as $vv)
			$qq[]=$vv;
			$qs.=$q[3];
		}
		$q[4]=$qq;
		$q[3]=$qs;
		$s=substr($s,0,-1);
	}else
		$s.='VALUES ('.implode(',',$q[2]).')';
	$stmt=_db($s,$q[3],$q[4]);
	$r=$stmt->insert_id;
	$stmt->close();
	return $r;
}

function _dbu($q,$w,$l=null){
	$a=func_get_args();
	unset($a[1]);
	unset($a[0]);
	if(!is_array($l))
		unset($a[2]);
	$q=_dbq($q,$w);
	foreach($q[4] as $k=>$v)
		if(is_array($v)){
			$q[2][$k]=$v[0].$q[2][$k].$v[2];
			$q[4][$k]=$v[1];
		}
	$w=_dbw($a,$q[0]);
	$s='UPDATE '.$q[0][0].' SET '._arrays_str($q[1],$q[2]);
	if($w[0])
		$s.=' WHERE '.$w[0];
	if(is_bool($l))
		$s.=' LIMIT 1';
	elseif(is_string($l))
		$s.=' '.$l;
	elseif(is_int($l))
		$s.=' LIMIT '.$l;
	if($w[0]){
		$q[3].=$w[1];
		$q[4]=array_merge($q[4],$w[2]);
	}
	$stmt=_db($s,$q[3],$q[4]);
	$r=$stmt->affected_rows;
	$stmt->close();
	return $r;
}

function _dbp($q,$v,$l){//Изменить или добавить
	$ar=func_get_args();
	$result=call_user_func_array("_dbu",$ar);
	if(!$result){
		unset($ar[1]);
		unset($ar[0]);
		if(!is_array($l))
			unset($ar[2]);
		foreach($ar as $w){
			$q.=','.substr($w[0],0,-1).($w[2]?".$w[2]":'');
			$v[]=$w[1];
		}
		$result=_dbi($q,$v);
	}
	return $result;
}

function _dba($q){//Все поля
	$a=func_get_args();
	unset($a[0]);
	$q=_dbq($q,null,true);
	$w=_dbw($a,$q[0]);
	$temp=$q[0][0];
	$stmt=_db("SELECT * FROM $temp WHERE $w[0] LIMIT 1",$w[1],$w[2]);
	$result=$stmt->get_result()->fetch_object();
	$stmt->close();
	return $result;
}

function _dbc(){//Количество
	$ar=func_get_args();
	array_splice($ar,1,0,'');
	$ar[1]=new stdClass;
	$ar[1]->count='count(*)';
	$stmt=call_user_func_array('_dbs',$ar);
	$stmt->fetch();
	$stmt->close();
	return $ar[1]->count;
}

function _dbd($q,$l=null){//Удалить
	$ar=func_get_args();
	unset($ar[0]);
	if($l===true)
		unlink($ar[1]);
	$q=_dbq($q,null,true);
	$w=_dbw($ar,$q[0]);
	$temp=$q[0][0];
	$stmt=_db("DELETE FROM $temp WHERE $w[0]".($l===true?' LIMIT 1':''),$w[1],$w[2]);
	$r=$stmt->affected_rows;
	$stmt->close();
	return $r;
}

function _dbds($q){// Удалить выборку // 'таблица:id'
	$q=explode(';',$q);
	$q=explode(':',$q[0]);
	$stmt=call_user_func_array("_dbs",func_get_args());
	$ar=$stmt->result->{$q[0]};
	$w='';
	$or='';
	while($stmt->fetch()){
		$w.="$or$q[0].$q[1]=".$ar->{$q[1]};
		$or=' OR ';
	}
	$stmt->close();
	$stmt=_db("DELETE FROM $q[0] WHERE $w");
	$r=$stmt->affected_rows;
	$stmt->close();
	return $r;
}

function _dbl($limit,$count=12){
	$limit=is_object($limit)?$limit->limit:$limit;
	return 'LIMIT '.($limit*$count).','.$count;
}

function _dbf($stmt,&$count=0){
	$result=[];
	while($stmt->fetch()){
		$result[$count]=new stdClass;
		foreach($stmt->result as $k=>$v)
			$result[$count]->{$k}=copyObject($v);
		$count++;
	}
	$stmt->close();
	return $result;
}
