<?php declare(strict_types=1);

    namespace STDW\Database\Param;

    use DateTimeInterface;
    use JsonException;
    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\Param\Types\ParamType;
    use STDW\Database\Exception\ParamException;
    use Stringable;


    /**
     * A driver-agnostic, typed value used for every value sent to a database.
     *
     * The type is guaranteed at construction time (pass-through semantics), so
     * drivers never need to cast or validate on bind: a PDO-based driver maps
     * the ParamType to its own binding constants (PARAM_*), while any other
     * driver simply reads the already-correct PHP value.
     *
     * Semantic PHP variants are folded into storage primitives by from():
     * DateTimeInterface becomes a "Y-m-d H:i:s" string, arrays become JSON
     * strings, Stringable objects become strings. Values of a binary nature
     * should be sent through binary().
     */
    final readonly class Param implements ParamInterface
    {
        /**
         * @param null|bool|int|float|string $value
         * @param ParamType $type
         */
        private function __construct(
            private null|bool|int|float|string $value,
            private ParamType $type,
        ) { }


        /** @return self
         */
        public static function null(): self
        {
            return new self(null, ParamType::Null);
        }

        /**
         * @param bool $value
         * @return self
         */
        public static function bool(bool $value): self
        {
            return new self($value, ParamType::Bool);
        }

        /**
         * @param int $value
         * @return self
         */
        public static function int(int $value): self
        {
            return new self($value, ParamType::Int);
        }

        /**
         * @param float $value
         * @return self
         */
        public static function float(float $value): self
        {
            return new self($value, ParamType::Float);
        }

        /**
         * @param string $value
         * @return self
         */
        public static function str(string $value): self
        {
            return new self($value, ParamType::String);
        }

        /**
         * @param string $value
         * @return self
         */
        public static function binary(string $value): self
        {
            return new self($value, ParamType::Binary);
        }

        /**
         * Infers the storage primitive from the PHP value. DateTimeInterface
         * is formatted as "Y-m-d H:i:s", arrays/JsonSerializable-free objects
         * of array nature become JSON strings and Stringable objects become
         * strings; anything else throws ParamException.
         *
         * @param mixed $value
         * @return self
         * @throws ParamException
         */
        public static function from(mixed $value): self
        {
            return match (true) {
                $value === null => self::null(),
                is_bool($value) => self::bool($value),
                is_int($value) => self::int($value),
                is_float($value) => self::float($value),
                is_string($value) => self::str($value),
                is_array($value) => self::str(self::encode($value)),
                $value instanceof DateTimeInterface => self::str($value->format('Y-m-d H:i:s')),
                $value instanceof Stringable => self::str($value->__toString()),

                default => throw ParamException::invalidValue(get_debug_type($value)),
            };
        }


        /** @return null|bool|int|float|string
         */
        public function value(): mixed
        {
            return $this->value;
        }

        /** @return ParamType
         */
        public function type(): ParamType
        {
            return $this->type;
        }

        /** @return string
         */
        public function __toString(): string
        {
            return match ($this->type) {
                ParamType::Null => '',
                ParamType::Bool => $this->value ? '1' : '0',

                default => (string) $this->value,
            };
        }


        /**
         * @param array<array-key, mixed> $value
         * @return string
         * @throws ParamException
         */
        private static function encode(array $value): string
        {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $exception) {
                throw ParamException::invalidValue('array<' . $exception->getMessage() . '>');
            }
        }
    }
