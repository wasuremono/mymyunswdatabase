<?php
// COMP3311 13s2 Assignment 3
// Functions for assignment Tasks A-E
// Written by <<YOUR NAME>>, October 2013

// assumes that defs.php has already been included


// Task A: get members of an academic object group

// E.g. list($type,$codes) = membersOf($db, 111899)
// Inputs:
//  $db = open database handle
//  $groupID = acad_object_group.id value
// Outputs:
//  array(GroupType,array(Codes...))
//  GroupType = "subject"|"stream"|"program"
//  Codes = acad object codes in alphabetical order
//  e.g. array("subject",array("COMP2041","COMP2911"))

function membersOf($db,$groupID)
{
	$q = "select * from acad_object_groups where id = %d";
    $q2 = "select * from acad_object_groups where parent = %d";
	$grp = dbOneTuple($db, mkSQL($q, $groupID));
	$x = array();
	$objtable = $grp["gtype"]."_group_members";
	$gtype =  $grp["gtype"];
	$typetable = $gtype."s";
	if($grp["gdefby"] == enumerated){
        $qenum = "select code from $objtable	
   	       inner join  $typetable on
              ($typetable.id = $objtable.$gtype)
	       where ao_group = %d";
	   $r = dbQuery($db,mkSql($qenum,$groupID));
       while ($t = dbNext($r)){
		   $code = $t["code"];
		   array_push($x,$code);
	   }
    } elseif ($grp["gdefby"] == pattern){
        $qpattern = "select definition from acad_object_groups where id = %d";
        $exclude = "";
        $r = dbOneTuple($db,mkSql($qpattern,$groupID));
        $definition= $r["definition"];
        $patternList = split(',',$definition);
        sort($patternList,SORT_STRING);
        foreach($patternList as $pattern){
            $p2 = preg_replace('/;/','|', $pattern);
            $p2 = preg_replace('/[{}]/','',$pattern);
            $p2 = preg_replace('/#/','_',$pattern);
            if(preg_match("/(!)/",$p2))
                $exclude = $exclude.$p2."|";
            else if(preg_match("/(FREE|GENG|^####|all|ALL|\/F)/i",$pattern))           
                array_push($x,$pattern);
            else {  
                if($exclude == "")
                    $qcode  = "select code from subjects where code similar to '$p2'";
                else 
                    $qcode  = "select code from subjects where code similar to '$p2' and code not similar to '$exclude'";
                $r = dbQuery($db,mkSql($qcode));
                while ($t = dbNext($r)){
                    $code = $t["code"];
                    array_push($x,$code);
                }
                
            }
            $pattern = strtok( ",");
        }
    }    
    $r = dbQuery($db,mkSql($q2,$groupID));
        while($t = dbNext($r)) {
           list($type,$child) = membersOf($db,$t["id"]);
           foreach($child as $code){
           array_push($x,$code);
           }
        }
    sort($x,SORT_STRING);
	return array($gtype,$x );
}	

// Task B: check if given object is in a group

// E.g. if (inGroup($db, "COMP3311", 111938)) ...
// Inputs:
//  $db = open database handle
//  $code = code for acad object (program,stream,subject)
//  $groupID = acad_object_group.id value
// Outputs:
//  true/false

