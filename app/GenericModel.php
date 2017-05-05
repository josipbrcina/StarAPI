<?php

namespace App;

use App\Services\GenericModelQueryBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Class GenericModel
 * @package App
 */
class GenericModel extends StarModel
{
    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'genericModels';

    /**
     * Collection name that's set statically to be reused on each instance creation
     *
     * @var string
     */
    protected static $collectionName;

    /**
     * Custom constructor so that we can set correct collection for each created instance
     *
     * GenericModel constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->collection = self::$collectionName;
    }

    /**
     * Get the guarded attributes for the model.
     *
     * @return array
     */
    public function getGuarded()
    {
        return $this->guarded = [];
    }

    /**
     * Validates collection is set properly, throws exception otherwise
     *
     * @param $resourceType
     * @return $this
     * @throws \Exception
     */
    public function confirmResourceOf($resourceType)
    {
        if (self::$collectionName !== $resourceType) {
            throw new \Exception('Model resource not configured correctly');
        }
        return $this;
    }

    /**
     * @param $resourceName
     */
    public static function setCollection($resourceName)
    {
        self::$collectionName = $resourceName;
    }

    /**
     * @return string
     */
    public static function getCollection()
    {
        return self::$collectionName;
    }

    /**
     * Will force update of all fields on save
     *
     * @return $this
     */
    public function markAsDirty()
    {
        $idValue = array_key_exists('_id', $this->original) ? $this->original['_id'] : null;
        $this->original = [];
        if ($idValue !== null) {
            $this->original['_id'] = $idValue;
        }
        return $this;
    }

    /**
     * Archive model - save it to {resource}_archived collection
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function archive()
    {
        if (strpos(self::getCollection(), '_archived')) {
            throw new \Exception('Model collection now allowed to archive', 403);
        }

        $preSetCollection = self::getCollection();
        $archivedModel = $this->replicate();
        self::setCollection($this->collection . '_archived');
        $archivedModel->collection = self::$collectionName;
        $archivedModel->_id = $this->original['_id'];

        if ($archivedModel->save()) {
            parent::delete();
            self::setCollection($preSetCollection);
            return $archivedModel;
        }
    }

    /**
     * Unarchive model - return to origin collection
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function unArchive()
    {
        if (!strpos(self::getCollection(), '_archived')) {
            throw new \Exception('Model collection now allowed to unArchive', 403);
        }

        $preSetCollection = self::getCollection();
        $unArchivedModel = $this->replicate();
        self::setCollection(str_replace('_archived', "", $this->collection));
        $unArchivedModel->collection = self::$collectionName;
        $unArchivedModel->_id = $this->original['_id'];

        if ($unArchivedModel->save()) {
            parent::delete();
            self::setCollection($preSetCollection);
            return $unArchivedModel;
        }
    }

    /**
     * Delete model - save it to {resource}_deleted collection
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function delete()
    {
        if (strpos(self::getCollection(), '_deleted')) {
            throw new \Exception('Model collection now allowed to delete', 403);
        }

        $preSetCollection = self::getCollection();
        $deletedModel = $this->replicate();
        self::setCollection($this->collection . '_deleted');
        $deletedModel->collection = self::$collectionName;
        $deletedModel->_id = $this->original['_id'];

        if ($deletedModel->save()) {
            parent::delete();
            self::setCollection($preSetCollection);
            return $deletedModel;
        }
    }

    /**
     * Restore model - return to origin collection
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function restore()
    {
        if (!strpos(self::getCollection(), '_deleted')) {
            throw new \Exception('Model collection now allowed to restore', 403);
        }

        $preSetCollection = self::getCollection();
        $restoredModel = $this->replicate();
        self::setCollection(str_replace('_deleted', "", $this->collection));
        $restoredModel->collection = self::$collectionName;
        $restoredModel->_id = $this->original['_id'];

        if ($restoredModel->save()) {
            parent::delete();
            self::setCollection($preSetCollection);
            return $restoredModel;
        }
    }

    /**
     * Save model to specific collection and database
     * @param null $collection
     * @param null $databaseName
     * @return bool
     */
    public function saveModel($collection = null, $databaseName = null)
    {
        $databaseToReconnect = null;
        $preSetCollection = null;

        if ($collection !== null) {
            $preSetCollection = self::getCollection();
            self::setCollection($collection);
            $this->collection = self::getCollection();
        }

        if ($databaseName !== null) {
            $databaseToReconnect = Config::get('database.connections.' . Config::get('database.default') . '.database');
            self::setDatabaseConnection($databaseName);
        }

        $savedModel = parent::save();

        if ($databaseName !== null) {
            self::setDatabaseConnection($databaseToReconnect);
        }

        if ($collection !== null) {
            self::setCollection($preSetCollection);
        }

        return $savedModel;
    }

    /**
     * @param null $collection
     * @param null $databaseName
     * @param array $attributes
     * @return static
     */
    public static function createModel(array $attributes = [], $collection = null, $databaseName = null)
    {
        $databaseToReconnect = null;
        $preSetCollection = null;

        if ($collection !== null) {
            $preSetCollection = self::getCollection();
            self::setCollection($collection);
        }

        if ($databaseName !== null) {
            $databaseToReconnect = Config::get('database.connections.' . Config::get('database.default') . '.database');
            self::setDatabaseConnection($databaseName);
        }

        $createdModel = parent::create($attributes);

        if ($databaseName !== null) {
            self::setDatabaseConnection($databaseToReconnect);
        }

        if ($collection !== null) {
            self::setCollection($preSetCollection);
        }

        return $createdModel;
    }

    /**
     * Set collection and database name for query
     * @param null $collection
     * @param null $databaseName
     * @return static
     */
    public static function whereTo($collection = null, $databaseName = null)
    {
        if ($collection !== null) {
            self::setCollection($collection);
        }

        if ($databaseName !== null) {
            self::setDatabaseConnection($databaseName);
        }

        return new static();
    }


    /**
     * Set database connection so we can modify model to specific database
     * @param null $connectionName
     * @return bool
     */
    private static function setDatabaseConnection($connectionName)
    {
        $defaultDb = Config::get('database.default');
        Config::set('database.connections.' . $defaultDb . '.database', strtolower($connectionName));
        DB::purge($defaultDb);
        DB::connection($defaultDb);

        return true;
    }
}
