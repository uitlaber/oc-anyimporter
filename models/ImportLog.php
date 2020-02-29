<?php namespace Uit\Importer\Models;

use Model;

/**
 * Model
 */
class ImportLog extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'uit_importer_logs';

    protected $jsonable = [
        'record_ids',
        'errors'
    ];

    protected $fillable = [
        'model',
        'record_ids',
        'errors',
        'file_id'
    ];
}
