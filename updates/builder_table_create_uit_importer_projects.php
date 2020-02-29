<?php namespace Uit\Importer\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateUitImporterProjects extends Migration
{
    public function up()
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
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('uit_importer_projects');
    }
}
