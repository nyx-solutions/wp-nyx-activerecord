<?php

    namespace nox\components\wordpress;

    use DateTime;
    use DateTimeZone;
    use Exception;
    use nox\base\exceptions\InvalidCallException;
    use nox\base\helpers\ArrayHelper;
    use nox\base\Model;
    use nox\components\wordpress\helpers\CastHelper;
    use wpdb;
    use function mb_strtolower;
    use function strtolower;

    /**
     * Class ActiveRecord
     *
     * @package nox\components\wordpress
     */
    abstract class ActiveRecord extends Model
    {
        /**
         * The attributes of the model.
         *
         * @var array
         */
        protected array $attributes = [];

        /**
         * The casted attributes of the model.
         *
         * @var array
         */
        protected array $castedAttributes = [];

        /**
         * @var array
         */
        protected array $config = [];

        /**
         * @var string|null
         */
        protected static ?string $table = null;

        /**
         * @var string
         */
        protected static string $tz = 'UTC';

        /**
         * @var string
         */
        protected static string $castingClass = CastHelper::class;

        /**
         * The attributes that should be cast to native types.
         *
         * @var array
         */
        protected static array $casts = [];

        #region Initialization
        /**
         * @inheritdoc
         */
        public function __construct($config = [])
        {
            $this->config = $config;

            parent::__construct([]);
        }

        /**
         * @inheritdoc
         */
        public function init()
        {
            $casts = [static::idAttribute() => 'int'];

            $createdAtAttribute = static::createdAtAttribute();
            $updatedAtAttribute = static::updatedAtAttribute();

            if (!is_null($createdAtAttribute)) {
                $casts[$createdAtAttribute] = 'datetime';
            }

            if (!is_null($updatedAtAttribute)) {
                $casts[$updatedAtAttribute] = 'datetime';
            }

            static::$casts = ArrayHelper::merge(static::$casts, $casts);

            $this->loadDataBaseAttributes();
            $this->loadAttributes($this->config);

            parent::init();
        }
        #endregion

        #region DB
        /**
         * Get the wpdb instance
         *
         * @return wpdb The wpdb instance
         */
        public static function wpdb(): wpdb
        {
            global $wpdb;

            return $wpdb;
        }

        /**
         * @return void
         */
        protected function loadDataBaseAttributes(): void
        {
            $db = static::wpdb();

            $schema = DB_NAME;
            $table = static::tableName();

            $query = <<<SQL
SELECT 
    `COLUMN_NAME` AS `attribute`, `DATA_TYPE` AS `type`, `COLUMN_KEY` AS `columnKey` 

FROM 
     `information_schema`.`columns`

WHERE 
      `table_schema` = '{$schema}' AND
      `table_name` = '{$table}'

ORDER BY 
     `table_name`, `ordinal_position`
SQL;

            $fields = $db->get_results($query);

            foreach ($fields as $field) {
                $this->attributes[$field->attribute] = null;
            }
        }

        /**
         * @param array $attributes
         */
        protected function loadAttributes(array $attributes = []): void
        {
            foreach ($attributes as $k => $v) {
                if (array_key_exists($k, $this->attributes)) {
                    $this->attributes[$k] = $v;
                }
            }
        }
        #endregion

        #region Table
        /**
         * @return string|null
         */
        public static function tableName(): ?string
        {
            if (!empty(static::$table)) {
                $db    = static::wpdb();
                $table = static::$table;

                return "{$db->prefix}{$table}";
            }

            return null;
        }

        /**
         * @return string
         */
        public static function idAttribute(): string
        {
            return 'id';
        }

        /**
         * @return string|null
         */
        public static function createdAtAttribute(): ?string
        {
            return 'createdAt';
        }

        /**
         * @return string|null
         */
        public static function updatedAtAttribute(): ?string
        {
            return 'updatedAt';
        }
        #endregion

        #region Save
        /**
         * Save the model
         *
         * @return bool
         */
        public function save(): bool
        {
            try {
                $idAttribute = static::idAttribute();

                $isNewRecord = $this->isNewRecord();

                $this->beforeSave($isNewRecord);

                $now = new DateTime('now', new DateTimeZone(static::$tz));

                $createdAtAttribute = static::createdAtAttribute();
                $updatedAtAttribute = static::updatedAtAttribute();

                if ($isNewRecord) {
                    if (!is_null($createdAtAttribute)) {
                        $this->attributes[$createdAtAttribute] = $now->format('Y-m-d H:i:s');
                    }

                    if (!is_null($updatedAtAttribute)) {
                        $this->attributes[$updatedAtAttribute] = $now->format('Y-m-d H:i:s');
                    }

                    $this->attributes[$idAttribute] = static::insert($this->attributes);
                } else {
                    if (!is_null($createdAtAttribute)) {
                        unset($this->attributes[$createdAtAttribute]);
                    }

                    if (!is_null($updatedAtAttribute)) {
                        $this->attributes[$updatedAtAttribute] = $now->format('Y-m-d H:i:s');
                    }

                    static::update($this->attributes)
                        ->where($idAttribute, $this->$idAttribute)
                        ->execute();
                }

                $this->afterSave($isNewRecord);

                return true;
            } catch (Exception $exception) {}

            return false;
        }
        #endregion

        #region Delete
        /**
         * Delete the model
         *
         * @return static The model instance
         *
         * @throws Exception
         */
        public function delete()
        {
            $idAttribute = static::idAttribute();

            if (array_key_exists($idAttribute, $this->attributes)) {
                $this->beforeDelete();

                static::deleteById($this->$idAttribute);

                $this->$idAttribute = null;

                $this->afterDelete();
            }

            return $this;
        }

        /**
         * Delete a row by id
         *
         * @param int $id The id of the row
         *
         * @throws Exception
         */
        public static function deleteById(int $id)
        {
            static::query()
                ->delete()
                ->where('id', $id)
                ->execute();
        }
        #endregion

        #region Queries
        /**
         * Create a model with an array of attributes
         *
         * @param array $attributes An array of attributes
         *
         * @return static|null An model instance
         *
         * @throws Exception
         */
        public static function create(array $attributes = []): ?ActiveRecord
        {
            $instance = new static($attributes);

            if ($instance->save()) {
                return $instance;
            }

            return null;
        }

        /**
         * Get a query instance
         *
         * @return Query A query instance
         */
        public static function query()
        {
            return new Query(['model' => get_called_class()]);
        }

        /**
         * Insert a row into the database
         *
         * @param array $data An array of properties
         *
         * @return int|null The last inserted id
         *
         * @throws Exception
         */
        public static function insert(array $data): ?int
        {
            static::query()
                ->insert($data)
                ->execute();

            $id = static::wpdb()->insert_id;

            if ((int)$id > 0) {
                return (int)$id;
            }

            throw new Exception('The system could not save the record.');
        }

        /**
         * Shortcut for creating a query instance and calling update on it
         *
         * @param string|array $column (optional) A column name or a data object
         * @param string       $value  (optional) A value of a column
         *
         * @return Query The current query object
         *
         * @throws Exception
         *
         * @see Query::update
         *
         */
        public static function update($column = null, $value = null)
        {
            $query = static::query()->update();

            call_user_func_array([$query, 'set'], func_get_args());

            return $query;
        }
        #endregion

        #region Events
        /**
         * @param bool $insert
         *
         * @return void
         */
        protected function beforeSave(bool $insert)
        {
        }

        /**
         * @param bool $insert
         *
         * @return void
         */
        protected function afterSave(bool $insert)
        {
        }

        /**
         * @return void
         */
        protected function beforeDelete()
        {
        }

        /**
         * @return void
         */
        protected function afterDelete()
        {
        }
        #endregion

        #region Casting
        /**
         * Get casted value of record instance
         *
         * @param string $prop
         * @param mixed  $val
         *
         * @return mixed Casted value
         */
        public static function castedValue(string $prop, $val)
        {
            return self::findCastedValue('cast', $prop, $val);
        }

        /**
         * Get decasted value of record instance
         *
         * @param string $prop
         * @param mixed  $val
         *
         * @return mixed Decasted value
         */
        protected static function decastedValue(string $prop, $val)
        {
            return self::findCastedValue('decast', $prop, $val);
        }

        /**
         * @param string $name
         * @param string $prop
         * @param mixed  $val
         *
         * @return mixed
         */
        protected static function findCastedValue(string $name, string $prop, $val)
        {
            if (array_key_exists($prop, static::$casts)) {
                $cast = static::$casts[$prop];

                if (is_array($cast)) {
                    if (array_key_exists($name, $cast)) {
                        $val = $cast[$name]($val);
                    }
                } else {
                    $val = static::doCasting($name, $cast, $val);
                }
            }

            return $val;
        }

        /**
         * @param string $name
         * @param string $cast
         * @param mixed  $val
         *
         * @return mixed
         */
        protected static function doCasting(string $name, string $cast, $val)
        {
            $cast = function_exists('mb_strtolower') ? mb_strtolower($cast) : strtolower($cast);

            /** @var CastHelper $castingClass */
            $castingClass = static::$castingClass;

            if (is_subclass_of($castingClass, CastHelper::class)) {
                if (array_key_exists($cast, $castingClass::$aliases)) {
                    $cast = $castingClass::$aliases[$cast];
                }

                $methodName = "{$name}_{$cast}";

                if (method_exists($castingClass, $methodName)) {
                    $val = $castingClass::{$methodName}($val);
                }
            }

            return $val;
        }
        #endregion

        #region Find
        /**
         * Get a model instance by id
         *
         * @param array $conditions The conditions of the query. e.g. [['attribute' => 'id', 'type' => '=', 'value' => null]]
         * @param bool  $or
         *
         * @return static[]
         *
         */
        public static function find(array $conditions = [], bool $or = false): array
        {
            $query = static::query();

            if (!empty($conditions)) {
                foreach ($conditions as $condition) {
                    if (isset($condition['attribute'], $condition['type'], $condition['value'])) {
                        if ($or) {
                            $query->orWhere($condition['attribute'], $condition['type'], $condition['value']);
                        } else {
                            $query->andWhere($condition['attribute'], $condition['type'], $condition['value']);
                        }
                    }
                }
            }

            $results = array_values(
                array_filter(
                    $query->get(),
                    fn ($obj) => $obj instanceof ActiveRecord
                )
            );

            if (!is_array($results)) {
                $results = [];
            }

            return $results;
        }

        /**
         * Get a model instance by id
         *
         * @param int         $id The id of the row
         * @param string|null $attribute
         *
         * @return static|null
         */
        public static function findOne(int $id, ?string $attribute = null)
        {
            try {
                if (empty($attribute)) {
                    $attribute = static::idAttribute();
                }

                $result = static::query()->andWhere($attribute, (int)$id)->one();

                if ($result instanceof ActiveRecord) {
                    return $result;
                }
            } catch (Exception $exception) {}

            return null;
        }

        /**
         * Get a model instance by id
         *
         * @param int         $id The id of the row
         * @param string|null $attribute
         *
         * @return static
         */
        public static function findOneOrNew(int $id, ?string $attribute = null): ActiveRecord
        {
            try {
                if (empty($attribute)) {
                    $attribute = static::idAttribute();
                }

                $result = static::query()->andWhere($attribute, $id)->one();

                if ($result instanceof ActiveRecord) {
                    return $result;
                }
            } catch (Exception $exception) {}

            return new static();
        }
        #endregion

        #region Verifications
        /**
         * @return bool
         */
        public function isNewRecord(): bool
        {
            $idAttribute = static::idAttribute();

            return (empty($this->attributes[$idAttribute]) || (int)$this->attributes[$idAttribute] <= 0);
        }
        /**
         * @inheritdoc
         */
        public function canGetProperty($name, $checkVars = true)
        {
            if (isset($this->attributes[$name])) {
                return true;
            }

            return parent::canGetProperty($name, $checkVars);
        }

        /**
         * @inheritdoc
         */
        public function canSetProperty($name, $checkVars = true)
        {
            if (isset($this->attributes[$name])) {
                return true;
            }

            return parent::canSetProperty($name, $checkVars);
        }
        #endregion

        #region Magic Attributes
        /**
         * @inheritdoc
         */
        public function __get($name)
        {
            if (array_key_exists($name, $this->attributes)) {
                if (!array_key_exists($name, $this->castedAttributes)) {
                    if (!array_key_exists($name, $this->attributes)) {
                        return null;
                    }

                    $value = $this->attributes[$name];

                    $this->castedAttributes[$name] = static::castedValue($name, $value);
                }

                return $this->castedAttributes[$name];
            }

            return parent::__get($name);
        }

        /**
         * @inheritdoc
         */
        public function __set($name, $value)
        {
            if (array_key_exists($name, $this->attributes)) {
                if (array_key_exists($name, $this->castedAttributes)) {
                    unset($this->castedAttributes[$name]);
                }

                $this->attributes[$name] = static::decastedValue($name, $value);

                return;
            }

            parent::__set($name, $value);
        }

        /**
         * @inheritdoc
         */
        public function __isset($name)
        {
            if (isset($this->attributes[$name])) {
                return true;
            }

            return parent::__isset($name);
        }

        /**
         * @inheritdoc
         */
        public function __unset($name)
        {
            if (isset($this->attributes[$name])) {
                throw new InvalidCallException('Unsetting database property: '.get_class($this).'::'.$name);
            }

            parent::__unset($name);
        }
        #endregion
    }
