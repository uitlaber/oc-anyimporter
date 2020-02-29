<?php namespace Uit\Importer\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateUitImporterProjects extends Migration
{
    public function up()
    {
        Schema::table('uit_importer_projects', function($table)
        {
            $table->string('csv_path')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('uit_importer_projects', function($table)
        {
            $table->dropColumn('csv_path');
        });
    }
}
