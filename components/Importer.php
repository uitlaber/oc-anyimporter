<?php namespace Uit\Importer\Components;

use Backend\Facades\BackendAuth;
use Cms\Classes\ComponentBase;
use Config;
use Flash;
use Input;
use League\Csv\Reader;
use Response;
use System\Models\File;
use Uit\Importer\Classes\Field;
use Yaml;
use Uit\Importer\Models\ImportLog;

class Importer extends ComponentBase
{
    public $files;

    public function componentDetails()
    {
        return [
            'name' => 'Importer Component',
            'description' => 'No description provided yet...',
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function init()
    {
        if (!BackendAuth::check()) {
            return Response::make($this->controller->run('404')->getContent(), 404);
        }
    }


    public function onRun()
    {
        $this->files = $this->loadAviableFiles();
        // $this->addCss('assets/css/selectize.css');
        $this->addCss('assets/css/selectize.bootstrap3.css');
        $this->addJs('assets/js/standalone/selectize.js');
    }

    /**
     * Загрузка файла csv
     * @return array
     */
    public function onUpload()
    {
        $csv = Input::file('csv');
        $file = (new File(['field' => 'CSV-IMPORTER']))->fromPost($csv);
        $file->save();

        return [
            '.aviable-csv' => $this->renderPartial('@_files', ['files' => $this->loadAviableFiles()]),
        ];
    }

    /**
     * Загрузка уже загруженных файлов
     * @return mixed
     */
    public function loadAviableFiles()
    {
        return File::where('field', 'CSV-IMPORTER')->get();
    }

    /**
     * Удаление файла csv
     * @return array|null
     */
    public function onDeleteCSV()
    {
        $file = $this->getCSVFile();
        if (is_null($file)) {
            return null;
        }
        $file->delete();
        Flash::success('Файл удален');

        return [
            '.aviable-csv' => $this->renderPartial('@_files', ['files' => $this->loadAviableFiles()]),
        ];
    }

    public function onDeleteParsedData()
    {
        $file = $this->getCSVFile();
        if (is_null($file)) {
            return null;
        }
        $configs = [];
        $configs = Yaml::parse($file->description);

        $logs = ImportLog::where('file_id', $file->id)->get();
        foreach ($logs as $log) {
            foreach ($configs as $type) {
                $model = $log->model;
                $model::withTrashed()->whereIn('id', $log->record_ids)->forceDelete();
            }
            $log->delete();
        }
    }

    /**
     * Выбрать файл из списка
     * @return array|null
     */
    public function onSelectCSV()
    {
        $file = $this->getCSVFile();
        if (is_null($file)) {
            return null;
        }
        $rows = $this->parseCSV($file);

        return [
            '.csv-settings' => $this->renderPartial('@_info', compact('rows', 'file')),
        ];
    }

    /**
     * Cохранить настройки
     * @return |null
     */
    public function onSave()
    {
        $file = $this->getCSVFile();
        if (is_null($file)) {
            return null;
        }
        $file->description = post('config');
        $file->save();
    }

    /**
     * Импорт
     * @return |null
     */
    public function onImport()
    {
        $file = $this->getCSVFile();
        if (is_null($file)) {
            return null;
        }

        $logs = [];

        $configs = Yaml::parse($file->description);
        $rows = $this->parseCSV($file);
        $headers = $rows[0];
        unset($rows[0]);

        $importLog = ImportLog::where('file_id', $file->id)->get();
        if ( $importLog->count()) {
            foreach ($importLog as $log) {
                $model = $log->model;
                $model::withTrashed()->whereIn('id', $log->record_ids)->forceDelete();
                $log->delete();
            }
        }
        foreach ($configs as $type) {
            $logs['error'][$type['model']] = [];
            $logs['added'][$type['model']] = [];
            $logs['skipped'][$type['model']] = [];
        }

        foreach ($rows as $rowIndex => $row) {
            $types = [];

            foreach ($configs as $typeName => $config) {

                $obj = new $config['model'];

                // if (isset($config['contains'])) {
                //     $contains = explode(',', $config['contains']);
                //     list($cModel, $cHeaderID, $cColumn) = $contains;
                //     $cExists = false;
                //     if (isset($row[$cHeaderID])) {
                //         $cExists = (new $cModel)->where($cColumn, $row[$cHeaderID])->exists();
                //     }
                //     if (!$cExists) {
                //         $logs['skipped'][$type['model']][] = $rowIndex.' пропущен - не потходит по параметрам';
                //         break 1;
                //     }
                // }

                //Уникальный ключь в таблице если не указан то по умолчанию ID
                $primaryKey = $config['primaryKey'] ?? 'id';

                $fieldObject = new Field($headers, $row, $obj);
                //Тут начинается чудо :D
                foreach ($config['fields'] as $field => $column) {
                    if (substr($column, 0, 7) === "column-") {
                        $columnID = str_replace('column-', '', $column);
                        $obj->{$field} = trim($row[$columnID]);
                    } elseif (substr($column, 0, 1) === "@") {
                        $fieldOutput = $fieldObject->do($column, $types);
                        if (isset($fieldOutput['is_object_value'])) {
                            $obj->{$field} = $fieldOutput['value'];
                        }
                    }
                }
              
                $obj->deleted_at = null;

                //Проверка на ункикальность
                $exists = null;
                if (isset($config['unique'])) {
                    $uniques = explode(',', $config['unique']);
                    if (count($uniques) && is_array($uniques)) {

                        foreach ($uniques as $column) {
                            //@TODO что то не так тут
                            $exists = (new $config['model'])->where($column, $obj->{$column})->first();
                            if (!is_null($exists)) {
                                break;
                            };
                        }
                    }
                }

                //Если в таблице уже существует то не записываем
                if (!is_null($exists)) {
                    $types[$typeName] = $exists;
                    // $logs['skipped'][$type['model']][] = $rowIndex . ' пропущен - уже существует';

                } else {
                    try {
                        $obj->save();
                        $types[$typeName] = $obj;
                    } catch (\Exception $e) {
                        $logs['error'][$config['model']][] = $rowIndex . ' ' . $typeName . ' ' . $e->getMessage();
                        continue 2;
                    }

                    if (isset($fieldObject->relations['belongsToMany']) && count($fieldObject->relations['belongsToMany'])) {
                        foreach ($fieldObject->relations['belongsToMany'] as $relationName => $relation_ids) {
                            $obj->$relationName()->sync($relation_ids);
                        }
                    }

                    if (isset($fieldObject->relations['attach']) && count($fieldObject->relations['attach'])) {
                        foreach ($fieldObject->relations['attach'] as $relationName => $relation) {
                            if (is_array($relation)) {
                                $obj->$relationName()->addMany($relation);
                            }else {
                                $obj->$relationName()->add($relation);
                            }
                        }
                    }

                    $logs['added'][$config['model']][] = $rowIndex.' добавлен ID:'.$obj->{$primaryKey};

                }
            }
        }

        // Запись в логи каждуб группу типа
        $reportLogs = [];
        foreach ($configs as $type) {
            ImportLog::create([
                'model' => $type['model'],
                'record_ids' => $logs['added'][$type['model']],
                'errors' => $logs['error'][$type['model']],
                'file_id' => $file->id
            ]);
            $reportLogs[] = $logs['error'][$type['model']];
            $reportLogs[] = $logs['added'][$type['model']];
            $reportLogs[] = $logs['skipped'][$type['model']];
        }

        return [
            '.error-logs' => $this->renderPartial('@_logs', ['logs' => $reportLogs])
        ];
    }

    /**
     * Загрузка данных по индексу
     * @return array
     */
    public function onLoadRow()
    {
        $file = $this->getCSVFile();
        $rows = $this->parseCSV($file);
        $index = post('row');
        return [
            '#rowBody' => $this->renderPartial('@_row', compact('rows', 'index'))
        ];
    }

    /**
     * Модель файла csv
     * @return |null
     */
    public function getCSVFile()
    {
        if (is_null(post('name'))) {
            return null;
        }
        $file = File::where('disk_name', post('name'))->where('field', 'CSV-IMPORTER')->first();

        return $file;
    }

    /**
     * Парсинг csv
     * @param $file
     * @return \Illuminate\Support\Collection|\October\Rain\Support\Collection
     */
    public function parseCSV($file)
    {
        $path = Config::get('filesystems.disks.local.root', storage_path() . '/app') . '/' . $file->getDiskPath();
        $reader = Reader::createFromPath($path, 'r');
        $reader->setDelimiter(';');
        $rows = $reader->fetchAll();
        return collect($rows);
    }
}
