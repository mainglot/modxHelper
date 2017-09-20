<?php

/*
Пример использования

    // Парсми ВСЕ import.xml и offers.xml (Например, если есть import2.xml и тд)
    
    $arFiles = glob(rtrim($DIR_NAME, '/') . '/*.xml');
    $this->modx->getService('shop', 'Shop1cXML', MODX_BASE_PATH . 'extension/lib/');

    $shop = $this->modx->shop;
    $shop->truncateTables();

    sort($arFiles);

    foreach ($arFiles as $filepath) {
        $shop->resetObject();
        $shop->processFile($filepath);
        $shop->saveItems();
    }
    $shop->resetObject();

*/

class Shop1cXML {
    
    protected $tmpCategoriesClass = 'Shopmodx1cTmpCategory';
	protected $tmpProductsClass = 'Shopmodx1cTmpProduct';
	
	public $groups = [];
	public $references = [];
	public $items = [];
    
    public function __construct ($modx, array $config = array()) {
        $this->modx = $modx;
        $this->config = array_merge([
            
            ], $config);
            
        $this->modx->addPackage('shopModx1C', MODX_CORE_PATH . 'components/shopmodx1c/model/');
        $this->modx->getService('helper', 'modxHelper', MODX_BASE_PATH . 'extension/lib/');
    }
    
    public function resetObject () {
        $this->groups = [];
        $this->references = [];
        $this->items = [];
        $this->_1cFileType = '';
    }
    
    public function truncateTables () {
        $classes = array(
            $this->tmpCategoriesClass,
            $this->tmpProductsClass,
        );
        
        foreach($classes as $class){
            if($table = $this->modx->getTableName($class)){
                $this->modx->exec("TRUNCATE TABLE {$table}");
            }
        }
    }
    
    public function saveGroups () {
        if (empty($this->groups)) return false;
    }
    