function inGroup($db, $code, $groupID){
    $q = "select * from acad_object_groups where id = %d";
    $q2 = "select * from acad_object_groups where parent = %d";
	$grp = dbOneTuple($db, mkSQL($q, $groupID));
	$x = array();
	$objtable = $grp["gtype"]."_group_members";
	$gtype =  $grp["gtype"];
	$typetable = $gtype."s";
	if($grp["gdefby"] == enumerated){
        $qenum = "select code from $objtable	
   	       inner join  $typetable on
              ($typetable.id = $objtable.$gtype)
	       where ao_group = %d";
	   $r = dbQuery($db,mkSql($qenum,$groupID));
       while ($t = dbNext($r)){
		   if($t["code"] == $code)
                return True;           
	   }
    } elseif ($grp["gdefby"] == pattern){
        $qpattern = "select definition from acad_object_groups where id = %d";
        $r = dbOneTuple($db,mkSql($qpattern,$groupID));
        $definition= $r["definition"];
        $patternList = split(',',$definition);
        sort($patternList,SORT_STRING);
        foreach($patternList as $pattern){
            $p2 = preg_replace('/;/','|', $pattern);
            $p2 = preg_replace('/[{}#]/','',$pattern);
            if(preg_match("/(!)/",$p2)){
                if(preg_match("/$p2/",$code))
                    return False;                
            }elseif(preg_match("/GENG/i",$p2)){  
                if(preg_match("/GEN/i",$code))
                    return True;     
            }elseif(preg_match("/(FREE|####|all|ALL|\/F)/i",$p2)){  
                if(!preg_match("/GEN/i",$code))
                    return True;                
            } else {  
                if(preg_match("/$p2/",$code))
                    return True;
                
            }
            $pattern = strtok( ",");
        }
    }    
    $r = dbQuery($db,mkSql($q2,$groupID));
    while(($t = dbNext($r)) !== false) {
       $children = inGroup($db, $code, $t["id"]);
       if($children)
       return $children;
    }	
    return False;
}


// Task C: can a subject be used to satisfy a rule

// E.g. if (canSatisfy($db, "COMP3311", 2449, $enr)) ...
// Inputs:
//  $db = open database handle
//  $code = code for acad object (program,stream,subject)
//  $ruleID = rules.id value
//  $enr = array(ProgramID,array(StreamIDs...))
// Outputs:

function canSatisfy($db, $code, $ruleID, $enrolment){
    if (preg_match('/^[A-Z]{5}[A-Z0-9]$/',$code))
        $table = "Streams";
    elseif (preg_match('/^[A-Z]{4}[0-9]{4}$/',$code))
        $table = "Subjects";
    $q1 = " select a.id,r.type,a.gdefby from
            acad_object_groups a 
            inner join rules r on (r.ao_group = a.id)
            where r.id = %d";
    $r = dbOneTuple($db,mkSql($q1,$ruleID)); 
    list($prog,$streams) = $enrolment;
    if(!inGroup($db,$code,$r["id"]))
       return False;
    if(!preg_match("/(CC|PE|FE|GE|DS|RQ)/i",$r["type"]))
        return False;
    if(preg_match("/^GEN/i",$code)){
        $qCF = "select facultyOf(o.offeredBy) from (select offeredBy from $table where code = '$code') as o";
        $qPF = "select facultyOf(o.offeredBy) from (select offeredBy from Programs where id = '$prog') as o";
        $codeFaculty = dbOneValue($db,mkSql($qCF));
        $progFaculty = dbOneValue($db,mkSql($qPF));
        if($codeFaculty == $progFaculty)
            return False;
        foreach($streams as $stream){
            $qS = "select facultyOf(o.offeredBy) from (select offeredBy from Streams where id = '$stream') as o";
            $streamFaculty = dbOneValue($db,mkSql($qS));
            if($streamFaculty == $codeFaculty)
               return False;
    
        }
    }
    return True;
    
}


// Task D: determine student progress through a degree

// E.g. $vtrans = progress($db, 3012345, "05s1");
// Inputs:
//  $db = open database handle
//  $stuID = People.unswid value (i.e. unsw student id)
//  $semester = code for semester (e.g. "09s2")
// Outputs:
//  Virtual transcript array (see spec for details)


