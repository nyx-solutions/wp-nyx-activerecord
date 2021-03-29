<?php

    /**
     * @noinspection PhpUnused
     */

    namespace nyx\components\wordpress\helpers;

    use DateTime;
    use DateTimeZone;
    use Exception;
    use nyx\base\helpers\DateTimeHelper;
    use function json_decode;
    use function json_encode;

    /**
     * Class Casting
     *
     * @package nyx\components\wordpress\helpers
     */
    class CastHelper
    {
        /**
         * @var string[]
         */
        public static array $aliases = [
            'integer' => 'int',
            'number'  => 'float',
            'bool'    => 'boolean',
        ];

        /**
         * @param mixed $val
         *
         * @return int
         */
        public static function cast_int($val): ?int
        {
            if (empty($val)) {
                return null;
            }

            return (int)$val;
        }

        /**
         * @param mixed $val
         *
         * @return float
         */
        public static function cast_float($val): ?float
        {
            if (empty($val)) {
                return null;
            }

            return (float)$val;
        }

        /**
         * @param mixed $val
         *
         * @return bool
         */
        public static function cast_boolean($val): bool
        {
            return (boolean)$val;
        }

        /**
         * @param mixed $val
         *
         * @return int
         */
        public static function decast_boolean($val)
        {
            return (($val) ? 1 : 0);
        }

        /**
         * @param mixed $val
         *
         * @return array
         */
        public static function cast_json($val): ?array
        {
            $json = json_decode($val, true);

            return ((is_array($json)) ? $json : []);
        }

        /**
         * @param mixed $val
         *
         * @return string
         */
        public static function decast_json($val): ?string
        {
            if (empty($val)) {
                return null;
            }

            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        /**
         * @param $val
         *
         * @return DateTime
         *
         * @throws Exception
         */
        public static function cast_datetime($val): ?DateTime
        {
            if (empty($val)) {
                return null;
            }

            $now = new DateTime('now', new DateTimeZone(DateTimeHelper::$currentTimeZone));

            return $now->setTimestamp(strtotime($val));
        }

        /**
         * @param DateTime $val
         *
         * @return string
         */
        public static function decast_datetime(DateTime $val): ?string
        {
            if (empty($val) || !$val instanceof DateTime) {
                return null;
            }

            return $val->format('Y-m-d H:i:s');
        }
    }
