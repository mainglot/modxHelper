<?php

/**
 * class modxHelper by DARTC
 * 
 * version 2014-11-08 12:35
 */

class modxHelper {

	/**
	 * Start object only with $modx
	 * @param modX $modx - main variable of MODX Revo
	 */
	public function __construct($modx) {
		$this->modx = $modx;
	}

	public function __call($method, $arguments) {
		
		$method = strtolower($method);
		switch ($method) {
			
			case 'print_r':
			case 'var_export':
			case 'var_dump':
			    
				return $this->pre($method($arguments[0], 1));
				break;

		}
	}

	/**
	 * Return string into <pre><code>...</code></pre>
	 * @param  string $str
	 * @return string
	 */
	public function pre ($str) {
		return '<pre><code>'.(string)$str.'</code></pre>';
	}

	/**
	 * fastQuery use xPDO-object like simple PDO
	 * @param  string  $classname    name of xPDO classname of object
	 * @param  array   $query        parameters for xPDO-object: select, where, sortby, join, limit, offset
	 * @param  string  $convertField result sort by it
	 * @param  boolean $showSQL      show MySQL-query in result array
	 * @param  string  $return       can be data (default) or onlydata (only result from DB)
	 * @return array
	 */
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
			$objectQuery->sortby($query['sortby']['field'], $query['sortby']['dir']);
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
	
	/**
	 * create HTTP-query and return result
	 * @param  string $url  link
	 * @param  string $type query method: GET or POST
	 * @param  array  $data query-data
	 * @return string       result of query
	 */
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

	/**
	 * getHeaders return only HTTP-headers for query
	 * @param  string $url  link
	 * @return array       HTTP headers
	 */
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

	/**
	 * downloadFileFromTo
	 * @param  string $url  link to file
	 * @param  string $path correct path to file
	 * @return boolean      result of downloading
	 */
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
        
	    	if (filesize($path) > 0) {
	    		return true;
	    	}
	    	unlink($path);
		