//TODO- Count max in each rule group
function progress($db, $stuID, $term)
{   $x = array();
    $q1 = " select * from transcript(%d,%d)";
    $trans = dbQuery($db,mkSql($q1,$stuID,$term));
    //Get Student streams/programs
    $ruleList = array("CC" =>array(),"PE" => array(), "FE" => array(), "GE" => array(), "LR" => array());
    $wam = array();
    while ($t = dbNext($trans)){  
        $year = 2000+substr($t["term"],0,2);
        $term = strtoupper(substr($t["term"],2,2));
        $q = "select id from semesters where year = $year and term = '$term'";
        $semID = dbOneValue($db, mkSQL($q));
        $q = "select id,program from Program_enrolments where student=%d and semester=%d";
        $pe = dbOneTuple($db, mkSQL($q,$stuID,$semID));
        $prog = $pe[1];
        $q = "select stream from Stream_enrolments where partof=%d";
        $r = dbQuery($db, mkSQL($q, $pe[0]));     
        $streams = array();  
        while ($t2 = dbNext($r)) { $streams[] = $t2[0];}        
        $enrolment = array($pe[1],$streams); // ProgID,StreamIDs
        $req = null;        
        if($t["code"]){
            if(!$t["grade"]){
                 $t["uoc"] = null;
                 $req = "Incomplete. Does not yet count";
            } else if($t["grade"] == 'FL') {
                 $req = "Failed. Does not count";
            } else {       
                foreach($streams as $s){
                    $q = "select rules.id,rules.min,rules.max,rules.type,rules,name from stream_rules inner join rules on (rules.id = stream_rules.rule) where stream_rules.stream = $s order by rules.id";
                    $r = dbQuery($db, mkSQL($q));
                    while($rule = dbNext($r)){
                        $ruleID = $rule["id"];
                        if(!(isset($ruleList[$rule["type"]][$ruleID]))){                     
                            $ruleList[$rule["type"]][$ruleID] = array($rule["id"],$rule["min"],$rule["max"],0,$rule["type"],false,"Stream"); 
                        }            
                    }
                }
                $q = "select rules.id,rules.min,rules.max,rules.type,rules.name from program_rules inner join rules on (rules.id = program_rules.rule) where program_rules.program = $prog order by rules.id";
                $r = dbQuery($db, mkSQL($q));
                while($rule = dbNext($r)){
                    $ruleID = $rule["id"];
                    if(!(isset($ruleList[$rule["type"]][$ruleID]))){
                        $ruleList[$rule["type"]][$ruleID] = array($rule["id"],$rule["min"],$rule["max"],0,$rule["type"],false,"Program"); 
                    }
                 }
                foreach($ruleList as &$ruleType){
                  // if(!($req)){
                        foreach($ruleType as &$rule){  
                            if(!$req){
                            if(canSatisfy($db,$t["code"],$rule[0],$enrolment)){ 
                                    //Check we're under max credit available
                                    if($rule[2]){
                                        if($rule[3] < $rule[2]){
                                            $req = ruleName($db,$rule[0]);
                                            $rule[3] = ($rule[3] + $t["uoc"]);
                                            
                                        } 
                                    } else {
                                            $req = ruleName($db,$rule[0]);
                                            $rule[3] = ($rule[3] + $t["uoc"]);
                                    }
                                    if($rule[3] >= $rule[1])
                                        $rule[5] = True;
                                
                            }
                        }
                    }
                }
            }
            if (!($req))
                $req = "Fits no requirement. Does not count";
            array_push($x,array($t["code"],$t["term"],$t["name"],$t["mark"],$t["grade"],$t["uoc"],$req));
        }    
    $wam =   array($t[2],$t[3],$t[5]); 
    }  
    array_push($x,$wam);
    foreach($ruleList as $ruleType){            
            foreach($ruleType as $rule){
                if(preg_match("/(CC|PE|FE|GE|LR)/i", $rule[4])){
                    if(!$rule[5]){
                        $rem = ($rule[1] - $rule[3]);
                        $desc1 = $rule[3]." so far; need ".$rem." UOC more";
                        $rName = ruleName($db,$rule[0]);
                        array_push($x,array($desc1,$rName));
                        array_push($x,array($rule[0], $rem[1],$rule[2],$rule[3],$rule[4],$rem[6]));
                    }
                }
            }
    }
	return $x; // stub
    
}


// Task E:

// E.g. $advice = advice($db, 3012345, 162, 164)
// Inputs:
//  $db = open database handle
//  $studentID = People.unswid value (i.e. unsw student id)
//  $currTermID = code for current semester (e.g. "09s2")
//  $nextTermID = code for next semester (e.g. "10s1")
// Outputs:
//  Advice array (see spec for details)

