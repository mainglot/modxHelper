<?php

/**
 * class modxHelper by DARTC
 * 
 * version 2014-09-04 11:40
 */

class modxHelper {

	public function __construct($modx) {
		$this->modx = $modx;
	}

	public function __call($method, $arguments) {
		switch ($method) {
			
			case 'print_r':
			case 'var_export':
			case 'var_dump':
			    
				return $this->pre($method($arguments[0], 1));
				break;

		}
	}

	public function pre ($str) {
		return '<pre><code>'.(string)$str.'</code></pre>';
	}

	public function fastQuery($classname, $query = array(), $convertField = 'id', $showSQL = false, $return = 'data') {
		$query = array_merge(array(
			'select' => '',
			'where' => array(),
			'sortby' => array(
				'field' => 'id',
				'dir'	=> 'asc',
				),
			'join' => array(),
			'limit' => 0,
			'offset' => 0,
			), $query);
			
	    

		$arOutput = array(
			'total_time' => array('start' => microtime(true)),
			'error' => 0,
			'message' => '',
			'status' => true,
			'classname' => $classname,
			'data' => array(),
			'sql' => '',
			);

		# Check $classname
		$classname = trim($classname);
		if (empty($classname)) {
			return $this->fastQueryReturn($arOutput, false, 'Не указан класс объекта');
		}

		# Check select
		if (strpos($query['select'], '@SQL') === 0) {
		    $query['select'] = substr($query['select'], 4);
		}
		else {
    		$arSelect = explode(',', $query['select']);
    		$arSelect = array_map('trim', $arSelect);
    		$arSelect = array_unique($arSelect);
    		$arSelect = array_filter($arSelect);
    
    		$arFields = array_keys($this->modx->getFields($classname));
    		if (empty($arFields)) {
    			return $this->fastQueryReturn($arOutput, false, 'У данного объекта нет полей');
    		}
            
    		if (!empty($arSelect)) {
    			$arSelect = array_intersect($arFields, $arSelect);
    		}
    		else {
    			$arSelect = $arFields;
    		}
    		$query['select'] = implode(',', $arSelect);
		}
		
		# Check convertation field
		$mixConvertFieldExisted = array_search($convertField, $arFields);
		if ($mixConvertFieldExisted !== false) {
			$mixConvertFieldInSelectExisted = array_search($convertField, $arSelect);
			if ($mixConvertFieldInSelectExisted === false) {
				$arSelect[] = $convertField;
			}
		}
		else {
			$convertField = '';
		}

		# Check Sort By field
		if (!empty($query['sortby']['field'])) {
			$mixOrderByExisted = array_search($query['sortby']['field'], $arFields);
			if ($mixOrderByExisted === false) {
				$query['sortby']['field'] = '';
			}
		}

		# Create query
		$objectQuery = $this->modx->newQuery($classname);
		
		$objectQuery->select($query['select']);
		if (!empty($query['join'])) {
			foreach ($query['join'] as $join => $joinValues) {
				if (!in_array($join, array('leftJoin', 'rightJoin', 'innerJoin'))
					|| empty($joinValues['class'])
					|| empty($joinValues['alias'])
					|| empty($joinValues['on'])
					) {
					continue;
				}
				$objectQuery->{$join}($joinValues['class'], $joinValues['alias'],$joinValues['on']);
			}
		}
		if (!empty($query['where'])) {
			$objectQuery->where($query['where']);
		}
		if (!empty($query['sortby']['field'])) {
			$objectQuery->sortby($query['sortby']['field'], $query['orderby']['dir']);
		}
		$objectQuery->limit($query['limit'], $query['offset']);

		$objectQuery->prepare();
		if ($showSQL) {
			$arOutput['sql'] = $objectQuery->toSQL();
		}

		# Check $return
		switch ($return) {
			case 'sql':
				return $this->fastQueryReturn($arOutput, true, 'Возвращаем запрос без выполнения в БД');
				break;
			default:
				break;
		}

		# Execute query
		$objectQuery->stmt->execute();
		$response = $objectQuery->stmt->fetchAll(PDO::FETCH_ASSOC);

		# Return empty data
		if (empty($response)) {
			return $this->fastQueryReturn($arOutput, false, 'БД вернула пустой результат.');
		}

		# Return data without modified
		if (empty($convertField)) {
			$arOutput['data'] = $response;
			return $this->fastQueryReturn($arOutput, true, '');
		}

		# Return data after modified
		foreach ($response as $row) {
			$arOutput['data'][ $row[$convertField] ][] = $row;
		}
		
		if ($return == 'dataonly') {
		    return $arOutput['data'];
		}
		
		return $this->fastQueryReturn($arOutput, true, '');
	}

	public function fastQueryReturn(&$arOutput, $status = true, $message = '') {
		$arOutput['total_time']['finish'] = microtime(true);
		$arOutput['total_time']['delta'] = $arOutput['total_time']['finish'] - $arOutput['total_time']['start'];
		$arOutput['status'] = $status;
		if ($status !== true) {
			$arOutput['error'] = 1;
		}
		$arOutput['message'] = $message;
		return $arOutput;
	}
	
