<?php

namespace Uit\Importer\Classes;

use Hash;
use Markdownify\Converter;
use System\Models\File;

class Field
{
    public $headers;
    public $row;
    public $obj;
    public $column;
    public $types;
    public $params;
    public $relations = [];

    public function __construct($headers, $row, $obj)
    {
        $this->headers = $headers;
        $this->row = $row;
        $this->obj = $obj;
    }
    /**
     * Начать волшебство
     *
     * @return void
     */
    function do($column, $types)
    {
        $this->column = $column;
        $this->types = $types;

        if (count($this->extractMethod()) == 2) {
            list($method, $params) = $this->extractMethod();
            $this->params = $params;
        } else {
            $method = $this->extractMethod()[0];
        }

        return $this->{$method}();
    }

    /**
     * Извлечение метода и параметров из строки
     *
     * @return void
     */
    public function extractMethod()
    {
        $result = [];
        $methodAndParams = explode(':', $this->column);

        if (isset($methodAndParams[0])) {
            $result[] = str_replace('@', '', $methodAndParams[0]);
        }
        if (isset($methodAndParams[1])) {
            $result[] = explode(',', $methodAndParams[1]);
        }
        return $result;
    }

    /**
     * Округление числа
     * @return array
     */
    public function round()
    {
        list($columnID, $precision) = $this->params;
        $value = $this->rowValue($columnID);
        return ['value' => round($value, $precision), 'is_object_value' => true];
    }

    /**
     * Рандомное значение
     *
     * @return void
     */
    public function random()
    {
        $limit = $this->params[0] ?? 5;

        return ['value' => str_random($limit), 'is_object_value' => true];
    }

    /**
     * Рандомное значение из указанных
     * @return array
     */
    public function array_rand()
    {
        $random_item = $this->params[array_rand($this->params,1)];
        return ['value' => $random_item, 'is_object_value' => true];
    }

    public function toArray()
    {
        list($skip, $name, $value) = $this->params;
        $length = count($this->row);
        $data = [];
        for ($i = $skip; $i < $length; $i ++){
            if($this->rowValue($i)){
                $data[] = [
                    $name => $this->headers[$i],
                    $value => $this->rowValue($i)
                ];
            }
        }
//        dd($data);
        return ['value' => $data, 'is_object_value' => true];
    }

    /**
     * Значение по умолчанию
     * Если в колонке нет данных то выставляется значение по умолчанию
     *
     * @return void
     */
    public function default()
    {
        list($columnID, $defaultValue) = $this->params;
        $value = $defaultValue;
        if ($this->rowValue($columnID)) {
            $value = $this->rowValue($columnID);
        } elseif ($defaultValue == "#fake_email") {
            $value = str_random(3) . str_random(3) . '@fake.ru';
        }

        return ['value' => $value, 'is_object_value' => true];
    }

    /**
     * Значение строки по индексу
     *
     * @param [type] $key
     * @return void
     */
    public function rowValue($headerID)
    {
        if (substr($headerID, 0, 7) === "column-") {
            $headerID = str_replace('column-', '', $headerID);
        }

        return $this->row[$headerID] ?? null;
    }

    /**
     * @belongsToMany:MihailBishkek\Birzha\Models\Material,title,column-30,materials
     */
    public function belongsToMany()
    {
        list($relationModel, $searchColumn, $columnID, $relationName) = $this->params;
        $searchTexts = $this->rowValue($columnID);
        $searchTextsArray = explode(',',$searchTexts);
        foreach ($searchTextsArray as $searchText){
            if($searchText) {
                $result = (new $relationModel)->where($searchColumn, $searchText)->first();
                if(is_null($result)) return;
                $this->relations['belongsToMany'][$relationName][] = $result->id;
            }
        }


    }

    /**
     * Relation Загрузка одного фала
     * @return void|null
     */
    public function attachOne()
    {
        list($from, $headerID, $relationName) = $this->params;
        $file = null;
        $value = $this->rowValue($headerID);
        if ($value == '' || is_null($value)) return null;
        try {
            switch ($from) {
                case 'url':
                    $file = (new File())->fromUrl($this->rowValue($headerID));
                case 'file':
                    $file = (new File())->fromFile(storage_path() . '/' . $this->rowValue($headerID));
            }
        } catch (\Exception $e) {
        }
        if (is_null($file)) return;

        $this->relations['attach'][$relationName] = $file;
    }

    /**
     * Relation  Загрузка нексколько файлов
     * @return void|null
     */
    public function attachMany()
    {
        list($from, $headerID, $relationName) = $this->params;
        $file = null;
        $value = $this->rowValue($headerID);
        if ($value == '' || is_null($value)) return null;
        $links = explode(',', $value);
        foreach ($links as $link) {
            try {
                switch ($from) {
                    case 'url':
                        $file = (new File())->fromUrl($this->rowValue($headerID));
                    case 'file':
                        $file = (new File())->fromFile(storage_path() . '/' . $this->rowValue($headerID));
                }
            } catch (\Exception $e) {
            }
            if (is_null($file)) return;

            $this->relations['attach'][$relationName][] = $file;
        }
    }


    /**
     * Хеш строки
     *
     * @return void
     */
    public function hash()
    {
        $hashed = Hash::make($this->params[0]);
        return ['value' => $hashed, 'is_object_value' => true];
    }

    /**
     * Значение уже добавленного в объект
     *
     * @return array
     */
    public function own()
    {
        return ['value' => $this->obj[$this->params[0]], 'is_object_value' => true];
    }

    /**
     * Указанное значение
     *
     * @return array
     */
    function var()
    {
        return ['value' => $this->params[0], 'is_object_value' => true];
    }

    public function required()
    {
        return ['value' => str_random(10), 'is_object_value' => true];
    }

    /**
     * Поиск строки в базе по колонке
     *
     * @return void
     */
    public function searchInModel()
    {
        list($model, $column, $search_text, $return_column) = $this->params;
        $result = (new $model)->where($column, $search_text)->first();
        return (!is_null($result)) ? $result->{$return_column} : null;
    }

    /**
     * Конвертация HTML в Markdown
     *
     * @return void
     */
    public function markdownify()
    {
        $columnID = $this->params[0];
        $converter = new Converter;
        $markdown = $converter->parseString($this->rowValue($columnID));

        return ['value' => $markdown, 'is_object_value' => true];
    }

    /**
     * Вывод данных из модели
     *
     * @return array
     */
    public function type()
    {
        list($name, $key) = $this->params;

        return ['value' => $this->types[$name]->{$key}, 'is_object_value' => true];
    }
}
