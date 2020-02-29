<?php namespace Uit\Importer\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateUitImporterLogs extends Migration
{
    public function up()
    {
        Schema::table('uit_importer_logs', function($table)
        {
            $table->integer('file_id')->unsigned();
        });
    }
    
    public function down()
    {
        Schema::table('uit_importer_logs', function($table)
        {
            $table->dropColumn('file_id');
        });
    }
}
