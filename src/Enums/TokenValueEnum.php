<?php

declare(strict_types=1);

namespace Tranxton\AddMissingImportsFixer\Enums;

final class TokenValueEnum
{
    public const T_OPEN_BRACE = '(';
    public const T_OPEN_CURLY_BRACE = '{';
    public const T_NEW_LINE = "\n";
    public const T_USE = 'use';
    public const T_WHITE_SPACE = " ";
    public const T_NS_SEPARATOR = '\\';
    public const T_SEMICOLON = ';';
    public const T_COLON = ':';
}
