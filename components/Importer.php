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

    public function onUpload()
    {
        $csv = Input::file('csv');

        $file = (new File(['field' => 'CSV-IMPORTER']))->fromPost($csv);

        $file->save();

        return [
            '.aviable-csv' => $this->renderPartial('@_files', ['files' => $this->loadAviableFiles()]),
        ];

    }

    public function loadAviableFiles()
    {

        return File::where('field', 'CSV-IMPORTER')->get();

    }

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

    public function onSave()
    {

        $file = $this->getCSVFile();

        if (is_null($file)) {
            return null;
        }

        $file->description = post('config');
        $file->save();

    }

    public function onImport()
    {

        $file = $this->getCSVFile();

        if (is_null($file)) {
            return null;
        }

        $configs = [];
        $rows = [];
        $logs = [];

        $configs = Yaml::parse($file->description);
        $rows = $this->parseCSV($file);

        unset($rows[0]);

        foreach ($configs as $type) {
            $type['model']::withTrashed()->where('is_parsed', $file->id)->forceDelete();
            $logs['error'][$type['model']] = [];
            $logs['added'][$type['model']] = [];
        }

        foreach ($rows as $rowIndex => $row) {
            $types = [];
            foreach ($configs as $typeName => $config) {

                $obj = new $config['model'];
                $uniques = [];

                if (isset($config['unique'])) {
                    $uniques = explode(',', $config['unique']);
                }

                $primaryKey = $config['primaryKey']??'id';


                foreach ($config['fields'] as $field => $column) {

                    if (substr($column, 0, 7) === "column-") {
                        $columnID = str_replace('column-', '', $column);
                        $obj->{$field} = trim($row[$columnID]);
                    } else if (substr($column, 0, 1) === "@") {
                        //  var_dump($column);

                        $result = (new Field($column, $types, $row, $obj))->do();

                        if (isset($result['is_object_value'])) {
                            $obj->{$field} = $result['value'];
                        }

                    }
                }
                $obj->is_parsed = $file->id;
                $obj->deleted_at = null;
                $exists = null;
                if (count($uniques)) {
                    foreach ($uniques as $column) {
                        $exists = (new $config['model'])->where($column, $obj->{$column})->first();
                        if (!is_null($exists)) {
                            break;
                        };
                    }
                }
                if (!is_null($exists)) {
                    $types[$typeName] = $exists;
                } else {

                    try{
                        $obj->save();
                    }catch(\Exception $e){
                        $logs['error'][$config['model']][] = $rowIndex.' '.$typeName.' '.$e->getMessage();
                        continue 2;
                    }


                    $logs['added'][$config['model']][] = $obj->{$primaryKey};

                    $types[$typeName] = $obj;


                }


            }

        }

        foreach ($configs as $type) {
            ImportLog::create([
                'model' => $type['model'],
                'record_ids' => $logs['added'][$type['model']],
                'errors' => $logs['error'][$type['model']],
            ]);
        }

    }

    public function onLoadRow(){
        $file = $this->getCSVFile();
        $rows = $this->parseCSV($file);
        $index = post('row');
        return [
            '#rowBody' => $this->renderPartial('@_row', compact('rows','index'))
        ];
    }

    public function getCSVFile()
    {

        if (is_null(post('name'))) {
            return null;
        }

        $file = File::where('disk_name', post('name'))->where('field', 'CSV-IMPORTER')->first();

        return $file;
    }

    public function parseCSV($file)
    {
        $path = Config::get('filesystems.disks.local.root', storage_path() . '/app') . '/' . $file->getDiskPath();
        $reader = Reader::createFromPath($path, 'r');
        $reader->setDelimiter(';');
        $rows = $reader->fetchAll();
        return collect($rows);
    }

}