    public function saveItems () {
        if (empty($this->items)) return false;
        
        $arTemps = [];
        foreach ($this->items as $id => $item) {
            $this->items[$id] = null;
            $arTemps[$id] = $item;
            if (count($arTemps) > 500) {
                $this->tryToInsert($arTemps);
                $arTemps = [];
            }
        }
        if (count($arTemps)) {
            $this->tryToInsert($arTemps);
        }
        unset($arTemps);
        $this->items = [];
    }
    
    
    public function tryToInsert($arItems) {
        $ids = array_keys($arItems);
        $existIds = $this->modx->helper->fastQuery($this->tmpProductsClass, ['where' => ['article:IN' => $ids]], 'article')['data'];
        if (is_array($existIds) && !empty($existIds)) {
            $existIds = array_keys($existIds);
        } else {
            $existIds = [];
        }
        
        // echo $this->modx->helper->var_export($existIds);
        
        $arInsert = [];
        $arUpdate = [];
        $table = $this->modx->getTableName($this->tmpProductsClass);
        
        foreach ($arItems as $id => $item) {
            $arItems[$id] = null;
            
            if (empty($existIds) || !in_array($id, $existIds)) {
                
                if ($this->_1cFileType === 'offers') {
                    continue;
                }
                
                $item2 = [
                        'article' => $item['id'],
                        'title' => $item['name'],
                        'description' => $item['description'],
                        'image' => $item['image'],
                        'groups' => $item['groups'],
                        'properties' => $item,
                        'requisites' => '',
                        'processed' => '0',
                        ];
                foreach ($item2 as $k=>$v) {
                    $item2[$k] = is_array($item2[$k]) ? json_encode($v) : $v;
                }
                
                foreach ($item2 as $k=>$v) {
                    $item2[$k] = $this->modx->quote($v);
                }
                $arInsert[] = $item2;
            } else {
                $arUpdate[] = $item;
            }
            
        }
        
        if (!empty($arInsert)) {
            $arInsert = array_map(function($v){
                return '('.implode(', ', $v).')';
            }, $arInsert);
            $fields = ['article', 'title', 'description', 'image', 'groups', 'properties', 'requisites', 'processed'];
            $return1 = $this->insertInDataBase($table, $arInsert, $fields);
        }
        
        if (!empty($arUpdate)) {
            $modx = &$this->modx;
            $arUpdate = array_map(function($v) use ($table, $modx) {
                $s = 'UPDATE '.$table.' SET requisites = '.$modx->quote(json_encode($v)).' WHERE article = '.$modx->quote($v['id']).'; ';
                return $s;
            }, $arUpdate);
            
            $sql = implode("\n", $arUpdate);

            $s = $this->modx->prepare($sql);
            $resut = $s->execute();
            if ($result === false) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'SQL ERROR Import');
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, print_r($s->errorInfo(), 1));
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, $sql);
            }
            
            $return2 = $result;
        }
        
        return true;
    }
    
    
    /*
        Общая функция для составления запроса на массовую вставку записей
    */
    protected function insertInDataBase($table, array $rows, array $columns){
        
        $columns_str = implode(", ", $columns);
        
        $sql = "INSERT INTO {$table} 
            ({$columns_str}) 
            VALUES \n";
            
        $sql .= implode(",\n", $rows). ";";
        
        $s = $this->modx->prepare($sql);
        
        $result = $s->execute();
        if(!$result){
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'SQL ERROR Import');
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, print_r($s->errorInfo(), 1));
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, $sql);
        }
        return $result;
    }
    
    public function saveReferences () {
        if (empty($this->references)) return false;
    }
    
    public function readBlock ($name, $endName = '', $data = array()) {
        $reader = &$this->reader;
        if (empty($endName)) {
            $endName = $name;
        }
        
        while ($reader->read()) {
            if (($reader->nodeType == XMLReader::ELEMENT) && ($reader->name == $name)) {
                
                $this->doAction($name, $data);
                while($reader->nodeType != XMLReader::END_ELEMENT && $reader->localName == $name) {
                    $reader->read();
                }
                
                if ($name === $endName) {
                    break;
                }
                
            } elseif ($reader->nodeType == XMLReader::END_ELEMENT && $reader->localName == $endName){
                break;
            }
        }
        
        return true;
    }
    
    public function processFile ($filepath) {
        
        $this->reader = new XMLReader();
        $reader = &$this->reader;
        
        $reader->open($filepath);
        
        $output = false;
        
        switch ($this->get1cFileType($filepath)) {
            case 'import':
                $output = $this->readBlock('КоммерческаяИнформация');
                break;
            case 'offers':
                $output = $this->readBlock('ПакетПредложений');
                break;
        }
        
        return $output;
    }
    
    protected $_1cFileType = '';
    public function get1cFileType ($filepath) {
        $filename = basename($filepath);
        $this->_1cFileType = '';
        if (strpos($filename, 'import') === 0) $this->_1cFileType = 'import';
        if (strpos($filename, 'offers') === 0) $this->_1cFileType = 'offers';
        return $this->_1cFileType;
    }
    
    public function doAction ($name) {
        $arActions = [
            'КоммерческаяИнформация' => 'General',
            'Классификатор' => 'Classify',
            'Группы' => 'Groups',
            'Группа' => 'Group',
            'Свойства' => 'References',
            'Свойство' => 'Reference',
            'Каталог' => 'Catalog',
            'Товары' => 'Items',
            'Товар' => 'Item',
            'ПакетПредложений' => 'PropositionGeneral',
            'Предложения' => 'PropositionItems',
            'Предложение' => 'PropositionItem',
            ];
        if (isset($arActions[$name])) {
            return $this->{'_action' . $arActions[$name]}($name);
        }
        return false;
    }
    
    public function getCurrentNode () {
        return simplexml_load_string($this->reader->readOuterXML());
    }
    
    public function _actionGeneral ($name) {
        $this->readBlock('Классификатор');
        $this->readBlock('Каталог');
    }
    
    public function _actionCatalog ($name) {
        $this->readBlock('Товары');
    }
    
    public function _actionClassify ($name) {
        $this->readBlock('Группы');
        
        $this->readBlock('Свойства');
    }
    
    public function _actionGroups ($name, $data = array()) {
        $this->readBlock('Группа', $name, $data);
    }
    
    public function _actionGroup ($name, $data = array()) {
        $xml = $this->getCurrentNode();
        
        $temp = [
            'id' => (string) $xml->{'Ид'},
            'name' => (string) $xml->{'Наименование'},
            'parent_id' => isset($data['parent_id']) ? $data['parent_id'] : '',
            'groups' => [],
            ];
        
        
        
        $this->groups[ $temp['id'] ] = $temp;
        
        // echo '<pre>'.var_export($temp, true).'</pre>';
        
        if (isset($xml->{'Группы'})) {
            $this->readBlock('Группы', '', ['parent_id' => $temp['id']]);
        }
        
        // echo 'Группа - ' . $temp['name'].'<br>';
    }
    
    public function _actionReferences ($name) {
        $this->readBlock('Свойство', $name);
    }
    
    public function _actionReference ($name) {
        $xml = $this->getCurrentNode();
        
        $temp = [
            'id' => (string) $xml->{'Ид'},
            'name' => (string) $xml->{'Наименование'},
            'values' => [],
            ];
        
        if (isset($xml->{'ВариантыЗначений'}->{'Справочник'})) {
            foreach ($xml->{'ВариантыЗначений'}->{'Справочник'} as $xml2) {
                $temp2 = [
                    'id' => (string) $xml2->{'ИдЗначения'},
                    'name' => (string) $xml2->{'Значение'},
                    ];
                $temp['values'][ $temp2['id'] ] = $temp2;
            }
        }
        
        
        $this->references[ $temp['id'] ] = $temp;
        
        // echo 'Свойство - ' .  $temp['name'] . '<br>';
        // echo ' -- <pre>'.var_export($temp['values'], true).'</pre>';
        
    }
    
    public function _actionItems ($name) {
        $this->readBlock('Товар', $name);
    }
    
    public function _actionItem ($name) {
        $xml = $this->getCurrentNode();     
        
        $temp = [
            'id' => (string) $xml->{'Ид'},
            'name' => (string) $xml->{'Наименование'},
            'article' => (string) $xml->{'Артикул'},
            'image' => (string) $xml->{'Картинка'},
            'description' => (string) $xml->{'Описание'},
            'groups' => [],
            'properties' => [],
            ];
        
        if (isset($xml->{'Группы'}->{'Ид'})) {
            foreach ($xml->{'Группы'}->{'Ид'} as $xml2) {
                $temp2 = (string) $xml2;
                $temp['groups'][] = $temp2;
            }
            unset($temp2, $xml2);
        }
        
        if (isset($xml->{'ЗначенияСвойств'}->{'ЗначенияСвойства'})) {
            foreach ($xml->{'ЗначенияСвойств'}->{'ЗначенияСвойства'} as $xml2) {
                $temp2 = [
                    'id' => (string) $xml2->{'Ид'},
                    'name_id' => (string) $xml2->{'Значение'},
                    'title' => '',
                    'name' => '',
                    ];
                if ($temp2['id'] && isset($this->references[ $temp2['id'] ]['name'])) {
                    $temp2['title'] = $this->references[ $temp2['id'] ]['name'];
                }
                if ($temp2['id'] && $temp2['name_id'] && isset($this->references[ $temp2['id'] ]['values'][ $temp2['name_id'] ]['name'])) {
                    $temp2['name'] = $this->references[ $temp2['id'] ]['values'][ $temp2['name_id'] ]['name'];
                }
                
                $temp['properties'][] = $temp2;
            }
            unset($temp2, $xml2);
        }
        
        if (isset($xml->{'ЗначенияРеквизитов'}->{'ЗначениеРеквизита'})) {
            foreach ($xml->{'ЗначенияРеквизитов'}->{'ЗначениеРеквизита'} as $xml2) {
                $temp2 = [
                    'title' => (string) $xml2->{'Наименование'},
                    'name' => (string) $xml2->{'Значение'},
                    ];
                $temp['properties'][] = $temp2;
            }
            unset($temp2, $xml2);
        }
        
        
        $this->items[ $temp['id'] ] = $temp;
        
        // echo 'Товар - ' . $temp['article'] . '<br>';
    }
    
    public function _actionPropositionGeneral ($name) {
        $this->readBlock('Предложения');
    }
    
    public function _actionPropositionItems ($name) {
        $this->readBlock('Предложение', $name);
    }
    
    public function _actionPropositionItem ($name) {
        $xml = $this->getCurrentNode();
        
        $temp = [
            'id' => (string) $xml->{'Ид'},
            'barcode' => (string) $xml->{'Штрихкод'},
            'name' => (string) $xml->{'Наименование'},
            'properties' => [],
            'prices' => [],
            'propositions' => [],
            'amount' => isset($xml->{'Количество'}) ? (float) $xml->{'Количество'} : 0,
            ];

        if (isset($xml->{'ХарактеристикиТовара'}->{'ХарактеристикаТовара'})) {
            foreach ($xml->{'ХарактеристикиТовара'}->{'ХарактеристикаТовара'} as $xml2) {
                $temp2 = [
                    'name' => (string) $xml2->{'Наименование'},
                    'value' => (string) $xml2->{'Значение'},
                    ];
                $temp['properties'][] = $temp2;
            }
            unset($temp2, $xml2);
        }
        
        if (isset($xml->{'Цены'}->{'Цена'})) {
            foreach ($xml->{'Цены'}->{'Цена'} as $xml2) {
                $temp2 = [
                    'price' => (string) $xml2->{'ЦенаЗаЕдиницу'},
                    'valute' => (string) $xml2->{'Валюта'},
                    'price_type_id' => (string) $xml2->{'ИдТипаЦены'},
                    ];
                $temp['prices'][] = $temp2;
            }
            unset($temp2, $xml2);
        }
        
        if (strpos($temp['id'], '#') === false) {
            $this->items[ $temp['id'] ] = $temp;
        } else {
            $temp2 = explode('#', $temp['id']);
            
            $temp['id'] = $temp2[0];
            $temp['proposition_id'] = $temp2[1];
            
            if (!isset($this->items[ $temp['id'] ])) {
                $this->items[ $temp['id'] ] = $temp;
            }
            
            unset($temp['propositions']);
            $this->items[ $temp['id'] ]['propositions'][ $temp['proposition_id'] ] = $temp;
        }
        
        // echo 'Предложение - ' . $temp['name'] . '<br>';
        
    }
    
}
