<?php

namespace Uit\Importer\Classes;

use Hash;
use Markdownify\Converter;

class Field
{
    public $obj;
    public $row;
    public $column;
    public $types;
    public $params;

    public function __construct($column, $types, $row, $obj)
    {
        $this->obj = $obj;
        $this->row = $row;
        $this->column = $column;
        $this->types = $types;
    }

    /**
     * Начать волшебство
     *
     * @return void
     */
    function do()
    {
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
    public function rowValue($key)
    {
        if (substr($key, 0, 7) === "column-") {
            $key = str_replace('column-', '', $key);
        }

        return $this->row[$key] ?? null;
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
