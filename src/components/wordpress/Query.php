<?php

    /**
     * @noinspection PhpUnused
     */

    namespace nyx\components\wordpress;

    use Exception;
    use nyx\base\Component;
    use wpdb;

    /**
     * The Query Builder Class
     *
     * @author Friedolin Förder
     */
    class Query extends Component
    {
        /**
         * The type of query
         *
         * @var string|null The type of query
         */
        protected ?string $type = null;

        /**
         * The current model or table name
         *
         * @var string|null The current model or table name
         */
        protected ?string $model = null;

        /**
         * SELECT properties
         *
         * @var array SELECT properties
         */
        protected array $select = [];

        /**
         * SET columns
         *
         * @var array SET columns
         */
        protected array $set = [];

        /**
         * INSERT rows
         *
         * @var array INSERT rows
         */
        protected array $insert = [];

        /**
         * WHERE conditions
         *
         * @var array WHERE conditions
         */
        protected array $where = [];

        /**
         * GROUP BY conditions
         *
         * @var array GROUP BY conditions
         */
        protected array $group_by = [];

        /**
         * HAVING conditions
         *
         * @var array HAVING conditions
         */
        protected array $having = [];

        /**
         * ORDER BY conditions
         *
         * @var array ORDER BY conditions
         */
        protected array $order_by = [];

        /**
         * LIMIT property
         *
         * @var int|array LIMIT property
         */
        protected $limit;

        /**
         * OFFSET property
         *
         * @var int|array OFFSET property
         */
        protected $offset;

        /**
         * JOIN command
         *
         * @var array JOIN commands
         */
        protected array $join = [];

        #region DataBase
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
        #endregion

        #region Queries Actions
        /**
         * Select rows
         *
         * @return static The current query object
         *
         * @throws Exception
         */
        public function select()
        {
            $this->type('SELECT');

            $this->select = array_merge($this->select, func_get_args());

            return $this;
        }

        /**
         * Delete rows
         *
         * @return static The current query object
         *
         * @throws Exception
         */
        public function delete()
        {
            return $this->type('DELETE');
        }

        /**
         * Update rows (Alias for Query::set)
         *
         * @param string|array $column (optional) A column name or a data object
         * @param string       $value  (optional) A value of a column
         *
         * @return static The current query object
         *
         * @throws Exception
         *
         * @see Query::set
         *
         */
        public function update($column = null, $value = null)
        {
            if (func_num_args() === 0) {
                return $this->type('UPDATE');
            } else {
                return call_user_func_array([$this, 'set'], func_get_args());
            }
        }

        /**
         * Set columns
         *
         * @param string|array $column A column name or a data object
         * @param string       $value  (optional) A value of a column
         *
         * @return Query The current query object
         *
         * @throws Exception
         */
        public function set($column, $value = null)
        {
            $this->type('UPDATE');

            if (func_num_args() === 1) {
                foreach ($column as $k => $v) {
                    $this->set($k, $v);
                }

                return $this;
            }

            if (is_null($value)) {
                $value = ['NULL'];
            }

            $this->set[$column] = $value;

            return $this;
        }

        /**
         * @param $sql
         * @param $args
         */
        protected function prepareSet(&$sql, &$args)
        {
            $set = [];
            foreach ($this->set as $key => $value) {
                if (is_array($value)) {
                    // include decoded value directly
                    $set[] = sprintf("`%s` = %s", $key, $value[0]);
                } else {
                    $set[]  = sprintf("`%s` = %%s", $key);
                    $args[] = $value;
                }
            }
            $sql[] = sprintf("SET %s", join(", ", $set));
        }

        /**
         * Insert rows
         *
         * @param array $data One data object or multiple rows
         *
         * @return Query The current query object
         *
         * @throws Exception
         */
        public function insert(array $data)
        {
            $this->type('INSERT');

            if (!array_key_exists(0, $data)) {
                $data = [$data];
            }

            foreach ($data as &$row) {
                foreach ($row as &$value) {
                    if (is_null($value)) {
                        $value = ['NULL'];
                    }
                }
            }

            $this->insert = array_merge($this->insert, $data);

            return $this;
        }

        /**
         * @param $sql
         * @param $args
         */
        protected function prepareInsert(&$sql, &$args)
        {
            $columns        = array_keys($this->insert[0]);
            $escapedColumns = [];
            foreach ($columns as $column) {
                $escapedColumns[] = "`".$column."`";
            }

            $values = [];

            foreach ($this->insert as $row) {
                $rowValues = [];

                foreach ($columns as $column) {
                    if (is_array($row[$column])) {
                        // decode raw value
                        $rowValues[] = $row[$column][0];
                    } else {
                        $rowValues[] = '%s';
                        $args[]      = $row[$column];
                    }
                }

                $values[] = sprintf("(%s)", join(', ', $rowValues));
            }

            $sql[] = sprintf("(%s) VALUES %s", join(", ", $escapedColumns), join(", ", $values));
        }

        /**
         * @param string $type
         *
         * @return static
         *
         * @throws Exception
         */
        protected function type(string $type): Query
        {
            if ($this->type && $this->type !== $type) {
                throw new Exception("The type of query is already '{$this->type}'");
            }

            $this->type = $type;

            return $this;
        }
        #endregion

        #region Where Actions
        /**
         * Add a where condition
         *
         * @param string|array $column        A column name, a raw condition wrapped in an array or multiple
         *                                    where conditions in an array
         * @param mixed        $typeOrValue   (optional) The type of the query (e.g. = or >) or the value of the column
         * @param mixed        $value         (optional) The value of the column
         *
         * @return Query The current query object
         *
         * @throws Exception
         */
        public function where($column, $typeOrValue = null, $value = null)
        {
            return $this->whereCondition('where', func_num_args(), $column, $typeOrValue, $value);
        }

        /**
         * Alias for Query::where
         *
         * @param mixed $key
         * @param mixed $typeOrValue (optional) The type of the query (e.g. = or >) or the value of the column
         * @param mixed $value       (optional) The value of the column
         *
         * @return Query The current query object
         *
         * @see Query::where
         */
        public function andWhere($key, $typeOrValue = null, $value = null)
        {
            return call_user_func_array([$this, 'where'], func_get_args());
        }

        /**
         * Create a where condition and adds it with the keyword OR
         *
         * @param mixed $key
         * @param mixed $typeOrValue (optional) The type of the query (e.g. = or >) or the value of the column
         * @param mixed $value       (optional) The value of the column
         *
         * @return static The current query object
         */
        public function orWhere($key, $typeOrValue = null, $value = null)
        {
            // create new group
            $this->where[] = [];

            // call where function
            return call_user_func_array([$this, 'where'], func_get_args());
        }

        /**
         * @param $array
         * @param $args
         * @param $column
         * @param $typeOrValue
         * @param $value
         *
         * @return static
         * @throws Exception
         */
        protected function whereCondition($array, $args, $column, $typeOrValue, $value)
        {
            $type = $typeOrValue;
            $obj  = null;

            if ($args === 1) {
                if (is_string($column)) {
                    $obj = [$column];
                } elseif (!is_array($column)) {
                    throw new Exception('Only one argument provided for function where, but this is not an string or array.');
                } elseif (array_key_exists(0, $column)) {
                    $obj = $column;
                } else {
                    foreach ($column as $k => $v) {
                        if (is_array($v) && count($v) === 2) {
                            $this->{$array}($k, $v[0], $v[1]);
                        } else {
                            $this->{$array}($k, $v);
                        }
                    }

                    return $this;
                }
            }

            if ($args === 2) {
                $value = $typeOrValue;
                $type  = is_null($value) ? 'IS' : '=';
            }

            if (is_null($value)) {
                // encode null value
                $value = ['NULL'];
            }

            if (!$obj) {
                $obj         = (object)[];
                $obj->column = $column;
                $obj->type   = strtoupper($type);
                $obj->value  = $value;
            }

            if (!$this->{$array}) {
                $this->{$array}[] = [];
            }

            $this->{$array}[count($this->{$array}) - 1][] = $obj;

            return $this;
        }



        /**
         * @param $array
         * @param $sql
         * @param $args
         */
        protected function prepareWhere($array, &$sql, &$args)
        {
            $where = [];
            foreach ($this->{$array} as $group) {
                $items = [];
                foreach ($group as $item) {
                    if (is_array($item)) {
                        $items[] = array_shift($item);
                        $args    = array_merge($args, $item);
                    } else {
                        if (is_array($item->column)) {
                            $column = $item->column[0];
                        } else {
                            $column = "`{$item->column}`";
                        }

                        if (is_array($item->value)) {
                            $value = $item->value[0];
                            if (is_array($value)) {
                                $values = [];
                                foreach ($value as $v) {
                                    $values[] = '%s';
                                    $args[]   = $v;
                                }
                                $value = sprintf('(%s)', join(', ', $values));
                            }
                        } else {
                            $value  = '%s';
                            $args[] = $item->value;
                        }
                        $items[] = sprintf("%s %s %s", $column, $item->type, $value);
                    }
                }
                $where[] = sprintf("( %s )", join(" AND ", $items));
            }
            $sql[] = sprintf("%s %s", strtoupper($array), join(" OR ", $where));
        }
        #endregion

        #region Group By
        /**
         * Add a group by section
         *
         * @param string|array   $column The column name or the raw string wrapped in an array
         * @param string|boolean $order  (optional) The order of the grouping, ASC/true or DESC/false
         *
         * @return Query The current query object
         */
        public function groupBy($column, $order = 'ASC')
        {
            return $this->orderByCondition('group_by', func_num_args(), $column, $order);
        }
        #endregion

        #region Having
        /**
         * Add a having condition
         *
         * @param string|array $column        A column name, a raw condition wrapped in an array or multiple
         *                                    where conditions in an array
         * @param mixed        $type_or_value (optional) The type of the query (e.g. = or >) or the value of the column
         * @param mixed        $value         (optional) The value of the column
         *
         * @return static The current query object
         *
         * @throws Exception
         */
        public function having($column, $type_or_value = null, $value = null)
        {
            return $this->whereCondition('having', func_num_args(), $column, $type_or_value, $value);
        }


        /**
         * Alias for Query::having
         *
         * @param string|array $column        A column name, a raw condition wrapped in an array or multiple
         *                                    where conditions in an array
         * @param mixed        $type_or_value (optional) The type of the query (e.g. = or >) or the value of the column
         * @param mixed        $value         (optional) The value of the column
         *
         * @return static The current query object
         *
         * @see Query::having
         */
        public function andHaving($column, $type_or_value = null, $value = null)
        {
            return call_user_func_array([$this, 'having'], func_get_args());
        }

        /**
         * Create a where condition and adds it with the keyword OR
         *
         * @param string|array $column        A column name, a raw condition wrapped in an array or multiple
         *                                    where conditions in an array
         * @param mixed        $type_or_value (optional) The type of the query (e.g. = or >) or the value of the column
         * @param mixed        $value         (optional) The value of the column
         *
         * @return Query The current query object
         */
        public function orHaving($column, $type_or_value = null, $value = null)
        {
            // create new group
            $this->having[] = [];

            // call where function
            return call_user_func_array([$this, 'having'], func_get_args());
        }
        #endregion

        #region Order By
        /**
         * Add a order by section
         *
         * @param string|array   $column The column name or the raw string wrapped in an array
         * @param string|boolean $order  (optional) The order of the grouping, ASC/true or DESC/false
         *
         * @return Query The current query object
         */
        public function orderBy($column, $order = 'ASC')
        {
            return $this->orderByCondition('order_by', func_num_args(), $column, $order);
        }

        /**
         * @param $array
         * @param $num_args
         * @param $column
         * @param $order
         *
         * @return static
         */
        protected function orderByCondition($array, $num_args, $column, $order)
        {
            $obj = null;
            if (is_array($column)) {
                if (array_key_exists(0, $column)) {
                    $obj = $column;
                } else {
                    foreach ($column as $k => $v) {
                        $this->{$array}($k, $v);
                    }

                    return $this;
                }
            } else {
                if ($order === false || is_string($order) && strtoupper($order) !== "ASC") {
                    $order = "DESC";
                }
                $obj         = (object)[];
                $obj->column = $column;
                $obj->order  = $order;
            }
            $this->{$array}[] = $obj;

            return $this;
        }

        /**
         * @param $array
         * @param $sql
         * @param $args
         */
        protected function prepareOrderBy($array, &$sql, &$args)
        {
            $group_by = [];
            foreach ($this->{$array} as $item) {
                if (is_array($item)) {
                    $group_by[] = array_shift($item);
                    $args       = array_merge($args, $item);
                } else {
                    $group_by[] = sprintf("`%s` %s", $item->column, $item->order);
                }
            }
            $sql[] = sprintf("%s %s", strtoupper(str_replace('_', ' ', $array)), join(", ", $group_by));
        }
        #endregion

        #region Limit
        /**
         * Add a limit
         *
         * @param int|string|array $limit The limit number or a raw string wrapped in an array
         *
         * @return Query
         */
        public function limit($limit)
        {
            $this->limit = $limit;

            return $this;
        }

        /**
         * Add a offset
         *
         * @param $offset
         *
         * @return Query
         */
        public function offset($offset)
        {
            $this->offset = $offset;

            return $this;
        }

        /**
         * @param $array
         * @param $sql
         * @param $args
         */
        protected function prepareLimit($array, &$sql, &$args)
        {
            $limit = $this->{$array};
            if (is_array($limit)) {
                $sql[] = strtoupper($array)." ".array_shift($limit);
                $args  = array_merge($args, $limit);
            } else {
                $args[] = (int)$limit;
                $sql[]  = strtoupper($array)." %d";
            }
        }
        #endregion

        #region Join
        /**
         * Add a join
         *
         * @param        $table
         * @param        $attribute
         * @param        $join_attribute
         * @param string $type
         *
         * @return Query
         */
        public function join($table, $attribute, $join_attribute, $type = 'inner')
        {
            $this->join[] = [$table, $attribute, $join_attribute, $type];

            return $this;
        }

        /**
         * @param $sql
         * @param $args
         */
        protected function prepareJoin(&$sql, &$args)
        {
            /** @var ActiveRecord $model */
            $model = $this->model;
            $table = $this->hasModel() ? $model::tableName() : $model;

            foreach ($this->join as $row) {
                $type  = strtoupper($row[3]);
                $sql[] = "{$type} JOIN `{$row[0]}` ON `{$table}`.`{$row[1]}` = `{$row[0]}`.`{$row[2]}`";
            }
        }
        #endregion

        #region Prepare & SQL
        /**
         * Prepare the sql string and the variables for the final sql statement
         *
         * @return object A preparation object
         *
         * @noinspection SqlWithoutWhere
         * @noinspection SqlResolve
         */
        public function prepare()
        {
            /** @var ActiveRecord $model */
            $model = $this->model;

            $table = ($this->hasModel()) ? $model::tableName() : $model;

            $args = [];
            $sql  = [];

            // SELECT, UPDATE, INSERT or DELETE
            if ($this->type === 'DELETE') {
                $sql[] = sprintf("DELETE FROM `%s`", $table);
            } elseif ($this->type === 'UPDATE') {
                $sql[] = sprintf("UPDATE `%s`", $table);
            } elseif ($this->type === 'INSERT') {
                $sql[] = "INSERT INTO `{$table}`";
            } else {
                $sql[] = sprintf("SELECT %s", $this->select ? join(", ", $this->select) : "*");
                if ($table) {
                    $sql[] = sprintf("FROM `%s`", $table);
                }
            }

            // SET
            if ($this->set) {
                $this->prepareSet($sql, $args);
            }

            // INSERT
            if ($this->insert) {
                $this->prepareInsert($sql, $args);
            }

            // JOIN
            if ($this->join) {
                $this->prepareJoin($sql, $args);
            }

            // WHERE
            if ($this->where) {
                $this->prepareWhere('where', $sql, $args);
            }

            // GROUP BY
            if ($this->group_by) {
                $this->prepareOrderBy('group_by', $sql, $args);
            }

            // HAVING
            if ($this->having) {
                $this->prepareWhere('having', $sql, $args);
            }

            // ORDER BY
            if ($this->order_by) {
                $this->prepareOrderBy('order_by', $sql, $args);
            }

            // LIMIT
            if ($this->limit) {
                $this->prepareLimit('limit', $sql, $args);
            } elseif ($this->offset) {
                $sql[] = "LIMIT 18446744073709551615";
            }

            // OFFSET
            if ($this->offset) {
                $this->prepareLimit('offset', $sql, $args);
            }

            // create output object
            $preparation       = (object)[];
            $preparation->sql  = join(" \n", $sql);
            $preparation->vars = $args;

            return $preparation;
        }

        /**
         * Create the final sql statement
         *
         * @return string The final sql statement
         */
        public function sql()
        {
            $preparation = $this->prepare();

            if ($preparation->vars) {
                array_unshift($preparation->vars, $preparation->sql);

                $sql = call_user_func_array([static::wpdb(), 'prepare'], $preparation->vars);
            } else {
                $sql = $preparation->sql;
            }

            return $sql;
        }
        #endregion

        #region Resultset
        /**
         * Get the results of the query
         *
         * @return array The results as an array
         */
        public function results()
        {
            $results = $this->rawResults();

            return $this->castedRows($results);
        }

        /**
         * Get the row of the query
         *
         * @return object|array The row as an object
         */
        public function row()
        {
            $row = $this->rawRow();

            return $this->castedRow($row);
        }

        /**
         * Get the column of the query
         *
         * @return array The column as an array
         * @throws Exception
         */
        public function column()
        {
            if (count($this->select) !== 1) {
                throw new Exception("Query.get_col: You have to provide exactly one select argument");
            }

            $prop   = $this->select[0];
            $values = static::wpdb()->get_col($this->sql());

            $castedValues = [];

            foreach ($values as $val) {
                $castedValues[] = $this->castedValue($prop, $val);
            }

            return $castedValues;
        }

        /**
         * @return array
         */
        protected function rawResults(): array
        {
            return (array)static::wpdb()->get_results($this->sql(), 'ARRAY_A');
        }

        /**
         * @return array
         */
        protected function rawRow(): array
        {
            return (array)static::wpdb()->get_row($this->sql(), 'ARRAY_A');
        }

        /**
         * Execute the query
         *
         * @return bool|int
         */
        public function execute()
        {
            return static::wpdb()->query($this->sql());
        }

        /**
         * Execute the query
         *
         * @param string $sql
         *
         * @return bool|int
         */
        public function executeQuery(string $sql)
        {
            return static::wpdb()->query($sql);
        }
        #endregion

        #region Get Actions
        /**
         * Get the value of the query
         *
         * @param string|null $sql
         *
         * @return string The value returned by the query
         * @throws Exception
         */
        public function var(?string $sql = null)
        {
            if (!empty($sql)) {
                return static::wpdb()->get_var($sql);
            }

            if (count($this->select) !== 1) {
                throw new Exception("Query.get_var: You have to provide exactly one select argument");
            }

            $prop = $this->select[0];
            $var = static::wpdb()->get_var($this->sql());

            return $this->castedValue($prop, $var);
        }

        /**
         * Get the results of the query as an array of model instances
         *
         * @return array|ActiveRecord[] The results as an array of model instances
         */
        public function get()
        {
            if (!$this->hasModel()) {
                return $this->results();
            }

            $modelClass = $this->model;
            $results    = $this->rawResults();
            $models     = [];

            foreach ($results as $result) {
                if (!empty($result)) {
                    $models[] = new $modelClass($result);
                }
            }

            return $models;
        }

        /**
         * Get the results of the query as a model instances
         *
         * @return ActiveRecord|array|null The results as a model instances
         */
        public function one()
        {
            if (!$this->hasModel()) {
                return $this->row();
            }

            $modelClass = $this->model;
            $result     = $this->rawRow();

            if (!empty($result)) {
                return new $modelClass($result);
            }

            return null;
        }
        #endregion

        #region Casting
        /**
         * @param array $rows
         *
         * @return array
         */
        protected function castedRows(array $rows): array
        {
            $castedRows = [];

            foreach ($rows as $row) {
                $castedRows[] = $this->castedRow($row);
            }

            return $castedRows;
        }

        /**
         * @param array $row
         *
         * @return array
         */
        protected function castedRow(array $row)
        {
            $castedRow = [];

            foreach ($row as $key => $value) {
                $castedRow[$key] = $this->castedValue($key, $value);
            }

            return $castedRow;
        }

        /**
         * @param $prop
         * @param $val
         *
         * @return mixed
         */
        protected function castedValue($prop, $val)
        {
            if ($this->hasModel()) {
                /** @var ActiveRecord $model */
                $model = $this->model;

                return $model::castedValue($prop, $val);
            }

            return $val;
        }
        #endregion

        #region Model
        /**
         * @return bool
         */
        public function hasModel()
        {
            return (is_subclass_of($this->model, ActiveRecord::class));
        }
        #endregion
    }
