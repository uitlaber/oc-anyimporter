<?php namespace Uit\Importer\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableDeleteUitImporterProjects extends Migration
{
    public function up()
    {
        Schema::dropIfExists('uit_importer_projects');
    }
    
    public function down()
    {
        Schema::create('uit_importer_projects', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->text('name');
            $table->dateTime('last_parsed')->nullable();
            $table->text('settings')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('csv_path', 191)->nullable();
        });
    }
}
