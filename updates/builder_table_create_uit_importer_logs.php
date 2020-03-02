<?php namespace Uit\Importer\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateUitImporterLogs extends Migration
{
    public function up()
    {
        Schema::create('uit_importer_logs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('model', 255);
            $table->longText('record_ids')->nullable();
            $table->text('errors');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('uit_importer_logs');
    }
}