	public function downloadPage($url, $type = 'GET', $data = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($type == 'GET' && !empty($data) && is_array($data)) {
            $linkQuery = http_build_query($data);
            $andCond = '?';
            if (strpos($url, $andCond) !== false) {
                $andCond = '&';
            }
            $url .= $andCond.$linkQuery;
        }
        if ($type == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (!empty($data) && is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        curl_setopt($ch, CURLOPT_URL,$url);
        $result=curl_exec($ch);
        curl_close($ch);
        return $result;
	}

	public function getHeaders($url, $type = 'GET', $data = array()) {
		$ch = curl_init($url);
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 3 );
		curl_exec( $ch );
		$headers = curl_getinfo( $ch );
		curl_close( $ch );

		return $headers;
	}

	public function downloadFileFromTo ($url, $path) {
		$fp = fopen ($path, 'wb+');
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		# increase timeout to download big file
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_FILE, $fp );

		curl_exec( $ch );

		curl_close( $ch );
		fclose( $fp );
        
    	if (filesize($path) > 0) return true;
    	unlink($path);
		
		return false;
	}
	
	public function explode($delimiter, &$string, $unique = true, $filter = true, $trim = true) {
	    $arOutput = array();
	    if (empty($string) || empty($delimiter)) {
	        return $arOutput;
	    }
	    
	    $arOutput = explode($delimiter, $string);
	    if ($unique) $arOutput = array_unique($arOutput);
	    if ($filter) $arOutput = array_filter($arOutput);
	    if ($trim) $arOutput = array_map('trim', $arOutput);
	    
	    return $arOutput;
	}
	
	public function xml2array($xmlstring) {
	    $xml = simplexml_load_string($xmlstring);
        $json = json_encode($xml);
        $array = json_decode($json,TRUE);
        return $array;
	}
	
	public function array2xml($input, &$xml, $numericOff = false) {
        foreach($input as $key => $value) {
            //$key = is_numeric($key) ? "item$key" : $key;
            if ($numericOff && is_numeric($key)) { $key = 'item'; }
            elseif (is_numeric($key)) { $key = 'item'.$key; }
            
            if(is_array($value)) {
                $subnode = $xml->addChild("$key");
                $this->array2xml($value, $subnode, $numericOff);
            }
            else {
                $xml->addChild("$key","$value");
            }
        }
    }
    
    public function readxml($file, $return = 'array') {
        if (!file_exists($file)) {
            return false;
        }
        
        $fo = fopen($file, 'r+');
        if ($fo === false) {
            return false;
        }
        $xmlstring = fread($fo, filesize($file));
        fclose($fo);
        
        if ($return == 'array') {
            return $this->xml2array($xmlstring);
        }
        
        return $xmlstring;
    }
    
    public function writexml($file, $array = array(), $into = 'data') {
        $path = dirname($file);
        if (!file_exists($path)) {
            return false;
        }
        
        $into = str_replace(array('<','>'),'',$into);
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><'.$into.'></'.$into.'>');
        $this->array2xml($array, $xml, true);
        
        return $xml->asXML($file);
    }
    
    public function ignore_other_executes ($filename = '') {
        if (empty($filename)) {
            $filename = md5($_SERVER['PHP_SELF']);
        }
        $this->ignore_other_executes_file = MODX_BASE_PATH.$filename.'.tmp';
        
        // clearstatcache(true, $this->ignore_other_executes_file);
        // echo $this->ignore_other_executes_file .' === '. (int)is_file($this->ignore_other_executes_file).'<br>';
        
        clearstatcache(true, $this->ignore_other_executes_file);
        if (is_file($this->ignore_other_executes_file)) {
            exit('Synchonization has already started! Please wait several minutes and try again.');
        }
        
        $rFile = fopen($this->ignore_other_executes_file, 'w+');
        $string = microtime();
        fwrite($rFile, $string);
        fclose($rFile);
        
        clearstatcache(true, $this->ignore_other_executes_file);
        if (!is_file($this->ignore_other_executes_file)) {
            exit('We couldn`t create garant for synchronization!');
        }
        
        // echo $string.'<br>';
        
        register_shutdown_function(array($this, 'ignore_other_executes_Callback'));
    }
    
    public function ignore_other_executes_Callback () {
        // echo '<br>';
        // readfile($this->ignore_other_executes_file);
        // echo '<br>'.microtime().'<br>';
        unlink($this->ignore_other_executes_file);
    }
    
    public function fastTVData($ids, $TVList, $isNumber = false, $where = array()) {
        if (!is_array($ids)) {
            $ids = $this->explode(',', $ids);
        }
        if (!is_array($TVList)) {
            $TVList = $this->explode(',', $TVList);
        }
        
        ### Get information about TV`s fields
        $findby = 'name';
        
        if ($isNumber) {
            $findby = 'id';
        }
        
        $TVInfo = $this->fastQuery(
            'modTemplateVar',
            array(
                'where' => array(
                    $findby.':IN' => $TVList,
                    ),
                )
            );
        
        ### Get raw data from DB
        $TVData = $this->fastQuery(
            'modTemplateVarResource',
            array(
                'where' => array_merge(array(
                    'contentid:IN' => $ids,
                    'tmplvarid:IN' => array_keys($TVInfo['data']),
                    ), $where),
                ),
            'contentid',
            true
            );
            
        $arOutput = array();
        foreach ($TVData['data'] as $docID => $items) {
            foreach ($items as $item) {
                $tvName = &$TVInfo['data'][$item['tmplvarid']][0]['name'];
                $arOutput[$docID][$tvName] = $item['value'];
            }
        }
        
        return $arOutput;
    }

}

return 'modxHelper';