function advice($db, $studentID, $currTermID, $nextTermID)
{   
    $completed = array();
    $x = array();
    $toComplete = array(); 
    $exclude = array();
    //Get Student streams/programs
    $ruleList = array("CC" =>array(),"PE" => array(), "FE" => array(), "GE" => array(), "LR" => array());
    $wam = array();
    $q = "select id,program from Program_enrolments where student=%d and semester=%d";
    $pe = dbOneTuple($db, mkSQL($q,$studentID,$nextTermID));
    $prog = $pe[1];
    
    $q = "select stream from Stream_enrolments where partof=%d";
    $r = dbQuery($db, mkSQL($q, $pe[0]));     
    $streams = array();  
    while ($t2 = dbNext($r)) { $streams[] = $t2[0];}        
        $enrolment = array($pe[1],$streams); // ProgID,StreamIDs
    //Create a list of rules for streams and programs
    foreach($streams as $s){
        $q = "select rules.id,rules.min,rules.max,rules.type,rules,name from stream_rules inner join rules on (rules.id = stream_rules.rule) where stream_rules.stream = $s order by rules.id";
        $r = dbQuery($db, mkSQL($q));
        while($rule = dbNext($r)){
            $ruleID = $rule["id"];
            if(!(isset($ruleList[$rule["type"]][$ruleID]))){                     
                $ruleList[$rule["type"]][$ruleID] = array($rule["id"],$rule["min"],$rule["max"],0,$rule["type"],false,"Stream"); 
            }            
        }
    }
    $q = "select rules.id,rules.min,rules.max,rules.type,rules.name from program_rules inner join rules on (rules.id = program_rules.rule) where program_rules.program = $prog order by rules.id";
    $r = dbQuery($db, mkSQL($q));
    while($rule = dbNext($r)){
        $ruleID = $rule["id"];
        if(!(isset($ruleList[$rule["type"]][$ruleID]))){
            $ruleList[$rule["type"]][$ruleID] = array($rule["id"],$rule["min"],$rule["max"],0,$rule["type"],false,"Program"); 
        }
    }
    //Count relevant credits towards rules
    $q1 = " select * from transcript(%d,%d)"; 
    $trans = dbQuery($db,mkSql($q1,$studentID,$currTermID));
    while ($t = dbNext($trans)){  
        $doExclude = true;
        $req = null;        
        if($t["code"]){
            if(!$t["grade"]){
                 $req = "Incomplete. Does not yet count";
            } else if($t["grade"] == 'FL') {
                $req = "Failed. Does not count";
                $doExclude = false;
            } else {       
                foreach($streams as $s){
                    $q = "select rules.id,rules.min,rules.max,rules.type,rules,name from stream_rules inner join rules on (rules.id = stream_rules.rule) where stream_rules.stream = $s order by rules.id";
                    $r = dbQuery($db, mkSQL($q));
                    while($rule = dbNext($r)){
                        $ruleID = $rule["id"];
                        if(!(isset($ruleList[$rule["type"]][$ruleID]))){                     
                            $ruleList[$rule["type"]][$ruleID] = array($rule["id"],$rule["min"],$rule["max"],0,$rule["type"],false,"Stream"); 
                        }            
                    }
                }
                $q = "select rules.id,rules.min,rules.max,rules.type,rules.name from program_rules inner join rules on (rules.id = program_rules.rule) where program_rules.program = $prog order by rules.id";
                $r = dbQuery($db, mkSQL($q));
                while($rule = dbNext($r)){
                    $ruleID = $rule["id"];
                    if(!(isset($ruleList[$rule["type"]][$ruleID]))){
                        $ruleList[$rule["type"]][$ruleID] = array($rule["id"],$rule["min"],$rule["max"],0,$rule["type"],false,"Program"); 
                    }
                }
                foreach($ruleList as &$ruleType){
                    foreach($ruleType as &$rule){  
                        if(!$req){
                            if(canSatisfy($db,$t["code"],$rule[0],$enrolment)){ 
                                    //Check we're under max credit available
                                    if($rule[2]){
                                        if($rule[3] < $rule[2]){
                                            $req = ruleName($db,$rule[0]);
                                            $rule[3] = ($rule[3] + $t["uoc"]);                                
                                        } 
                                    } else {
                                        $req = ruleName($db,$rule[0]);
                                        $rule[3] = ($rule[3] + $t["uoc"]);
                                    }
                                    if($rule[3] >= $rule[1])
                                        $rule[5] = True;           
                            }
                        }
                    }
                }
            }
            if (!($req)){
                $req = "Fits no requirement. Does not count";
            }
            $thisCode = $t["code"];
            if($doExclude){            
                $q = "select excluded, equivalent from subjects where subjects.code = '$thisCode'";
                $r = dbOneTuple($db,$q);
                list($throw,$ex) = membersOf($db,$r["excluded"]);
                foreach($ex as $next){
                    array_push($exclude,$next);
                }
                list($throw,$ex) = membersOf($db,$r["equivalent"]);
                foreach($ex as $next){
                    array_push($exclude,$next);
                }
                array_push($exclude,$t["code"]);
            } 
            
        }    
    } 
    
    $level1 = false;
    $level2 = false;
    foreach($ruleList as &$ruleType){
        foreach($ruleType as &$rule){
        
            $rem = $rule[1] - $rule[3];
            $rid = $rule[0];
            if(!$rule[5]){                
                $rName = ruleName($db,$rid);
                if($rid){
                    $q = "select ao_group from rules where id = $rid";
                    $r = dbOneValue($db,mkSql($q));
                }    
                list($type,$tempList) = membersOf($db,$r);  
                    if(preg_match("/(CC|PE)/i", $rule[4])){       
                    foreach($tempList as $nextCourse){
                    $doExclude = false;
                        if(in_array($nextCourse,$exclude)){
                            
                            $doExclude = True;
                        }
                        $q = "select c.subject from
                              courses c inner join
                              subjects s on(s.id = c.subject)
                              where c.semester = $nextTermID
                              and s.code = '$nextCourse'";
                        $r = dbOneValue($db,$q);
                        if(!$r){                            
                            $doExclude = True;
                            
                        }
                              
                                //If curr < min then offer subjects from that course
                            //if(($rule[3] <= $rule[1]) ) {
                                if(preg_match("/Level 1/i", $rName)){
                                    if(($rule[3] >= $rule[1])){
                                        $doExclude = True;
                                        $level1 = true;
                                    }
                                    //Create List
                                    
                                } else if (preg_match("/Level 2/i", $rName)){
                                    if(($rule[3] >= $rule[1])){
                                        $level2 = true;
                                        $doExclude = True;
                                     }
                                        if(!$level1){                                        
                                        $doExclude = True;    
                                        }
                                } else if (preg_match("/Level (3|4)/i", $rName)){
                                    if(($rule[3] >= $rule[1]) ) 
                                        $doExclude = True;
                                    if(!$level2){
                                        $doExclude = True;
                                    }
                                } else {
                                if(($rule[3] >= $rule[1])){
                                    $doExclude = True;
                                    }
                                    //Create List
                                }
                            //} 
                            if(!$doExclude){ 
                                $q = "select uoc,name from subjects where code = '$nextCourse'";
                                $r = dbOneTuple($db,$q);
                                $nextName = $r["name"];
                                $nextUOC = $r["uoc"];
                                array_push($toComplete,array($nextCourse,$nextName,$nextUOC,$rName));
                                $q = "select excluded, equivalent from subjects where subjects.code = '$nextCourse'";
                                $r = dbOneTuple($db,$q);
                                list($throw,$ex) = membersOf($db,$r["excluded"]);
                                foreach($ex as $next){
                                    array_push($exclude,$next);
                                }
                                list($throw,$ex) = membersOf($db,$r["equivalent"]);
                                foreach($ex as $next){
                                    array_push($exclude,$next);
                                }
                            } else {                                
                            }
                
                    }
                   

                
                }else if ($rule[4] == 'FE'){
                    array_push($toComplete,array("Free....","Free Electives (many choices)",$rem,$rName));
                } else if ($rule[4] == 'GE'){
                    array_push($toComplete,array("GenEd...","General Education (many choices)",$rem,$rName));
                } else if ($rule[4] == 'LR'){
                    array_push($toComplete,array("Limit...","$rName (many choices)",$rem,$rName));
                }
            }
        }
    }
    return $toComplete;
}
?>
