<?php
//error_reporting(E_ALL);
//ini_set('display_errors', '0');
include('QuiteSimpleXMLElement.php');
$amazonRemplacementImg = file_get_contents('http://www.bu.fr/images/defaultcover.jpg', false);
if (isset( $_GET['q'])) {
	$q = $_GET['q'];
}
if (isset( $_GET['relevant']) && (isset( $_GET['index']))) {
	$index = $_GET['index'];
	$index = $index." all/relevant";
	$qs = make_query($index.' "' . addslashes($q) . '"');
}
elseif (isset( $_GET['index'])) {
	$index = $_GET['index'];
	$index = $index;
	$qs = make_query($index.'=' . addslashes($q) . '');
}	
	$baseurl = 'http://opac.koha.fr:9998/biblios?';
	$uri = $baseurl . $qs;
	$source = file_get_contents2($uri);
	$source= str_replace(array("\n", "\r", "\t"), '', $source);
	parseSRU($source);
	
function parseSRU($data)
    {		
		$xml = new QuiteSimpleXMLElement($data);
		$xml->registerXPathNamespaces(array(
        	'zs' => 'http://www.loc.gov/zing/srw/',
			'marc' => 'http://www.loc.gov/MARC21/slim',
			'diag' => 'http://www.loc.gov/zing/srw/diagnostic/'
			));
    
 
	foreach ($xml->xpath("/zs:searchRetrieveResponse/zs:records/zs:record") as $record) {
    	$output['biblionumber'] = $record->text('.//marc:controlfield[@tag="001"]');
    	$output['ppn'] = $record->text('.//marc:controlfield[@tag="009"]');
        $output['authors'] = array();
        $output['subjects'] = array();
        $output['series'] = array();
        foreach ($record->xpath('.//marc:datafield') as $node) {
            $marcfield = intval($node->attributes()->tag);
            switch ($marcfield) {
               
                // 010 - International Standard Book Number (R)
                case 10: // Test added
                    $isbn = $node->text('marc:subfield[@code="a"]');
                    $isbn = preg_replace('/^([0-9\-]+).*$/', '\1', $isbn);
                    if (empty($isbn)) break;
                    if (!isset($output['isbn'])) $output['isbn'] = array();
                    array_push($output['isbn'], $isbn);
                    //$output['cover'] = getAmazonCover($output['isbn']);
                    
                    break;
                // 011 - International Standard Serial Number (R)
                case 11: // Test added
                    $issn = $node->text('marc:subfield[@code="a"]');
                    $issn = preg_replace('/^([0-9\-]+).*$/', '\1', $issn);
                    if (empty($issn)) break;
                    if (!isset($output['issn'])) $output['issn'] = array();
                    array_push($output['issn'], $issn);
                    break;
               
                // 013 - International Standard Music Number (R)
                case 13: // Test added
                    $ismn = $node->text('marc:subfield[@code="a"]');
                    $ismn = preg_replace('/^([0-9\-]+).*$/', '\1', $ismn);
                    if (empty($ismn)) break;
                    if (!isset($output['ismn'])) $output['issn'] = array();
                    array_push($output['ismn'], $ismn);
                    break;
				// 073 - EAN (R)
                case 73: // Test added
                    $ean = $node->text('marc:subfield[@code="a"]');
                    $ean = preg_replace('/^([0-9\-]+).*$/', '\1', $ean);
                    if (empty($ean)) break;
                    if (!isset($output['ean'])) $output['ean'] = array();
                    array_push($output['ean'], $ean);
                    break;	
				// 099 - Itemtype (R)
                case 99: // Test added
                    $itemtype = $node->text('marc:subfield[@code="t"]');
                    if (empty($itemtype)) break;
                    if (!isset($output['itemtype'])) $output['itemtype'] = array();
                    array_push($output['itemtype'], getLabel('CCODE',$itemtype));
                    break;                
                // 200 - Title   
                case 200:
                $title = array(
                		'title' => $node->text('marc:subfield[@code="a"]'),
                        'subtitle'  => $node->text('marc:subfield[@code="e"]'),
						'altertitle' => $node->text('marc:subfield[@code="d"]'),
                        'volume' => $node->text('marc:subfield[@code="v"]'),
                        'part_title' => $node->text('marc:subfield[@code="i"]'),
                        'part_num' => $node->text('marc:subfield[@code="h"]')
					);
                
                    $output['titles'][] = $title;
                    break;
                case 205:
                    $output['edition'] = $node->text('marc:subfield[@code="a"]');
                    break;
               
                case 210:
                    $output['publisher'] = $node->text('marc:subfield[@code="c"]');
                    $output['year'] = preg_replace('/[^0-9,]|,[0-9]*$/', '', current($node->xpath('marc:subfield[@code="d"]')));
                    break;
                
                case 330:
                    $output['summary'] = array(
                        'text' => $node->text('marc:subfield[@code="a"]')
                    );
                    break;
                
                case 410:
                    $serie = array(
                        'title' => $node->text('marc:subfield[@code="t"]'),
                        'volume' => $node->text('marc:subfield[@code="v"]')
                    );
                    $output['series'][] = $serie;
                    break;
                    
                case 600:
				case 601:
				case 604:
				case 605:
				case 606:
				case 607:
				case 610:
                    $emne = $node->text('marc:subfield[@code="a"]');
                      $tmp = array('term' => trim($emne, '.'));
                      $system = $node->text('marc:subfield[@code="2"]');
                      if ($system !== false) $tmp['system'] = $system;
                      $subdiv = $node->text('marc:subfield[@code="x"]');
                      if ($subdiv !== false) $tmp['subdiv'] = trim($subdiv, '.');
                      $time = $node->text('marc:subfield[@code="z"]');
                      if ($time !== false) $tmp['time'] = $time;
                      $geo = $node->text('marc:subfield[@code="y"]');
                      if ($geo !== false) $tmp['geo'] = $geo;
                      array_push($output['subjects'], $tmp);
                    break;
                case 700:
				case 702:
				case 710:
				case 720:
                    $author = array(
                        'lastname' => $node->text('marc:subfield[@code="a"]'),
                        'firstname' => $node->text('marc:subfield[@code="b"]'),
                        'role' => getLabel('QUALIF',$node->text('marc:subfield[@code="4"]')),
                        'ppn' => $node->text('marc:subfield[@code="3"]'),
                    );
                    $authority = $node->text('marc:subfield[@code="c"]');
                    if (!empty($authority)) $author['authority'] = $authority;
                    $output['authors'][] = $author;
                    
                    break;
                case 856:
                    $links = array(
                        'label' => $node->text('marc:subfield[@code="z"]'),
                        'url' => $node->text('marc:subfield[@code="u"]')
                    );
                    $output['links'][] = $links;
                    break;
                    
				case 930:
				if($itemtype == 'REVUE'){
					$holding = array();
/* 					preg_match_all('/(\(\d{4}\))/',current($node->xpath('marc:subfield[@code="r"]')), $matches); */
					$holding = array(
                    	'rcr' => $node->text('marc:subfield[@code="5"]'), 
/*                     	'daterange' => preg_replace('/[^0-9]/', '',$matches[0]), */
                        'callnumber' => $node->text('marc:subfield[@code="a"]'),                        
						);
						$output['locations'][] = $holding;
					}
					break;
					
				case 955:
				if($itemtype == 'REVUE'){
					$holding = array();
					$enddate = array();
					$startdate = array();
					preg_match('/(\-\s*\(\d{4}\))/',current($node->xpath('marc:subfield[@code="r"]')), $enddate);
					preg_match('/(\(\d{4}\)\s*\-)/',current($node->xpath('marc:subfield[@code="r"]')), $startdate);
					$holding = array(
                    	'rcr' => $node->text('marc:subfield[@code="5"]'),
                    	'startdate' => preg_replace('/[^0-9]/', '',$startdate[0]),
                    	'enddate' => preg_replace('/[^0-9]/', '',$enddate[0]),
                        'text' => $node->text('marc:subfield[@code="r"]'), 
                        'missing' => $node->text('marc:subfield[@code="w"]'),                     
						);
						$output['holdings'][] = $holding;
					}
					break;
				// HOLDINGS						
                case 995:
                if($itemtype != 'REVUE'){
                $withdrawnstatus = "false";
                $damagedcode = $node->text('marc:subfield[@code="1"]');
                $itemlostcode = $node->text('marc:subfield[@code="2"]');
                $withdrawncode = $node->text('marc:subfield[@code="3"]');
                
                if($itemlostcode > 0 || $withdrawncode){ $withdrawnstatus = "true";}
                
                	$item = array(
						'damaged' => getLabel('DAMAGED',$node->text('marc:subfield[@code="1"]')),
                        'itemlost'  => getLabel('LOST',$node->text('marc:subfield[@code="2"]')),
                        'withdrawn' => getLabel('WITHDRAWN',$node->text('marc:subfield[@code="3"]')),
                        'withdrawnstatus' =>  $withdrawnstatus,
                        'itemnumber' => $node->text('marc:subfield[@code="9"]'),
/*                         'availability' => getAvailability($node->text('marc:subfield[@code="9"]')), */
                        'ccode' => getLabel('ITEMCCODE',$node->text('marc:subfield[@code="h"]')),
                        'homebranch'=> getLabel('BRANCH',$node->text('marc:subfield[@code="b"]')),
                        'location' => getLabel('LOC',$node->text('marc:subfield[@code="e"]')),
                        'barcode' => $node->text('marc:subfield[@code="f"]'),
                        'itemcallnumber'=> $node->text('marc:subfield[@code="k"]'),
                        'notforloan' => getLabel('ETAT',$node->text('marc:subfield[@code="o"]')),
                        'onloan' => $node->text('marc:subfield[@code="n"]'),
						'itemtype' => getLabel('ITEMTYPE',$node->text('marc:subfield[@code="r"]')),
						'issues' => $node->text('marc:subfield[@code="x"]'),
						'itemnotes' => $node->text('marc:subfield[@code="u"]')
                    );
					$output['item'][] = $item;
				}
					break;
            }
        } 
        
     if($itemtype == 'VIDEO'){  
      	  //$output['cover'] = getMovieCover($output['authors'][0]['lastname'],$output['titles'][0]['title']); 
     }   
               
     $json['record'][] = $output;
}
return_json($json); 
    
 }
 
 /**
  *	Function accepts either 12 or 13 digit number, and either provides or checks the validity of the 13th checksum digit
  *    Optionally converts to ISBN 10 as well.
  */
	function isbn13checker($input, $convert = FALSE){
		$output = FALSE;
		if (strlen($input) < 12){
			$output = array('error'=>'ISBN too short.');
		}
		if (strlen($input) > 13){
			$output = array('error'=>'ISBN too long.');
		}
		if (!$output){
			$runningTotal = 0;
			$r = 1;
			$multiplier = 1;
			for ($i = 0; $i < 13 ; $i++){
				$nums[$r] = substr($input, $i, 1);
				$r++;
			}
			$inputChecksum = array_pop($nums);
			foreach($nums as $key => $value){
				$runningTotal += $value * $multiplier;
				$multiplier = $multiplier == 3 ? 1 : 3;
			}
			$div = $runningTotal / 10;
			$remainder = $runningTotal % 10;
			$checksum = $remainder == 0 ? 0 : 10 - substr($div, -1);
			$output = array('checksum'=>$checksum);
			$output['isbn13'] = substr($input, 0, 12) . $checksum;
			if ($convert){
				$output['isbn10'] = isbn13to10($output['isbn13']);
			}
			if (is_numeric($inputChecksum) && $inputChecksum != $checksum){
				$output['error'] = 'Input checksum digit incorrect: ISBN not valid';
				$output['input_checksum'] = $inputChecksum;
			}
		}
		return $output;
	}
	
	function isbn10checker($input, $convert = FALSE){
		$output = FALSE;
		if (strlen($input) < 9){
			$output = array('error'=>'ISBN too short.');
		}
		if (strlen($input) > 10){
			$output = array('error'=>'ISBN too long.');
		}
		if (!$output){
			$runningTotal = 0;
			$r = 1;
			$multiplier = 10;
			for ($i = 0; $i < 10 ; $i++){
				$nums[$r] = substr($input, $i, 1);
				$r++;
			}
			$inputChecksum = array_pop($nums);
			foreach($nums as $key => $value){
				$runningTotal += $value * $multiplier;
				//echo $value . 'x' . $multiplier . ' + ';
				$multiplier --;
				if ($multiplier === 1){
					break;
				}
			}
			//echo ' = ' . $runningTotal;
			$remainder = $runningTotal % 11;
			$checksum = $remainder == 1 ? 'X' : 11 - $remainder;
			$checksum = $checksum == 11 ? 0 : $checksum;
			$output = array('checksum'=>$checksum);
			$output['isbn10'] = substr($input, 0, 9) . $checksum;
			if ($convert){
				$output['isbn13'] = isbn10to13($output['isbn10']);
			}
			if ((is_numeric($inputChecksum) || $inputChecksum == 'X') && $inputChecksum != $checksum){
				$output['error'] = 'Input checksum digit incorrect: ISBN not valid';
				$output['input_checksum'] = $inputChecksum;
			}
		}
		return $output;
	}
	
	function isbn10to13($isbn10){
		$isbntem = strlen($isbn10) == 10 ? substr($isbn10, 0,9) : $isbn10;
		$isbn13data = isbn13checker('978' . $isbntem);
		return $isbn13data['isbn13'];
	}
	
	function isbn13to10($isbn13){
		$isbntem = strlen($isbn13) == 13 ? substr($isbn13, 12) : $isbn13;
		$isbntem = substr($isbn13, -10);
		$isbn10data = isbn10checker($isbntem);
		return $isbn10data['isbn10'];
	}
	
	function xml_special_chars($string){
	    $string = str_replace("\"","'",$string);
	    $string = str_replace("&","&amp;",$string);
	    $string = str_replace("<","&lt;",$string);
	    $string = str_replace(">","&gt;",$string);
	    return $string;
	}
	
	function file_get_contents2($url) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_USERAGENT, 'koha');
	    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
	    $data = curl_exec($ch);
	    curl_close($ch);
	    return $data;
	}
	
	function make_query($cql, $start = 1, $count = 10) {
	    return http_build_query(array(
	        'version' => '1.2',
	        'operation' => 'searchRetrieve',
	        'startRecord' => $start,
	        'maximumRecords' => $count,
	        'query' => $cql
	    ));
	}
	
	function return_json($obj) {
	    if (isset($_REQUEST['callback'])) {
	       header('Content-Type: text/javascript; charset=utf8');
		   header('Access-Control-Allow-Methods: GET, POST');
	        echo $_REQUEST['callback'] . '(' . json_encode($obj) . ')';
	        exit();
	    } else {
	        header('Content-type: application/json; charset=utf-8');
	        echo json_encode($obj);
	        exit();
	    }
	}
	
	function getLabel($cat,$key) {
			include('connexion.inc.php');
			
			if($cat == 'BRANCH'){
				$query = 'SELECT branchname FROM branches WHERE branchcode= "'.$key.'";';
			}
			else if ($cat == 'ITEMTYPE') {$query = 'SELECT description FROM itemtypes WHERE itemtype= "'.$key.'";';}
			else {$query = 'SELECT lib FROM authorised_values WHERE category= "'.$cat.'" AND authorised_value = "'.$key.'";';}
			
			foreach ($sql->query($query) as $row) {
					$lib = $row[0];
				}
			$lib = trim(xml_special_chars($lib));
			return $lib;
	}
	
	function getAvailability($id) {
		$uri = 'http://opac.koha.fr/cgi-bin/koha/ilsdi.pl?service=GetAvailability&id='.$id.'&id_type=item';
		$source = file_get_contents2($uri);
		$source= str_replace(array("\n", "\r", "\t"), '', $source);
		$xml = new QuiteSimpleXMLElement($source);
		$xml->registerXPathNamespaces(array(
	        'dlf' => 'http://diglib.org/ilsdi/1.1',
	    ));
		return $xml->text('.//dlf:availabilitystatus');
	}	
	
	function getMovieCover($author,$title){
			/* $author = utf8_encode($author); */
			$author = iconv('UTF-8', 'ASCII//TRANSLIT', $author);
			/* $title = utf8_encode($title); */
			$title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
			$author  = preg_replace('/\s+/', ' ',$author);
			$title  = preg_replace('/\s+/', ' ',$title);
			$url = preg_replace('/\s/','%20','http://opac.koha.fr/opac-tmpl/rennes2/svc/getTMDBmovie.php?callback=&director-sn='.$author.'&movie1='.$title);
			$json = file_get_contents($url);
			$json = substr($json,1,strlen($json)-3);
			$array = json_decode($json);
			if(isset($array[0])){
				$cover = 'http://cf2.imgobject.com/t/p/w75'.$array[0]->poster_path;
			}
			else {
				$cover = 'http://www.bu.fr/images/defaultcover.jpg';
			}	
	 return $cover;
		
	}
	
	function getAmazonCover($isbn){
		$isbn = isbn13to10(preg_replace('/[^\d]/','',$isbn[0]));
		if($isbn != ''){
			$cover = 'http://images.amazon.com/images/P/'.$isbn.'.01.TZZZZZZZ.jpg';			
			$data = file_get_contents($cover);
		
			if ($data==$amazonRemplacementImg) {
				$cover = 'http://www.bu.fr/images/defaultcover.jpg';
			}
		 }
		 else {$cover = 'http://www.bu.fr/images/defaultcover.jpg';}
		
		return $cover;
	
	}
	
?>
