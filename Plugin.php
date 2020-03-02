<?php namespace Uit\Importer;

use System\Classes\PluginBase;
use Uit\Importer\Components\Importer;

class Plugin extends PluginBase
{
    public $require = ['Inetis.Dump'];

    public function registerComponents()
    {
        return [
            Importer::class => 'importer'
        ];
    }

    public function registerSettings()
    {
    }
}
