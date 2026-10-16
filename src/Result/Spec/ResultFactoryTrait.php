<?php declare(strict_types=1);

    namespace STDW\Database\Result\Spec;

    use STDW\Contract\Database\Result\ResultInterface;
    use STDW\Database\Result\Result;

    
    trait ResultFactoryTrait
    {
        /**
         * @param null|array<int|string, mixed>|object $data
         * @param int $count
         * @return self
         */
        public static function ok(null|array|object $data, int $count): ResultInterface
        {
            return new Result($data, $count, true);
        }

        /**
         * @param null|array<int|string, mixed>|object $data
         * @param int $count
         * @return self
         */
        public static function error(null|array|object $data = null, int $count = 0): ResultInterface
        {
            return new Result($data, $count, false);
        }
    }
