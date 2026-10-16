<?php declare(strict_types=1);

    use STDW\Contract\Database\Param\Types\ParamType;
    use STDW\Database\Exception\ParamException;
    use STDW\Database\Param\Param;
    use Tests\Stubs\StringableStub;


    test('null()', function () {
        $param = Param::null();

        expect($param->value())->toBeNull()
            ->and($param->type())->toBe(ParamType::Null)
            ->and((string) $param)->toBe('');
    });

    test('bool()', function () {
        expect(Param::bool(true)->value())->toBeTrue()
            ->and(Param::bool(true)->type())->toBe(ParamType::Bool)
            ->and((string) Param::bool(true))->toBe('1')
            ->and((string) Param::bool(false))->toBe('0');
    });

    test('int()', function () {
        $param = Param::int(42);

        expect($param->value())->toBe(42)
            ->and($param->type())->toBe(ParamType::Int)
            ->and((string) $param)->toBe('42');
    });

    test('float()', function () {
        $param = Param::float(1.5);

        expect($param->value())->toBe(1.5)
            ->and($param->type())->toBe(ParamType::Float)
            ->and((string) $param)->toBe('1.5');
    });

    test('str()', function () {
        $param = Param::str('hello');

        expect($param->value())->toBe('hello')
            ->and($param->type())->toBe(ParamType::String);
    });

    test('binary()', function () {
        $param = Param::binary("\x00\x01");

        expect($param->value())->toBe("\x00\x01")
            ->and($param->type())->toBe(ParamType::Binary);
    });

    test('from() infers primitives', function () {
        expect(Param::from(null)->type())->toBe(ParamType::Null)
            ->and(Param::from(true)->type())->toBe(ParamType::Bool)
            ->and(Param::from(7)->type())->toBe(ParamType::Int)
            ->and(Param::from(7.5)->type())->toBe(ParamType::Float)
            ->and(Param::from('x')->type())->toBe(ParamType::String);
    });

    test('from() folds DateTimeInterface into a string', function () {
        $date = new DateTimeImmutable('2026-10-15 12:34:56');
        $param = Param::from($date);

        expect($param->type())->toBe(ParamType::String)
            ->and($param->value())->toBe('2026-10-15 12:34:56');
    });

    test('from() folds arrays into a JSON string', function () {
        $param = Param::from(['a' => 1, 'b' => [2, 3]]);

        expect($param->type())->toBe(ParamType::String)
            ->and($param->value())->toBe('{"a":1,"b":[2,3]}');
    });

    test('from() folds Stringable objects into strings', function () {
        $param = Param::from(new StringableStub('abc'));

        expect($param->type())->toBe(ParamType::String)
            ->and($param->value())->toBe('abc');
    });

    test('from() rejects unsupported types', function () {
        Param::from(new stdClass());
    })->throws(ParamException::class);