		return false;
	}
	
	/**
	 * fast explode array
	 * @param  string  $delimiter 
	 * @param  string  $string   
	 * @param  boolean $unique   
	 * @param  boolean $filter   
	 * @param  boolean $trim     
	 * @return array             
	 */
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
	
	/**
	 * fast convert xml to array
	 * @param  string $xmlstring xml-string
	 * @return array            
	 */
	public function xml2array($xmlstring) {
	    	$xml = simplexml_load_string($xmlstring);
	        $json = json_encode($xml);
	        $array = json_decode($json,TRUE);
	        return $array;
	}
	
	/**
	 * fast convert array to xml
	 * @param  array  $input      
	 * @param  object  $xml        
	 * @param  boolean $numericOff 
	 */
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
    
	/**
	 * fast read xml
	 * @param  string $file   correct path to file
	 * @param  string $return array or any other
	 * @return object|array 
	 */
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
    
	/**
	 * fast write xml to file
	 * @param  string $file  correct path to file
	 * @param  array  $array data
	 * @param  string $into  main xml-tag
	 * @return boolean
	 */
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
    
	/**
	 * ignore_other_executes create temporary file, 
	 * which will be deleted after script ending, 
	 * and any other process will be stoped 
	 * while this temporary file is exist
	 * @uses register_shutdown_function()
	 * @uses self::ignore_other_executes_Callback()
	 * @param  string $filename correct path
	 */
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
    
	/**
	 * fastTVData
	 * @param  mixed  $ids      list ids of modResource
	 * @param  mixed  $TVList   list names or ids of TV
	 * @param  boolean $isNumber must be true if list uses ids
	 * @param  array   $where    xPDOObject->where()
	 * @return array
	 */
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
	
	/**
	 * translit ru to en
	 * @param  string $str
	 * @return strin      
	 */
	public function translit($str) {
		if (isset($this->modx->translit)) {
			return $this->modx->translit->translate($str, 'russian');
		}
		
		if (!isset($this->translitTable)) {
			$this->translitTable = array(
			'а' => 'a',   'б' => 'b',   'в' => 'v',
			'г' => 'g',   'д' => 'd',   'е' => 'e',
			'ё' => 'yo',   'ж' => 'zh',  'з' => 'z',
			'и' => 'i',   'й' => 'j',   'к' => 'k',
			'л' => 'l',   'м' => 'm',   'н' => 'n',
			'о' => 'o',   'п' => 'p',   'р' => 'r',
			'с' => 's',   'т' => 't',   'у' => 'u',
			'ф' => 'f',   'х' => 'x',   'ц' => 'c',
			'ч' => 'ch',  'ш' => 'sh',  'щ' => 'shh',
			'ь' => '_',  'ы' => 'y',   'ъ' => '_',
			'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
			
			'А' => 'A',   'Б' => 'B',   'В' => 'V',
			'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
			'Ё' => 'YO',   'Ж' => 'Zh',  'З' => 'Z',
			'И' => 'I',   'Й' => 'J',   'К' => 'K',
			'Л' => 'L',   'М' => 'M',   'Н' => 'N',
			'О' => 'O',   'П' => 'P',   'Р' => 'R',
			'С' => 'S',   'Т' => 'T',   'У' => 'U',
			'Ф' => 'F',   'Х' => 'X',   'Ц' => 'C',
			'Ч' => 'CH',  'Ш' => 'SH',  'Щ' => 'SHH',
			'Ь' => '_',  'Ы' => 'Y',   'Ъ' => '_',
			'Э' => 'E',   'Ю' => 'YU',  'Я' => 'YA',
			);
		}
		
		return strtr($str, $this->translitTable);
	}
	
	/**
	 * zipProcess - create or update zip-file
	 * @param  array  $arFiles  list of correct filepath
	 * @param  string  $sZipPath correct zip-path
	 * @param  integer $debug    default 0, if need debug must be 1
	 * @return boolean	result of update zip-file
	 */
	public function zipProcess($arFiles, $sZipPath, $debug = 0) {
		if (!is_array($arFiles) || empty($arFiles) || !is_string($sZipPath) || empty($sZipPath)) {
			return false;
		}
		
		$inputFiles = array();
		foreach ($arFiles as $k => $path) {
			if (!file_exists($path)) {
				continue;
			}
			$info = pathinfo($path);
			$info['newfilename'] = $this->translit($info['filename']);
			$info['newbasename'] = str_replace($info['filename'], $info['newfilename'], $info['basename']);
			$info['fullpath'] = $path;
			
			$inputFiles[$info['newbasename']] = $info;
		}
		
		
		$zip = new zipArchive();
		$zip->open($sZipPath, ZIPARCHIVE::CREATE);
		
		$archiveFiles = array();
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$archiveFiles[$zip->getNameIndex($i)] = 1;
		}
		
		
		$files = array(
			'input' => array_keys($inputFiles),
			'archive' => array_keys($archiveFiles),
			);
		
		$addFiles = array_diff($files['input'], $files['archive']);
		$delFiles = array_diff($files['archive'], $files['input']);
		$issFiles = array_intersect($files['archive'], $files['input']);
		
		if (isset($debug) && $debug === 1) {
			echo '<b>Add</b>'.$this->var_export($addFiles);
			echo '<b>Del</b>'.$this->var_export($delFiles);
			echo '<b>Iss</b>'.$this->var_export($issFiles);
		}
		
		
		foreach ($addFiles as $filename) {
			$zip->addFile($inputFiles[$filename]['fullpath'], $filename);
		}
		foreach ($delFiles as $filename) {
			$zip->deleteName($filename);
		}
		
		$bResultStatus = $zip->close();
		
		$zip = null;
		unset($zip);
		
		return $bResultStatus;
	}
	
	
	public $cache_way_default = 'modxhelper/';
	public $cache_key_hash = 'md5';
	public $cache_lifetime = 7200;
	
	/**
	 * cache is simple way to use modX::cacheManager
	 * @uses modX::cacheManager
	 * @param  string  $action   get|set|delete|clean
	 * @param  string  $key      cache-key
	 * @param  array   $data     data to cache
	 * @param  string  $way      path in cache directory
	 * @param  integer $lifetime 
	 * @return array 	'msg' has status and 'data' has data
	 */
	public function cache ($action, $key = '', $data = array(), $way = '', $lifetime = 0) {
		$action = empty($action) || !is_string($action) ? 'get' : $action;
		$key = hash($this->cache_key_hash, (string) $key);
		$lifetime = is_int($lifetime) ? $lifetime : $this->cache_lifetime;
		$way = !empty($way) && is_string($way) ? $way : $this->cache_way_default;
		
		$output = array(
			'msg' => 'Start cache function',
			'data' => array(),
		);
		
		$cache_options = array(xPDO::OPT_CACHE_KEY => $way);
		
		switch ($action) {
		
			case 'get':
				if (empty($key)) {
					$output['msg'] = 'Empty key';
				break;
				}
				
				$output['data'] = $this->modx->cacheManager->get($key, $cache_options);
				if (empty($output['data'])) {
					$output['msg'] = 'Data from cache isn`t exist';
				}
				else {
					$output['msg'] = 'Success';
				}
				break;
			
			case 'add':
			case 'set':
				if (empty($key)) {
					$output['msg'] = 'Empty key';
					break;
				}
				
				$result = $this->modx->cacheManager->set($key, $data, $lifetime, $cache_options);
				$output['data'] = $key;
				if ($result) {
					$output['msg'] = 'Data updated in cache';
				}
				else {
					$output['msg'] = 'Data wasn`t updated in cache';
				}
			break;
			
			case 'delete':
			case 'remove':
				if (empty($key)) {
					$output['msg'] = 'Empty key';
					break;
				}
				
				$result = $this->modx->cacheManager->delete($key, $cache_options);
				$output['data'] = $key;
				if ($result) {
					$output['msg'] = 'Data updated in cache';
				}
				else {
					$output['msg'] = 'Data wasn`t updated in cache';
				}
			break;
			
			case 'clean':
				$result = $this->modx->cacheManager->clean($cache_options);
				if ($result) {
					$output['msg'] = 'Data cleaned in cache';
				}
				else {
					$output['msg'] = 'Data wasn`t cleaned in cache';
				}
			break;
			
			default:
				$output['msg'] = 'Incorrect value of action';
			break;
		}
		
		return $output;
	}
	
	public $iconvFrom;
	public $iconvTo;
	
	public function iconvArray ($array, $from, $to) {
		$this->iconvFrom = (string)$from;
		$this->iconvTo = (string)$to;
		return array_walk_recursive($array, array($this, 'iconv'));	
	}
	
	public function iconv (&$value, $from = '', $to = '') {
		if (empty($this->iconvFrom)) {
			$this->iconvFrom = (string)$from;
		}
		if (empty($this->iconvTo)) {
			$this->iconvTo = (string)$to;
		}
	        if (is_string($value)) {
			$value = iconv($this->iconvFrom, $this->iconvTo, $value);
		}
	}

}

return 'modxHelper';
