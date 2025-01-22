<?php

declare(strict_types=1);

namespace Tranxton\AddMissingImportsFixer\Enums;

final class ExcludedSymbolsEnum
{
    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';
    public const TYPE_ARRAY = 'array';
    public const TYPE_OBJECT = 'object';
    public const TYPE_CALLABLE = 'callable';
    public const TYPE_ITERABLE = 'iterable';
    public const TYPE_VOID = 'void';
    public const TYPE_MIXED = 'mixed';
    public const TYPE_NEVER = 'never';

    public const VALUE_TRUE = 'true';
    public const VALUE_FALSE = 'false';
    public const VALUE_NULL = 'null';

    public const TOKEN_SELF = 'self';
    public const TOKEN_STATIC = 'static';
    public const TOKEN_PARENT = 'parent';
    public const TOKEN_THIS = '$this';

    public const ALL_TYPES = [
        self::TYPE_INT,
        self::TYPE_FLOAT,
        self::TYPE_STRING,
        self::TYPE_BOOL,
        self::TYPE_ARRAY,
        self::TYPE_OBJECT,
        self::TYPE_CALLABLE,
        self::TYPE_ITERABLE,
        self::TYPE_VOID,
        self::TYPE_MIXED,
        self::TYPE_NEVER,
    ];

    public const ALL_TOKENS = [
        self::TOKEN_SELF,
        self::TOKEN_STATIC,
        self::TOKEN_PARENT,
        self::TOKEN_THIS,
    ];

    public const ALL_SYMBOLS = [
        self::TYPE_INT,
        self::TYPE_FLOAT,
        self::TYPE_STRING,
        self::TYPE_BOOL,
        self::TYPE_ARRAY,
        self::TYPE_OBJECT,
        self::TYPE_CALLABLE,
        self::TYPE_ITERABLE,
        self::TYPE_VOID,
        self::TYPE_MIXED,
        self::TYPE_NEVER,
        self::VALUE_TRUE,
        self::VALUE_FALSE,
        self::VALUE_NULL,
        self::TOKEN_SELF,
        self::TOKEN_STATIC,
        self::TOKEN_PARENT,
        self::TOKEN_THIS,
    ];

    public function isExcludedType(string $type): bool
    {
        return in_array(strtolower($type), self::ALL_TYPES, true);

    }

    public function isExcludedToken(string $token): bool
    {
        return in_array(strtolower($token), self::ALL_TOKENS, true);

    }

    public function isExcludedSymbol(string $symbol): bool
    {
        return in_array(strtolower($symbol), self::ALL_SYMBOLS, true);
    }
}